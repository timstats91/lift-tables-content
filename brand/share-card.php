<?php
/**
 * Draw the default link preview card.
 *
 * Used for pages with no photo of their own: the site's home page, the
 * directory archives and the written pages. The card is the reverse logo on
 * the brand's ink ground, over the hazard stripe the brand guide uses for
 * safety-critical edges, so a shared link is recognisably this site.
 *
 * Rendering uses headless Chrome, the same approach as
 * generators/category-pngs.php.
 *
 * Usage: php brand/share-card.php
 * Output: brand/share-card.png (1200 x 630)
 *
 * Environment:
 *   CHROME  path to a Chrome or Edge executable, when it isn't in a standard
 *           install location.
 */

const CARD_W = 1200;
const CARD_H = 630;

$root = dirname( __DIR__ );
$logo = $root . '/brand/logo-reverse.svg';
$png  = $root . '/brand/share-card.png';

if ( ! is_file( $logo ) ) {
	fwrite( STDERR, "brand/logo-reverse.svg is missing. Run brand/generate-logo.php first.\n" );
	exit( 2 );
}

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

$tagline = 'Specs, models and distributors for 20 manufacturers';

$html = '<!doctype html><html><head><meta charset="utf-8">'
	. '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@100..125,400..800&display=swap">'
	. '<style>'
	. 'html,body{margin:0;width:' . CARD_W . 'px;height:' . CARD_H . 'px;overflow:hidden}'
	. 'body{background:#18212a;display:flex;flex-direction:column;justify-content:center;'
	. 'font-family:Archivo,Arial,sans-serif}'
	. '.plate{padding:0 96px;display:flex;flex-direction:column;gap:34px}'
	. 'img{display:block;width:640px}'
	. 'p{margin:0;color:#aeb8c1;font-size:38px;font-weight:500;line-height:1.3;max-width:20ch}'
	. '.hazard{position:absolute;left:0;right:0;bottom:0;height:22px;'
	. 'background:repeating-linear-gradient(-45deg,#f5b800 0 26px,#18212a 26px 52px)}'
	. '</style></head><body>'
	. '<div class="plate">'
	. '<img src="data:image/svg+xml;base64,' . base64_encode( file_get_contents( $logo ) ) . '" alt="">'
	. '<p>' . htmlspecialchars( $tagline, ENT_QUOTES, 'UTF-8' ) . '</p>'
	. '</div><div class="hazard"></div></body></html>';

$tmp  = sys_get_temp_dir() . '/edc-share-card';
$page = $tmp . '/card.html';

if ( ! is_dir( $tmp ) ) {
	mkdir( $tmp, 0777, true );
}

file_put_contents( $page, $html );

$command = sprintf(
	'%s --headless=new --disable-gpu --hide-scrollbars --force-device-scale-factor=1 --virtual-time-budget=4000 --window-size=%d,%d --screenshot=%s %s 2>&1',
	escapeshellarg( $chrome ),
	CARD_W,
	CARD_H,
	escapeshellarg( $png ),
	escapeshellarg( 'file:///' . str_replace( '\\', '/', ltrim( realpath( $page ), '/' ) ) )
);

exec( $command, $output, $status );
unlink( $page );
@rmdir( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

if ( 0 !== $status || ! is_file( $png ) ) {
	fwrite( STDERR, "Failed to render the card: " . implode( "\n", $output ) . "\n" );
	exit( 1 );
}

echo "brand/share-card.png written (" . CARD_W . ' x ' . CARD_H . ")\n";
