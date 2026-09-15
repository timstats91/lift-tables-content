# Lift Tables — site content

Content for the lift tables niche site: brands, equipment categories, product
lines and models. It is loaded into WordPress by the importers in the
[Equipment Directory Core](https://github.com/timstats91/equipment-directory-core)
plugin, and kept here so every fact can be reviewed, versioned and re-imported.

## Layout

```
brand/               logo files, their generator, and the brand guide with its source and build script
brands.json          manufacturers, parent brands and parent companies
pages.json           site pages and the theme template each one uses
categories.json      equipment categories and their intro copy
catalog/<brand>.json one brand's product lines and models
generators/          scripts that build catalog files and category illustrations
images/lines/        manufacturer photos for product lines, with credit and source in images.json
images/categories/   one drawing per equipment category, built by generators/category-images.php
  png/               the same drawings as 1200 x 800 PNG, exported by generators/category-pngs.php
```

Each entry carries a `sources` list of the pages its facts came from. Sources
are for review only; they are not imported.

## Importing

From the plugin directory, in this order (brands first, since catalogs
reference them):

```bash
php tools/import-brands.php     <this repo>/brands.json
php tools/import-categories.php <this repo>/categories.json
php tools/import-catalog.php    <this repo>/catalog/jet.json
php tools/import-pages.php      <this repo>/pages.json
```

Add `--dry-run` to see what would change. A normal run only creates missing
items; `--update` overwrites existing ones from the file. Full option details
are in each importer's header.

## Generated catalogs

`catalog/lange-lift.json` and `catalog/bishamon.json` are built by the scripts
in `generators/`. Edit the generator and re-run it rather than hand-editing
those JSON files, or the next regeneration will overwrite the change:

```bash
php generators/lange-lift.php
php generators/bishamon.php
```

`catalog/jet.json` is hand-maintained.

In `catalog/autoquip.json`, `catalog/american-lifts.json`,
`catalog/advance-lifts.json`, `catalog/air-technical-industries.json`,
`catalog/southworth-products.json`, `catalog/presto-lifts.json`,
`catalog/ecoa.json`, `catalog/vestil.json`, `catalog/lift-products.json`,
`catalog/beacon-industries.json`, `catalog/wesco-industrial-products.json`,
`catalog/lexco.json`, `catalog/econo-lift.json` and
`catalog/premier-handling-solutions.json` the product lines are
hand-maintained, but the products are generated. Each
product is a draft holding one design's model table, built from the
manufacturer's published data: Autoquip's lifts API (which also covers American
Lifts), the spec tables on advancelifts.com, ATI's WooCommerce Store API,
Southworth's spec CSV files, the spec tables on prestolifts.com (which also
cover ECOA), Vestil's per-model data sheets, the spec tables on liftproducts.com,
beacontechnology.com and econolift.net, Wesco's item catalog (which also covers
Lexco) and the specification lists on phsinc.com. Regenerate them with:

```bash
node generators/line-models.js catalog
node generators/line-models.js catalog --brands=vestil   # one brand only
```

It replaces only the `products` array in those files, and fills in a line's
`capacity_range` from its models when the line doesn't set one. Advance Lifts
publishes the Pallet Pro specs as images, so those four rows are typed into the
generator. ATI publishes no model list for its Mechanical Lift Tables or
Zero-Low Crate Positioners, so those two lines have no table. Southworth's
Mast Lift 26 spec sheet is a two-column list, so its row is typed in too.
ECOA's HH, CLT and Magnum MLTDL tables give only end/side capacity, so those
rows show it in the notes and the lines' capacity ranges come from ECOA's
descriptions. Vestil data sheets with no specs are skipped; base models sold
with a choice of platform (EHLTD) take their specs from the family's model
chart. Lift Products base models sold in several capacities link to a page per
capacity, and the generator follows those links. PHS gives part numbers rather
than model numbers, so its tables list part numbers, matched in order to the
columns of each specification group; its heavy duty sheet shows a 336 x 48 in.
standard platform for one model, read as 36 x 48 in.

Blue Giant and Pentalift publish their model specifications only in PDFs, as
images or tables with merged cells, so those rows are typed into
`generators/data/pdf-models.json` with the PDF each product came from. Blue
Giant's Scissor Lift and Tilt line has no model table in any of its PDFs, and
its Double Wide brochure lists 4,000 to 12,000 lb models where its web page says
2,000 to 6,000 lb; the brochure is used. Pentalift's one-page bulletins for the
LTNI, 10L, 12L, IR, L, TL, TLH, NL, NLT, DSL, TSL, QSL and mechanical bin tilter
lines describe the series without model numbers, so those lines have no table.
Superlift publishes its specs as images, so its lines have no tables either.

## Brand

`brand/brand-guide.html` is the brand guide: logo, colour, type, signature
details, category illustrations, writing and imagery rules. Edit
`brand/brand-guide.src.html` and rebuild with `php brand/build-guide.php`, which
fills in the current logo files and drawings.

Category illustrations are drawn by `generators/category-images.php` into
`images/categories/<slug>.svg` and assigned through `"image"` in
`categories.json`; the category importer puts them in the media library. The
brand guide sets out how they're constructed. After changing a drawing, re-export
the PNG copies (used where SVG isn't accepted, such as link previews):

```bash
php generators/category-images.php
php generators/category-pngs.php
``` The four logo SVGs are drawn by
`brand/generate-logo.php`; edit and re-run it rather than editing the SVGs:

```bash
php brand/generate-logo.php
```

The site uses them through Appearance → Customize → Directory Branding:

| Setting | Value |
|---|---|
| Accent colour | `#F5B800` |
| Logo | `/wp-content/edc-content/lift-tables/brand/logo.svg` |
| Reverse logo | `/wp-content/edc-content/lift-tables/brand/logo-reverse.svg` |
| Icon | `/wp-content/edc-content/lift-tables/brand/mark.svg` |

## Product line photos

`images/lines/images.json` lists a photo for each product line: the post, the
processed file, alt text, who it's credited to, the page it came from and the
original file's URL. **These are manufacturer photos used before asking
permission**; each is credited on the site with a link to its source.

To rebuild the processed files, download each entry's `original` into a folder
named `<slug>.<ext>`, then:

```bash
php -d extension=gd -d memory_limit=2G generators/line-images.php --from=<folder>
php tools/import-images.php <this repo>/images/lines/images.json   # from the plugin directory, with GD loaded
```

The generator trims cut-out shots and centres them on white at 1200 x 900, or
centre-crops real photos (`"fit": "cover"`). It never retouches the product.
Three lines have no photo because the only originals found were too small to
use (300 to 430 px): Lange Lift Modified & Custom Lifts, Autoquip AutoTilt
Portable Tilters and American Lifts Tork Non-Intrusive Tilters. Their cards
show the category drawing instead. Advance Lifts photos come from its photo
gallery, since the images on its product pages are under 500 px. The Advance
Lifts PM/PP photo has a cream background, so it uses `"fit": "cover"`.
Presto and ECOA publish product images at 300 to 350 px, so only Presto XW,
Presto Tandem and ECOA Extended Travel have photos; their other lines show
category drawings. Vestil photos are each line's featured model, named in the
alt text.
Four lines added in the last batch have no photo: Lift Products Max-M22/M33
and Pentalift ProAir Lift and Rotate (originals of 350 and 410 px), and Beacon
BCDL and BHLTTL (the only images are scans with a grey border). Beacon's other
photos are 450 x 280 px, so they sit small in the frame. Several Lift Products
photos carry the manufacturer's own "Quick Ship" badge, and Econo Lift's images
are catalogue illustrations with callout labels; both are used as published.

**To take images down** (for example, if a manufacturer asks), delete their
entries from `images.json` and run the importer with `--prune`. That removes
the featured images and deletes the files from the media library. A single
image can also be deleted from Media in WordPress.

## Product line documents

`documents/documents.json` lists manufacturer PDFs (brochures, spec sheets,
catalogs, owner's manuals and forms) and the product lines each belongs on. Each
entry records the title, type, who it's credited to, the page it's published on
and the PDF's own URL. **These are manufacturer documents used before asking
permission**; line pages credit each one with a link to its source.

The PDFs are not in version control. They live in `documents/files/`, which Git
ignores, and the importer downloads any that are missing from their `original`
URL, so a fresh checkout only needs the manifest. From the plugin directory:

```bash
php tools/import-documents.php <this repo>/documents/documents.json            # attach to lines without documents
php tools/import-documents.php <this repo>/documents/documents.json --update   # replace lines' document lists
php tools/import-documents.php <this repo>/documents/documents.json --prune    # take down documents removed from the file
```

The manifest holds 354 documents on 245 lines, covering 15 brands. Within a
brand, each line lists catalogs and brochures first, then spec sheets, manuals,
installation guides, parts lists and application forms.

What's included is each brand's line-level literature, owner's manuals and
application or survey forms. Left out: drawings, labels and certificates for
single models (Vestil's and Advance Lifts' per-model PDFs, Wesco's item sheets),
warranties, terms, Southworth's service procedures and maintenance sheets, and
general reference sheets such as Beacon's voltage, NEMA and paint colour charts.
Spanish-language copies aren't included.

Notes by brand:

- **Blue Giant:** installation manuals, parts lists, placard, warranty guide and
  PL4 operator's manual sit behind a dealer login, so they aren't included.
- **Pentalift:** calls its website owner's manuals generic and asks for a lift's
  serial number to supply the right one; that notice is shown with both manuals.
  The all-products catalog is on every Pentalift line. The E-Series, drive-on bin
  tilter and older bin tilter sheets aren't attached, since no line here covers
  those products.
- **Autoquip and American Lifts:** Autoquip's manuals page links three Super
  Titan manuals that are the same file, so one is used. Its Air Force Pneumatic
  Tilters link serves the AutoTilt manual and its 90 Degree Tilter link serves
  the Series 35 Tilter manual, so those two lines have no manual. The Tiltlift
  manual is listed without a file.
- **Presto and ECOA:** Presto publishes ECOA's literature and manuals, so they
  are credited to Presto Lifts. Presto's general catalog is on every Presto and
  ECOA line.
- **Beacon:** the BBTT tech sheet link serves a platform cart sheet, and two BHTT
  "tech sheets" are handwritten sketches; none of the three is used.
- **Superlift:** its tilter sheet is linked from most product pages but is only
  attached to the tilter lines.
- **No documents:** JET, Premier Handling Solutions, Wesco and Lexco publish no
  line-level PDFs. Econo Lift's site wasn't responding when the documents were
  gathered (September 2026), so its lines have none yet.

## Products are drafts at launch

Every catalog sets `"product_defaults": { "status": "draft" }`, so products
import as drafts while product lines publish. Model numbers and specs from
draft products still appear on their product line's page. To give products
their own pages later, publish them in WordPress; the importer never changes
an existing item's status.

## Status

| Catalog | Lines | Products (draft) | Model numbers |
|---|---|---|---|
| JET | 1 | 5 | 5 |
| Lange Lift | 5 | 17 | 78 |
| Bishamon | 8 | 16 | 69 |
| Autoquip | 16 | 26 | 736 |
| American Lifts | 8 | 8 | 131 |
| Advance Lifts | 14 | 27 | 583 |
| Air Technical Industries | 14 | 12 | 194 |
| Southworth | 30 | 38 | 203 |
| Presto Lifts | 27 | 35 | 272 |
| ECOA | 5 | 10 | 114 |
| Vestil | 46 | 66 | 755 |
| Lift Products | 28 | 37 | 226 |
| Beacon | 33 | 54 | 550 |
| Wesco | 4 | 7 | 47 |
| Lexco | 6 | 6 | 101 |
| Premier Handling Solutions | 9 | 8 | 78 |
| Pentalift | 23 | 11 | 93 |
| Blue Giant | 11 | 13 | 111 |
| Econo Lift | 16 | 21 | 247 |
| Superlift | 15 | 0 | 0 |

Every brand in `brands.json` now has a catalog file.

The catalogs added from Autoquip onward cover lift tables, tilt tables,
pallet positioners and elevating carts only. Dock
lifts, VRCs, turntables without a lift, upenders, dumpers, coil cars and
personnel work platforms are left out, as are stackers, skid lifters, work
positioners with forks or end-effectors and accessories sold as separate
products (Lexco's push-pull die conveyor, Pentalift's portability packages). Where a manufacturer sells several
platform configurations of one design as separate families (Autoquip Series 35
and Super Titan, and its three mechanical lifts), they are one line here.
Autoquip lists its Single Arm and Double Arm lifts as replaced, so they are
not included. Capacity ranges for Autoquip and American Lifts come from
Autoquip's model API, linked in each line's sources.

## Open questions

- **Beacon model numbers:** Beacon Industries sells its lift tables under its
  own B-prefixed model numbers. Many of them match other manufacturers' codes
  with a B added (BEHLT, BCART, BMLT, BDSL); the catalog lists them as Beacon
  publishes them and makes no claim about who builds them.

- **JET SLT-1100:** JET publishes a 63 x 31-1/2 in. table size but assembled
  dimensions of 50.75 x 29.75 x 37.75 in. The table size is used as published.
- **Bishamon L66K-TT4444 / TT4448:** Bishamon's table gives platform sizes that
  contradict its own model-code convention, so those two platforms are blank.
- **Bishamon suffixes:** EZU-15-R-SS and EZ Loader -E / -SS are listed without
  explanation; no claims are made about what the suffixes mean.
- **Slug collisions:** the Bishamon EZ-Off Lifter and MobiLeveler ESX use the
  same slug for their product line and their product, and the product URL
  currently renders the line. Unresolved; the slugs may need to change.
