<?php
/**
 * Generate Lange Lift design products from the specs scraped off langelift.com.
 * Writes products into the lange-lift catalog file and refreshes line categories.
 */

$file = dirname( __DIR__ ) . '/catalog/lange-lift.json';
$data = json_decode( file_get_contents( $file ), true );

$site = 'https://www.langelift.com';

$powers = array(
	'manual'   => array(
		'line'     => 'lange-lift-manual-lift-tables',
		'label'    => 'Manual',
		'suffix'   => '',
		'max'      => 4,
		'power'    => 'manual',
		'voltage'  => '',
		'category' => 'Lift Tables > Manual Lift Tables',
		'url'      => '/manual-hydraulic-lift-tables-square/%d-manual-powered-hydraulic-lift-table/',
		'specs'    => array( array( 'Power', 'Manual foot pump' ), array( 'Controls', 'Foot pedals: lift and lower' ) ),
		'blurb'    => 'A foot pump raises the deck and a second pedal lowers it, so the table needs no power supply and can be used anywhere it can be rolled.',
	),
	'electric' => array(
		'line'     => 'lange-lift-electric-lift-tables',
		'label'    => 'Electric',
		'suffix'   => '-EP',
		'max'      => 5,
		'power'    => 'electric',
		'voltage'  => '120V AC',
		'category' => 'Lift Tables > Electric Lift Tables',
		'url'      => '/electric-powered-hydraulic-lift-tables/%d-electric-powered-hydraulic-lift-tables/',
		'specs'    => array( array( 'Power', '120V AC electric-over-hydraulic motor' ), array( 'Controls', 'Guarded dual foot switch: lift and lower' ) ),
		'blurb'    => 'A 120V AC motor drives the hydraulics from a standard outlet, and guarded dual foot switches raise and lower the deck for quick, repeated cycling.',
	),
	'battery'  => array(
		'line'     => 'lange-lift-battery-lift-tables',
		'label'    => 'Battery',
		'suffix'   => '-BP',
		'max'      => 5,
		'power'    => 'battery',
		'voltage'  => '12V DC',
		'category' => 'Lift Tables > Battery Powered Lift Tables',
		'url'      => '/battery-powered-hydraulic-lift-tables/%d-battery-powered-hydraulic-lift-tables/',
		'specs'    => array( array( 'Power', 'Enclosed 12V deep cycle battery, DC motor, on-board charger' ), array( 'Controls', 'Push-button coil-cord pendant: lift and lower' ) ),
		'blurb'    => 'An enclosed 12V deep cycle battery powers a DC motor, with push-button pendant controls and an on-board charger, so the table lifts at the touch of a button with no cord or hose in use.',
	),
	'air'      => array(
		'line'     => 'lange-lift-air-lift-tables',
		'label'    => 'Air',
		'suffix'   => '-AP',
		'max'      => 5,
		'power'    => 'pneumatic',
		'voltage'  => '',
		'category' => 'Lift Tables > Pneumatic Lift Tables',
		'url'      => '/air-powered-hydraulic-lift-tables/%d-air-powered-hydraulic-lift-tables/',
		'specs'    => array( array( 'Power', 'Air motor, 80 PSI at 50 CFM recommended, on-board filter, regulator and lubricator' ), array( 'Controls', 'Air valve pedal: lift and lower' ) ),
		'blurb'    => 'An air motor drives the hydraulics from shop air, controlled by a foot-operated air valve, with no electrical components, which makes it suitable for spray booths and other areas where electrical equipment is not allowed.',
	),
);

$sizes = array(
	24 => 'the most compact standard deck, suited to smaller parts, dies and fixtures and to tight working areas',
	30 => 'a mid-size deck that balances footprint against working surface',
	36 => 'a larger deck for bigger parts, fixtures and assemblies',
	48 => 'the largest standard deck, for wide parts, larger assemblies and several items at once',
);

$common_specs = array(
	array( 'Vertical travel', '18 in' ),
	array( 'Deck rotation', '360 degrees, continuous, with lock handle at any height' ),
	array( 'Casters', '5 in cast iron, 2 rigid and 2 swivel' ),
	array( 'Floor lock', 'Foot-actuated' ),
	array( 'Made in', 'Menomonee Falls, Wisconsin, USA' ),
);

$products = array();
$order    = 0;

foreach ( $powers as $key => $power ) {
	foreach ( $sizes as $size => $size_text ) {
		$order += 10;

		$models = array();
		for ( $capacity = 1; $capacity <= $power['max']; $capacity++ ) {
			$models[] = array(
				'model'        => sprintf( 'L-%d%d%s', $capacity, $size, $power['suffix'] ),
				'capacity_lbs' => $capacity * 1000,
			);
		}

		$first = $models[0]['model'];
		$last  = end( $models )['model'];
		$range = sprintf( '%s–%s lb', number_format( 1000 ), number_format( $power['max'] * 1000 ) );

		$products[] = array(
			'slug'               => sprintf( 'lange-lift-%d-inch-%s-hydraulic-lift-table', $size, $key ),
			'title'              => sprintf( 'Lange Lift %d-Inch %s Hydraulic Lift Table', $size, $power['label'] ),
			'line'               => $power['line'],
			'menu_order'         => $order,
			'platform_length_in' => $size,
			'platform_width_in'  => $size,
			'lowered_height_in'  => 27,
			'raised_height_in'   => 45,
			'power_source'       => $power['power'],
			'voltage'            => $power['voltage'],
			'excerpt'            => sprintf( 'Mobile %s post lift table with a %d in. square rotating deck, rated %s, models %s to %s.', strtolower( $power['label'] ), $size, $range, $first, $last ),
			'content'            => sprintf(
				"<p>This Lange Lift table has a %d in. square steel deck, %s. It is offered with %s power in %d capacities from 1,000 to %s lb, and every model lifts from 27 in. to 45 in.</p>\n<p>%s The deck rotates a full 360 degrees and locks at any height, and the table rolls on two rigid and two swivel cast iron casters with a foot-operated floor lock. Model numbers encode the configuration: in %s, the first digit is the capacity in thousands of pounds and the next two are the deck size.</p>",
				$size,
				$size_text,
				strtolower( $power['label'] ),
				$power['max'],
				number_format( $power['max'] * 1000 ),
				$power['blurb'],
				$first
			),
			'models'             => $models,
			'specs'              => array_merge(
				array(
					array( 'Deck', sprintf( '%d in square, formed and welded steel', $size ) ),
					array( 'Base', sprintf( '%d in square, formed steel legs, welded steel frame', $size ) ),
				),
				$power['specs'],
				$common_specs
			),
			'categories'         => array(
				'Lift Tables > Post Lift Tables',
				'Lift Tables > Mobile Lift Tables',
				'Lift Tables > Rotating Lift Tables',
				'Lift Tables > Hydraulic Lift Tables',
				$power['category'],
			),
			'sources'            => array( $site . sprintf( $power['url'], $size ) ),
		);
	}
}

// The round manual table: 30 in. round deck on a 24 in. square base.
$products[] = array(
	'slug'               => 'lange-lift-30-inch-round-manual-hydraulic-lift-table',
	'title'              => 'Lange Lift 30-Inch Round Manual Hydraulic Lift Table',
	'line'               => 'lange-lift-manual-lift-tables',
	'menu_order'         => 45,
	'platform_length_in' => 30,
	'platform_width_in'  => 30,
	'lowered_height_in'  => 27,
	'raised_height_in'   => 45,
	'power_source'       => 'manual',
	'excerpt'            => 'Mobile manual post lift table with a 30 in. round rotating deck on a 24 in. base, rated 1,000–2,000 lb.',
	'content'            => "<p>Lange Lift's round-deck manual table puts a 30 in. diameter steel deck on a compact 24 in. square base, giving a larger working surface than the 24 in. square table without a bigger footprint. It is offered in 1,000 and 2,000 lb capacities, and both lift from 27 in. to 45 in.</p>\n<p>A foot pump raises the deck and a second pedal lowers it, so no power supply is needed. The round deck rotates a full 360 degrees and locks at any height, and the table rolls on two rigid and two swivel cast iron casters with a foot-operated floor lock.</p>",
	'models'             => array(
		array( 'model' => 'L-124-30RD', 'capacity_lbs' => 1000 ),
		array( 'model' => 'L-224-30RD', 'capacity_lbs' => 2000 ),
	),
	'specs'              => array_merge(
		array(
			array( 'Deck', '30 in diameter round, formed and welded steel' ),
			array( 'Base', '24 in square, formed steel legs, welded steel frame' ),
		),
		$powers['manual']['specs'],
		$common_specs
	),
	'categories'         => array(
		'Lift Tables > Post Lift Tables',
		'Lift Tables > Mobile Lift Tables',
		'Lift Tables > Rotating Lift Tables',
		'Lift Tables > Hydraulic Lift Tables',
		'Lift Tables > Manual Lift Tables',
	),
	'sources'            => array( $site . '/manual-hydraulic-lift-tables-square/30-round-manual-powered-hydraulic-lift-table/' ),
);

$data['products'] = $products;

// Lines: post lift tables are the primary type, then the shared attributes.
$line_categories = array(
	'lange-lift-manual-lift-tables'   => 'Lift Tables > Manual Lift Tables',
	'lange-lift-electric-lift-tables' => 'Lift Tables > Electric Lift Tables',
	'lange-lift-battery-lift-tables'  => 'Lift Tables > Battery Powered Lift Tables',
	'lange-lift-air-lift-tables'      => 'Lift Tables > Pneumatic Lift Tables',
);

foreach ( $data['lines'] as &$line ) {
	if ( isset( $line_categories[ $line['slug'] ] ) ) {
		$line['categories'] = array(
			'Lift Tables > Post Lift Tables',
			'Lift Tables > Mobile Lift Tables',
			'Lift Tables > Rotating Lift Tables',
			'Lift Tables > Hydraulic Lift Tables',
			$line_categories[ $line['slug'] ],
		);
	}
	if ( 'lange-lift-electric-lift-tables' === $line['slug'] ) {
		$line['line_code'] = 'EP';
	}
	if ( 'lange-lift-battery-lift-tables' === $line['slug'] ) {
		$line['line_code'] = 'BP';
	}
	if ( 'lange-lift-air-lift-tables' === $line['slug'] ) {
		$line['line_code'] = 'AP';
	}
}
unset( $line );

file_put_contents( $file, json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );

$model_count = array_sum( array_map( fn( $p ) => count( $p['models'] ), $products ) );
echo count( $products ) . " designs, {$model_count} models written.\n";
