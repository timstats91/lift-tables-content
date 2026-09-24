<?php
/**
 * Draw one illustration per buying guide, for the page and for link previews.
 *
 * Same hand again, from lib/drawing.php. What separates a guide drawing from
 * a category one is that it shows a *change* rather than a machine: the deck
 * in two positions, the load in two places, the jack before and after. A
 * category drawing answers "what is this"; a guide drawing answers "what
 * happens", which is what a guide is about.
 *
 * The earlier position is drawn in Line Strong, the way a drawing shows a
 * previous state, and the current one in ink. No text: fonts don't load
 * inside an image file, and the social preview sets the title beside it.
 *
 * Usage: php generators/guide-images.php
 * Output: images/guides/<page-slug>.svg
 */

require __DIR__ . '/lib/drawing.php';

/** A crate drawn as a previous position: Line Strong, no diagonals. */
function ghost_crate( $x, $bottom, $w, $h ) {
	return rect( $x, $bottom - $h, $w, $h, 'none', LINE, 2.5 );
}

/** A pallet jack in side view: handle, body, forks, wheels. */
function pallet_jack( $x, $toward = 1 ) {
	$out  = line( $x, GROUND - 12, $x - 8 * $toward, 150, INK, 6 );
	$out .= rect( $x - 19, GROUND - 38, 38, 20 );
	$out .= rect( $x + 19 * $toward, GROUND - 32, 112 * $toward, 8 );
	$out .= circle( $x - 10 * $toward, GROUND - 6, 6 );
	$out .= circle( $x + 122 * $toward, GROUND - 8, 8 );

	return $out;
}

/** A curved arrow showing the tipping moment a load applies. */
function moment_arc( $cx, $cy, $r ) {
	return path(
		sprintf( 'M%s %s A %s %s 0 0 1 %s %s', n( $cx ), n( $cy - $r ), n( $r ), n( $r ), n( $cx + $r ), n( $cy ) ),
		'none',
		MUTED,
		2.5,
		' stroke-dasharray="6 4"'
	)
	. path(
		sprintf( 'M%s %s L%s %s L%s %s', n( $cx + $r - 7 ), n( $cy - 9 ), n( $cx + $r ), n( $cy + 1 ), n( $cx + $r + 8 ), n( $cy - 7 ) ),
		'none',
		MUTED,
		2.5
	);
}

/* ------------------------------------------------------------------ */

$images = array();

// What is a lift table: the deck in both positions, which is the whole idea.
$low = scissor_table( array( 'h' => 26, 'leg' => 240 ) );
$t   = scissor_table( array( 'h' => 120, 'leg' => 240 ) );
$images['what-is-a-lift-table'] = array(
	'A lift table platform shown lowered and raised',
	ground()
		. ghost_crate( 170, $low['top'], 140, 74 )
		. rect( 100, $low['top'], 280, 20, 'none', LINE, 2.5 )
		. $t['svg']
		. crate( 170, $t['top'], 140, 74 )
		. dim_v( 424, $low['top'], $t['top'] ),
);

// Capacity: the same weight, centred and then out at the end, with the moment
// it applies to the mechanism.
$t = scissor_table( array( 'h' => 104 ) );
$images['lift-table-capacity-selection-guide'] = array(
	'A load shown centred on a lift table platform and again at one end',
	ground()
		. $t['svg']
		. ghost_crate( 182, $t['top'], 116, 70 )
		. crate( 268, $t['top'], 116, 70 )
		. moment_arc( 330, 232, 40 ),
);

// Loading: a jack can reach a floor-level deck and cannot reach a standard
// one, so both decks are drawn.
$t = scissor_table(
	array(
		'pin'      => 196,
		'base_top' => 264,
		'base_h'   => 12,
		'base_x'   => array( 176, 404 ),
		'plat_x'   => array( 160, 420 ),
		'plat_t'   => 14,
		'h'        => 6,
		'leg'      => 210,
		'outer'    => 12,
		'inner'    => 7,
		'cylinder' => false,
	)
);
$images['lift-table-loading-options'] = array(
	'A pallet jack rolling onto a floor level lift table, with a standard deck height shown above',
	ground()
		. rect( 160, 180, 260, 16, 'none', LINE, 2.5 )
		. line( 150, 188, 160, 188, LINE, 2.5 ) . line( 420, 188, 430, 188, LINE, 2.5 )
		. $t['svg']
		. pallet_jack( 64 )
		. pallet( 196, $t['top'], 150 ),
);

/* ------------------------------------------------------------------ */

$dir = dirname( __DIR__ ) . '/images/guides';

if ( ! is_dir( $dir ) ) {
	mkdir( $dir, 0777, true );
}

foreach ( $images as $slug => list( $title, $body ) ) {
	file_put_contents( "{$dir}/{$slug}.svg", doc( $title, $body ) );
}

echo count( $images ) . " illustrations written to images/guides/\n";
