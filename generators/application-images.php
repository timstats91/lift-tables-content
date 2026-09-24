<?php
/**
 * Draw one illustration per application.
 *
 * Same hand as the category drawings -- same canvas, ground, stroke weights
 * and scissor geometry, from lib/drawing.php -- with one rule that separates
 * the two families at a glance:
 *
 *   A category drawing shows a machine on its own. An application drawing
 *   shows the same machine in a setting, and the setting is drawn in Line
 *   Strong hairlines behind it, never in front.
 *
 * So a reader can tell what they are looking at before reading a word: heavy
 * ink in the foreground is the equipment, pale line work behind it is the
 * room. Safety yellow stays where the brand guide puts it, on the part that
 * lifts and nothing else.
 *
 * The dimension line is deliberately absent. It answers "how high does it
 * go", which is a category question; an application drawing answers "where
 * does this live".
 *
 * Usage: php generators/application-images.php
 * Output: images/applications/<application-slug>.svg
 */

require __DIR__ . '/lib/drawing.php';

/* ------------------------------------------------------------------ */
/* Settings -- the room, in Line Strong, behind everything             */
/* ------------------------------------------------------------------ */

/** Pallet racking: two uprights and the beams between them. */
function racking( $x1, $x2, $top, $beams ) {
	$out = line( $x1, $top, $x1, GROUND, LINE, 2.5 ) . line( $x2, $top, $x2, GROUND, LINE, 2.5 );

	foreach ( $beams as $y ) {
		$out .= line( $x1 - 6, $y, $x2 + 6, $y, LINE, 2.5 );
	}

	return $out;
}

/** A run of wall tiling: seams only, no fill. */
function tiled_wall( $x1, $x2, $y1, $y2, $pitch = 34 ) {
	$out = '';

	for ( $y = $y1; $y <= $y2; $y += $pitch ) {
		$out .= line( $x1, $y, $x2, $y, LINE, 1.5 );
	}

	// Offset every other course, the way tile is actually laid.
	$row = 0;
	for ( $y = $y1; $y < $y2; $y += $pitch ) {
		$offset = ( $row % 2 ) ? $pitch / 2 : 0;
		for ( $x = $x1 + $offset; $x <= $x2; $x += $pitch ) {
			$out .= line( $x, $y, $x, min( $y + $pitch, $y2 ), LINE, 1.5 );
		}
		++$row;
	}

	return $out;
}

/** A ceiling filter unit on its hangers. */
function ceiling_filter( $x1, $x2, $y, $h = 22 ) {
	$out = rect( $x1, $y, $x2 - $x1, $h, 'none', LINE, 2.5 );

	for ( $x = $x1 + 10; $x < $x2; $x += 14 ) {
		$out .= line( $x, $y + 3, $x, $y + $h - 3, LINE, 1.5 );
	}

	$out .= line( $x1 + 24, 0, $x1 + 24, $y, LINE, 1.5 );
	$out .= line( $x2 - 24, 0, $x2 - 24, $y, LINE, 1.5 );

	return $out;
}

/** A workbench with a parts-bin rack over it. */
function bench( $x1, $x2, $top ) {
	$out  = rect( $x1, $top, $x2 - $x1, 8, PAPER, MUTED, 2.5 );
	$out .= line( $x1 + 10, $top + 8, $x1 + 10, GROUND, MUTED, 2.5 );
	$out .= line( $x2 - 10, $top + 8, $x2 - 10, GROUND, MUTED, 2.5 );

	// Bins standing on the bench, angled open toward the operator, against a
	// back panel so the rack reads as one object rather than floating shapes.
	$rail = $top - 30;
	$out .= line( $x1 + 2, $rail - 8, $x1 + 2, $top, LINE, 2.5 );
	$out .= line( $x2 - 2, $rail - 8, $x2 - 2, $top, LINE, 2.5 );

	for ( $i = 0; $i < 3; $i++ ) {
		$bx   = $x1 + 6 + $i * ( ( $x2 - $x1 - 12 ) / 3 );
		$bw   = ( $x2 - $x1 - 12 ) / 3 - 6;
		$out .= path(
			sprintf(
				'M%s %s L%s %s L%s %s L%s %s Z',
				n( $bx ), n( $rail ),
				n( $bx + $bw ), n( $rail + 4 ),
				n( $bx + $bw - 3 ), n( $top ),
				n( $bx + 3 ), n( $top )
			),
			PAPER,
			LINE,
			2.5
		);
	}

	return $out;
}

/** A floor drain set into the ground band. */
function floor_drain( $x, $w = 54 ) {
	$out = rect( $x, GROUND + 1, $w, 12, PAPER, MUTED, 2.5 );

	for ( $i = 1; $i < 5; $i++ ) {
		$out .= line( $x + $i * ( $w / 5 ), GROUND + 3, $x + $i * ( $w / 5 ), GROUND + 11, MUTED, 1.5 );
	}

	return $out;
}

/** A tote or bin carried on the platform: the load, in grey. */
function tote( $x, $bottom, $w, $h ) {
	$y   = $bottom - $h;
	$out = path(
		sprintf(
			'M%s %s L%s %s L%s %s L%s %s Z',
			n( $x ), n( $y ),
			n( $x + $w ), n( $y ),
			n( $x + $w - 10 ), n( $bottom ),
			n( $x + 10 ), n( $bottom )
		),
		PAPER,
		MUTED,
		2.5
	);
	$out .= line( $x - 4, $y, $x + $w + 4, $y, MUTED, 3 );

	return $out;
}

/* ------------------------------------------------------------------ */
/* The drawings                                                        */
/* ------------------------------------------------------------------ */

$images = array();

// Warehouse: a pallet being built on the table, racking behind, an empty
// pallet waiting on the floor.
$t = scissor_table();
$images['warehouse-and-distribution'] = array(
	'Lift table building a pallet load, with racking behind',
	racking( 390, 446, 44, array( 92, 170 ) )
		. ground()
		. pallet( 36, GROUND, 74 )
		. $t['svg']
		. pallet( 158, $t['top'], 164 )
		. crate( 166, $t['top'] - 26, 72, 46 )
		. crate( 244, $t['top'] - 26, 72, 46 ),
);

// Assembly: the table at bench height beside a bench, with a part on the deck.
$t = scissor_table(
	array(
		'pin'      => 126,
		'base_x'   => array( 106, 330 ),
		'plat_x'   => array( 88, 348 ),
		'h'        => 74,
		'leg'      => 210,
	)
);
$images['assembly-and-production'] = array(
	'Lift table at bench height beside a workbench with parts bins',
	bench( 356, 446, 206 )
		. ground()
		. $t['svg']
		. crate( 164, $t['top'], 108, 52 ),
);

// Food: stainless work in a washdown bay -- tiled wall, floor drain, a tote
// on the deck.
$t = scissor_table(
	array(
		'pin'      => 140,
		'base_x'   => array( 120, 368 ),
		'plat_x'   => array( 100, 388 ),
		'h'        => 96,
	)
);
$images['food-and-beverage'] = array(
	'Lift table with a tote in a washdown bay, tiled wall and floor drain',
	tiled_wall( 34, 446, 60, 242, 42 )
		. ground()
		. floor_drain( 376 )
		. $t['svg']
		. tote( 172, $t['top'], 144, 62 ),
);

// Cleanroom: the same table under a ceiling filter, between panel joints.
$t = scissor_table(
	array(
		'pin'      => 140,
		'base_x'   => array( 120, 368 ),
		'plat_x'   => array( 100, 388 ),
		'h'        => 96,
	)
);
$images['pharmaceutical-and-cleanroom'] = array(
	'Lift table under a cleanroom ceiling filter, between wall panel joints',
	line( 54, 0, 54, GROUND, LINE, 2.5 )
		. line( 426, 0, 426, GROUND, LINE, 2.5 )
		. ceiling_filter( 154, 334, 34 )
		. ground()
		. $t['svg']
		. tote( 180, $t['top'], 128, 58 ),
);

/* ------------------------------------------------------------------ */

$dir = dirname( __DIR__ ) . '/images/applications';

if ( ! is_dir( $dir ) ) {
	mkdir( $dir, 0777, true );
}

foreach ( $images as $slug => list( $title, $body ) ) {
	file_put_contents( "{$dir}/{$slug}.svg", doc( $title, $body ) );
}

echo count( $images ) . " illustrations written to images/applications/\n";
