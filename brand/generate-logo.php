<?php
/**
 * Build the Lift Tables logo files.
 *
 * The wordmark is drawn from the letter shapes below instead of set in a font,
 * so the SVGs render identically everywhere and need no font files. Letters
 * sit on a 20-unit cap height with a 4-unit stroke; curved letters (A, B, S)
 * are cut with 45-degree chamfers, the same corner used on the badge.
 *
 * Usage: php brand/generate-logo.php
 */

const CAP    = 20;
const GAP    = 3;
const SPACE  = 8;
const INK    = '#18212A';
const PAPER  = '#FFFFFF';
const SIGNAL = '#F5B800';

// Width and path data for each letter, drawn from its own origin.
$glyphs = array(
	'L' => array( 12, 'M0 0H4V16H12V20H0Z' ),
	'I' => array( 4, 'M0 0H4V20H0Z' ),
	'F' => array( 13, 'M0 0H13V4H4V8H11V12H4V20H0Z' ),
	'T' => array( 14, 'M0 0H14V4H9V20H5V4H0Z' ),
	'E' => array( 13, 'M0 0H13V4H4V8H11V12H4V16H13V20H0Z' ),
	'A' => array( 14, 'M3 0H11L14 3V20H10V13H4V20H0V3Z M5 4H9L10 5V9H4V5Z' ),
	'B' => array( 14, 'M0 0H11L14 3V7L12 10L14 13V17L11 20H0Z M4 4H9L10 5V8H4Z M4 12H10V15L9 16H4Z' ),
	'S' => array( 14, 'M3 0H14V4H5L4 5V8H11L14 11V17L11 20H0V16H9L10 15V12H3L0 9V3Z' ),
);

/**
 * Wordmark path data, offset by $x / $y, and its width.
 */
function wordmark( $text, $glyphs, $x, $y ) {
	$paths  = array();
	$cursor = 0;

	foreach ( str_split( $text ) as $index => $char ) {
		if ( ' ' === $char ) {
			$cursor += SPACE - GAP;
			continue;
		}

		list( $width, $d ) = $glyphs[ $char ];

		$dx = $x + $cursor;
		$paths[] = sprintf( '<path transform="translate(%s %s)" d="%s"/>', $dx, $y, $d );

		$cursor += $width + GAP;
	}

	return array( implode( '', $paths ), $cursor - GAP );
}

/**
 * The badge: a steel plate with one chamfered corner, holding a scissor lift
 * table seen side-on -- platform, crossed legs, base frame.
 */
function mark( $plate, $legs, $platform, $x = 0, $y = 0 ) {
	return sprintf(
		'<g transform="translate(%1$s %2$s)">'
		. '<path fill="%3$s" d="M0 0H32L40 8V40H0Z"/>'
		. '<path fill="none" stroke="%4$s" stroke-width="3.4" d="M10.5 12.5 29.5 30.5M29.5 12.5 10.5 30.5"/>'
		. '<rect fill="%5$s" x="7" y="9" width="26" height="5"/>'
		. '<rect fill="%4$s" x="9" y="29" width="22" height="3"/>'
		. '<circle fill="%4$s" cx="20" cy="21.5" r="3.2"/>'
		. '<circle fill="%3$s" cx="20" cy="21.5" r="1.3"/>'
		. '</g>',
		$x,
		$y,
		$plate,
		$legs,
		$platform
	);
}

function svg( $width, $height, $title, $body ) {
	return sprintf(
		'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$s %2$s" width="%1$s" height="%2$s" role="img" aria-label="%3$s">'
		. "\n<title>%3\$s</title>\n%4\$s\n</svg>\n",
		$width,
		$height,
		$title,
		$body
	);
}

$name = 'LIFT TABLES';
$dir  = __DIR__;

list( $word, $word_width ) = wordmark( $name, $glyphs, 54, 10 );
$width = 54 + $word_width;

$files = array(
	// Dark badge and ink wordmark, for light backgrounds.
	'logo.svg'         => svg( $width, 40, 'Lift Tables', mark( INK, PAPER, SIGNAL ) . '<g fill="' . INK . '" fill-rule="evenodd">' . $word . '</g>' ),
	// Signal badge and white wordmark, for dark backgrounds.
	'logo-reverse.svg' => svg( $width, 40, 'Lift Tables', mark( SIGNAL, INK, INK ) . '<g fill="' . PAPER . '" fill-rule="evenodd">' . $word . '</g>' ),
	// One colour, for embossing, engraving, fax and single-colour print.
	'logo-mono.svg'    => svg( $width, 40, 'Lift Tables', mark( INK, PAPER, PAPER ) . '<g fill="' . INK . '" fill-rule="evenodd">' . $word . '</g>' ),
	// The badge alone: favicon, app icon, social avatar.
	'mark.svg'         => svg( 40, 40, 'Lift Tables', mark( INK, PAPER, SIGNAL ) ),
);

foreach ( $files as $file => $contents ) {
	file_put_contents( $dir . '/' . $file, $contents );
	echo "wrote brand/{$file}\n";
}
