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

const PNG_W = 1200;
const PNG_H = 800;

$root = dirname( __DIR__ );
$src  = $root . '/images/categories';
$out  = $src . '/png';

$chrome = getenv( 'CHROME' );

if ( ! $chrome ) {
	foreach (
		array(
			'C:/Program Files/Google/Chrome/Application/chrome.exe',
			'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
			'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
			'/usr/bin/google-chrome',
			'/usr/bin/chromium',
		) as $candidate
	) {
		if ( is_file( $candidate ) ) {
			$chrome = $candidate;
			break;
		}
	}
}

if ( ! $chrome || ! is_file( $chrome ) ) {
	fwrite( STDERR, "Chrome not found. Set CHROME to a Chrome or Edge executable.\n" );
	exit( 2 );
}

if ( ! is_dir( $out ) ) {
	mkdir( $out, 0777, true );
}

$files   = glob( $src . '/*.svg' );
$tmp     = sys_get_temp_dir() . '/edc-category-pngs';
$written = 0;

if ( ! is_dir( $tmp ) ) {
	mkdir( $tmp, 0777, true );
}

foreach ( $files as $file ) {
	$slug = basename( $file, '.svg' );
	$png  = $out . '/' . $slug . '.png';
	$page = $tmp . '/' . $slug . '.html';

	// The same ground the site puts behind the drawings: Galvanized with a
	// Galvanized Deep grid, scaled up with the image.
	$html = '<!doctype html><html><head><meta charset="utf-8"><style>'
		. 'html,body{margin:0;width:' . PNG_W . 'px;height:' . PNG_H . 'px;overflow:hidden}'
		. 'body{background-color:#f3f5f7;background-image:linear-gradient(#e8ecef 2px,transparent 2px),linear-gradient(90deg,#e8ecef 2px,transparent 2px);background-size:40px 40px;background-position:-1px -1px}'
		. 'img{display:block;width:' . PNG_W . 'px;height:' . PNG_H . 'px}'
		. '</style></head><body><img src="data:image/svg+xml;base64,' . base64_encode( file_get_contents( $file ) ) . '" alt=""></body></html>';

	file_put_contents( $page, $html );

	$command = sprintf(
		'%s --headless=new --disable-gpu --hide-scrollbars --force-device-scale-factor=1 --window-size=%d,%d --screenshot=%s %s 2>&1',
		escapeshellarg( $chrome ),
		PNG_W,
		PNG_H,
		escapeshellarg( $png ),
		escapeshellarg( 'file:///' . str_replace( '\\', '/', ltrim( realpath( $page ), '/' ) ) )
	);

	exec( $command, $output, $status );

	if ( 0 !== $status || ! is_file( $png ) || filemtime( $png ) < time() - 60 ) {
		fwrite( STDERR, "Failed to render {$slug}: " . implode( "\n", $output ) . "\n" );
		continue;
	}

	unlink( $page );
	++$written;
}

@rmdir( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

echo "{$written} of " . count( $files ) . " PNGs written to images/categories/png/\n";
exit( $written === count( $files ) ? 0 : 1 );
