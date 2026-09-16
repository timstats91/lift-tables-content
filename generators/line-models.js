// Build the draft products that hold model tables for the generated catalogs
// (see README), from each manufacturer's published model data: APIs, spec
// tables, CSV files, data sheets and item catalogs. Replaces the "products"
// array in each catalog file; lines are left as they are.
//
//   node generators/line-models.js catalog
//
// Responses are cached in the system temp folder; delete that folder to refetch.
const fs = require('fs');
const { execFileSync } = require('child_process');

const CATALOG = process.argv[2];
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/128 Safari/537.36';
const CACHE = require('path').join(require('os').tmpdir(), 'edc-line-models-cache');
fs.mkdirSync(CACHE, { recursive: true });

function get(url) {
  const file = CACHE + '/' + require('crypto').createHash('md5').update(url).digest('hex');
  if (!fs.existsSync(file)) execFileSync('curl', ['-sL', '-A', UA, '-o', file, url]);
  return fs.readFileSync(file, 'utf8');
}

const clean = s => s.replace(/<[^>]+>/g, ' ').replace(/&#8243;|&#8221;|&#8220;|&quot;/g, '"').replace(/&#8242;|&#8217;/g, "'").replace(/&#215;/g, 'x').replace(/&#186;|&deg;|&#176;|º/g, '°').replace(/&amp;/g, '&').replace(/&nbsp;/g, ' ').replace(/\s+/g, ' ').trim();

// Inches from "9-3/4", "10 -3/4", "1 1/2", "3/8", "18\"", "10'" (feet), "4.25\"".
function inches(v) {
  if (v === undefined || v === null) return '';
  let s = String(v).trim().replace(/["”]/g, '').replace(/\s*-\s*(?=\d+\/\d+)/, ' ');
  if (!s || /n\/a/i.test(s)) return '';
  // Feet and inches: "1' 3-1/2", "11' 3-1/2".
  const fi = s.match(/^(\d+)'\s*-?\s*(.+)$/);
  if (fi) return Math.round((parseInt(fi[1]) * 12 + (inches(fi[2]) || 0)) * 1000) / 1000;
  let feet = false;
  if (/'$/.test(s)) { feet = true; s = s.replace(/'$/, ''); }
  const m = s.match(/^(\d*(?:\.\d+)?)?(?:\s+)?(?:(\d+)\/(\d+))?$/);
  if (!m || (!m[1] && !m[2])) return '';
  let n = (m[1] ? parseFloat(m[1]) : 0) + (m[2] ? parseInt(m[2]) / parseInt(m[3]) : 0);
  if (feet) n *= 12;
  return Math.round(n * 1000) / 1000;
}

function lbs(v) {
  const n = parseInt(String(v || '').replace(/[^\d]/g, ''));
  return isNaN(n) ? '' : n;
}

// "36 X 48", "6' X 8'", "108 x 68" -> "36 x 48" plus unit.
function size(v) {
  if (!v) return null;
  const s = String(v).replace(/[”"]/g, '').replace(/\*/g, '').trim();
  const m = s.match(/^([\d.'\s-]+?)\s*[xX]\s*([\d.'\s-]+)$/);
  if (!m) return null;
  const ft = /'/.test(s);
  // "8'-6" becomes 8.5 when the size is in feet.
  const part = p => { const f = p.match(/(\d+)'\s*-\s*(\d+)/); return f ? String(parseInt(f[1]) + parseInt(f[2]) / 12) : p.replace(/'/g, '').trim(); };
  return { text: `${part(m[1])} x ${part(m[2])}`, unit: ft ? 'ft' : 'in' };
}

function platform(min, max) {
  const a = size(min);
  const b = size(max);
  if (!a) return '';
  if (b && b.text !== a.text && b.unit === a.unit) return `${a.text} to ${b.text} ${a.unit}`;
  return `${a.text} ${a.unit}`;
}

// 22.1875 -> "22-3/16"
function frac(n) {
  const whole = Math.floor(n);
  let sixteenths = Math.round((n - whole) * 16);
  if (!sixteenths) return String(whole);
  let d = 16;
  while (sixteenths % 2 === 0) { sixteenths /= 2; d /= 2; }
  return `${whole}-${sixteenths}/${d}`;
}

function notes(parts) {
  return parts.filter(Boolean).join(', ');
}

function row(r) {
  const out = { model: r.model };
  for (const k of ['capacity_lbs', 'platform', 'lowered_height_in', 'raised_height_in', 'notes']) {
    if (r[k] !== '' && r[k] !== undefined && r[k] !== null) out[k] = r[k];
  }
  return out;
}

/* ---------------------------------------------------------------- Autoquip */

function autoquip(family) {
  const data = JSON.parse(get(`https://autoquip.com/wp-json/lifts/v1/filter?productLine=${family}&per_page=-1`));
  return data
    .filter(m => (m.productCategories || []).some(c => c.slug === family))
    .map(m => row({
      model: m.name.replace(/\s+/g, ''),
      capacity_lbs: lbs(m.capacity),
      platform: m.min_platform_width && m.min_platform_length
        ? platform(`${m.min_platform_width} x ${m.min_platform_length}`, m.max_platform_width && m.max_platform_length ? `${m.max_platform_width} x ${m.max_platform_length}` : '')
        : '',
      lowered_height_in: inches(m.lowered_height),
      raised_height_in: inches(m.raised_height),
      notes: notes([m.travel_in ? `${frac(inches(m.travel_in))} in travel` : '', m.travel_deg ? `${m.travel_deg}° tilt` : '']),
    }));
}

/* ----------------------------------------------------------- Advance Lifts */

function tables(html) {
  return [...html.matchAll(/<table[\s\S]*?<\/table>/gi)].map(t =>
    [...t[0].matchAll(/<tr[\s\S]*?<\/tr>/gi)].map(r => [...r[0].matchAll(/<t[dh][^>]*>([\s\S]*?)<\/t[dh]>/gi)].map(c => clean(c[1])))
  );
}

// Rows of one Advance Lifts table as objects keyed by header, split into
// sections where the table has a full-width heading row.
function advance(url, index = 0, columns = null) {
  const t = tables(get(url))[index];
  let header = null;
  let section = '';
  const rows = [];
  for (const cells of t) {
    if (!cells.some(Boolean)) continue;
    if (cells[0] === 'Model' || cells[0] === 'Model No.') {
      // Two-row headers (PT, ATT) use colspans, so their columns are passed in.
      header = columns || cells;
      continue;
    }
    if (!header) continue;
    if (cells.filter(Boolean).length === 1) { section = cells.filter(Boolean)[0]; continue; }
    if (cells.length !== header.length) continue;
    const o = { section };
    header.forEach((h, i) => { o[h.toLowerCase()] = cells[i]; });
    rows.push(o);
  }
  return rows;
}

const pick = (o, ...keys) => { for (const k of keys) { const hit = Object.keys(o).find(x => x.includes(k)); if (hit && o[hit]) return o[hit]; } return ''; };

function advanceLift(o) {
  const travel = inches(pick(o, 'travel'));
  return row({
    model: o.model || o['model no.'],
    capacity_lbs: lbs(pick(o, 'capacity')),
    platform: platform(pick(o, 'min platform', 'min. platform', 'platform minimum', 'minimum'), pick(o, 'max platform', 'max. platform', 'platform maximum', 'maximum platform', 'maximum')),
    lowered_height_in: inches(pick(o, 'lowered')),
    raised_height_in: inches(pick(o, 'raised')),
    notes: notes([travel ? `${frac(travel)} in travel` : '', pick(o, 'tilt') ? `${pick(o, 'tilt').replace(/°|º/g, '')}° tilt` : '', pick(o, 'turn')]),
  });
}

/* --------------------------------------------------------------------- ATI */

function ati(category) {
  let all = [];
  for (let page = 1; page < 5; page++) {
    const d = JSON.parse(get(`https://airtechnical.com/wp-json/wc/store/v1/products?category=${category}&per_page=100&page=${page}`));
    all = all.concat(d);
    if (d.length < 100) break;
  }
  return all.map(p => {
    const a = {};
    for (const at of p.attributes || []) a[at.name] = at.terms.map(t => clean(t.name)).join(' / ');
    const travel = inches(a['Vertical Trave']);
    return row({
      model: p.sku,
      capacity_lbs: lbs(a.Cap || a.Capacity),
      platform: platform(a['Table Size'] || a['Platform Size']),
      lowered_height_in: inches(a['Lowered Height']),
      raised_height_in: inches(a['Raised Ht']),
      notes: notes([travel ? `${frac(travel)} in travel` : '', a.Post ? `${a.Post}-post` : '']),
    });
  });
}

/* -------------------------------------------------------------- Southworth */

// RFC 4180 CSV, allowing line breaks inside quoted header cells.
function csv(text) {
  const rows = []; let row = []; let cell = ''; let q = false;
  text = text.replace(/^﻿/, '');
  for (let i = 0; i < text.length; i++) {
    const ch = text[i];
    if (q) {
      if (ch === '"' && text[i + 1] === '"') { cell += '"'; i++; } else if (ch === '"') q = false; else cell += ch;
    } else if (ch === '"') q = true;
    else if (ch === ',') { row.push(cell); cell = ''; }
    else if (ch === '\n' || ch === '\r') { if (ch === '\r' && text[i + 1] === '\n') i++; row.push(cell); rows.push(row); row = []; cell = ''; }
    else cell += ch;
  }
  if (cell || row.length) { row.push(cell); rows.push(row); }
  return rows.filter(r => r.some(c => c.trim())).map(r => r.map(c => c.replace(/\s+/g, ' ').trim()));
}

// "9 ½", "43 ⅝", "9-3/16" -> inches; unicode fractions first.
const UNI = { '½': '1/2', '¼': '1/4', '¾': '3/4', '⅛': '1/8', '⅜': '3/8', '⅝': '5/8', '⅞': '7/8', '⅓': '1/3', '⅔': '2/3' };
const uni = s => String(s || '').replace(/\s*([½¼¾⅛⅜⅝⅞⅓⅔])/g, (m, f) => ' ' + UNI[f]).replace(/⁄/g, '/').trim().replace(/(\d)\s+(\d+\/\d+)/g, '$1-$2');
const inch = v => inches(uni(v).replace(/"+$/, '').replace(/\s*(in\.?|inches)$/i, ''));

// Capacity: "2,000 lbs", "400 - 4500 lbs", "up to 2,200 lbs." -> { max, range }
function capacity(v) {
  const nums = String(v || '').replace(/,/g, '').match(/\d+(?:\.\d+)?/g);
  if (!nums) return { max: '', range: '' };
  const n = nums.map(Number);
  return { max: Math.max(...n), range: n.length > 1 && /\d\s*(-|–|to)\s*\d/.test(String(v).replace(/,/g, '')) ? `${n[0].toLocaleString('en-US')}–${n[n.length - 1].toLocaleString('en-US')} lb` : '' };
}

const cleanSize = v => uni(v).replace(/["”″]/g, '').replace(/\s*[xX]\s*/g, ' x ').replace(/\s+/g, ' ').trim();

function sizeRange(min, max) {
  const a = cleanSize(min), b = cleanSize(max);
  if (!a || !/\d/.test(a)) return '';
  return b && /\d/.test(b) && b !== a ? `${a} to ${b} in` : `${a} in`;
}

// Find a header index by any of the given patterns.
const col = (header, ...pats) => header.findIndex(h => pats.some(p => p.test(h)));

function southworth(file, extra = {}) {
  const rows = csv(get(`https://www.southworthproducts.com/wp-content/uploads/${file}`));
  const h = rows[0];
  const iModel = col(h, /^model/i);
  const iCap = col(h, /capacity/i);
  const iRaised = col(h, /raised height|extended height|lift height/i);
  const iLow = col(h, /lowered height|low height|compressed height/i);
  const iStd = col(h, /standard base|std\.? base|standard platform|^platform|pan size|platform size|usable pan|minimum platform/i);
  const iMax = col(h, /max(imum)?\.? platform/i);
  const iTilt = col(h, /tilt angle/i);
  const iTurn = col(h, /turntable diameter/i);
  const iPower = col(h, /voltage|^power$|^air$|min air/i);
  const iFork = col(h, /fork length/i);
  return rows.slice(1).map(c => {
    const cap = capacity(c[iCap]);
    let plat = iStd >= 0 ? sizeRange(c[iStd], iMax >= 0 ? c[iMax] : '') : '';
    if (!plat && iTurn >= 0 && c[iTurn]) plat = `${cleanSize(c[iTurn])} in round turntable`;
    if (!plat && iFork >= 0 && c[iFork]) plat = `${cleanSize(c[iFork])} in forks`;
    return row({
      model: c[iModel].replace(/\s*\(.*?\)\s*/g, ' ').trim(),
      capacity_lbs: cap.max,
      platform: plat,
      lowered_height_in: iLow >= 0 ? inch(c[iLow]) : '',
      raised_height_in: iRaised >= 0 ? inch(c[iRaised]) : '',
      notes: notes([
        cap.range ? `self-levels ${cap.range}` : '',
        iTilt >= 0 && c[iTilt] ? `${c[iTilt].replace(/[˚°]/g, '').replace(/\s*or\s*/, ' or ')}° tilt` : '',
        iPower >= 0 && c[iPower] && c[iPower] !== '--' ? c[iPower].replace(/\s+/g, ' ') : '',
        extra.note ? extra.note(c, h) : '',
      ]),
    });
  });
}

/* ------------------------------------------------------------ Presto / ECOA */

// Presto tables label every cell ("Load Capacity : 2000 lbs"), so rows are
// read by label. Group heading rows only fill the first cell.
function presto(path, index = 0) {
  const t = tables(get(`https://prestolifts.com/products/${path}`))[index] || [];
  const out = [];
  let travel = '';
  for (const cells of t) {
    const o = {};
    for (const cell of cells) {
      const m = cell.match(/^(.*?)\s*:\s*(.*)$/);
      if (m) o[m[1].toLowerCase().replace(/\s+/g, ' ').trim()] = m[2].trim();
    }
    if (o['vertical travel']) travel = o['vertical travel'];
    const model = o['model number'] || o.number || o.model;
    if (!model || cells.filter(Boolean).length < 3) continue;
    const g = (...keys) => { for (const k of keys) { const hit = Object.keys(o).find(x => x === k || x.startsWith(k)); if (hit && o[hit]) return o[hit]; } return ''; };
    const cap = capacity(g('load capacity', 'capacity', 'tilt capacity'));
    const low = g('lowered height', 'low height');
    const raised = g('raised height');
    const tr = inch(g('vertical travel') || '') || (raised && low ? '' : inch(travel));
    const tilt = g('degree of tilt');
    let plat = sizeRange(g('std. base & platform', 'standard platform', 'platform size', 'platform', 'standard base'), g('maximum platform', 'max platform'));
    if (!plat && g('turntable diameter')) plat = `${cleanSize(g('turntable diameter'))} in round turntable`;
    if (!plat && g('fork length')) plat = `${cleanSize(g('fork length'))} in forks`;
    const unit = s => /\d/.test(s) && !/["']/.test(s) ? s : s;
    out.push(row({
      model: model.replace(/\s+/g, ' '),
      capacity_lbs: cap.max,
      platform: plat,
      lowered_height_in: inch(unit(low)),
      raised_height_in: inch(raised),
      notes: notes([
        cap.range ? `self-levels ${cap.range}` : '',
        !cap.max && g('end/side capacity') ? `${capacity(g('end/side capacity')).max.toLocaleString('en-US')} lb end/side capacity` : '',
        tr ? `${frac(tr)} in travel` : '',
        tilt ? `${tilt.replace(/[˚°]/g, '')}° tilt` : '',
      ]),
    }));
  }
  return out;
}

/* ------------------------------------------------------------------ Vestil */

function vestilFamily(fid) {
  const h = get(`https://www.vestil.com/product.php?FID=${fid}`);
  const skus = [];
  for (const t of h.matchAll(/<table[\s\S]*?<\/table>/gi)) {
    if (!/Model #/.test(t[0])) continue;
    for (const r of t[0].matchAll(/<tr[\s\S]*?<\/tr>/gi)) {
      const c = [...r[0].matchAll(/<t[dh][^>]*>([\s\S]*?)<\/t[dh]>/gi)].map(x => clean(x[1]));
      if (c[0] && c[0] !== 'Model #') skus.push(c[0]);
    }
  }
  return skus;
}

function vestilAttrs(model) {
  const h = get(`https://www.vestil.com/skur.php?Model=${encodeURIComponent(model)}`);
  const a = {};
  for (const m of h.matchAll(/skur-spec-label">([\s\S]*?)<\/span>\s*<span class="skur-spec-value">([\s\S]*?)<\/span>/g)) a[clean(m[1]).replace(/:$/, '')] = clean(m[2]);
  for (const r of h.matchAll(/<tr[\s\S]*?<\/tr>/gi)) {
    const c = [...r[0].matchAll(/<t[dh][^>]*>([\s\S]*?)<\/t[dh]>/gi)].map(x => clean(x[1]));
    if (c.length === 2 && /:$/.test(c[0])) a[c[0].replace(/:$/, '')] = c[1];
  }
  return a;
}

// The family page's model chart: rows by capacity and raised height, no model numbers.
function vestilChart(fid) {
  const t = tables(get(`https://www.vestil.com/product.php?FID=${fid}`)).find(rows => rows.some(r => r.some(c => /^Capacity/.test(c)) && r.some(c => /^Raised Height/.test(c))));
  if (!t) return [];
  const hi = t.findIndex(r => r.some(c => /^Capacity/.test(c)));
  const h = t[hi];
  const at = (r, re) => { const i = h.findIndex(c => re.test(c)); return i >= 0 ? r[i] : ''; };
  return t.slice(hi + 1).filter(r => r.length === h.length).map(r => ({
    capacity: at(r, /^Capacity/), raised: at(r, /^Raised/), lowered: at(r, /^Lowered/), width: at(r, /^Platform Width/), length: at(r, /^Platform Length/),
  }));
}

function vestil(...fids) {
  const out = [];
  for (const fid of fids) {
    for (const model of vestilFamily(fid)) {
      const a = vestilAttrs(model);
      const g = (...keys) => { for (const k of keys) if (a[k]) return a[k]; return ''; };
      const cap = capacity(g('Uniform Capacity (lb.)', 'Uniform Capacity (lbs.)', 'Uniform Static Capacity (lb.)', 'Capacity (lbs.)', 'Capacity'));
      const pair = v => (v.match(/^(.+?)\s+to\s+(.+)$/) || []).slice(1);
      let low = g('Lowered Height (in.)', 'Platform Lowered Height (in.)', 'Minimum Height (in)', 'Platform Height (in.)', 'Lowered Height');
      let raised = g('Raised Height (in.)', 'Platform Raised Height (in.)', 'Maximum Height (in)', 'Raised Height');
      const span = g('Lower/Raised Height (in.)', 'Service Range (in.)');
      if (span && pair(span).length) { low = low || pair(span)[0]; raised = raised || pair(span)[1]; }
      const level = g('Level Height (in.)', 'Horizontal Height (in.)', 'Deck Level Height (in.)', 'Lowered Fork Height (in.)');
      let plat = '';
      const w = g('Platform Width (in.)', 'Platform Width (in,)', 'Usable Width (in.)', 'Usable Platform Width (in.)', 'Deck Inside Width (in.)');
      const l = g('Platform Length (in.)', 'Platform Length (in,)', 'Usable Length (in.)', 'Usable Platform Length (in.)', 'Deck Inside Length (in.)');
      if (w && l) plat = `${uni(w)} x ${uni(l)} in`;
      else if (g('Platform Width/Length (in.)')) plat = `${uni(g('Platform Width/Length (in.)'))} in`;
      else if (g('Platform Size (WxL) (in.)')) plat = `${cleanSize(g('Platform Size (WxL) (in.)'))} in`;
      else if (g('Platform Width Range (in.)') && g('Platform Length Range (in.)')) {
        const wr = pair(g('Platform Width Range (in.)')), lr = pair(g('Platform Length Range (in.)'));
        plat = wr.length && lr.length ? `${wr[0]} x ${lr[0]} to ${wr[1]} x ${lr[1]} in` : '';
      } else if (g('Platform Diameter (in.)')) plat = `${uni(g('Platform Diameter (in.)'))} in round`;
      else if (g('Deck Deminsions')) plat = `${cleanSize(g('Deck Deminsions'))} in`;
      else if (g('Fork Length (in.)')) plat = `${uni(g('Fork Length (in.)'))} in forks`;
      const tilt = g('Maximum Tilt Degree', 'Maximum Tilted Fork Degree', 'Degrees of Tilt', 'Max Tilt Angle', 'Maximum Tilt (degrees)', 'End Tilt (degrees)');
      const tiltDeg = tilt ? (tilt.match(/(\d+)\s*°?\s*$/) || tilt.match(/(\d+)/) || [])[1] : '';
      if (tiltDeg && !low) low = g('Lowered Fork Height (in.)');
      const travel = inch(g('Vertical Travel (in.)', 'Maximum Travel Distance (in)'));
      let power = g('Voltage/Phase', 'Standard Voltage/Phase', 'Power', 'AC Power', 'Power Source', 'Operation', 'Operation Method');
      if (!power && /^two$/i.test(g('Foot Pump Speed'))) power = '2-speed foot pump';
      else if (!power && g('Foot Pump Speed')) power = g('Foot Pump Speed');
      const posts = g('Post (qty.)');
      // Base models sold with a choice of platform (EHLTD-2-70) have sparse data
      // sheets; their specs come from the family's model chart, matched on
      // capacity and the raised height at the end of the model number.
      if ([cap.max, plat, low, raised].filter(Boolean).length < 2 && cap.max) {
        const raisedCode = parseInt((model.match(/-(\d+)$/) || [])[1]);
        const hit = vestilChart(fid).find(c => capacity(c.capacity).max === cap.max && inch(c.raised) === raisedCode);
        if (hit) {
          raised = hit.raised; low = hit.lowered;
          const [w1, w2 = w1] = uni(hit.width).replace(/"/g, '').split('-');
          const [l1, l2 = l1] = uni(hit.length).replace(/"/g, '').split('-');
          if (w1 && l1) plat = w1 === w2 && l1 === l2 ? `${w1} x ${l1} in` : `${w1} x ${l1} to ${w2} x ${l2} in`;
        }
      }
      // Some data sheets carry no specs at all; skip those rather than list a bare model number.
      if ([cap.max, plat, low, raised].filter(Boolean).length < 2) { report.push(`vestil ${model}: no usable specs, skipped`); continue; }
      out.push(row({
        model,
        capacity_lbs: cap.max,
        platform: plat,
        lowered_height_in: inch(low) || (tiltDeg ? inch(level) : ''),
        raised_height_in: inch(raised),
        notes: notes([
          cap.range ? `self-levels ${cap.range}` : '',
          travel ? `${frac(travel)} in travel` : '',
          tiltDeg ? `${tiltDeg}° tilt` : '',
          posts ? `${posts} post${posts === '1' ? '' : 's'}` : '',
          power ? power.replace(/\s*\/\s*/g, '/').replace(/\s+/g, ' ') : '',
        ]),
      }));
    }
  }
  return out;
}

/* ----------------------------------------------------------- Lift Products */

// Lift Products spec tables ("std-spec") have two header rows joined by
// rowspan/colspan, and some rows are missing their closing </tr>. Returns
// { cols, rows } per table, with composite headers such as "Height Lower" and
// each cell as { text, html }.
function specGrid(table) {
  const rows = table.split(/<tr\b/i).slice(1).map(r => [...r.matchAll(/<t([dh])([^>]*)>([\s\S]*?)<\/t[dh]>/gi)].map(c => ({
    th: c[1] === 'h', cs: +(c[2].match(/colspan="(\d+)"/) || [0, 1])[1], rs: +(c[2].match(/rowspan="(\d+)"/) || [0, 1])[1],
    text: clean(c[3].replace(/<option[^>]*>Select( One)?<\/option>/gi, '').replace(/<\/option>/gi, ' ').replace(/<br\s*\/?>/gi, ' ')), html: c[3],
  })));
  const out = [];
  const carry = {};
  for (const cells of rows) {
    if (!cells.length) continue;
    const line = [];
    let x = 0;
    const skip = () => { while (carry[x] && carry[x].n > 0) { line[x] = carry[x].cell; carry[x].n--; x++; } };
    for (const c of cells) {
      skip();
      for (let i = 0; i < c.cs; i++) { line[x] = c; if (c.rs > 1) carry[x] = { cell: c, n: c.rs - 1 }; x++; }
    }
    skip();
    // Work positioner tables put their second header row in <td> cells.
    out.push({ head: cells.every(c => c.th) || cells.every(c => !/\d/.test(c.text)), line });
  }
  const head = out.filter(r => r.head);
  const cols = (head[0] ? head[0].line : []).map((_, i) => [...new Set(head.map(r => r.line[i] && r.line[i].text).filter(Boolean))].join(' '));
  return { cols, rows: out.filter(r => !r.head).map(r => r.line) };
}

// One Lift Products page's model rows. Base models whose capacity cell is a
// menu of per-capacity pages are replaced by the rows on those pages.
function liftProducts(path, seen = new Set()) {
  const url = `https://www.liftproducts.com/${path}.html`;
  const html = get(url);
  const out = [];
  for (const t of html.matchAll(/<table[^>]*class="[^"]*std-spec[^"]*"[\s\S]*?<\/table>/gi)) {
    const { cols, rows } = specGrid(t[0]);
    const at = (...pats) => col(cols, ...pats);
    const iModel = at(/^(base )?model$/i);
    const iTravel = at(/vertical travel/i);
    const iLow = at(/^height\*? (lower|down|min)$/i, /^lowered height$/i);
    const iUp = at(/^height\*? (upper|up|max)$/i);
    const iCap = at(/^cap(acity)?\.?( \(lbs\))?( std)?$/i, /^capacity( \(lbs\))? min$/i);
    const iCapMax = at(/^capacity( \(lbs\))? max$/i);
    const iPlat = at(/^platform( size)?( min| std)?$/i);
    const iPlatMax = at(/^platform( size)? max$/i);
    const iTilt = at(/^tilt$/i);
    const iRot = at(/^rotation$/i);
    // Work positioner tables repeat "Height" for both columns.
    const heights = cols.map((c, i) => (c === 'Height' ? i : -1)).filter(i => i >= 0);
    const low = iLow >= 0 ? iLow : heights[0], up = iUp >= 0 ? iUp : heights[1];
    for (const r of rows) {
      const v = i => (i >= 0 && r[i] ? r[i].text : '');
      if (!v(iModel)) continue;
      const links = iCap >= 0 && r[iCap] ? [...r[iCap].html.matchAll(/value="(https:\/\/www\.liftproducts\.com\/[^"#]+)\.html/g)].map(m => m[1].replace('https://www.liftproducts.com/', '')) : [];
      if (links.length) {
        for (const l of [...new Set(links)]) if (!seen.has(l) && l !== path) { seen.add(l); out.push(...liftProducts(l, seen)); }
        continue;
      }
      const caps = capacity(v(iCap));
      const capMax = capacity(v(iCapMax)).max;
      const std = /std$/i.test(cols[iCap] || '');
      const round = s => { const m = s.match(/^([\d.]+)"?\s*(dia\.?|ø|&oslash;)/i); return m ? `${m[1]} in round` : ''; };
      const plat = round(v(iPlat)) || sizeRange(v(iPlat), v(iPlatMax));
      const travel = inch(v(iTravel));
      const tilt = v(iTilt).replace(/&deg;|°/g, '');
      out.push(row({
        model: v(iModel),
        capacity_lbs: capMax && !std ? capMax : caps.max,
        platform: plat,
        lowered_height_in: low >= 0 ? inch(v(low)) : '',
        raised_height_in: up >= 0 ? inch(v(up)) : '',
        notes: notes([
          capMax && !std ? `self-levels ${caps.max.toLocaleString('en-US')}–${capMax.toLocaleString('en-US')} lb` : '',
          capMax && std ? `available up to ${capMax.toLocaleString('en-US')} lb` : '',
          travel ? `${frac(travel)} in travel` : '',
          tilt ? `${tilt.replace(/^0-/, '').replace(/\//, ' or ')}° tilt` : '',
          v(iRot) ? `${v(iRot).replace(/&deg;|°/g, '')}° rotation` : '',
        ]),
      }));
    }
  }
  return out;
}

/* ------------------------------------------------------------------ Beacon */

const ENT = { '&frac12;': '½', '&frac14;': '¼', '&frac34;': '¾', '&#8539;': '⅛', '&#8540;': '⅜', '&#8541;': '⅝', '&#8542;': '⅞', '&#189;': '½', '&#188;': '¼', '&#190;': '¾' };

// Beacon model tables: a "Model No." header row, then rows numbered "1) BEHLT-1-43".
// Long series continue in further tables with the same header.
function beacon(path) {
  let html = get(`https://www.beacontechnology.com/lifting-tables/${path}/`);
  for (const [e, c] of Object.entries(ENT)) html = html.split(e).join(c);
  const out = [];
  for (const t of tables(html)) {
    // A table can hold several header rows: variants with other columns, and
    // option lists (ramps, foot controls) that have no capacity column.
    let h = null;
    let iCap, iStd, iMax, iW, iL, iLow, iUp, iRange, iTravel, iTilt;
    // Some series split their table under captions such as "90 Degree Tilt".
    let captionTilt = '';
    for (const r of t) {
      const caption = r.length === 1 && (r[0].match(/(\d+) Degree Tilt/i) || [])[1];
      if (caption) captionTilt = caption;
      if (/^Model No/i.test(r[0] || '')) {
        h = r;
        const at = (...pats) => col(h, ...pats);
        iCap = at(/^cap/i);
        iStd = at(/^platform std/i, /^platform size/i, /^platform top/i);
        iMax = at(/^platform max/i);
        iW = at(/^platform width/i);
        iL = at(/^platform length/i);
        iLow = at(/^lowered height/i, /^level height/i, /^horizontal height/i);
        iUp = at(/^raised height/i);
        iRange = at(/^service range/i);
        iTravel = at(/^(vertical )?travel( \(inches\))?$/i);
        iTilt = at(/degree of tilt/i, /angle of tilt/i, /^max\.? tilt/i);
        continue;
      }
      const m = (r[0] || '').match(/^\d+\)\s*(\S+)/);
      if (!h || !m || iCap < 0 || r.length !== h.length) continue;
      const v = i => (i >= 0 && r[i] ? uni(r[i]).replace(/(\d)-\s+(\d+\/\d+)/g, '$1-$2') : '');
      const cap = capacity(v(iCap));
      let plat = '';
      if (iW >= 0 && iL >= 0) {
        const span = s => s.replace(/["”]/g, '').split(/\s*(?:-|to)\s*/).map(x => x.trim()).filter(Boolean);
        const [w1, w2 = w1] = span(v(iW)), [l1, l2 = l1] = span(v(iL));
        plat = w1 && l1 ? (w1 === w2 && l1 === l2 ? `${w1} x ${l1} in` : `${w1} x ${l1} to ${w2} x ${l2} in`) : '';
      } else if (iStd >= 0) {
        const s = v(iStd).replace(/^(\S+?x[^x]+?)x.*$/, '$1');
        const ring = s.match(/^([\d-/]+)"?-?\s*(open ring|solid)/i);
        plat = ring ? `${ring[1]} in round` : sizeRange(s, v(iMax));
      }
      let low = v(iLow), up = v(iUp);
      const range = v(iRange).match(/^(.+?)\s+to\s+(.+)$/);
      if (range) { low = low || range[1]; up = up || range[2]; }
      const travel = inch(v(iTravel));
      const tilt = v(iTilt).replace(/degrees?|°/gi, '').trim() || captionTilt;
      out.push(row({
        model: m[1],
        capacity_lbs: cap.max,
        platform: plat,
        lowered_height_in: inch(low),
        raised_height_in: inch(up),
        notes: notes([
          cap.range ? `self-levels ${cap.range}` : '',
          travel ? `${frac(travel)} in travel` : '',
          tilt ? `${tilt.replace(/,?\s*&\s*|,\s*/g, ', ').replace(/, (\d+)$/, ' or $1')}° tilt` : '',
        ]),
      }));
    }
  }
  return out;
}

/* ----------------------------------------------------------- Wesco / Lexco */

// Wesco's catalog lists a group's items; each item page has an attribute
// table ("Model | N/A LT-02-1616") and a title with the capacity.
function wesco(group, filter = () => true) {
  const C = 'https://catalog.wescomfg.com';
  const list = get(`${C}/viewitems/${group}?pagesize=200`);
  const items = [...new Set([...list.matchAll(/href="(\/item\/[^"]+)"/g)].map(m => m[1]))];
  const out = [];
  for (const item of items) {
    const html = get(C + item);
    const title = clean((html.match(/<title>([^<]*)/) || [])[1] || '').replace(/ On Wesco.*$/, '');
    const a = {};
    for (const r of tables(html).flat()) if (r.length === 2 && r[1].startsWith('N/A')) a[r[0]] = r[1].replace(/^N\/A\s*/, '');
    const part = (title.match(/^Part No\. ([^,]+)/) || [])[1];
    const model = a.Model || part;
    if (!model || !a['Raised Height'] && !a.Capacity || !filter(model, a, title)) continue;
    const caps = capacity(a.Capacity || (title.match(/([\d,]+) lb/) || [])[1]);
    const w = a['Table Width'], l = a['Table Length'];
    let plat = w && l ? `${cleanSize(w)} x ${cleanSize(l)} in` : (a.Platform ? `${cleanSize(a.Platform)} in` : '');
    if (!plat && a['Carousel Diameter']) plat = `${cleanSize(a['Carousel Diameter'])} in round`;
    const posts = a['Support Posts'];
    const travel = inch((a.Travel || a.Lift || '').replace(/\s+"/, '"'));
    out.push(row({
      model,
      capacity_lbs: caps.max,
      platform: plat.replace(/\s+in/, ' in'),
      lowered_height_in: inch((a['Lowered Height'] || '').replace(/\s+"/, '"')),
      raised_height_in: inch((a['Raised Height'] || '').replace(/\s*\(.*\)/, '').replace(/\s+"/, '"')),
      notes: notes([
        caps.range ? `self-levels ${caps.range}` : '',
        travel ? `${frac(travel)} in travel` : '',
        posts ? (/none/i.test(posts) ? 'no support posts' : `${posts} support posts`) : '',
        /double scissor/i.test(title + (a.Capacity || '')) ? 'double scissor' : '',
      ]),
    }));
  }
  return out;
}

/* -------------------------------------------------------------- Econo Lift */

// Econo Lift model tables: a "<SERIES> MODEL NO." header, section rows such
// as "36" Travel - 24" Wide x 48" Long Platform/Base", then model rows. Tilter
// pages instead list models across the columns of a spec table.
function econo(path) {
  const out = [];
  for (const t of tables(get(`https://econolift.net/${path}/`))) {
    if (/^SPECIFICATIONS$/i.test(t[0] && t[0][1])) {
      const models = t[0].slice(2);
      const spec = label => (t.find(r => new RegExp(label, 'i').test(r[1] || '')) || []).slice(2);
      models.forEach((m, i) => {
        const caps = String(spec('^Capacity')[i] || '').split('/');
        m.split('/').forEach((model, j) => out.push(row({
          model,
          capacity_lbs: lbs(caps[j] || caps[0]),
          platform: spec('^Fork Length')[i] ? `${cleanSize(spec('^Fork Length')[i].replace(/\s*in\.?$/, ''))} in forks` : '',
          lowered_height_in: inch(String(spec('^Lowered Height')[i] || '').replace(/\s*in\..*$/, '')),
          notes: notes([
            spec('^Lift$')[i] ? `${frac(inch(spec('^Lift$')[i].replace(/\s*in\.?$/, '')))} in lift` : '',
            spec('^Tilt Angle')[i] ? `${spec('^Tilt Angle')[i].replace(/[˚°]/g, '')}° tilt` : '',
          ]),
        })));
      });
      continue;
    }
    const h = t[0] || [];
    if (!/MODEL NO/i.test(h[0] || '')) continue;
    const at = (...pats) => col(h, ...pats);
    const iCap = at(/^CAPACITY/i);
    const iStd = at(/STD\.? PLATFORM|^PLATFORM SIZE/i);
    const iMax = at(/OVERSIZ|MAX\.? PLATFORM/i);
    const iLow = at(/^LOW(ER|'D)? HEIGHT/i, /^PLATFORM HEIGHT/i);
    const iUp = at(/^RAISED HEIGHT/i, /^TOTAL (HEIGHT|HT)/i);
    const iTravel = at(/^(VERTICAL )?TRAVEL/i);
    const iTilt = at(/^TILT ANGLE/i);
    let sectionTravel = '';
    for (const r of t.slice(1)) {
      if (r.filter(Boolean).length === 1) { sectionTravel = (r[0].match(/^([\d.]+)"\s*Travel/i) || [])[1] || ''; continue; }
      if (!r[0] || r.length !== h.length) continue;
      const caps = capacity(r[iCap]);
      const travel = inch(iTravel >= 0 ? r[iTravel] : '') || inch(sectionTravel);
      const tilt = iTilt >= 0 ? r[iTilt].replace(/[˚°]/g, '') : '';
      out.push(row({
        model: r[0].replace(/\s+/g, ' '),
        capacity_lbs: caps.max,
        platform: iStd >= 0 ? sizeRange(r[iStd], iMax >= 0 ? r[iMax] : '') : '',
        lowered_height_in: iLow >= 0 ? inch(r[iLow]) : '',
        raised_height_in: iUp >= 0 ? inch(r[iUp]) : '',
        notes: notes([
          caps.range ? `self-levels ${caps.range}` : '',
          travel ? `${frac(travel)} in travel` : '',
          tilt ? `${tilt}° tilt` : '',
        ]),
      }));
    }
  }
  return out;
}

/* ----------------------------------------------- Premier Handling Solutions */

// PHS pages list part numbers ("24" Travel 1000999 2000 lbs., ...) and, in a
// separate "SPECIFICATIONS" block, one column per model under each travel or
// series heading. Part numbers are matched to columns in order within a group;
// capacities come from the specification block, since the part list has typos
// (80000 for 8,000 lb).
function phs(path) {
  const html = get(`https://www.phsinc.com/${path}/`);
  const lines = html.replace(/<(script|style)[\s\S]*?<\/\1>/gi, ' ').replace(/<(br|\/p|\/li|\/h\d|\/tr|\/div|\/td|\/th)[^>]*>/gi, '\n').split('\n').map(l => clean(l)).filter(Boolean);
  const norm = s => s.toUpperCase().replace(/[″”"]/g, '"').replace(/\s+/g, ' ').replace(/ TRAVEL$/, '').trim();
  // Part list: "24" Travel 1000999 2000 lbs., 36" Travel ..."
  const list = lines.find(l => /\d{7} \d+ lbs/.test(l)) || '';
  const parts = {};
  for (const m of list.matchAll(/(X?(?:WB)?\d+"?\s*(?:Travel|Series))\s+(\d{7})/gi)) (parts[norm(m[1])] = parts[norm(m[1])] || []).push(m[2]);
  const start = lines.findIndex(l => /SPECIFICATIONS$/.test(l));
  const groups = [];
  let g = null, key = null;
  for (const l of lines.slice(start + 1)) {
    if (/^Contact Us$/i.test(l)) break;
    if (/^X?(?:WB)?\d+[″”"]\s*(TRAVEL|Series)$/i.test(l) || /^\d+ Series$/i.test(l)) { g = { label: norm(l), cols: {} }; groups.push(g); key = null; continue; }
    if (!g) continue;
    if (/^[A-Z][A-Z .\/]+$/.test(l) && !/\d/.test(l) || /^Platform W\/L$/i.test(l)) { key = l.toUpperCase(); g.cols[key] = []; continue; }
    if (key) g.cols[key].push(l);
  }
  const out = [];
  const used = {};
  for (const grp of groups) {
    const caps = grp.cols['LOAD CAPACITY'] || [];
    const pool = parts[grp.label] || [];
    caps.forEach((c, i) => {
      const n = (used[grp.label] = (used[grp.label] || 0) + 1) - 1;
      const model = pool[n];
      if (!model) return;
      const col = k => (grp.cols[k] || [])[i] || '';
      const fix = s => s.replace(/(^|\D)3(36)(?=\D)/, '$1$2'); // "336″ x 48″" is 36 x 48 on PHS's heavy duty sheet
      const plat = sizeRange(fix(col('STANDARD PLATFORM') || col('PLATFORM SIZE') || col('PLATFORM W/L')), col('MAX. PLATFORM'));
      const travel = inch((grp.label.match(/^X?(?:WB)?(\d+)"$/) || [])[1] || '');
      out.push(row({
        model,
        capacity_lbs: lbs(c),
        platform: plat,
        lowered_height_in: inch(col('LOWERED HEIGHT').replace(/[”″"]/g, '')),
        raised_height_in: inch(col('RAISED HEIGHT').replace(/[”″"]/g, '')),
        notes: notes([
          travel ? `${frac(travel)} in travel` : '',
          /^WB/.test(grp.label) ? 'wide base' : '',
          /SERIES/.test(grp.label) ? grp.label.toLowerCase() : '',
        ]),
      }));
    });
  }
  return out;
}

/* ------------------------------------------------------ Typed from PDFs */

// Blue Giant and Pentalift publish model specifications only in PDFs, as
// images or merged-cell tables, so their rows are typed into
// generators/data/pdf-models.json with the PDF each came from.
const PDF_MODELS = JSON.parse(fs.readFileSync(require('path').join(__dirname, 'data', 'pdf-models.json'), 'utf8')).products;
const pdfPlan = brand => PDF_MODELS.filter(x => x.brand === brand).map(x => P(x.line, x.stem, x.title, x.source, () => x.rows.map(row)));

/* ---------------------------------------------------------------- Products */

const AQ = 'https://autoquip.com/products/';
const AL = 'https://www.advancelifts.com/';
const ATI = 'https://airtechnical.com/product/';

const P = (line, stem, title, source, rows) => ({ line, stem, title, source, rows });

const plan = {
  autoquip: [
    P('autoquip-series-35-scissor-lift-tables', 'autoquip-series-35-standard', 'Series 35 Standard', AQ + 'scissor-lift-tables/series-35/', () => autoquip('series-35')),
    P('autoquip-series-35-scissor-lift-tables', 'autoquip-series-35-extra-narrow', 'Series 35 Extra Narrow', AQ + 'scissor-lift-tables/series-35-extra-narrow/', () => autoquip('series-35-extra-narrow')),
    P('autoquip-series-35-scissor-lift-tables', 'autoquip-series-35-extra-wide', 'Series 35 Extra Wide', AQ + 'scissor-lift-tables/series-35-extra-wide/', () => autoquip('series-35-extra-wide')),
    P('autoquip-series-35-scissor-lift-tables', 'autoquip-series-35-double-long', 'Series 35 Double Long', AQ + 'scissor-lift-tables/series-35-double-long/', () => autoquip('series-35-double-long')),
    P('autoquip-series-35-scissor-lift-tables', 'autoquip-series-35-double-wide', 'Series 35 Double Wide', AQ + 'scissor-lift-tables/series-35-double-wide/', () => autoquip('series-35-double-wide')),
    P('autoquip-series-35-scissor-lift-tables', 'autoquip-series-35-exw-double-long', 'Series 35 Extra Wide Double Long', AQ + 'scissor-lift-tables/series-35-exw-double-long/', () => autoquip('series-35-exw-double-long')),
    P('autoquip-series-35-low-profile-lift-tables', 'autoquip-series-35-low-profile', 'Series 35 Low Profile', AQ + 'scissor-lift-tables/series-35-low-profile/', () => autoquip('series-35-low-profile')),
    P('autoquip-series-35-rover-mobile-lift-tables', 'autoquip-series-35-rover-mobile', 'Series 35 Rover Mobile', AQ + 'scissor-lift-tables/series-35-rover-mobile/', () => autoquip('series-35-rover-mobile')),
    P('autoquip-series-35-double-high-lift-tables', 'autoquip-series-35-double-high', 'Series 35 Double High', AQ + 'scissor-lift-tables/series-35-double-high/', () => autoquip('series-35-double-high')),
    P('autoquip-series-35-triple-high-lift-tables', 'autoquip-series-35-triple-high', 'Series 35 Triple High', AQ + 'scissor-lift-tables/series-35-triple-high/', () => autoquip('series-35-triple-high')),
    P('autoquip-titan-scissor-lift-tables', 'autoquip-titan', 'Titan', AQ + 'scissor-lift-tables/titan-scissor-lift-table/', () => autoquip('titan-scissor-lift-table')),
    P('autoquip-super-titan-scissor-lift-tables', 'autoquip-super-titan-single', 'Super Titan', AQ + 'scissor-lift-tables/super-titan/', () => autoquip('super-titan')),
    P('autoquip-super-titan-scissor-lift-tables', 'autoquip-super-titan-double-long', 'Super Titan Double Long', AQ + 'scissor-lift-tables/super-titan-double-long/', () => autoquip('super-titan-double-long')),
    P('autoquip-super-titan-scissor-lift-tables', 'autoquip-super-titan-double-wide', 'Super Titan Double Wide', AQ + 'scissor-lift-tables/super-titan-double-wide/', () => autoquip('super-titan-double-wide')),
    P('autoquip-super-titan-scissor-lift-tables', 'autoquip-super-titan-quad', 'Super Titan Quad', AQ + 'scissor-lift-tables/super-titan-quad/', () => autoquip('super-titan-quad')),
    P('autoquip-q-lift-scissor-lifts', 'autoquip-q-lift', 'Q Lift', AQ + 'scissor-lift-tables/q-lift-scissor-lift/', () => autoquip('q-lift-scissor-lift')),
    P('autoquip-double-pantograph-scissor-lifts', 'autoquip-double-pantograph', 'Double Pantograph', AQ + 'scissor-lift-tables/double-pantograph/', () => autoquip('double-pantograph')),
    P('autoquip-mechanical-scissor-lifts', 'autoquip-stacking-chain-mechanical', 'Stacking Chain Mechanical', AQ + 'scissor-lift-tables/stacking-chain-mechanical/', () => autoquip('stacking-chain-mechanical')),
    P('autoquip-mechanical-scissor-lifts', 'autoquip-vertical-ball-screw-mechanical', 'Vertical Ball Screw Mechanical', AQ + 'scissor-lift-tables/vertical-ball-screw-mechanical/', () => autoquip('vertical-ball-screw-mechanical')),
    P('autoquip-mechanical-scissor-lifts', 'autoquip-electro-mechanical', 'Electro-Mechanical', AQ + 'scissor-lift-tables/electro-mechanical-scissor-lift/', () => autoquip('electro-mechanical-scissor-lift')),
    P('autoquip-xlr-pan-style-scissor-lifts', 'autoquip-xlr', 'XLR', AQ + 'scissor-lift-tables/xlr-hydraulic/', () => autoquip('xlr-hydraulic')),
    P('autoquip-abs-pneumatic-scissor-lifts', 'autoquip-abs-pneumatic', 'ABS Pneumatic', AQ + 'scissor-lift-tables/abs-pneumatic/', () => autoquip('abs-pneumatic')),
    P('autoquip-air-force-lift-and-tilt-tables', 'autoquip-air-force-lift-and-tilt', 'Air Force Lift and Tilt Table', AQ + 'scissor-lift-tables/air-force-lift-and-tilt-table/', () => autoquip('air-force-lift-and-tilt-table')),
    P('autoquip-air-force-pneumatic-tilters', 'autoquip-air-force-pneumatic-tilter', 'Air Force Pneumatic Tilter', AQ + 'tilters/air-force-pneumatic-tilters/', () => autoquip('air-force-pneumatic-tilters')),
    P('autoquip-series-35-tilters', 'autoquip-series-35-tilter', 'Series 35 Tilter', AQ + 'tilters/series-35-tilters/', () => autoquip('series-35-tilters')),
    P('autoquip-autotilt-portable-tilters', 'autoquip-autotilt', 'AutoTilt Portable Tilter', AQ + 'tilters/autotilt-portable-tilter/', () => autoquip('autotilt-portable-tilter')),
  ],
  'american-lifts': [
    P('american-lifts-torklift-scissor-lift-tables', 'american-lifts-torklift', 'TorkLift', AQ + 'scissor-lift-tables/torklift/', () => autoquip('torklift')),
    P('american-lifts-torklift-t2-double-pantograph-lifts', 'american-lifts-torklift-t2', 'TorkLift T2', AQ + 'scissor-lift-tables/torklift-t2-double-pantograph/', () => autoquip('torklift-t2-double-pantograph')),
    P('american-lifts-torklift-t3-triple-pantograph-lifts', 'american-lifts-torklift-t3', 'TorkLift T3', AQ + 'scissor-lift-tables/torklift-t3-triple-pantograph/', () => autoquip('torklift-t3-triple-pantograph')),
    P('american-lifts-torklift-t4-quad-pantograph-lifts', 'american-lifts-torklift-t4', 'TorkLift T4', AQ + 'scissor-lift-tables/torklift-t4-quad-pantograph/', () => autoquip('torklift-t4-quad-pantograph')),
    P('american-lifts-compact-lift-tables', 'american-lifts-compact-lift', 'Compact Lift', AQ + 'scissor-lift-tables/compact-lift/', () => autoquip('compact-lift')),
    P('american-lifts-tiltlift-lift-and-tilt-tables', 'american-lifts-tiltlift', 'TiltLift', AQ + 'tilters/tiltlift/', () => autoquip('tiltlift')),
    P('american-lifts-90-degree-tilters', 'american-lifts-90-degree-tilter', '90 Degree Tilter', AQ + 'tilters/90-degree-tilter/', () => autoquip('90-degree-tilter')),
    P('american-lifts-tork-non-intrusive-tilters', 'american-lifts-tork-non-intrusive-tilter', 'Tork Non-Intrusive Tilter', AQ + 'tilters/tork-non-intrusive-tilter/', () => autoquip('tork-non-intrusive-tilter')),
  ],
  'advance-lifts': [
    P('advance-lifts-p-series-lift-tables', 'advance-lifts-p-series-single', 'P Series Single Scissor', AL + 'scissors-lifts-tables/single-scissors-p-series-lift-tables/', () => advance(AL + 'scissors-lifts-tables/single-scissors-p-series-lift-tables/').map(advanceLift)),
    P('advance-lifts-p-series-lift-tables', 'advance-lifts-p-series-double-wide', 'P Series Double Wide', AL + 'scissors-lift-tables/double-wide-pdw-series-lift-tables/', () => advance(AL + 'scissors-lift-tables/double-wide-pdw-series-lift-tables/').map(advanceLift)),
    P('advance-lifts-p-series-lift-tables', 'advance-lifts-p-series-double-long', 'P Series Double Long', AL + 'index.php/scissors-lift-tables/double-long-pdl-series-lift-tables/', () => advance(AL + 'index.php/scissors-lift-tables/double-long-pdl-series-lift-tables/').map(advanceLift)),
    P('advance-lifts-p-series-lift-tables', 'advance-lifts-p-series-double-extra-long', 'P Series Double Extra Long', AL + 'scissors-lift-tables/double-long-pdl-series-lift-tables-2/', () => advance(AL + 'scissors-lift-tables/double-long-pdl-series-lift-tables-2/').map(advanceLift)),
    P('advance-lifts-hd-series-lift-tables', 'advance-lifts-hd-series-single', 'HD Series Single Scissor', AL + 'scissors-lifts-tables/single-scissors-hd-series-lift-tables/', () => advance(AL + 'scissors-lifts-tables/single-scissors-hd-series-lift-tables/').map(advanceLift)),
    P('advance-lifts-hd-series-lift-tables', 'advance-lifts-hd-series-double-wide', 'HD Series Double Wide', AL + 'scissor-lifts-tables/double-wide-hd-series-lift-tables/', () => advance(AL + 'scissor-lifts-tables/double-wide-hd-series-lift-tables/').map(advanceLift)),
    P('advance-lifts-hd-series-lift-tables', 'advance-lifts-hd-series-double-long', 'HD Series Double Long', AL + 'scissor-lifts-tables/double-long-hd-series-lift-tables/', () => advance(AL + 'scissor-lifts-tables/double-long-hd-series-lift-tables/').map(advanceLift)),
    P('advance-lifts-super-duty-and-jumbo-lift-tables', 'advance-lifts-super-duty-single-long', 'Super Duty Single Long', AL + 'index.php/scissors-lift-tables/super-duty-sd-series-lift-tables/', () => advance(AL + 'index.php/scissors-lift-tables/super-duty-sd-series-lift-tables/').map(advanceLift)),
    P('advance-lifts-super-duty-and-jumbo-lift-tables', 'advance-lifts-super-duty-double-long', 'Super Duty Double Long', AL + 'index.php/scissors-lift-tables/double-long-sd-series-lift-tables/', () => advance(AL + 'index.php/scissors-lift-tables/double-long-sd-series-lift-tables/').map(advanceLift)),
    P('advance-lifts-super-duty-and-jumbo-lift-tables', 'advance-lifts-jumbo-single-scissor', 'Jumbo Single Scissor', AL + 'index.php/scissors-lift-tables/jumbo-jdl-series-lift-tables/', () => advance(AL + 'index.php/scissors-lift-tables/jumbo-jdl-series-lift-tables/').filter(o => /SINGLE/.test(o.section)).map(advanceLift)),
    P('advance-lifts-super-duty-and-jumbo-lift-tables', 'advance-lifts-jumbo-double-scissor', 'Jumbo Double Scissor', AL + 'index.php/scissors-lift-tables/jumbo-jdl-series-lift-tables/', () => advance(AL + 'index.php/scissors-lift-tables/jumbo-jdl-series-lift-tables/').filter(o => /DOUBLE/.test(o.section)).map(advanceLift)),
    P('advance-lifts-high-cycle-lift-tables', 'advance-lifts-high-cycle', 'High Cycle (HC)', AL + 'scissors-lift-tables/high-cycle-hc-series-lifts/', () => advance(AL + 'scissors-lift-tables/high-cycle-hc-series-lifts/').map(advanceLift)),
    P('advance-lifts-high-cycle-lift-tables', 'advance-lifts-ultra-high-cycle', 'Ultra High Cycle (UHC)', AL + 'index.php/scissors-lift-tables/ultra-high-cycle-uhc-series-lift-tables/', () => advance(AL + 'index.php/scissors-lift-tables/ultra-high-cycle-uhc-series-lift-tables/').map(advanceLift)),
    P('advance-lifts-air-spring-lift-tables', 'advance-lifts-at-single', 'AT Single Air Spring', AL + 'index.php/scissors-lift-tables/air-spring-actuated-at-series/', () => advance(AL + 'index.php/scissors-lift-tables/air-spring-actuated-at-series/').map(advanceLift)),
    P('advance-lifts-air-spring-lift-tables', 'advance-lifts-atdl-double-long', 'ATDL Double Long Air Spring', AL + 'scissors-lift-tables/air-spring-double-long-atdl-series/', () => advance(AL + 'scissors-lift-tables/air-spring-double-long-atdl-series/').map(advanceLift)),
    P('advance-lifts-air-spring-lift-tables', 'advance-lifts-atdw-double-wide', 'ATDW Double Wide Air Spring', AL + 'scissors-lift-tables/air-spring-double-wide-atdw-series/', () => advance(AL + 'scissors-lift-tables/air-spring-double-wide-atdw-series/').map(advanceLift)),
    P('advance-lifts-multi-stage-lift-tables', 'advance-lifts-msl', 'MSL Multi-Stage', AL + 'scissors-lift-tables/multi-stage-msl-series-lift-tables/', () => advance(AL + 'scissors-lift-tables/multi-stage-msl-series-lift-tables/').map(advanceLift)),
    P('advance-lifts-multi-stage-lift-tables', 'advance-lifts-bfl', 'BFL Multi-Stage', AL + 'scissors-lift-tables/big-friggin-bfl-series-lift-tables/', () => advance(AL + 'scissors-lift-tables/big-friggin-bfl-series-lift-tables/').map(advanceLift)),
    // Pallet Pro specs are published as images; transcribed from the three detail images.
    P('advance-lifts-pallet-pro-palletizers', 'advance-lifts-pallet-pro', 'Pallet Pro', AL + 'palletizer-self-leveling-lifts/', () => [
      { model: 'SL-S', capacity_lbs: 4500, platform: '43 in round turntable', lowered_height_in: 9.5, raised_height_in: 28, notes: '18.5 in travel, spring' },
      { model: 'SL-P', capacity_lbs: 4500, platform: '43 in round turntable', lowered_height_in: 10.5, raised_height_in: 30.5, notes: '20 in travel, air spring' },
      { model: 'PL-2524', capacity_lbs: 2500, platform: '43 in round turntable', lowered_height_in: 8.75, raised_height_in: 32.75, notes: '24 in travel, 110V electric-hydraulic' },
      { model: 'PL-4024', capacity_lbs: 4000, platform: '43 in round turntable', lowered_height_in: 8.75, raised_height_in: 32.75, notes: '24 in travel, 110V electric-hydraulic' },
    ]),
    P('advance-lifts-pt-series-lift-and-tilt-tables', 'advance-lifts-pt-series', 'PT Series', AL + 'tilt-tables-upenders/pt-series-hydraulic-lifts-tilt-tables-2000-5500-24-48-travel-30-45-60-degree-of-tilt/', () => advance(AL + 'tilt-tables-upenders/pt-series-hydraulic-lifts-tilt-tables-2000-5500-24-48-travel-30-45-60-degree-of-tilt/', 0, ['Model', 'Travel', 'Tilt', 'Capacity', 'Min Platform', 'Max Platform', 'Lowered', 'Raised', 'Lift speed', 'Tilt speed', 'Weight']).map(advanceLift)),
    P('advance-lifts-att-series-air-lift-and-tilt-tables', 'advance-lifts-att-series', 'ATT Series', AL + 'tilt-tables-upenders/att-series-air-lift-tilt-tables-1500-6000-24-travel-35-degree-of-tilt/', () => advance(AL + 'tilt-tables-upenders/att-series-air-lift-tilt-tables-1500-6000-24-travel-35-degree-of-tilt/', 0, ['Model', 'Capacity', 'Min Platform', 'Max Platform', 'Lowered', 'Travel', 'Tilt', 'Weight']).map(advanceLift)),
    P('advance-lifts-tt-series-electric-tilt-tables', 'advance-lifts-tt-series', 'TT Series', AL + 'tilt-tables-upenders/electric-tilters-tt/', () => advance(AL + 'tilt-tables-upenders/electric-tilters-tt/').map(o => row({ ...advanceLift(o), notes: `${o['degrees of tilt']}° tilt, ${o['number of cylinders']} cylinder${o['number of cylinders'] === '1' ? '' : 's'}` }))),
    P('advance-lifts-ati-series-air-tilt-tables', 'advance-lifts-ati-series', 'ATI Series', AL + 'tilt-tables-upenders/air-operted-tilters-ati/', () => advance(AL + 'tilt-tables-upenders/air-operted-tilters-ati/').map(o => row({ ...advanceLift(o), notes: `${o.tilt}° tilt, ${o['air springs']} air spring${o['air springs'] === '1' ? '' : 's'}` }))),
    P('advance-lifts-bin-tilters', 'advance-lifts-bin-tilter', 'Bin Tilter', AL + 'tilt-tables-upenders/bin-tilters-bt/', () => advance(AL + 'tilt-tables-upenders/bin-tilters-bt/').map(o => row({
      model: o.model,
      capacity_lbs: lbs(o['capacity lbs.']),
      platform: o['inside pan'] && o['inside pan'] !== 'N/A' ? `${size(o['inside pan']).text} in pan` : `${inches(o['fork length'])} in forks`,
      notes: `${o['tilt degrees']}° tilt, ${/^BTP/.test(o.model) ? 'stationary pan, 110V' : /^BTS/.test(o.model) ? 'portable straddle, 12V battery' : 'portable, 12V battery'}`,
    }))),
    P('advance-lifts-pm-pp-series-lift-and-turn-tables', 'advance-lifts-pm-series-manual-turn', 'PM Series Manual Turn', AL + 'turn-tables/lift-turn-pm-pp-series/', () => advance(AL + 'turn-tables/lift-turn-pm-pp-series/', 0).map(advanceLift)),
    P('advance-lifts-pm-pp-series-lift-and-turn-tables', 'advance-lifts-pp-series-powered-turn', 'PP Series Powered Turn', AL + 'turn-tables/lift-turn-pm-pp-series/', () => advance(AL + 'turn-tables/lift-turn-pm-pp-series/', 1).map(advanceLift)),
    P('advance-lifts-atr-series-air-lift-and-turn-tables', 'advance-lifts-atr-series', 'ATR Series', AL + 'turn-tables/air-operated-lift-turn-atr-series/', () => advance(AL + 'turn-tables/air-operated-lift-turn-atr-series/').map(advanceLift)),
  ],
  'air-technical-industries': [
    P('ati-zero-low-lift-tables', 'ati-zero-low-lift-table', 'Zero-Low Lift Table', ATI + 'zero-low-scissors-lift-tables/', () => ati(1125)),
    P('ati-low-profile-zero-low-lift-tables', 'ati-low-profile-zero-low-lift-table', 'Low-Profile Zero-Low Lift Table', ATI + 'low-profile-zero-low-lift-tables/', () => ati(1126)),
    P('ati-zero-low-lift-and-tilt-tables', 'ati-zero-low-lift-and-tilt-table', 'Zero-Low Lift and Tilt Table', ATI + 'zero-low-lift-tilt-tables/', () => ati(1124)),
    P('ati-zero-low-3-point-entry-lift-tables', 'ati-zero-low-3-point-entry-lift-table', 'Zero-Low 3-Point Entry Lift Table', ATI + 'zero-low-3-point-entry-lift-tables-2/', () => ati(1122)),
    P('ati-hydraulic-lift-tables', 'ati-hydraulic-lift-table', 'Hydraulic Lift Table', ATI + 'hydraulic-lift-table/', () => ati(1113)),
    P('ati-vertical-column-lift-tables', 'ati-vertical-column-lift-table', 'Vertical Column Lift Table', ATI + 'vertical-column-lift-tables/', () => ati(1208)),
    P('ati-mini-scissor-lift-tables', 'ati-mini-scissor-lift-table', 'Mini Scissor Lift Table', ATI + 'mini-scissors-lift-tables/', () => ati(1120)),
    P('ati-tandem-scissor-lift-tables', 'ati-tandem-scissor-lift-table', 'Tandem Scissor Lift Table', ATI + 'tandem-scissors-lift-table/', () => ati(1119)),
    P('ati-double-scissor-lift-tables', 'ati-double-scissor-lift-table', 'Double Scissor Lift Table', ATI + 'double-scissors-lift-tables/', () => ati(1118)),
    P('ati-lift-and-tilt-tables', 'ati-lift-and-tilt-table', 'Lift-and-Tilt Table', ATI + 'lift-and-tilt-tables/', () => ati(1117)),
    P('ati-crate-positioners', 'ati-crate-positioner', 'Crate Positioner', ATI + 'crate-positioner-upender-tilt-table/', () => ati(1095)),
    P('ati-post-lift-tables', 'ati-post-lift-table', 'Post Lift Table', ATI + 'post-lift-tables/', () => ati(1112)),
  ],
  'southworth-products': [
    ...[
      ['southworth-backsaver-hydraulic-lift-tables', 'southworth-backsaver-hydraulic', 'Backsaver Hydraulic', 'backsaver-hydraulic-lift-tables', '2024/06/Lift-Tables_Product-Specs-Backsaver-Hydraulic-Lift-Tables.csv'],
      ['southworth-backsaver-lite-lift-tables', 'southworth-backsaver-lite', 'Backsaver Lite', 'backsaver-lite-lift-tables', '2024/06/Product-Specs-Backsaver-Lite-Lift-Tables.csv'],
      ['southworth-backsaver-lite-portable-lift-tables', 'southworth-backsaver-lite-portable', 'Backsaver Lite Portable', 'backsaver-lite-portable-lift-tables', '2024/06/Product-Specs-Backsaver-Lite-Portable-Lift-Tables.csv'],
      ['southworth-backsaver-compact-lift-tables', 'southworth-backsaver-compact', 'Backsaver Compact', 'backsaver-compact-lift-tables', '2024/06/Product-Specs-Compact-Lift-Tables.csv'],
      ['southworth-ls-series-lift-tables', 'southworth-ls-series', 'LS-Series', 'ls-series-hydraulic-lift-tables', '2024/06/Product-Specs-LS-Series-Hydraulic-Lift.csv'],
      ['southworth-lsd-series-dual-lift-tables', 'southworth-lsd-series', 'LSD-Series Standard', 'lsd-series-dual-lift-tables', '2024/06/Product-Specs-LSD-Series-Dual-Lift-Tables.csv'],
      ['southworth-lsd-series-dual-lift-tables', 'southworth-lsd-series-wide-base', 'LSD-Series Wide Base', 'lsd-series-dual-lift-tables', '2024/06/Product-Specs-LSD-Series-Dual-Lift-Tables-wide.csv'],
      ['southworth-lst-series-tandem-lift-tables', 'southworth-lst-series', 'LST-Series Standard', 'lst-series-tandem-lift-tables', '2024/06/Product-Specs-LST-Series-Tandem-Lift.csv'],
      ['southworth-lst-series-tandem-lift-tables', 'southworth-lst-series-wide', 'LST-Series Wide', 'lst-series-tandem-lift-tables', '2024/06/Product-Specs-LST-Series-Tandem-Lift-Wide.csv'],
      ['southworth-l-series-cam-lift-tables', 'southworth-l-series-cam', 'L-Series Cam', 'l-series-cam-lift-tables', '2024/06/Product-Specs-L-Series-Cam-Lift-Tables.csv'],
      ['southworth-heavy-duty-hydraulic-lift-tables', 'southworth-heavy-duty-hydraulic', 'Heavy-Duty Hydraulic', 'heavy-duty-hydraulic-lift-tables', '2024/06/Product-Specs-Heavy-Duty-Hydraulic-Lift-Tables.csv'],
      ['southworth-spacesaver-high-rise-lift-tables', 'southworth-spacesaver', 'Spacesaver High-Rise', 'spacesaver-lsh-series-lift-tables', '2024/06/Product-Specs-Spacesaver-High-Rise-Lift-Table.csv'],
      ['southworth-stainless-steel-lift-tables', 'southworth-stainless-steel', 'Stainless Steel', 'stainless-steel-lift-tables', '2024/06/Product-Specs-Stainless-Steel-Lift-Tables.csv'],
      ['southworth-pneumatic-lift-tables', 'southworth-pneumatic', 'Pneumatic', 'pneumatic-lift-tables', '2024/06/Product-Specs-Pneumatic-Lift-Tables.csv'],
      ['southworth-floor-height-lift-tables', 'southworth-floor-height', 'Floor-Height', 'floor-height-lift-tables', '2024/06/Product-Specs-Floor-Height-Lift-Tables.csv'],
      ['southworth-liftmat-low-profile-lift-tables', 'southworth-liftmat', 'LiftMat', 'liftmat-low-profile-lift-tables', '2024/06/Product-Specs-LiftMat-Low-Profile-Lift-Table.csv'],
      ['southworth-flush-mount-turntable-lifts', 'southworth-flush-mount-turntable-lift', 'Flush-Mount Turntable Lift', 'flush-mount-turntable-lifts', '2024/06/Product-Specs-Flushmount-Turntables.csv'],
      ['southworth-manual-dandy-lift-tables', 'southworth-manual-dandy', 'Manual Dandy', 'manual-dandy-lift-tables', '2024/11/Product-Specs-Manual-Dandy-Lift-Table7-2026.csv'],
      ['southworth-powered-dandy-lift-tables', 'southworth-powered-dandy', 'Powered Dandy', 'powered-dandy-lift-tables', '2024/06/Product-Specs-Powered-Dandy-Lifts.csv'],
      ['southworth-dandy-levelers', 'southworth-dandy-leveler', 'Dandy Leveler', 'dandy-levelers', '2024/06/Product-Specs-Dandy-Levelers.csv'],
      ['southworth-lift-and-tilt-tables', 'southworth-hydraulic-lift-and-tilt', 'Hydraulic Lift + Tilt', 'lift-tilt-tables', '2024/11/Product-Specs-Hydraulic-Lift-Tilt-Tables-6-25-25.csv'],
      ['southworth-lift-and-tilt-tables', 'southworth-pneumatic-lift-and-tilt', 'Pneumatic Lift + Tilt', 'lift-tilt-tables', '2024/11/Product-Specs-Pneumatic-Lift-Tilt3.csv'],
      ['southworth-low-profile-lift-and-tilt-tables', 'southworth-low-profile-lift-and-tilt', 'Low-Profile Lift + Tilt', 'low-profile-lift-and-tilt-table', '2024/06/Product-Specs-Low-Profile-Lift-Tilt.csv'],
      ['southworth-fixed-height-tilters', 'southworth-fixed-height-tilter', 'Fixed-Height Tilter', 'fixed-height-tilters', '2024/11/Product-Specs-Fixed-Height-Tilters7.csv'],
      ['southworth-pan-style-container-tilters', 'southworth-pan-style-container-tilter', 'Pan-Style Container Tilter', 'pan-style-container-tilters', '2024/06/Product-Specs-Pan-Style-Container-Tilters.csv'],
      ['southworth-portable-container-tilters', 'southworth-portable-container-tilter', 'E-Z Reach Portable Container Tilter', 'portable-container-tilters', '2024/11/Product-Specs-Portable-Container-Tilters.csv'],
      ['southworth-roll-on-container-tilters', 'southworth-roll-on-container-tilter', 'Roll-On Container Tilter', 'roll-on-container-tilters', '2024/06/Product-Specs-Roll-On-Container-Tilters.csv'],
      ['southworth-palletpal-360-level-loaders', 'southworth-palletpal-360', 'PalletPal 360 Spring', 'palletpal-360', '2024/06/Product-Specs-PalletPal-360.csv'],
      ['southworth-palletpal-360-level-loaders', 'southworth-palletpal-360-air', 'PalletPal 360 Air', 'palletpal-360-air', '2024/06/Product-Specs-PalletPal-360-Air.csv'],
      ['southworth-palletpal-360-level-loaders', 'southworth-stainless-steel-palletpal', 'Stainless Steel PalletPal', 'stainless-steel-palletpal', '2024/06/SSPPL.csv'],
      ['southworth-powered-palletpal-levelers', 'southworth-powered-palletpal-hydraulic', 'Powered PalletPal Hydraulic', 'powered-palletpal-levelers-lifters', '2024/06/Product-Specs-Powered-PalletPal-Levelers_Tilters-Hydraulic.csv'],
      ['southworth-powered-palletpal-levelers', 'southworth-powered-palletpal-pneumatic', 'Powered PalletPal Pneumatic', 'powered-palletpal-levelers-lifters', '2024/06/Product-Specs-Powered-PalletPal-Levelers_Tilters-Pneumatic.csv'],
      ['southworth-palletpal-roll-in-level-loaders', 'southworth-palletpal-roll-in', 'PalletPal Roll-In', 'palletpal-roll-in-roll-e', '2024/06/Product-Specs-PalletPal-Roll-In.csv'],
      ['southworth-palletpal-roll-on-level-loaders', 'southworth-palletpal-roll-on-2500', 'PalletPal Roll-On 2,500 lb', 'palletpal-roll-on-level-loader', '2024/06/Product-Specs-PalletPal-Roll-on-Pallet-Positioner-ROLLC2.csv'],
      ['southworth-palletpal-roll-on-level-loaders', 'southworth-palletpal-roll-on-4000', 'PalletPal Roll-On 4,000 lb', 'palletpal-roll-on-level-loader', '2024/06/Product-Specs-PalletPal-Roll-on-Pallet-Positioner-ROLLC4.csv'],
      ['southworth-palletpal-roll-on-level-loaders', 'southworth-palletpal-roll-on-turntable', 'PalletPal Roll-On with Turntable', 'palletpal-roll-on-leveler-with-turntable', '2024/06/Product-Specs-PalletPal-Roll-On-Leveler-with-Turntable.csv'],
      ['southworth-stackbox-positioners', 'southworth-stackbox-positioner', 'Stackbox Positioner', 'stackbox-positioners', '2024/06/Product-Specs-Stackbox-Positioners.csv'],
    ].map(([line, stem, title, page, file]) => P(line, stem, title, `https://www.southworthproducts.com/products/${page}/`, () => southworth(file))),
    // The Mast Lift 26 spec sheet is a transposed two-column list, so its one row is typed in.
    P('southworth-mast-lift-26', 'southworth-mast-lift-26', 'Mast Lift 26', 'https://www.southworthproducts.com/products/mast-lift-26/', () => [
      { model: 'Mast Lift 26', capacity_lbs: 1800, platform: '44 x 56 in (L x W)', lowered_height_in: 0.5, raised_height_in: 36.5, notes: 'about 7 sec rise, 230V 1-phase' },
    ]),
  ],
  'presto-lifts': [
    ['presto-xl-series-scissor-lift-tables', 'presto-xl-series', 'XL Series', 'scissor-lift-tables/hydraulic-lift-tables/xl-series-standard-duty-scissor-lifts-2'],
    ['presto-xw-series-wide-base-lift-tables', 'presto-xw-series', 'XW Series', 'scissor-lift-tables/hydraulic-lift-tables/xw-series-wide-base-lift-tables-2'],
    ['presto-dxs-series-double-scissor-lifts', 'presto-dxs-series', 'DXS Series', 'scissor-lift-tables/hydraulic-lift-tables/dxs-series-double-scissor-lift-2'],
    ['presto-tandem-scissor-lifts', 'presto-tandem', 'Tandem and Wide Base Tandem', 'scissor-lift-tables/hydraulic-lift-tables/tandem-scissor-lift-2'],
    ['presto-dual-scissor-lifts', 'presto-dual', 'Dual Scissor', 'scissor-lift-tables/hydraulic-lift-tables/dual-scissor-lifts-2'],
    ['presto-xz-series-floor-height-lift-tables', 'presto-xz-series', 'XZ Series', 'scissor-lift-tables/floor-height-lift-tables'],
    ['presto-xzt-series-floor-level-lift-and-tilt-tables', 'presto-xzt-series', 'XZT Series', 'lift-tilt-tables/xzt-series-floor-level'],
    ['presto-hydraulic-scissor-lift-and-tilt-tables', 'presto-hydraulic-lift-and-tilt', 'Hydraulic Lift and Tilt', 'lift-tilt-tables/hydraulic-scissor-lift-tilt-tables'],
    ['presto-axt-pneumatic-lift-and-tilt-tables', 'presto-axt-series', 'AXT and AXST Series', 'lift-tilt-tables/pneumatic-lift-tilt'],
    ['presto-light-duty-scissor-lift-tables', 'presto-xs-series', 'XS Series Electric', 'scissor-lift-tables/light-duty-lifts/light-duty-electric-scissor-lift-table-2'],
    ['presto-light-duty-scissor-lift-tables', 'presto-xf-series', 'XF Series Manual', 'scissor-lift-tables/light-duty-lifts/light-duty-manual-scissor-lift-table-2'],
    ['presto-ax-pneumatic-scissor-lifts', 'presto-ax-series', 'AX Series', 'scissor-lift-tables/pneumatic-lifts/ax-series-standard-duty-pneumatic-scissor-lifts'],
    ['presto-ax-pneumatic-scissor-lifts', 'presto-axs-series', 'AXS Series Heavy-Duty', 'scissor-lift-tables/pneumatic-lifts/axs-series-heavy-duty-scissor-lifts-2'],
    ['presto-axr-pneumatic-turntable-lifts', 'presto-axr-series', 'AXR Series', 'scissor-lift-tables/pneumatic-lifts/axr-series-turntable-lifts-2'],
    ['presto-axr-pneumatic-turntable-lifts', 'presto-axsr-series', 'AXSR Series Heavy-Duty', 'scissor-lift-tables/pneumatic-lifts/axsr-series-heavy-duty-turntable-lifts-2'],
    ['presto-dbp-portable-double-scissor-lifts', 'presto-dbp-series', 'DBP Series', 'scissor-lift-tables/portable-lifts/dbp-series-double-scissor-high-lift-2'],
    ['presto-xbp-wbp-battery-portable-lift-tables', 'presto-xbp-wbp-series', 'XBP and WBP Series', 'scissor-lift-tables/portable-lifts/xbp-wbp-series-dc-electric-lift-2'],
    ['presto-xp-wxp-manual-portable-lift-tables', 'presto-xp-wxp-series', 'XP and WXP Series', 'scissor-lift-tables/portable-lifts/xp-wxp-manual-foot-pump-lift-tables-mobile-lift-tables'],
    ['presto-pt-pts-portable-container-tilters', 'presto-pt-pts-series', 'PT and PTS Series', 'tilters/container-tilters/pt-pts-series-portable-container-tilters'],
    ['presto-srt-stationary-container-tilters', 'presto-srt-series', 'SRT Series', 'tilters/container-tilters/srt-series-stationary-container-tilters'],
    ['presto-tz-floor-level-45-degree-tilters', 'presto-tz-series', 'TZ Series', 'tilters/container-tilters/tz-series-45-degree-tilters'],
    ['presto-zrt-floor-level-container-tilters', 'presto-zrt-series', 'ZRT Series', 'tilters/container-tilters/zrt-series-floor-level-container-tilters'],
    ['presto-at-series-pneumatic-tilt-tables', 'presto-at-series', 'AT Series', 'tilters/fixed-height-platform-tilters/pneumatic-tilt-table-at-series-2'],
    ['presto-tt-series-tilt-tables', 'presto-tt-series', 'TT Series', 'tilters/fixed-height-platform-tilters/tt-series-standard-tilt-tables'],
    ['presto-wt-series-wide-base-tilt-tables', 'presto-wt-series', 'WT Series', 'tilters/fixed-height-platform-tilters/wide-base-tilt-tables-wt-series-2'],
    ['presto-p3-load-levelers', 'presto-p3-airbag', 'P3 All-Around Airbag', 'pallet-handling-equipment/p3-all-around-airbag-automatic-load-leveler'],
    ['presto-p3-load-levelers', 'presto-p3-spring', 'P3 All-Around Spring', 'pallet-handling-equipment/p3-all-around-spring'],
    ['presto-p3-load-levelers', 'presto-p3-operator-controlled', 'P3 Operator Controlled', 'pallet-handling-equipment/p3-operator-controlled-load-leveler'],
    ['presto-p4-floor-height-load-levelers', 'presto-p4', 'P4 Floor Height', 'pallet-handling-equipment/p4-floor-height-load-leveler'],
    ['presto-p4-floor-height-load-levelers', 'presto-p4-turntable', 'P4 with Built-In Turntable', 'pallet-handling-equipment/p4-floor-height-load-leveler-with-built-in-turntable'],
    ['presto-lp-low-profile-lift-tables', 'presto-lp', 'LP Low-Profile', 'pallet-handling-equipment/low-profile-lift-turntable-lift'],
    ['presto-u-lift-roll-in-levelers', 'presto-u-lift', 'U-Lift Roll-In', 'pallet-handling-equipment/u-lift-roll-in-leveler'],
    ['presto-post-lift-tables', 'presto-pl-hydraulic-cantilever', 'PL Hydraulic Cantilever', 'post-lift-tables/hydraulic-cantilever'],
    ['presto-post-lift-tables', 'presto-bp-battery-post', 'BP Battery-Operated Post', 'post-lift-tables/hydraulic-electromechanical'],
    ['presto-post-lift-tables', 'presto-p-hand-crank-post', 'P Series Hand Crank Post', 'post-lift-tables/mechanical-hand-crank'],
  ].map(([line, stem, title, path]) => P(line, stem, title, `https://prestolifts.com/products/${path}`, () => presto(path))),
  ecoa: [
    ['ecoa-hlt-series-scissor-lifts', 'ecoa-hlt-series', 'HLT Series', 'ecoa-equipment/scissor-lifts/hlt-series-scissor-lift-2'],
    ['ecoa-hh-series-heavy-duty-lifts', 'ecoa-hh-series', 'HH Series', 'ecoa-equipment/scissor-lifts/hh-series-heavy-duty-lifts-2'],
    ['ecoa-clt-compact-double-scissor-lifts', 'ecoa-clt-series', 'CLT Series', 'ecoa-equipment/scissor-lifts/clt-compact-double-scissor-lifts-2'],
    ['ecoa-extended-travel-scissor-lifts', 'ecoa-dsl-double-scissor', 'DSL Double Scissor', 'ecoa-equipment/extended-vertical-travel-scissor-lifts/dsl-double-scissor-lifts-2'],
    ['ecoa-extended-travel-scissor-lifts', 'ecoa-tsl-triple-scissor', 'TSL Triple Scissor', 'ecoa-equipment/extended-vertical-travel-scissor-lifts/tsl-triple-scissor-lifts-2'],
    ['ecoa-extended-travel-scissor-lifts', 'ecoa-qsl-quad-scissor', 'QSL Quad Scissor', 'ecoa-equipment/extended-vertical-travel-scissor-lifts/qsl-quad-scissor-lifts-2'],
    ['ecoa-magnum-super-heavy-duty-scissor-lifts', 'ecoa-magnum-mlt', 'Magnum MLT', 'ecoa-equipment/magnum-super-heavy-duty-scissor-lifts/mlt-series-scissor-lifts-2'],
    ['ecoa-magnum-super-heavy-duty-scissor-lifts', 'ecoa-magnum-mltdl', 'Magnum MLTDL Double Long', 'ecoa-equipment/magnum-super-heavy-duty-scissor-lifts/mltdl-series-double-long-scissor-lifts-2'],
    ['ecoa-magnum-super-heavy-duty-scissor-lifts', 'ecoa-magnum-mltdw', 'Magnum MLTDW Double Wide', 'ecoa-equipment/magnum-super-heavy-duty-scissor-lifts/mltdw-series-double-wide-scissor-lifts-2'],
    ['ecoa-magnum-super-heavy-duty-scissor-lifts', 'ecoa-magnum-mltqd', 'Magnum MLTQD Double Wide Double Long', 'ecoa-equipment/magnum-super-heavy-duty-scissor-lifts/mltqd-series-double-wide-double-long-scissor-lifts-2'],
  ].map(([line, stem, title, path]) => P(line, stem, title, `https://prestolifts.com/products/${path}`, () => presto(path))),
  vestil: [
    ['vestil-electric-hydraulic-scissor-lift-tables', 'vestil-ehlt', 'EHLT', [196]],
    ['vestil-electric-hydraulic-scissor-lift-tables', 'vestil-ehlt-n', 'EHLT-N Narrow', [180]],
    ['vestil-electric-hydraulic-scissor-lift-tables', 'vestil-ehlt-e', 'EHLT-E Economical', [1743]],
    ['vestil-powered-lift-tables-with-manual-rotation', 'vestil-hst', 'HST', [1437]],
    ['vestil-single-scissor-lift-and-tilt-tables', 'vestil-uni', 'UNI', [211]],
    ['vestil-single-scissor-lift-and-tilt-tables', 'vestil-uni-p', 'UNI-P Portable', [213]],
    ['vestil-ground-lift-scissor-tables', 'vestil-ehltg', 'EHLTG', [208]],
    ['vestil-ground-lift-scissor-tables', 'vestil-ehltg-handrails', 'EHLTG with Handrails', [1632]],
    ['vestil-pneumatic-scissor-lift-tables', 'vestil-at-pneumatic', 'AT Pneumatic', [300]],
    ['vestil-pneumatic-scissor-lift-tables', 'vestil-ablt', 'ABLT Air Bag', [200]],
    ['vestil-pneumatic-scissor-lift-tables', 'vestil-ablt-heavy-duty', 'ABLT Heavy-Duty Air Bag', [198]],
    ['vestil-low-profile-electric-lift-tables', 'vestil-ehu-ehe', 'EHU and EHE', [207]],
    ['vestil-low-profile-electric-lift-tables', 'vestil-ehltx', 'EHLTX', [206]],
    ['vestil-lift-and-tilt-scissor-tables', 'vestil-ehltt', 'EHLTT', [209]],
    ['vestil-double-scissor-lift-tables', 'vestil-ehltd', 'EHLTD', [202]],
    ['vestil-portable-scissor-lift-tables', 'vestil-pst', 'PST', [203]],
    ['vestil-work-station-scissor-lift-tables', 'vestil-ehlt-ws', 'EHLT-WS and EHLT-WSI', [205]],
    ['vestil-zero-lift-and-tilt-tables', 'vestil-zltt', 'ZLTT', [210]],
    ['vestil-rotary-air-hydraulic-scissor-lift-tables', 'vestil-ahlt', 'AHLT', [197]],
    ['vestil-table-top-scissor-lift-tables', 'vestil-emlt', 'EMLT', [1786]],
    ['vestil-portable-electric-hydraulic-lift-tables', 'vestil-ehltp', 'EHLTP', [1441]],
    ['vestil-portable-electric-hydraulic-lift-tables', 'vestil-ehltp-hoist', 'EHLTP with Hoist', [1795]],
    ['vestil-hinge-and-sliding-tilt-tables', 'vestil-ehtt', 'EHTT', [214]],
    ['vestil-ground-tilters', 'vestil-glt', 'GLT', [215]],
    ['vestil-tandem-lifting-tables', 'vestil-ehlt-tl', 'EHLT-TL', [1734]],
    ['vestil-lift-and-tilt-workstation-tables', 'vestil-ultt', 'ULTT', [212]],
    ['vestil-shorty-scissor-lift-tables', 'vestil-ehlts', 'EHLTS and EHLTSD', [201]],
    ['vestil-ground-lift-and-tilt-tables', 'vestil-ehltgt', 'EHLTGT', [1436]],
    ['vestil-tilt-master-container-tilters', 'vestil-tm-tms', 'Tilt Master and Tilt Master Straddle', [221]],
    ['vestil-tilt-master-container-tilters', 'vestil-tm-dc', 'DC Powered Tilt Master', [1421]],
    ['vestil-tilt-master-container-tilters', 'vestil-tm-manual', 'Manual Tilt Master', [218]],
    ['vestil-tilt-master-container-tilters', 'vestil-ulm-tm', 'ULMA Stainless Steel Tilt Master', [1624]],
    ['vestil-economy-transporter-tilters', 'vestil-ett', 'ETT', [217]],
    ['vestil-bench-top-tilters', 'vestil-btt', 'BTT', [216]],
    ['vestil-efficiency-master-tilt-tables', 'vestil-em1', 'EM1', [219]],
    ['vestil-corner-tilters', 'vestil-air-corner-tilter', 'Air Corner Tilter', [222]],
    ['vestil-corner-tilters', 'vestil-emc-corner-tilter', 'EMC Electric/Hydraulic Corner Tilter', [223]],
    ['vestil-spring-scissor-tables', 'vestil-sst', 'SST', [273]],
    ['vestil-self-elevating-spring-tables', 'vestil-ets', 'ETS', [276]],
    ['vestil-self-elevating-lift-carts', 'vestil-scsc-auto-hite', 'Auto-Hite Cart', [274]],
    ['vestil-self-elevating-lift-carts', 'vestil-scsc-self-elevating', 'Self-Elevating Lift Cart', [275]],
    ['vestil-hydraulic-post-tables', 'vestil-ht', 'HT Hydraulic Post', [285]],
    ['vestil-hydraulic-post-tables', 'vestil-ht-rounded', 'HT Rounded Top', [1782]],
    ['vestil-air-hydraulic-post-tables', 'vestil-ht-air', 'Air Hydraulic Post', [280]],
    ['vestil-linear-actuated-post-tables', 'vestil-ht-la', 'Linear Actuated Post', [281]],
    ['vestil-mechanical-post-tables', 'vestil-mt', 'MT Mechanical Post', [282]],
    ['vestil-die-tables', 'vestil-die', 'DIE', [279]],
    ['vestil-hydraulic-elevating-carts', 'vestil-cart', 'CART', [294]],
    ['vestil-hydraulic-elevating-carts', 'vestil-cart-pss', 'CART Partially Stainless Steel', [290]],
    ['vestil-hydraulic-elevating-carts', 'vestil-cart-scale', 'CART with Built-In Scale', [287]],
    ['vestil-hydraulic-elevating-carts', 'vestil-cart-auto-shift', 'CART Auto-Shift', [295]],
    ['vestil-mechanical-scissor-carts', 'vestil-cart-m', 'CART-M', [286]],
    ['vestil-premium-scissor-lift-carts', 'vestil-cart-premium', 'Premium CART', [288]],
    ['vestil-low-profile-scissor-lift-carts', 'vestil-cart-lp', 'Low Profile CART', [289]],
    ['vestil-low-profile-scissor-lift-carts', 'vestil-cart-lp-heavy-duty', 'Heavy-Duty Low Profile CART-LP', [1428]],
    ['vestil-low-profile-scissor-lift-carts', 'vestil-cart-lp-auto-shift', 'Low Profile CART-LP-AS Auto-Shift', [1685]],
    ['vestil-stainless-steel-scissor-carts', 'vestil-sssc', 'SSSC', [291]],
    ['vestil-foot-pump-and-powered-scissor-lift-tables', 'vestil-sctab', 'SCTAB', [292]],
    ['vestil-pneumatic-tire-elevating-carts', 'vestil-cart-pn', 'CART-PN', [293]],
    ['vestil-dc-powered-and-manual-scissor-carts', 'vestil-cart-dc-manual', 'DC Powered and Manual CART', [296]],
    ['vestil-dc-powered-hydraulic-elevating-carts', 'vestil-cart-dc', 'CART-DC', [297]],
    ['vestil-powered-drive-elevating-carts', 'vestil-cart-dc-ctd', 'Powered Drive and Powered Lift CART', [298]],
    ['vestil-powered-drive-elevating-carts', 'vestil-cart-ctd', 'Traction Drive Electric Hydraulic CART', [299]],
    ['vestil-air-hydraulic-carts', 'vestil-air-cart', 'AIR Cart', [301]],
    ['vestil-lift-and-tilt-carts', 'vestil-cart-lt', 'CART-LT', [302]],
    ['vestil-linear-actuated-elevating-carts', 'vestil-cart-la', 'CART-LA', [277]],
  ].map(([line, stem, title, fids]) => P(line, stem, title, `https://www.vestil.com/product.php?FID=${fids[0]}`, () => vestil(...fids))),
  'lift-products': [
    ['lift-products-guardian-lift-tables', 'lift-products-guardian', 'Guardian', 'lifttables/guardian_lift_tables'],
    ['lift-products-g-series-lift-tables', 'lift-products-g-series', 'G-Series', 'lifttables/2G-3G'],
    ['lift-products-max-m22-m33-lift-tables', 'lift-products-max-m22-m33', 'Max-M22/M33', 'lifttables/M22'],
    ['lift-products-max-lift-scissor-lift-tables', 'lift-products-max-lift', 'Max-Lift', 'lifttables/maxlift/max-lift-specs'],
    ['lift-products-max-lift-xl-heavy-duty-lift-tables', 'lift-products-max-lift-xl', 'Max-Lift XL', 'lifttables/max-lift-xl'],
    ['lift-products-max-lift-tandem-lift-tables', 'lift-products-max-lift-xxl', 'Max-Lift Double Long', 'lifttables/tandem/double-long'],
    ['lift-products-max-lift-tandem-lift-tables', 'lift-products-max-lift-xxw', 'Max-Lift Double Wide', 'lifttables/tandem/double-wide'],
    ['lift-products-max-lift-double-high-lift-tables', 'lift-products-max-lift-xxh', 'Max-Lift Double High', 'lifttables/multi-stage'],
    ['lift-products-mml-lift-carts', 'lift-products-mml', 'MML', 'liftcarts/mml'],
    ['lift-products-mml-pss-stainless-steel-lift-carts', 'lift-products-mml-pss', 'MML-PSS', 'liftcarts/mml-pss'],
    ['lift-products-mmle-battery-powered-lift-carts', 'lift-products-mmle', 'MMLE', 'liftcarts/mmle'],
    ['lift-products-mmla-linear-actuated-lift-carts', 'lift-products-mmla', 'MMLA', 'liftcarts/mmla'],
    ['lift-products-lpmc-self-propelled-lift-carts', 'lift-products-lpmc', 'LPMC', 'lifttables/mobile/lpmc'],
    ['lift-products-lpt-mgv-self-propelled-lift-tables', 'lift-products-lpt-mgv', 'LPT-MGV', 'lifttables/mobile/mgv'],
    ['lift-products-moto-cart-jr-lift-carts', 'lift-products-moto-cart-jr-elt', 'Moto-Cart Jr. ELT', 'electriccarts/moto-cart-jr-elt'],
    ['lift-products-moto-cart-jr-lift-carts', 'lift-products-moto-cart-jr-mlt', 'Moto-Cart Jr. MLT', 'electriccarts/moto-cart-jr-lt'],
    ['lift-products-roto-max-work-positioners', 'lift-products-roto-max', 'Roto-Max', 'lifttables/rotating/roto_max'],
    ['lift-products-roto-max-work-positioners', 'lift-products-roto-max-ss', 'Roto-Max SS', 'lifttables/stainless/roto-max-ss'],
    ['lift-products-guardian-lift-n-spin', 'lift-products-guardian-lift-n-spin', 'Guardian Lift-N-Spin', 'lifttables/rotating/guardian_lift_spin'],
    ['lift-products-lpsl-level-loaders', 'lift-products-lpsl-spring', 'LPSL Spring Level Loader', 'lifttables/rotating/spring-level-loader'],
    ['lift-products-lpsl-level-loaders', 'lift-products-lpsl-air', 'LPSL-AIR Level Loader', 'lifttables/rotating/air-bag-level-loader'],
    ['lift-products-guardian-low-profile-lift-tables', 'lift-products-guardian-low-profile', 'Guardian Low Profile', 'lifttables/lowprofile/guardian'],
    ['lift-products-guardian-low-profile-lift-tables', 'lift-products-guardian-low-profile-sg', 'Guardian Low Profile SG', 'lifttables/stainless/lpbl-sg'],
    ['lift-products-guardian-e-lift-tables', 'lift-products-guardian-e-lift', 'Guardian E-Lift', 'lifttables/lowprofile/elift'],
    ['lift-products-guardian-e-lift-tables', 'lift-products-guardian-e-lift-sg', 'Guardian E-Lift SG', 'lifttables/stainless/lpble-sg'],
    ['lift-products-guardian-u-lift-tables', 'lift-products-guardian-u-lift', 'Guardian U-Lift', 'lifttables/lowprofile/ulift'],
    ['lift-products-guardian-u-lift-tables', 'lift-products-guardian-u-lift-sg', 'Guardian U-Lift SG', 'lifttables/stainless/lpblu-sg'],
    ['lift-products-sxt-stainless-steel-lift-tables', 'lift-products-sxt', 'SXT', 'lifttables/stainless/sxt'],
    ['lift-products-sxtlp-stainless-steel-ground-entry-lift-tables', 'lift-products-sxtlp', 'SXTLP', 'lifttables/stainless/sxtlp'],
    ['lift-products-level-lifter-ground-entry-lift-tables', 'lift-products-level-lifter', 'Level Lifter', 'lifttables/groundentry/level_lifter'],
    ['lift-products-max-lift-and-tilt-tables', 'lift-products-max-lift-and-tilt', 'Max-Lift & Tilt', 'lifttables/tilting/max-lift-tilt'],
    ['lift-products-compact-lift-and-tilt-tables', 'lift-products-compact-lift-and-tilt', 'Compact Lift & Tilt', 'tilters/compact-lift'],
    ['lift-products-max-tilt-tilt-tables', 'lift-products-max-tilt', 'Max-Tilt', 'tilters/max-tilt'],
    ['lift-products-maxx-ergo-tilters', 'lift-products-maxx-ergo-tilter', 'Maxx-Ergo Tilter', 'tilters/max-ergo-tilter'],
    ['lift-products-work-positioners', 'lift-products-mxh15', 'MXH15 Work Positioner', 'workpositioners/hydraulic'],
    ['lift-products-work-positioners', 'lift-products-ml15', 'ML15 Work Positioner', 'workpositioners/pneumatic'],
    ['lift-products-work-positioners', 'lift-products-mxhr', 'MXHR Work Positioner', 'workpositioners/rotating'],
  ].map(([line, stem, title, path]) => P(line, stem, title, `https://www.liftproducts.com/${path}.html`, () => liftProducts(path))),
  'beacon-industries': [
    ['beacon-behlt-electric-hydraulic-scissor-lift-tables', 'beacon-behlt', 'BEHLT', 'scissor-lift/hydraulic-lift'],
    ['beacon-behlt-electric-hydraulic-scissor-lift-tables', 'beacon-behlt-n', 'BEHLT-N Narrow', 'scissor-lift/narrow-scissor-lift'],
    ['beacon-behlt-ws-economy-scissor-lift-tables', 'beacon-behlt-ws', 'BEHLT-WS', 'scissor-lift/economy-scissor-lift'],
    ['beacon-compact-scissor-lift-tables', 'beacon-behlts', 'BEHLTS', 'scissor-lift/hydraulic-scissor-lift'],
    ['beacon-compact-scissor-lift-tables', 'beacon-behltsd', 'BEHLTSD', 'scissor-lift/small-lift-table'],
    ['beacon-bcdl-small-scissor-lift-tables', 'beacon-bcdl', 'BCDL', 'scissor-lift/small-scissor-lift'],
    ['beacon-behltd-double-scissor-lift-tables', 'beacon-behltd', 'BEHLTD', 'scissor-lift/double-scissor-lift'],
    ['beacon-behltx-low-profile-scissor-lift-tables', 'beacon-behltx', 'BEHLTX', 'scissor-lift/low-scissor-lift'],
    ['beacon-behu-u-shaped-lift-tables', 'beacon-behu', 'BEHU', 'scissor-lift/stationary-scissor-lift'],
    ['beacon-behu-u-shaped-lift-tables', 'beacon-behu-ss', 'BEHU-SS', 'scissor-lift/stainless-steel-adjustable-u-table'],
    ['beacon-behltg-ground-lift-tables', 'beacon-behltg', 'BEHLTG', 'scissor-lift/ground-lift-table'],
    ['beacon-behlt-tl-long-scissor-lift-tables', 'beacon-behlt-tl', 'BEHLT-TL', 'scissor-lift/long-scissor-lift-table'],
    ['beacon-bhlttl-tandem-scissor-lift-tables', 'beacon-bhlttl', 'BHLTTL', 'scissor-lift/scissor-lift-table'],
    ['beacon-bmlt-heavy-duty-scissor-lift-tables', 'beacon-bmlt', 'BMLT', 'scissor-lift/hydraulic-lift-table'],
    ['beacon-bmlt-heavy-duty-scissor-lift-tables', 'beacon-bmltdl', 'BMLTDL Double Long', 'scissor-lift/hydraulic-platform-lift'],
    ['beacon-bmlt-heavy-duty-scissor-lift-tables', 'beacon-bmltdw', 'BMLTDW Double Wide', 'scissor-lift/heavy-capacity-scissor-lift'],
    ['beacon-bmlt-heavy-duty-scissor-lift-tables', 'beacon-bmltqd', 'BMLTQD Quad', 'scissor-lift/heavy-duty-scissor-lift'],
    ['beacon-high-travel-scissor-lifts', 'beacon-bdsl', 'BDSL Double', 'scissor-lift/upright-scissor-lift'],
    ['beacon-high-travel-scissor-lifts', 'beacon-btsl', 'BTSL Triple', 'scissor-lift/electric-scissor-lift'],
    ['beacon-high-travel-scissor-lifts', 'beacon-bqsl', 'BQSL Quadruple', 'scissor-lift/tall-scissor-lift'],
    ['beacon-high-travel-scissor-lifts', 'beacon-bcltpb', 'BCLTPB Stacked Dual', 'scissor-lift/scissor-lift-mechanism'],
    ['beacon-high-travel-scissor-lifts', 'beacon-bz-bdsl', 'BZ-BDSL Mobile', 'portable-scissor-lift/hydraulic-elevating-cart'],
    ['beacon-buni-lift-and-tilt-tables', 'beacon-buni', 'BUNI', 'tilt-table/lift-and-tilt-table'],
    ['beacon-buni-lift-and-tilt-tables', 'beacon-buni-p', 'BUNI-P Portable', 'tilt-table/portable-tilt-table'],
    ['beacon-behltt-lift-and-tilt-tables', 'beacon-behltt', 'BEHLTT', 'tilt-table/lift-tilt-table'],
    ['beacon-bzltt-ground-lift-and-tilt-tables', 'beacon-bzltt', 'BZLTT', 'tilt-table/ground-lift-and-tilt'],
    ['beacon-bhtt-hydraulic-tilt-tables', 'beacon-bhtt', 'BHTT', 'tilt-table/hydraulic-tilt-table'],
    ['beacon-behtt-tilt-platforms', 'beacon-behtt', 'BEHTT', 'tilt-table/tilt-platform'],
    ['beacon-bglt-ground-tilters', 'beacon-bglt', 'BGLT', 'tilt-table/ground-tilters'],
    ['beacon-bem1-industrial-tilt-tables', 'beacon-bem1', 'BEM1', 'tilt-table/industrial-tilt-table'],
    ['beacon-bemc-corner-tilters', 'beacon-bemc', 'BEMC', 'tilt-table/corner-tilting'],
    ['beacon-bbtt-bench-top-tilters', 'beacon-bbtt', 'BBTT', 'tilt-table/bench-top-tilters'],
    ['beacon-foot-pump-scissor-lift-carts', 'beacon-bcart-hydraulic', 'Hydraulic Lift Cart', 'portable-scissor-lift/hydraulic-lift-cart'],
    ['beacon-foot-pump-scissor-lift-carts', 'beacon-bcart-s-fr', 'BCART-S-FR', 'portable-scissor-lift/lift-table-cart'],
    ['beacon-foot-pump-scissor-lift-carts', 'beacon-bsctab', 'BSCTAB', 'portable-scissor-lift/mobile-elevating-table'],
    ['beacon-bcart-lp-low-profile-lift-cart', 'beacon-bcart-lp', 'BCART-LP', 'portable-scissor-lift/low-scissor-lift-cart'],
    ['beacon-bcart-pn-pneumatic-tire-lift-carts', 'beacon-bcart-pn', 'BCART-PN', 'portable-scissor-lift/lift-table-with-air-tires'],
    ['beacon-dc-powered-scissor-lift-carts', 'beacon-bcart-dc', 'BCART-DC', 'portable-scissor-lift/mobile-scissor-lift'],
    ['beacon-dc-powered-scissor-lift-carts', 'beacon-bcart-sctab', 'BCART-SCTAB', 'portable-scissor-lift/scissor-lift-cart'],
    ['beacon-bcart-dc-ctd-powered-drive-lift-carts', 'beacon-bcart-dc-ctd', 'BCART-DC-CTD', 'portable-scissor-lift/powered-lift-cart'],
    ['beacon-bpst-portable-lift-tables', 'beacon-bpst', 'BPST', 'portable-scissor-lift/portable-lift-table'],
    ['beacon-bcart-la-linear-actuated-lift-carts', 'beacon-bcart-la', 'BCART-LA', 'air-and-mechanical-scissor-lift/elevating-portable-cart'],
    ['beacon-bcart-m-mechanical-lift-carts', 'beacon-bcart-m', 'BCART-M', 'air-and-mechanical-scissor-lift/manual-lift-cart'],
    ['beacon-stainless-steel-lift-carts', 'beacon-bsssc', 'BSSSC', 'portable-scissor-lift/stainless-lift-cart'],
    ['beacon-stainless-steel-lift-carts', 'beacon-bcart-pss', 'BCART-PSS', 'portable-scissor-lift/stainless-portable-lift'],
    ['beacon-stainless-steel-lift-carts', 'beacon-bcart-m-pss', 'BCART-M-PSS', 'air-and-mechanical-scissor-lift/scissor-cart-mostly-stainless-steel'],
    ['beacon-stainless-steel-lift-carts', 'beacon-bcart-la-pss', 'BCART-LA-PSS', 'air-and-mechanical-scissor-lift/elevating-cart-linear-actuated-mostly-stainless-steel'],
    ['beacon-self-elevating-spring-carts-and-tables', 'beacon-bscsc', 'BSCSC Scissor Cart', 'portable-scissor-lift/scissor-cart'],
    ['beacon-self-elevating-spring-carts-and-tables', 'beacon-bscsc-adjusting', 'BSCSC Self Adjusting Cart', 'air-and-mechanical-scissor-lift/self-adjusting-cart'],
    ['beacon-self-elevating-spring-carts-and-tables', 'beacon-bets', 'BETS', 'air-and-mechanical-scissor-lift/self-elevating-table'],
    ['beacon-bsst-spring-scissor-lift', 'beacon-bsst', 'BSST', 'air-and-mechanical-scissor-lift/spring-scissor-lift-mostly-stainless-steel'],
    ['beacon-pneumatic-scissor-lifts', 'beacon-bablt', 'BABLT Air Lift Table', 'air-and-mechanical-scissor-lift/air-lift-table'],
    ['beacon-pneumatic-scissor-lifts', 'beacon-bat-10', 'BAT-10', 'air-and-mechanical-scissor-lift/pneumatic-scissor-lift'],
    ['beacon-pneumatic-scissor-lifts', 'beacon-bair-d', 'BAIR Air Scissor Lift Cart', 'air-and-mechanical-scissor-lift/air-scissor-lift'],
  ].map(([line, stem, title, path]) => P(line, stem, title, `https://www.beacontechnology.com/lifting-tables/${path}/`, () => beacon(path))),
  'wesco-industrial-products': [
    ['wesco-hydraulic-post-lift-tables', 'wesco-lt-manual-post-lift-tables', 'Manual Lift Tables', 'lift-equipment/-from-200-to-6000-lb-manual-and-powered-lift', m => !/^PLT/.test(m)],
    ['wesco-hydraulic-post-lift-tables', 'wesco-plt-powered-post-lift-tables', 'Powered Lift Tables', 'lift-equipment/-from-200-to-6000-lb-manual-and-powered-lift', m => /^PLT/.test(m)],
    ['wesco-scissor-lift-tables-and-carts', 'wesco-manual-scissor-lift-carts', 'Manual Scissor Lift Carts', 'lift-equipment/scissors-lift-tables-die-lift-table', m => !/^(PSLT|MELT|DT-)/.test(m)],
    ['wesco-scissor-lift-tables-and-carts', 'wesco-powered-scissor-lift-tables', 'Powered Scissor Lift Tables and Carts', 'lift-equipment/scissors-lift-tables-die-lift-table', m => /^(PSLT|MELT)/.test(m)],
    ['wesco-scissor-lift-tables-and-carts', 'wesco-dt-die-lift-table', 'DT Die Lift Table', 'lift-equipment/scissors-lift-tables-die-lift-table', m => /^DT-/.test(m)],
    ['wesco-hclt-precision-lift-tables', 'wesco-hclt', 'HCLT Precision Lift Tables', 'lift-equipment/wesco-hclt-series-precision-lift-tables'],
    ['wesco-ppl-pallet-leveler', 'wesco-ppl', 'PPL Pallet Leveler', 'lift-equipment/pallet-leveler'],
  ].map(([line, stem, title, group, filter]) => P(line, stem, title, `https://catalog.wescomfg.com/viewitems/${group}`, () => wesco(group, filter))),
  'econo-lift': [
    ['econo-lift-sl-scissor-lift-tables', 'econo-lift-sl', 'SL Lift Tables', 'lifts-tilts/lift-tables'],
    ['econo-lift-double-scissor-lift-tables', 'econo-lift-dsl', 'Double Scissor Lift Tables', 'lifts-tilts/double-scissor-lift-tables'],
    ['econo-lift-3hd-heavy-duty-lift-tables', 'econo-lift-3hd', '3HD Heavy Duty Lift Tables', 'lifts-tilts/heavy-duty-lift-tables'],
    ['econo-lift-tsl-tandem-scissor-lifts', 'econo-lift-tsl', 'TSL Tandem Scissor Lifts', 'lifts-tilts/tandem-scissor-lifts'],
    ['econo-lift-lp-low-profile-scissor-lifts', 'econo-lift-lp', 'LP Low Profile Scissor Lifts', 'lifts-tilts/low-profile-scissor-lifts'],
    ['econo-lift-light-duty-portable-lifts', 'econo-lift-light-duty-portable', 'Light Duty Portable Lifts', 'lifts-tilts/light-duty-portable-lifts'],
    ['econo-lift-light-duty-stationary-lifts', 'econo-lift-light-duty-stationary', 'Light Duty Stationary Lifts', 'lifts-tilts/light-duty-stationary-lifts'],
    ['econo-lift-plt-self-propelled-lift-tables', 'econo-lift-plt', 'PLT Self Propelled Lift Tables', 'lifts-tilts/self-propelled-lift-tables'],
    ['econo-lift-tr-sl-lift-tilt-tables', 'econo-lift-tr-sl', 'TR-SL Lift/Tilt Tables', 'lifts-tilts/lifttilt-tables'],
    ['econo-lift-trt-tilt-tables', 'econo-lift-trt', 'TRT Tilt Tables', 'lifts-tilts/tilt-tables'],
    ['econo-lift-mgt-mechanical-gravity-tilters', 'econo-lift-mgt', 'MGT Mechanical Gravity Tilters', 'lifts-tilts/mechanical-gravity-tilters'],
    ['econo-lift-spt-spring-tables', 'econo-lift-spt', 'SPT Spring Tables', 'lifts-tilts/spring-tables'],
    ['econo-lift-air-bag-lift-and-tilt-tables', 'econo-lift-absl', 'ABSL Air Bag Lift Tables', 'air-bags/air-bag-lift-tables'],
    ['econo-lift-air-bag-lift-and-tilt-tables', 'econo-lift-abtr', 'ABTR Air Bag Lift/Tilt Tables', 'air-bags/air-bag-lifttilt-tables'],
    ['econo-lift-air-bag-lift-and-tilt-tables', 'econo-lift-abtrt', 'ABTRT Air Bag Tilt Tables', 'air-bags/air-bag-tilt-tables'],
    ['econo-lift-drive-on-lift-and-tilt-tables', 'econo-lift-do-sl', 'DO-SL Drive-On Lift Tables', 'drive-on/drive-on-lift-tables'],
    ['econo-lift-drive-on-lift-and-tilt-tables', 'econo-lift-do-sl-trt', 'DO-SL/TRT Drive-On Lift/Tilt Tables', 'drive-on/drive-on-lifttilt-tables'],
    ['econo-lift-drive-on-lift-and-tilt-tables', 'econo-lift-do-trt', 'DO-TRT Drive-On Tilt Tables', 'drive-on/drive-on-tilt-tables'],
    ['econo-lift-container-tilters', 'econo-lift-ptr', 'PTR Powered Tilters', 'tilters/powered-tilters'],
    ['econo-lift-container-tilters', 'econo-lift-str', 'STR Stationary Tilters', 'tilters/stationary-tilters'],
    ['econo-lift-container-tilters', 'econo-lift-tr', 'TR Tote Box Tilters', 'tilters/tote-box-tilters'],
  ].map(([line, stem, title, path]) => P(line, stem, title, `https://econolift.net/${path}/`, () => econo(path))),
  'premier-handling-solutions': [
    ['phs-standard-duty-lift-tables', 'phs-standard-duty', 'Standard Duty Lift Table', 'lift-tables/standard-duty-lift-table'],
    ['phs-heavy-duty-lift-tables', 'phs-heavy-duty', 'Heavy Duty Lift Table', 'lift-tables/heavy-duty-lift-table'],
    ['phs-double-wide-lift-tables', 'phs-double-wide', 'Double Wide Lift Table', 'lift-tables/double-wide-lift-table'],
    ['phs-wide-base-lift-tables', 'phs-wide-base', 'Wide Base Lift Table', 'lift-tables/wide-base-lift-table'],
    ['phs-wide-base-tandem-lift-tables', 'phs-wide-base-tandem', 'Wide Base Tandem Lift Table', 'lift-tables/wide-base-tandem-lift-table'],
    ['phs-zero-lift-tables', 'phs-zero-lift', 'Zero Lift Table', 'lift-tables/zero-lift-table'],
    ['phs-pneumatic-lift-tables', 'phs-pneumatic', 'Pneumatic Lift Table', 'lift-tables/pneumatic-lift-table'],
    ['phs-portable-electric-lift-tables', 'phs-portable-electric', 'Portable Electric Lift Table', 'lift-tables/portable-electric-lift-table'],
  ].map(([line, stem, title, path]) => P(line, stem, title, `https://www.phsinc.com/${path}/`, () => phs(path))),
  'blue-giant': pdfPlan('blue-giant'),
  pentalift: pdfPlan('pentalift'),
  superlift: pdfPlan('superlift'),
  lexco: [
    ['lexco-lzl-zero-lift-tables', 'lexco-lzl', 'LZL Zero Lift Tables', 'lexco-hydraulic-lift-tables-die-handlers/ic-lift-tables-die-handlers-lexco-zero-lift-tables'],
    ['lexco-ht-fr-rotating-hydraulic-lift-tables', 'lexco-ht-fr', 'HT-FR Foot Operated Hydraulic Lift Tables', 'lexco-hydraulic-lift-tables-die-handlers/lexco--foot-operated-hydraulic-lift-table'],
    ['lexco-foot-operated-and-electric-hydraulic-lift-tables', 'lexco-ht-electric', 'Foot Operated and Electric Hydraulic Lift Tables', 'lexco-hydraulic-lift-tables-die-handlers/lexco-foot-operated-electric-hydraulic-lift-table'],
    ['lexco-portable-hydraulic-lift-tables', 'lexco-ht-portable', 'Foot Powered Portable Hydraulic Lift Tables', 'lexco-hydraulic-lift-tables-die-handlers/lexco-foot-powered-portable-hydraulic-lift-table'],
    ['lexco-stn-long-deck-lift-tables', 'lexco-stn', 'STN Long Deck Lift Tables', 'lexco-hydraulic-lift-tables-die-handlers/lexco-long-deck-hydraulic-foot-operated-lift-table'],
    ['lexco-dh-die-handlers', 'lexco-dh', 'DH Die Handlers', 'lexco-hydraulic-lift-tables-die-handlers/lexco--die-handler'],
  ].map(([line, stem, title, group]) => P(line, stem, title, `https://catalog.wescomfg.com/viewitems/${group}`, () => wesco(group))),
};

const brandName = { autoquip: 'Autoquip', 'american-lifts': 'American Lifts', 'advance-lifts': 'Advance Lifts', 'air-technical-industries': 'Air Technical Industries', 'southworth-products': 'Southworth', 'presto-lifts': 'Presto', ecoa: 'ECOA', vestil: 'Vestil', 'lift-products': 'Lift Products', 'beacon-industries': 'Beacon', 'wesco-industrial-products': 'Wesco', lexco: 'Lexco', 'econo-lift': 'Econo Lift', 'premier-handling-solutions': 'Premier Handling', 'blue-giant': 'Blue Giant', pentalift: 'Pentalift', superlift: 'Superlift' };

// --brands=a,b limits the run to those catalogs.
const only = (process.argv.find(a => a.startsWith('--brands=')) || '').slice(9).split(',').filter(Boolean);
const PREVIEW = process.argv.includes('--preview');

function range(vals, unit) {
  const v = vals.filter(x => x !== '' && x !== undefined);
  if (!v.length) return '';
  const lo = Math.min(...v), hi = Math.max(...v);
  const f = n => (Number.isInteger(n) ? n.toLocaleString('en-US') : frac(n));
  return lo === hi ? `${f(lo)}${unit}` : `${f(lo)}–${f(hi)}${unit}`;
}

let report = [];
for (const [brand, products] of Object.entries(plan)) {
  if (only.length && !only.includes(brand)) continue;
  const file = `${CATALOG}/${brand}.json`;
  // --preview prints what would be built without a catalog file to write to.
  const catalog = PREVIEW ? { lines: [...new Set(products.map(p => p.line))].map(slug => ({ slug, categories: [] })) } : JSON.parse(fs.readFileSync(file, 'utf8'));
  const lines = Object.fromEntries(catalog.lines.map(l => [l.slug, l]));
  const out = [];
  const seen = new Set();
  const order = {};
  for (const p of products) {
    const line = lines[p.line];
    if (!line) throw new Error(`${brand}: no line ${p.line}`);
    // Autoquip lists a few model numbers twice with different platforms or
    // lowered heights. Keep one row and note the other configuration.
    const rows = [];
    for (const r of p.rows()) {
      if (!r.model || seen.has(r.model)) continue;
      const first = rows.find(x => x.model === r.model);
      if (!first) { rows.push(r); continue; }
      const also = [r.platform && r.platform !== first.platform ? `${r.platform} platform` : '', r.lowered_height_in !== first.lowered_height_in ? `${frac(r.lowered_height_in)} in lowered` : ''].filter(Boolean).join(', ');
      if (also) first.notes = notes([first.notes, `also listed with ${also}`]);
      report.push(`${brand} ${r.model}: listed twice, merged`);
    }
    rows.forEach(r => seen.add(r.model));
    if (!rows.length) { report.push(`${brand} ${p.stem}: NO ROWS`); continue; }
    order[p.line] = (order[p.line] || 0) + 10;
    const travel = rows.map(r => { const m = (r.notes || '').match(/(\d+)(?:-(\d+)\/(\d+))? in travel/); return m ? parseInt(m[1]) + (m[2] ? m[2] / m[3] : 0) : ''; });
    const tilt = rows.map(r => { const m = (r.notes || '').match(/(\d+)° tilt/); return m ? parseInt(m[1]) : ''; });
    const bits = [`${rows.length} model${rows.length === 1 ? '' : 's'}`];
    const cap = range(rows.map(r => r.capacity_lbs), ' lb');
    if (cap) bits.push(`rated ${cap}`);
    let excerpt = bits.join(' ');
    const extras = [];
    const tr = range(travel, ' in.');
    if (tr) extras.push(`${tr} of travel`);
    const tl = range(tilt, ' degrees');
    if (tl) extras.push(`${tl} of tilt`);
    if (extras.length) excerpt += ', with ' + extras.join(' and ');
    const title = `${brandName[brand]} ${p.title} Models`;
    out.push({
      slug: `${p.stem}-models`,
      title,
      line: p.line,
      menu_order: order[p.line],
      excerpt: `${excerpt[0].toUpperCase()}${excerpt.slice(1)}.`,
      models: rows,
      categories: line.categories,
      sources: [p.source],
    });
    report.push(`${brand} ${p.stem}: ${rows.length} rows, ${cap}${PREVIEW ? ' | ' + excerpt + ' | e.g. ' + JSON.stringify(rows[0]) : ''}`);
  }
  if (PREVIEW) continue;
  // Lines without a hand-written capacity range take it from their models.
  for (const line of catalog.lines) {
    if (line.capacity_range) continue;
    const caps = out.filter(p => p.line === line.slug).flatMap(p => p.models.map(r => r.capacity_lbs));
    const r = range(caps, ' lbs');
    if (r) line.capacity_range = r;
  }
  catalog.product_defaults = { status: 'draft' };
  catalog.products = out;
  fs.writeFileSync(file, JSON.stringify(catalog, null, 4) + '\n');
}
console.log(report.join('\n'));
