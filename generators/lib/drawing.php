<?php
/**
 * Shared drawing primitives for the brand's technical illustrations.
 *
 * Extracted from category-images.php so the application drawings are made
 * with the same hand: same canvas, same ground, same stroke weights, same
 * scissor geometry. See brand/brand-guide.html, "Category illustrations",
 * which these constants encode.
 *
 * Nothing here draws a finished image -- each generator composes these.
 *
 * @package EquipmentDirectoryContent
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
 * Fail if the mechanism does not stand on its own base.
 *
 * The span of a scissor is sqrt(leg^2 - height^2), so lowering a table makes
 * the legs more horizontal and the span WIDER. Narrow the base at the same
 * time as lowering the deck -- an easy thing to do, because a lower table
 * looks like it should be a smaller one -- and the right-hand roller ends up
 * past the end of the base, hanging in mid air.
 *
 * It is a quiet error: the drawing still renders, and at tile size the gap is
 * a few pixels. So it is checked here rather than left to the eye.
 *
 * @param float $x1     Fixed pin, the left foot.
 * @param float $x2     Roller, the right foot.
 * @param array $base_x [left, right] of the base frame.
 */
function edc_assert_feet_on_base( $x1, $x2, $base_x ) {
	if ( $x1 < $base_x[0] || $x2 > $base_x[1] ) {
		fwrite(
			STDERR,
			sprintf(
				"Scissor feet are off the base: feet at %.1f and %.1f, base %.1f to %.1f.\n"
				. "A lower deck needs a WIDER base, not a narrower one.\n",
				$x1,
				$x2,
				$base_x[0],
				$base_x[1]
			)
		);
		exit( 1 );
	}
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

	edc_assert_feet_on_base( $x1, $x2, $o['base_x'] );

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
