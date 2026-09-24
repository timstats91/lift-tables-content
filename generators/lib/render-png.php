<?php
/**
 * Render the brand's SVG drawings to PNG with headless Chrome.
 *
 * SVG is what the site uses, but link previews on social networks, email and
 * messaging apps don't accept it. Rendering through a browser means the PNG
 * matches exactly what a visitor sees, rather than a second renderer's
 * interpretation.
 *
 * Every PNG is drawn on the brand's drawing-grid ground rather than on
 * transparency, because a transparent PNG shows up black in some previews.
 *
 * Extracted from category-pngs.php so applications and guides export the same
 * way. See brand/brand-guide.html.
 *
 * @package EquipmentDirectoryContent
 */

/**
 * Locate a Chrome or Edge executable, or exit with an explanation.
 *
 * @return string
 */
function edc_find_chrome() {
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

	return $chrome;
}

/**
 * Export every SVG in a directory as a PNG of the given size.
 *
 * @param string $src    Directory of .svg files.
 * @param string $out    Directory to write .png files to.
 * @param int    $width  PNG width.
 * @param int    $height PNG height.
 * @param string $label  Where the files went, for the closing line.
 * @return int Exit status: 0 when every file rendered.
 */
function edc_render_pngs( $src, $out, $width, $height, $label ) {
	$chrome = edc_find_chrome();

	if ( ! is_dir( $out ) ) {
		mkdir( $out, 0777, true );
	}

	$files   = glob( $src . '/*.svg' );
	$tmp     = sys_get_temp_dir() . '/edc-pngs-' . md5( $src );
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
			. 'html,body{margin:0;width:' . $width . 'px;height:' . $height . 'px;overflow:hidden}'
			. 'body{background-color:#f3f5f7;background-image:linear-gradient(#e8ecef 2px,transparent 2px),linear-gradient(90deg,#e8ecef 2px,transparent 2px);background-size:40px 40px;background-position:-1px -1px}'
			. 'img{display:block;width:' . $width . 'px;height:' . $height . 'px}'
			. '</style></head><body><img src="data:image/svg+xml;base64,' . base64_encode( file_get_contents( $file ) ) . '" alt=""></body></html>';

		file_put_contents( $page, $html );

		$command = sprintf(
			'%s --headless=new --disable-gpu --hide-scrollbars --force-device-scale-factor=1 --window-size=%d,%d --screenshot=%s %s 2>&1',
			escapeshellarg( $chrome ),
			$width,
			$height,
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

	echo "{$written} of " . count( $files ) . " PNGs written to {$label}\n";

	return $written === count( $files ) ? 0 : 1;
}
