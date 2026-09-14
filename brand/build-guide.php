<?php
/**
 * Build brand/brand-guide.html from brand/brand-guide.src.html.
 *
 * Fills in the current logo files and category illustrations, so the guide
 * always shows the artwork the site actually uses. Run after changing the
 * logo generator, the category image generator or the guide source.
 *
 * Usage: php brand/build-guide.php
 */

$root = dirname( __DIR__ );
$html = file_get_contents( __DIR__ . '/brand-guide.src.html' );

/** An SVG file's markup for inlining: no fixed size, no title. */
function inline_svg( $file ) {
	$svg = file_get_contents( $file );
	$svg = preg_replace( '/ width="\d+" height="\d+"/', '', $svg, 1 );
	$svg = preg_replace( '/<title>[^<]*<\/title>\s*/', '', $svg, 1 );
	return trim( $svg );
}

/** An SVG file's contents without its outer <svg> element. */
function svg_inner( $file ) {
	$svg = inline_svg( $file );
	$svg = preg_replace( '/^<svg[^>]*>\s*/', '', $svg );
	return preg_replace( '/\s*<\/svg>$/', '', $svg );
}

$categories = json_decode( file_get_contents( $root . '/categories.json' ), true );
$sheet      = array();

foreach ( $categories['categories'] as $category ) {
	if ( empty( $category['image'] ) ) {
		continue;
	}

	$name  = trim( substr( strrchr( '> ' . $category['path'], '>' ), 1 ) );
	$data  = base64_encode( file_get_contents( $root . '/' . $category['image'] ) );

	// Images, not inline SVG: every drawing defines the same hatch pattern
	// id, and inlining them together would make those ids collide.
	$sheet[] = sprintf(
		'<li><div class="sheet__art"><img src="data:image/svg+xml;base64,%1$s" width="480" height="320" alt="" loading="lazy" /></div><div class="sheet__name">%2$s</div></li>',
		$data,
		htmlspecialchars( $name )
	);
}

$replacements = array(
	'{{LOGO}}'           => inline_svg( __DIR__ . '/logo.svg' ),
	'{{LOGO_REVERSE}}'   => inline_svg( __DIR__ . '/logo-reverse.svg' ),
	'{{LOGO_MONO}}'      => inline_svg( __DIR__ . '/logo-mono.svg' ),
	'{{MARK}}'           => inline_svg( __DIR__ . '/mark.svg' ),
	'{{LOGO_INNER}}'     => svg_inner( __DIR__ . '/logo.svg' ),
	'{{SCISSOR_INNER}}'  => svg_inner( $root . '/images/categories/scissor-lift-tables.svg' ),
	'{{CATEGORY_SHEET}}' => implode( "\n\t\t\t\t", $sheet ),
);

$html = strtr( $html, $replacements );

if ( preg_match( '/\{\{[A-Z_]+\}\}/', $html, $left ) ) {
	fwrite( STDERR, "Unfilled placeholder: {$left[0]}\n" );
	exit( 1 );
}

file_put_contents( __DIR__ . '/brand-guide.html', $html );

echo 'wrote brand/brand-guide.html (' . count( $sheet ) . " illustrations)\n";
