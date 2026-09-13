# Lift Tables — site content

Content for the lift tables niche site: brands, equipment categories, product
lines and models. It is loaded into WordPress by the importers in the
[Equipment Directory Core](https://github.com/timstats91/equipment-directory-core)
plugin, and kept here so every fact can be reviewed, versioned and re-imported.

## Layout

```
brands.json          manufacturers, parent brands and parent companies
categories.json      equipment categories and their intro copy
catalog/<brand>.json one brand's product lines and models
generators/          scripts that build catalog files for large model matrices
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
