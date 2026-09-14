<?php
/**
 * Standardize product line photos listed in images/lines/images.json.
 *
 * Every output is a 1200 x 900 (4:3) JPEG, so cards and pages line up:
 *
 *   contain  Cut-out product shots (transparent or white backgrounds) are
 *            trimmed to the product, then centred on white with even margins.
 *            Nothing about the product is changed.
 *   cover    Photos with a real background are centre-cropped to 4:3 instead,
 *            since a scene boxed on white looks worse than a trimmed scene.
 *
 * Small cut-outs are enlarged by at most 1.6x, so a low-resolution shot stays
 * sharp rather than filling the frame blurrily. Cover photos always fill the
 * frame. Originals too small for either (under about 450 px) aren't worth
 * using; leave them out of the manifest.
 *
 * Originals aren't kept in the repository. Point --from at a folder holding
 * them named <slug>.<ext> (as downloaded from each entry's "original" URL).
 *
 * Usage: php -d memory_limit=2G generators/line-images.php --from=<folder>
 * Needs the GD extension.
 */

const OUT_W       = 1200;
const OUT_H       = 900;
const MARGIN      = 0.07;
const MAX_ENLARGE = 1.6;
const WORK_MAX    = 2400;
const QUALITY     = 86;

if ( ! function_exists( 'imagecreatefromstring' ) ) {
	fwrite( STDERR, "The GD extension is required.\n" );
	exit( 2 );
}

$root = dirname( __DIR__ );
$from = null;

foreach ( array_slice( $argv, 1 ) as $arg ) {
	if ( 0 === strpos( $arg, '--from=' ) ) {
		$from = rtrim( substr( $arg, 7 ), '/\\' );
	}
}

if ( ! $from || ! is_dir( $from ) ) {
	fwrite( STDERR, "Usage: php generators/line-images.php --from=<folder of originals>\n" );
	exit( 2 );
}

$manifest = json_decode( file_get_contents( $root . '/images/lines/images.json' ), true );
$written  = 0;

/** A truecolor copy with alpha preserved, scaled down to WORK_MAX if larger. */
function load_image( $file ) {
	$src = imagecreatefromstring( file_get_contents( $file ) );

	if ( ! $src ) {
		return null;
	}

	$w     = imagesx( $src );
	$h     = imagesy( $src );
	$scale = min( 1, WORK_MAX / max( $w, $h ) );
	$nw    = (int) round( $w * $scale );
	$nh    = (int) round( $h * $scale );

	$img = imagecreatetruecolor( $nw, $nh );
	imagealphablending( $img, false );
	imagesavealpha( $img, true );
	imagefill( $img, 0, 0, imagecolorallocatealpha( $img, 0, 0, 0, 127 ) );
	imagecopyresampled( $img, $src, 0, 0, 0, 0, $nw, $nh, $w, $h );
	imagedestroy( $src );

	return $img;
}

/** Whether a pixel is background: transparent, or near white. */
function is_background( $img, $x, $y ) {
	$c = imagecolorsforindex( $img, imagecolorat( $img, $x, $y ) );
	return $c['alpha'] > 110 || ( $c['red'] > 244 && $c['green'] > 244 && $c['blue'] > 244 );
}

/** Bounding box of everything that isn't background. */
function content_box( $img ) {
	$w = imagesx( $img );
	$h = imagesy( $img );

	$top = 0;
	while ( $top < $h && row_empty( $img, $top, $w ) ) {
		++$top;
	}
	$bottom = $h - 1;
	while ( $bottom > $top && row_empty( $img, $bottom, $w ) ) {
		--$bottom;
	}
	$left = 0;
	while ( $left < $w && col_empty( $img, $left, $top, $bottom ) ) {
		++$left;
	}
	$right = $w - 1;
	while ( $right > $left && col_empty( $img, $right, $top, $bottom ) ) {
		--$right;
	}

	return array( $left, $top, $right - $left + 1, $bottom - $top + 1 );
}

function row_empty( $img, $y, $w ) {
	for ( $x = 0; $x < $w; $x += 2 ) {
		if ( ! is_background( $img, $x, $y ) ) {
			return false;
		}
	}
	return true;
}

function col_empty( $img, $x, $top, $bottom ) {
	for ( $y = $top; $y <= $bottom; $y += 2 ) {
		if ( ! is_background( $img, $x, $y ) ) {
			return false;
		}
	}
	return true;
}

foreach ( $manifest['images'] as $entry ) {
	$slug     = $entry['slug'];
	$original = glob( $from . '/' . $slug . '.*' );

	if ( ! $original ) {
		fwrite( STDERR, "{$slug}: no original in {$from}\n" );
		continue;
	}

	$img = load_image( $original[0] );

	if ( ! $img ) {
		fwrite( STDERR, "{$slug}: could not read {$original[0]}\n" );
		continue;
	}

	$out   = imagecreatetruecolor( OUT_W, OUT_H );
	$white = imagecolorallocate( $out, 255, 255, 255 );
	imagefill( $out, 0, 0, $white );
	imagealphablending( $out, true );

	$w = imagesx( $img );
	$h = imagesy( $img );

	if ( 'cover' === ( $entry['fit'] ?? 'contain' ) ) {
		// Centre-crop the photo to 4:3, then scale to the output size.
		$ratio = OUT_W / OUT_H;
		$cw    = $w / $h > $ratio ? (int) round( $h * $ratio ) : $w;
		$ch    = $w / $h > $ratio ? $h : (int) round( $w / $ratio );
		// A photo fills the frame, so it is enlarged as far as it needs to be.
		$scale = OUT_W / $cw;
		$dw    = (int) round( $cw * $scale );
		$dh    = (int) round( $ch * $scale );

		imagecopyresampled( $out, $img, (int) ( ( OUT_W - $dw ) / 2 ), (int) ( ( OUT_H - $dh ) / 2 ), (int) ( ( $w - $cw ) / 2 ), (int) ( ( $h - $ch ) / 2 ), $dw, $dh, $cw, $ch );
	} else {
		list( $bx, $by, $bw, $bh ) = content_box( $img );

		$avail_w = OUT_W * ( 1 - 2 * MARGIN );
		$avail_h = OUT_H * ( 1 - 2 * MARGIN );
		$scale   = min( MAX_ENLARGE, $avail_w / $bw, $avail_h / $bh );
		$dw      = (int) round( $bw * $scale );
		$dh      = (int) round( $bh * $scale );

		imagecopyresampled( $out, $img, (int) ( ( OUT_W - $dw ) / 2 ), (int) ( ( OUT_H - $dh ) / 2 ), $bx, $by, $dw, $dh, $bw, $bh );
	}

	$target = $root . '/' . $entry['file'];

	if ( ! is_dir( dirname( $target ) ) ) {
		mkdir( dirname( $target ), 0777, true );
	}

	imageinterlace( $out, true );
	imagejpeg( $out, $target, QUALITY );
	imagedestroy( $out );
	imagedestroy( $img );

	printf( "%-36s %s\n", $slug, basename( $target ) );
	++$written;
}

echo "{$written} of " . count( $manifest['images'] ) . " images written\n";
exit( $written === count( $manifest['images'] ) ? 0 : 1 );
