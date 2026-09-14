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
images/categories/   one drawing per equipment category, built by generators/category-images.php
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

## Brand

`brand/brand-guide.html` is the brand guide: logo, colour, type, signature
details, category illustrations, writing and imagery rules. Edit
`brand/brand-guide.src.html` and rebuild with `php brand/build-guide.php`, which
fills in the current logo files and drawings.

Category illustrations are drawn by `generators/category-images.php` into
`images/categories/<slug>.svg` and assigned through `"image"` in
`categories.json`; the category importer puts them in the media library. The
brand guide sets out how they're constructed. The four logo SVGs are drawn by
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

Brands in `brands.json` without a catalog file yet: Southworth, Presto, ECOA,
Autoquip, American Lifts, Advance Lifts, Vestil, Lift Products, Air Technical
Industries, Beacon, Wesco, Lexco, Premier Handling Solutions, Pentalift,
Blue Giant, Econo Lift, Superlift.

## Open questions

- **JET SLT-1100:** JET publishes a 63 x 31-1/2 in. table size but assembled
  dimensions of 50.75 x 29.75 x 37.75 in. The table size is used as published.
- **Bishamon L66K-TT4444 / TT4448:** Bishamon's table gives platform sizes that
  contradict its own model-code convention, so those two platforms are blank.
- **Bishamon suffixes:** EZU-15-R-SS and EZ Loader -E / -SS are listed without
  explanation; no claims are made about what the suffixes mean.
- **Slug collisions:** the Bishamon EZ-Off Lifter and MobiLeveler ESX use the
  same slug for their product line and their product, and the product URL
  currently renders the line. Unresolved; the slugs may need to change.
