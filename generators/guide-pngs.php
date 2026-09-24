<?php
/**
 * Export the guide drawings as PNG, for link previews.
 *
 * 1200 x 630, the ratio Open Graph previews crop to. A guide is the page most
 * likely to be shared as a link, so this is the one image on the site whose
 * only job is to look right in somebody else's feed. Run guide-images.php
 * first.
 *
 * Usage: php generators/guide-pngs.php
 * Output: images/guides/png/<page-slug>.png
 *
 * Environment:
 *   CHROME  path to a Chrome or Edge executable, when it isn't in a standard
 *           install location.
 */

require __DIR__ . '/lib/render-png.php';

$src = dirname( __DIR__ ) . '/images/guides';

exit( edc_render_pngs( $src, $src . '/png', 1200, 630, 'images/guides/png/' ) );
