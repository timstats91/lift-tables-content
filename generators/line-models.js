// Build the draft products that hold model tables for the Autoquip, American
// Lifts, Advance Lifts and ATI catalogs, from each manufacturer's published
// model data: Autoquip's lifts API, Advance Lifts' spec tables and ATI's
// WooCommerce Store API. Replaces the "products" array in each catalog file;
// lines are left as they are.
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
  const m = s.match(/^(\d+(?:\.\d+)?)?(?:\s+)?(?:(\d+)\/(\d+))?$/);
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
};

const brandName = { autoquip: 'Autoquip', 'american-lifts': 'American Lifts', 'advance-lifts': 'Advance Lifts', 'air-technical-industries': 'Air Technical Industries' };

function range(vals, unit) {
  const v = vals.filter(x => x !== '' && x !== undefined);
  if (!v.length) return '';
  const lo = Math.min(...v), hi = Math.max(...v);
  const f = n => (Number.isInteger(n) ? n.toLocaleString('en-US') : frac(n));
  return lo === hi ? `${f(lo)}${unit}` : `${f(lo)}–${f(hi)}${unit}`;
}

let report = [];
for (const [brand, products] of Object.entries(plan)) {
  const file = `${CATALOG}/${brand}.json`;
  const catalog = JSON.parse(fs.readFileSync(file, 'utf8'));
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
    report.push(`${brand} ${p.stem}: ${rows.length} rows, ${cap}`);
  }
  catalog.product_defaults = { status: 'draft' };
  catalog.products = out;
  fs.writeFileSync(file, JSON.stringify(catalog, null, 4) + '\n');
}
console.log(report.join('\n'));
