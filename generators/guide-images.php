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
// Only the lowered deck is ghosted, not a whole second mechanism, so this is
// a height rather than a table: base top, less the lift, less the deck. Built
// as a table it would have failed the feet-on-base check -- a scissor lowered
// to 26px spans almost its full leg length, far wider than a standard base.
$lowered_top = 252 - 26 - 20;

$t = scissor_table( array( 'h' => 120, 'leg' => 240 ) );
$images['what-is-a-lift-table'] = array(
	'A lift table platform shown lowered and raised',
	ground()
		. ghost_crate( 170, $lowered_top, 140, 74 )
		. rect( 100, $lowered_top, 280, 20, 'none', LINE, 2.5 )
		. $t['svg']
		. crate( 170, $t['top'], 140, 74 )
		. dim_v( 424, $lowered_top, $t['top'] ),
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
		// 188, not 196: at a 6px lift the legs are almost flat and span
		// nearly their full 210, so the roller needs the base to reach it.
		'pin'      => 188,
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

// Power: one table, and the choice of what drives it. The mains power unit is
// drawn; an air supply, the usual alternative, is ghosted on the other side.
$t = scissor_table( array( 'h' => 110 ) );
$images['lift-table-power-options'] = array(
	'A lift table with its hydraulic power unit, and an air supply shown as the alternative',
	ground()
		. path( 'M120 268 C100 268 98 250 78 250', 'none', LINE, 4 )
		. rect( 54, 238, 24, 24, 'none', LINE, 2.5 )
		. line( 30, 250, 54, 250, LINE, 4 )
		. $t['svg']
		. path( 'M360 266 C372 266 376 252 388 252', 'none', MUTED, 5 )
		. rect( 388, 222, 60, 54 )
		. rect( 402, 210, 32, 12 )
		. circle( 418, 250, 11, PAPER, INK, 2.5 ),
);

// Duty cycle: the deck at both ends of its stroke, and the loop it runs.
$t = scissor_table( array( 'h' => 120 ) );
$images['lift-table-duty-cycle'] = array(
	'A lift table deck shown lowered and raised, with arrows for the repeating cycle',
	ground()
		. rect( 100, 202, 280, 20, 'none', LINE, 2.5 )
		. $t['svg']
		. path( 'M420 130 A 28 28 0 0 1 420 186', 'none', MUTED, 3 )
		. path( 'M428 180 L420 186 L428 192', 'none', MUTED, 3 )
		. path( 'M420 186 A 28 28 0 0 1 420 130', 'none', MUTED, 3 )
		. path( 'M412 124 L420 130 L412 136', 'none', MUTED, 3 ),
);

// ANSI MH29.1: the table held up on its maintenance prop, with its toe guard
// and a locked-out disconnect -- the moment the standard cares most about.
$t = scissor_table( array( 'h' => 120 ) );
$images['ansi-mh29-1-lift-table-standard'] = array(
	'A raised lift table held on its maintenance prop, with a toe guard and a locked power disconnect',
	ground()
		. $t['svg']
		. line( 302, 252, 290, 166, MUTED, 8, ' stroke-linecap="round"' )
		// The guard hangs in front of the legs; the two top pins go back on over it.
		. rect( 104, 133, 272, 6, PAPER, INK, 2 )
		. circle( 140, 132, 6 )
		. circle( $t['x2'], 132, 8 )
		. rect( 396, 226, 48, 50 )
		. path( 'M412 244 V236 A8 8 0 0 1 428 236 V244', 'none', INK, 2.5 )
		. rect( 408, 244, 24, 16, PAPER, INK, 2.5 ),
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
