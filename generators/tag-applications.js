/*
 * Tag product lines with applications, from evidence rather than assertion.
 *
 * Eight application terms were proposed. Four survive, because manufacturer
 * copy describes what a table IS, not what industry buys it, and keyword
 * mining for industries mostly finds false positives:
 *
 *   "weld"     -> 14 hits, every one of them "welded frame". Construction.
 *   "paint"    -> 9 hits, 8 of them the table's own finish. One real.
 *   "print"    -> 19 hits, every one of them "footprint".
 *   "packaging"-> 6 hits, all incidental items in a list of generic uses.
 *   automotive -> 0. aerospace -> 0. foundry -> 0.
 *
 * What manufacturers DO state is the work the table does: palletizing, order
 * picking, machine feeding, assembly, washdown, cleanroom. Those four are
 * below. Anything resting on a guess is left out: an untagged line is cheaper
 * than a wrong one, and an application page is only worth having if every
 * line on it belongs there.
 *
 * Writes `applications` into each catalog file, which import-catalog.php
 * already reads, so this survives a re-import and is reviewable in Git.
 *
 *   node tag-applications.js catalog [--dry-run]
 */
const fs = require('fs');
const path = require('path');

const DIR = process.argv[2] || 'catalog';
const DRY = process.argv.includes('--dry-run');

// Word-boundary matching with a short suffix, so "pallet" reaches
// "palletizing" but "press" never fires on "impressive".
const rx = (word) =>
  new RegExp('\\b' + word.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\w{0,4}\\b', 'i');

const APPS = {
  'Warehouse and Distribution': {
    words: ['pallet', 'palletiz', 'depalletiz', 'order picking', 'order-picking', 'warehouse',
      'distribution cent', 'loading dock', 'shipping', 'fulfillment', 'stacking and shipping'],
    // These three exist so that a pallet truck can load them. That is not an
    // inference about the buyer, it is what the equipment is for.
    categories: ['pallet-lift-tables', 'floor-level-lift-tables', 'low-profile-lift-tables'],
  },
  'Assembly and Production': {
    words: ['assembly', 'sub-assembly', 'subassembly', 'machine feed', 'machine tending',
      'work positioning', 'workstation', 'work station', 'production line', 'ergonomic'],
    categories: [],
    // "stack two scissor assemblies and weld them together" is construction,
    // not an assembly line. Drop a line whose only mention is structural.
    excludeWhenOnly: /(scissor|leg|frame|pantograph|cylinder)\s+assembl/i,
  },
  'Food and Beverage': {
    words: ['food', 'beverage', 'dairy', 'bakery', 'brewery', 'usda', 'sanitary',
      'washdown', 'wash-down', 'wash down', 'hygien', 'food-grade', 'food grade'],
    // A stainless table exists to be washed down. Every stainless line in the
    // catalog that says anything about why says food or pharmaceutical.
    categories: ['stainless-steel-lift-tables'],
  },
  'Pharmaceutical and Cleanroom': {
    words: ['pharmaceutical', 'cleanroom', 'clean room', 'clean-room', 'sterile',
      'laboratory', 'medical'],
    categories: [],
  },
};

const slugOf = (categoryPath) =>
  String(categoryPath).split('>').pop().trim().toLowerCase()
    .replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

const report = {};
for (const name of Object.keys(APPS)) report[name] = { copy: 0, category: 0, total: 0 };
let total = 0;
const untagged = [];

for (const file of fs.readdirSync(DIR).filter((f) => f.endsWith('.json'))) {
  const full = path.join(DIR, file);
  const data = JSON.parse(fs.readFileSync(full, 'utf8'));
  if (!Array.isArray(data.lines)) continue;

  for (const line of data.lines) {
    total++;

    const prose = [line.title, line.excerpt, line.content, (line.key_features || []).join(' ')]
      .filter(Boolean).join(' ').replace(/<[^>]+>/g, ' ');
    const cats = (line.categories || []).map(slugOf);

    const tags = [];

    for (const [name, rule] of Object.entries(APPS)) {
      let byCopy = rule.words.some((w) => rx(w).test(prose));

      if (byCopy && rule.excludeWhenOnly) {
        // Strip the structural sense and see whether anything is left.
        const stripped = prose.replace(new RegExp(rule.excludeWhenOnly.source, 'gi'), ' ');
        byCopy = rule.words.some((w) => rx(w).test(stripped));
      }

      const byCategory = rule.categories.some((c) => cats.includes(c));

      if (byCopy || byCategory) {
        tags.push(name);
        report[name].total++;
        if (byCopy) report[name].copy++;
        else report[name].category++;
      }
    }

    if (tags.length) {
      line.applications = tags;
    } else {
      delete line.applications;
      untagged.push(line.slug);
    }
  }

  if (!DRY) fs.writeFileSync(full, JSON.stringify(data, null, 4) + '\n');
}

console.log('lines: ' + total + '\n');
console.log('application'.padEnd(32) + 'lines'.padStart(6) + 'by copy'.padStart(9) + 'by category'.padStart(13));
console.log('-'.repeat(60));
for (const [name, r] of Object.entries(report).sort((a, b) => b[1].total - a[1].total)) {
  console.log(name.padEnd(32) + String(r.total).padStart(6) + String(r.copy).padStart(9) + String(r.category).padStart(13));
}
console.log('\nuntagged (general purpose, no stated use): ' + untagged.length);
if (DRY) console.log('\n(dry run -- nothing written)');
