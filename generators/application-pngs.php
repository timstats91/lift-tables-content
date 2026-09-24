<?php
/**
 * Export the application drawings as PNG, for link previews.
 *
 * 1200 x 630 rather than the categories' 1200 x 800: the application pages are
 * the ones most likely to be shared into a chat or a post, and 1.91:1 is the
 * ratio Open Graph previews crop to. Run application-images.php first.
 *
 * Usage: php generators/application-pngs.php
 * Output: images/applications/png/<application-slug>.png
 *
 * Environment:
 *   CHROME  path to a Chrome or Edge executable, when it isn't in a standard
 *           install location.
 */

require __DIR__ . '/lib/render-png.php';

$src = dirname( __DIR__ ) . '/images/applications';

exit( edc_render_pngs( $src, $src . '/png', 1200, 630, 'images/applications/png/' ) );
