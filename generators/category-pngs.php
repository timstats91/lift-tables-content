<?php
/**
 * Export the category drawings as PNG.
 *
 * SVG is what the site uses, but link previews on social networks, email and
 * messaging apps don't accept it. Each PNG is the drawing on the brand's
 * drawing-grid ground at 1200 x 800, so it reads as a finished image wherever
 * it lands; a transparent PNG shows up black in some previews.
 *
 * Rendering uses headless Chrome, so the PNG matches exactly what a browser
 * draws from the SVG. Run category-images.php first.
 *
 * Usage: php generators/category-pngs.php
 * Output: images/categories/png/<category-slug>.png
 *
 * Environment:
 *   CHROME  path to a Chrome or Edge executable, when it isn't in a standard
 *           install location.
 */

require __DIR__ . '/lib/render-png.php';

$src = dirname( __DIR__ ) . '/images/categories';

exit( edc_render_pngs( $src, $src . '/png', 1200, 800, 'images/categories/png/' ) );
