<?php
/**
 * Draw one illustration per lift table category.
 *
 * Every image is a side elevation in the brand's technical-drawing style
 * (see brand/brand-guide.html, "Category illustrations"): a 480 x 320 canvas,
 * a hatched ground line, ink outlines, white fills, safety yellow only on the
 * part that lifts, and gauge grey for everything secondary. There is no text,
 * because fonts don't load inside an image file and a drawing without words
 * works in any language.
 *
 * Usage: php generators/category-images.php
 * Output: images/categories/<category-slug>.svg
 */

const W      = 480;
const H      = 320;
const GROUND = 276;
const INK    = '#18212A';
const PAPER  = '#FFFFFF';
const SIGNAL = '#F5B800';
const MUTED  = '#5C6975';
const LINE   = '#AAB4BD';
const FAINT  = '#D6DCE1';
const STROKE = 3;

/* ------------------------------------------------------------------ */
/* Primitives                                                          */
/* ------------------------------------------------------------------ */

function n( $v ) {
	return rtrim( rtrim( number_format( $v, 1, '.', '' ), '0' ), '.' );
}

function rect( $x, $y, $w, $h, $fill = PAPER, $stroke = INK, $sw = STROKE, $extra = '' ) {
	return sprintf(
		'<rect x="%s" y="%s" width="%s" height="%s" fill="%s" stroke="%s" stroke-width="%s"%s/>',
		n( $x ), n( $y ), n( $w ), n( $h ), $fill, $stroke, n( $sw ), $extra
	);
}

function line( $x1, $y1, $x2, $y2, $color = INK, $w = STROKE, $extra = '' ) {
	return sprintf(
		'<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-width="%s"%s/>',
		n( $x1 ), n( $y1 ), n( $x2 ), n( $y2 ), $color, n( $w ), $extra
	);
}

function path( $d, $fill = 'none', $stroke = INK, $sw = STROKE, $extra = '' ) {
	return sprintf( '<path d="%s" fill="%s" stroke="%s" stroke-width="%s"%s/>', $d, $fill, $stroke, n( $sw ), $extra );
}

function circle( $cx, $cy, $r, $fill = PAPER, $stroke = INK, $sw = STROKE ) {
	return sprintf( '<circle cx="%s" cy="%s" r="%s" fill="%s" stroke="%s" stroke-width="%s"/>', n( $cx ), n( $cy ), n( $r ), $fill, $stroke, n( $sw ) );
}

/** A scissor leg: an ink bar with a white core, so crossings stay readable. */
function leg( $x1, $y1, $x2, $y2, $outer = 18, $inner = 12 ) {
	return line( $x1, $y1, $x2, $y2, INK, $outer ) . line( $x1, $y1, $x2, $y2, PAPER, $inner );
}

/** Hatched ground between two x positions. */
function ground( $x1 = 30, $x2 = 450, $y = GROUND ) {
	return rect( $x1, $y, $x2 - $x1, 14, 'url(#hatch)', 'none', 0 ) . line( $x1, $y, $x2, $y, INK, STROKE );
}

/** Vertical dimension line with end ticks. */
function dim_v( $x, $y1, $y2 ) {
	return line( $x, $y1, $x, $y2, MUTED, 1.5 )
		. line( $x - 9, $y1, $x + 9, $y1, MUTED, 1.5 )
		. line( $x - 9, $y2, $x + 9, $y2, MUTED, 1.5 );
}

/** Hydraulic cylinder: a grey barrel and an ink rod. */
function cylinder( $x1, $y1, $x2, $y2, $barrel = 10 ) {
	$mx = $x1 + ( $x2 - $x1 ) * 0.58;
	$my = $y1 + ( $y2 - $y1 ) * 0.58;

	return line( $mx, $my, $x2, $y2, INK, 4 ) . line( $x1, $y1, $mx, $my, MUTED, $barrel );
}

/** Swivel caster: fork plate and wheel, axle at ($x, $axle). */
function caster( $x, $top, $axle, $r = 11 ) {
	return rect( $x - 7, $top, 14, $axle - $top, PAPER, INK, 2.5 )
		. circle( $x, $axle, $r, PAPER, INK, STROKE )
		. circle( $x, $axle, 3, INK, INK, 0 );
}

/** Push handle rising from a base. */
function push_handle( $x, $base, $top, $toward = -1 ) {
	return line( $x, $base, $x, $top + 10, INK, 6 )
		. path( sprintf( 'M%s %s L%s %s L%s %s', n( $x ), n( $top + 12 ), n( $x ), n( $top ), n( $x + 26 * $toward ), n( $top - 8 ) ), 'none', INK, 6 );
}

/** Shipping crate outline: the load, in grey. */
function crate( $x, $bottom, $w, $h ) {
	$y = $bottom - $h;
	return rect( $x, $y, $w, $h, PAPER, MUTED, 2.5 )
		. line( $x, $y, $x + $w, $bottom, LINE, 2 )
		. line( $x + $w, $y, $x, $bottom, LINE, 2 )
		. rect( $x, $y, $w, $h, 'none', MUTED, 2.5 );
}

/** A pallet seen from the side: top deck, three blocks, bottom deck. */
function pallet( $x, $bottom, $w ) {
	$out  = rect( $x, $bottom - 6, $w, 6, PAPER, MUTED, 2.5 );
	$out .= rect( $x, $bottom - 26, $w, 6, PAPER, MUTED, 2.5 );
	foreach ( array( 0, 0.5, 1 ) as $f ) {
		$bx   = $x + ( $w - 26 ) * $f;
		$out .= rect( $bx, $bottom - 20, 26, 14, PAPER, MUTED, 2.5 );
	}
	return $out;
}

/**
 * A scissor lift table.
 *
 * Fixed pins on the left, rollers on the right; the span follows from the
 * leg length and the lift height, so every table is geometrically real.
 */
function scissor_table( $o = array() ) {
	$o = array_merge(
		array(
			'pin'      => 140,
			'base_top' => 252,
			'base_h'   => 24,
			'base_x'   => array( 120, 360 ),
			'plat_x'   => array( 100, 380 ),
			'plat_t'   => 20,
			'h'        => 110,
			'leg'      => 240,
			'stages'   => 1,
			'cylinder' => true,
			'outer'    => 18,
			'inner'    => 12,
			'barrel'   => 10,
			'twin'     => false,
			'platform' => null,
		),
		$o
	);

	$yb      = $o['base_top'];
	$stage_h = $o['h'] / $o['stages'];
	$span    = sqrt( $o['leg'] ** 2 - $stage_h ** 2 );
	$x1      = $o['pin'];
	$x2      = $x1 + $span;
	$top_bot = $yb - $o['h'];
	$top     = $top_bot - $o['plat_t'];

	$out = '';

	if ( $o['cylinder'] ) {
		$offsets = $o['twin'] ? array( -14, 14 ) : array( 0 );
		foreach ( $offsets as $dx ) {
			$out .= cylinder( $x1 + 62 + $dx, $yb, $x1 + 0.6 * $span + $dx, $yb - 0.6 * $stage_h, $o['barrel'] );
		}
	}

	for ( $i = 0; $i < $o['stages']; $i++ ) {
		$low  = $yb - $i * $stage_h;
		$high = $low - $stage_h;
		$out .= leg( $x1, $high, $x2, $low, $o['outer'], $o['inner'] );
		$out .= leg( $x1, $low, $x2, $high, $o['outer'], $o['inner'] );
	}

	if ( $o['base_h'] > 0 ) {
		$out .= rect( $o['base_x'][0], $yb, $o['base_x'][1] - $o['base_x'][0], $o['base_h'] );
	}

	$out .= null !== $o['platform']
		? $o['platform']
		: rect( $o['plat_x'][0], $top, $o['plat_x'][1] - $o['plat_x'][0], $o['plat_t'], SIGNAL );

	$pin_r = $o['outer'] > 18 ? 8 : 6;

	for ( $i = 0; $i <= $o['stages']; $i++ ) {
		$y     = $yb - $i * $stage_h;
		$out  .= circle( $x1, $y, $pin_r );
		$out  .= circle( $x2, $y, $i > 0 && $i < $o['stages'] ? $pin_r : $pin_r + 2 );
	}

	for ( $i = 0; $i < $o['stages']; $i++ ) {
		$out .= circle( ( $x1 + $x2 ) / 2, $yb - ( $i + 0.5 ) * $stage_h, $pin_r + 1 );
	}

	return array(
		'svg'  => $out,
		'top'  => $top,
		'x2'   => $x2,
		'yb'   => $yb,
		'span' => $span,
	);
}

function doc( $title, $body ) {
	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . W . ' ' . H . '" width="' . W . '" height="' . H . '" role="img" aria-label="' . htmlspecialchars( $title ) . '">'
		. "\n<title>" . htmlspecialchars( $title ) . "</title>\n"
		. '<defs><pattern id="hatch" width="9" height="9" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><line x1="0" y1="0" x2="0" y2="9" stroke="' . LINE . '" stroke-width="2"/></pattern></defs>'
		. "\n" . $body . "\n</svg>\n";
}

/* ------------------------------------------------------------------ */
/* The drawings                                                        */
/* ------------------------------------------------------------------ */

$images = array();

// Lift Tables: the everyday scissor table, carrying a load.
$t = scissor_table();
$images['lift-tables'] = array(
	'Lift table carrying a crate',
	ground() . $t['svg'] . crate( 170, $t['top'], 140, 78 ) . dim_v( 420, GROUND, $t['top'] ),
);

// Scissor: the mechanism itself, nothing else.
$t = scissor_table();
$images['scissor-lift-tables'] = array(
	'Scissor lift table',
	ground() . $t['svg'] . dim_v( 420, GROUND, $t['top'] ),
);

// Mobile: base on casters with a push handle.
$t = scissor_table(
	array(
		'pin'      => 168,
		'base_top' => 218,
		'base_x'   => array( 108, 358 ),
		'plat_x'   => array( 138, 370 ),
		'h'        => 92,
		'leg'      => 200,
	)
);
$images['mobile-lift-tables'] = array(
	'Mobile lift table on casters with a push handle',
	ground()
		. caster( 150, 242, GROUND - 12 ) . caster( 336, 242, GROUND - 12 )
		. push_handle( 116, 218, 76 )
		. $t['svg']
		. dim_v( 410, GROUND, $t['top'] ),
);

// Post: a single telescoping column under the deck.
$images['post-lift-tables'] = array(
	'Post lift table with a single telescoping column',
	ground()
		. rect( 150, 262, 180, 14 )
		. rect( 223, 176, 34, 86 )
		. rect( 231, 132, 18, 44, PAPER, INK, STROKE )
		. rect( 214, 170, 52, 10 )
		. line( 240, 146, 186, 126, INK, 5 ) . line( 240, 146, 294, 126, INK, 5 )
		. rect( 140, 110, 200, 20, SIGNAL )
		. dim_v( 390, GROUND, 110 ),
);

// Double scissor: two scissor pairs stacked for extra travel.
$t = scissor_table(
	array(
		'pin'      => 150,
		'base_x'   => array( 130, 350 ),
		'plat_x'   => array( 114, 366 ),
		'h'        => 172,
		'leg'      => 190,
		'stages'   => 2,
	)
);
$images['double-scissor-lift-tables'] = array(
	'Double scissor lift table with two stacked scissor pairs',
	ground() . $t['svg'] . dim_v( 410, GROUND, $t['top'] ),
);

// Rotating: a turntable on a bearing, with a rotation arrow.
$t   = scissor_table( array( 'h' => 96 ) );
$top = $t['top'];
$cy  = $top - 42;
$images['rotating-lift-tables'] = array(
	'Rotating lift table with a turntable top',
	ground()
		. $t['svg']
		. rect( 214, $top - 8, 52, 8, PAPER, INK, STROKE )
		. rect( 116, $top - 24, 248, 16, SIGNAL )
		. path( sprintf( 'M150 %1$s A90 18 0 0 0 330 %1$s', n( $cy ) ), 'none', MUTED, 3 )
		. path( sprintf( 'M330 %1$s A90 18 0 0 0 150 %1$s', n( $cy ) ), 'none', LINE, 2.5, ' stroke-dasharray="6 6"' )
		. path( sprintf( 'M322 %s L330 %s L338 %s', n( $cy + 8 ), n( $cy - 4 ), n( $cy + 8 ) ), 'none', MUTED, 3 )
		. dim_v( 420, GROUND, $top - 24 ),
);

// Tilt: a hinged deck raised at one end by a cylinder, tipping its load
// toward the operator, with the angle marked.
$hx    = 104;
$hy    = 232;
$angle = 26;
$rad   = deg2rad( $angle );
$at    = static fn( $d ) => array( $hx + $d * cos( $rad ), $hy - $d * sin( $rad ) );
list( $cx, $cyl ) = $at( 214 );
list( $ax, $ay )  = $at( 120 );
$images['tilt-tables'] = array(
	'Tilt table with a hinged deck raised at one end',
	ground()
		. cylinder( 318, 232, $cx, $cyl, 14 )
		. rect( 88, 232, 290, 44 )
		. line( $hx, $hy, 250, $hy, LINE, 2, ' stroke-dasharray="6 5"' )
		. sprintf(
			'<g transform="rotate(-%1$s %2$s %3$s)">%4$s%5$s</g>',
			$angle,
			$hx,
			$hy,
			rect( $hx - 10, $hy - 24, 318, 24, SIGNAL ),
			crate( $hx + 60, $hy - 24, 150, 84 )
		)
		. circle( $hx, $hy, 9 )
		. path( sprintf( 'M%s %s A120 120 0 0 0 %s %s', n( $hx + 120 ), n( $hy ), n( $ax ), n( $ay ) ), 'none', MUTED, 2.5 )
		. circle( $cx, $cyl, 7 ),
);

// Pallet: a pallet sits on the deck.
$t = scissor_table( array( 'h' => 88, 'leg' => 230 ) );
$images['pallet-lift-tables'] = array(
	'Pallet lift table carrying a pallet',
	ground() . $t['svg'] . pallet( 118, $t['top'], 244 ) . dim_v( 420, GROUND, $t['top'] ),
);

// Low profile: a very low table a pallet jack can reach.
$t = scissor_table(
	array(
		'pin'      => 204,
		'base_top' => 268,
		'base_h'   => 8,
		'base_x'   => array( 186, 440 ),
		'plat_x'   => array( 176, 450 ),
		'plat_t'   => 14,
		'h'        => 24,
		'leg'      => 226,
		'outer'    => 12,
		'inner'    => 7,
		'cylinder' => false,
	)
);
$images['low-profile-lift-tables'] = array(
	'Low profile lift table beside a pallet jack',
	ground( 20, 462 )
		. line( 58, 222, 28, 132, MUTED, 6 ) . line( 18, 128, 40, 136, MUTED, 6 )
		. rect( 44, 222, 42, 34, PAPER, MUTED, 2.5 )
		. rect( 86, 250, 80, 10, PAPER, MUTED, 2.5 )
		. circle( 62, 262, 14, PAPER, MUTED, 3 )
		. circle( 156, 268, 5, PAPER, MUTED, 2.5 )
		. $t['svg']
		. dim_v( 466, GROUND, $t['top'] ),
);

// Floor level: set in a pit so the deck sits flush with the floor.
$floor = 140;
$t     = scissor_table(
	array(
		'pin'      => 162,
		'base_top' => 268,
		'base_x'   => array( 140, 340 ),
		'h'        => 112,
		'leg'      => 206,
		'plat_x'   => array( 132, 348 ),
		'plat_t'   => 16,
	)
);
$images['floor-level-lift-tables'] = array(
	'Floor level lift table set in a pit, flush with the floor',
	rect( 30, $floor, 92, 160, 'url(#hatch)', 'none', 0 )
		. rect( 358, $floor, 92, 160, 'url(#hatch)', 'none', 0 )
		. rect( 122, 292, 236, 12, 'url(#hatch)', 'none', 0 )
		. path( sprintf( 'M30 %1$s H122 V292 H358 V%1$s H450', $floor ), 'none', INK, STROKE )
		. $t['svg']
		. crate( 190, $floor, 100, 64 )
		. line( 122, $floor - 1, 132, $floor - 1, INK, STROKE )
		. line( 348, $floor - 1, 358, $floor - 1, INK, STROKE ),
);

// Heavy duty: heavier legs and frame, twin cylinders, a dense load.
$t   = scissor_table(
	array(
		'base_h' => 30,
		'base_top' => 246,
		'plat_t' => 28,
		'h'      => 96,
		'leg'    => 232,
		'outer'  => 26,
		'inner'  => 16,
		'barrel' => 13,
		'twin'   => true,
	)
);
$top = $t['top'];
$images['heavy-duty-lift-tables'] = array(
	'Heavy duty lift table carrying a steel load',
	ground()
		. $t['svg']
		. rect( 160, $top - 70, 160, 70, FAINT, INK, STROKE )
		. line( 160, $top - 50, 320, $top - 50, LINE, 2.5 )
		. line( 160, $top - 20, 320, $top - 20, LINE, 2.5 )
		. dim_v( 420, GROUND, $top ),
);

// Stainless steel: washdown droplets over the table.
$t    = scissor_table();
$top  = $t['top'];
$drop = static fn( $x, $y ) => path( sprintf( 'M%1$s %2$s C%3$s %4$s %5$s %6$s %1$s %7$s C%8$s %6$s %9$s %4$s %1$s %2$s Z', n( $x ), n( $y - 14 ), n( $x + 3 ), n( $y - 6 ), n( $x + 10 ), n( $y + 2 ), n( $y + 9 ), n( $x - 10 ), n( $x - 3 ) ), PAPER, MUTED, 2.5 );
$images['stainless-steel-lift-tables'] = array(
	'Stainless steel lift table under washdown spray',
	ground()
		. $t['svg']
		. $drop( 176, $top - 44 ) . $drop( 240, $top - 70 ) . $drop( 304, $top - 38 )
		. $drop( 208, $top - 96 ) . $drop( 276, $top - 104 )
		. dim_v( 420, GROUND, $top ),
);

// Hydraulic: the cylinder is the subject, fed by a hose from a power unit.
$t = scissor_table( array( 'barrel' => 15, 'pin' => 120, 'base_x' => array( 100, 340 ), 'plat_x' => array( 80, 360 ) ) );
$images['hydraulic-lift-tables'] = array(
	'Hydraulic lift table with its cylinder, hose and power unit',
	ground()
		. $t['svg']
		. path( 'M340 266 C356 266 360 250 376 250', 'none', MUTED, 5 )
		. rect( 376, 222, 64, 54 )
		. rect( 392, 210, 32, 12 )
		. circle( 408, 250, 11, PAPER, INK, 2.5 )
		. line( 408, 250, 414, 244, INK, 2.5 )
		. dim_v( 44, GROUND, $t['top'] ),
);

// Electric: a motor unit marked with a bolt, and its power cord.
$t = scissor_table( array( 'pin' => 120, 'base_x' => array( 100, 340 ), 'plat_x' => array( 80, 360 ) ) );
$images['electric-lift-tables'] = array(
	'Electric lift table with a motor unit and power cord',
	ground()
		. $t['svg']
		. path( 'M340 266 C356 266 360 252 372 252', 'none', MUTED, 5 )
		. rect( 372, 212, 66, 64 )
		. path( 'M410 222 L394 246 H406 L398 266 L420 238 H408 L416 222 Z', INK, INK, 1.5 )
		. path( 'M438 232 C462 232 456 196 466 180', 'none', MUTED, 4 )
		. rect( 458, 164, 16, 18, PAPER, INK, 2.5 )
		. line( 462, 164, 462, 156, INK, 2.5 ) . line( 470, 164, 470, 156, INK, 2.5 )
		. dim_v( 44, GROUND, $t['top'] ),
);

// Pneumatic: an air bag (bellows) lift, with its air line and coupler.
$bellows = '';
foreach ( array( 0, 1, 2 ) as $i ) {
	$y        = 176 + $i * 30;
	$bellows .= sprintf( '<rect x="150" y="%s" width="180" height="28" rx="14" fill="%s" stroke="%s" stroke-width="%s"/>', $y, PAPER, INK, STROKE );
}
foreach ( array( 1, 2 ) as $i ) {
	$bellows .= rect( 162, 174 + $i * 30, 156, 6, INK, INK, 0 );
}
$images['pneumatic-lift-tables'] = array(
	'Pneumatic air bag lift table with its air line',
	ground()
		. rect( 120, 264, 240, 12 )
		. $bellows
		. rect( 110, 150, 260, 24, SIGNAL )
		. path( 'M360 270 C392 270 396 240 426 240', 'none', MUTED, 5 )
		. rect( 426, 230, 22, 20, PAPER, INK, 2.5 )
		. line( 448, 240, 462, 240, INK, 4 )
		. dim_v( 60, GROUND, 150 ),
);

// Manual: a foot pump and pedal on a mobile table.
$t = scissor_table(
	array(
		'pin'      => 176,
		'base_top' => 218,
		'base_x'   => array( 140, 392 ),
		'plat_x'   => array( 132, 364 ),
		'h'        => 92,
		'leg'      => 196,
	)
);
$images['manual-lift-tables'] = array(
	'Manual lift table with a foot pump pedal',
	ground()
		. caster( 170, 242, GROUND - 12 ) . caster( 336, 242, GROUND - 12 )
		. push_handle( 384, 218, 76, 1 )
		. $t['svg']
		. rect( 146, 190, 26, 28 )
		. line( 150, 214, 100, 238, INK, 5 )
		. rect( 84, 234, 30, 8, PAPER, INK, 2.5 )
		. path( 'M99 196 V222 M91 214 L99 224 L107 214', 'none', MUTED, 3 )
		. dim_v( 426, GROUND, $t['top'] ),
);

// Battery powered: a battery pack on a mobile table.
$t = scissor_table(
	array(
		'pin'      => 168,
		'base_top' => 218,
		'base_x'   => array( 108, 408 ),
		'plat_x'   => array( 138, 370 ),
		'h'        => 92,
		'leg'      => 200,
	)
);
$images['battery-powered-lift-tables'] = array(
	'Battery powered lift table with an on-board battery',
	ground()
		. caster( 150, 242, GROUND - 12 ) . caster( 386, 242, GROUND - 12 )
		. push_handle( 116, 218, 76 )
		. $t['svg']
		. rect( 356, 170, 50, 48 )
		. rect( 364, 162, 10, 8, INK, INK, 0 ) . rect( 388, 162, 10, 8, INK, INK, 0 )
		. line( 364, 190, 376, 190, INK, 3 ) . line( 370, 184, 370, 196, INK, 3 )
		. line( 386, 190, 398, 190, INK, 3 )
		. dim_v( 440, GROUND, $t['top'] ),
);

/* ------------------------------------------------------------------ */

$dir = dirname( __DIR__ ) . '/images/categories';

if ( ! is_dir( $dir ) ) {
	mkdir( $dir, 0777, true );
}

foreach ( $images as $slug => list( $title, $body ) ) {
	file_put_contents( "{$dir}/{$slug}.svg", doc( $title, $body ) );
}

echo count( $images ) . " illustrations written to images/categories/\n";
