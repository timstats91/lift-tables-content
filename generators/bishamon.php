<?php
/**
 * Generate the Bishamon catalog from specs captured off bishamon.com.
 * Rows: [model, capacity_lbs, platform, lowered_in, raised_in, notes]
 */

$out  = dirname( __DIR__ ) . '/catalog/bishamon.json';
$site = 'https://bishamon.com';

function rows( $rows ) {
	return array_map(
		static function ( $r ) {
			$row = array( 'model' => $r[0], 'capacity_lbs' => $r[1] );
			if ( '' !== $r[2] ) { $row['platform'] = $r[2]; }
			if ( null !== $r[3] ) { $row['lowered_height_in'] = $r[3]; }
			if ( null !== $r[4] ) { $row['raised_height_in'] = $r[4]; }
			if ( ! empty( $r[5] ) ) { $row['notes'] = $r[5]; }
			return $row;
		},
		$rows
	);
}

$lines = array(
	array(
		'slug'           => 'bishamon-alpha-series',
		'title'          => 'Bishamon ALPHA Series Lift Tables',
		'excerpt'        => 'Stationary electric-hydraulic scissor lift tables from Bishamon in 2,200, 4,400 and 6,600 lb capacities, with fixed or rotating tops.',
		'content'        => "<p>The ALPHA Series is Bishamon's range of heavy-duty stationary scissor lift tables, powered by a 115V electric-hydraulic unit. It comes in three capacity classes, the L22K (2,200 lb), L44K (4,400 lb) and L66K (6,600 lb), each offered with a fixed rectangular platform or a rotating top in round or square sizes.</p>\n<p>Bishamon offers the ALPHA with modular attachments including hand or foot controls, a safety pin, bellows skirting and a cart base.</p>",
		'line_code'      => 'ALPHA',
		'capacity_range' => '2,200–6,600 lbs',
		'key_features'   => array( '115V electric-hydraulic power', 'Fixed or rotating platform tops', 'Three capacity classes: L22K, L44K, L66K', 'Hand or foot control, bellows and cart options' ),
		'categories'     => array( 'Lift Tables > Scissor Lift Tables', 'Lift Tables > Electric Lift Tables', 'Lift Tables > Hydraulic Lift Tables', 'Lift Tables > Rotating Lift Tables' ),
		'sources'        => array( $site . '/alpha/' ),
	),
	array(
		'slug'           => 'bishamon-ez-up-series',
		'title'          => 'Bishamon EZ UP Pneumatic Lift Tables',
		'excerpt'        => 'Air-powered scissor lift tables from Bishamon, rated up to 1,700 lb on shop air, with rectangular or round platforms.',
		'content'        => "<p>EZ UP tables are pneumatic scissor lift tables that run on shop air, for facilities that want dependable load positioning without electricity. Capacity depends on supply pressure: 1,500 lb at 90 psi and 1,700 lb at 100 psi.</p>\n<p>The EZU-15 has a 28 x 48 in. platform, and the EZU-15-R has a 43 in. round platform. Both give 20 in. of travel.</p>",
		'line_code'      => 'EZU',
		'capacity_range' => '1,500–1,700 lbs',
		'key_features'   => array( 'Powered by shop air, no electricity required', '1,500 lb at 90 psi, 1,700 lb at 100 psi', 'Rectangular or 43 in. round platform', '20 in. of travel' ),
		'categories'     => array( 'Lift Tables > Pneumatic Lift Tables', 'Lift Tables > Scissor Lift Tables', 'Lift Tables > Pallet Lift Tables' ),
		'sources'        => array( $site . '/ezup-en/' ),
	),
	array(
		'slug'           => 'bishamon-loprofile-lx-series',
		'title'          => 'Bishamon LoProfile LX Series Lift Tables',
		'excerpt'        => 'Electric-hydraulic low profile scissor lift tables that lower to 2.9 to 4.3 in., rated 550 to 4,400 lb.',
		'content'        => "<p>LoProfile LX tables are electric-hydraulic scissor lift tables built to fold down very low, with lowered platform heights from 2.9 to 4.3 in. That lets pallets, carts and materials be loaded without a pit or a permanent ramp.</p>\n<p>The series runs from the 550 lb LX-25 to the 4,400 lb LX-200, with platforms up to 45.5 x 81 in. and raised heights up to 39.4 in.</p>",
		'line_code'      => 'LX',
		'capacity_range' => '550–4,400 lbs',
		'key_features'   => array( 'Lowered heights from 2.9 to 4.3 in.', 'Electric-hydraulic power', 'Load pallets and carts without a pit', 'Capacities from 550 to 4,400 lb' ),
		'categories'     => array( 'Lift Tables > Low Profile Lift Tables', 'Lift Tables > Scissor Lift Tables', 'Lift Tables > Electric Lift Tables', 'Lift Tables > Hydraulic Lift Tables', 'Lift Tables > Pallet Lift Tables' ),
		'sources'        => array( $site . '/loprofile-en/' ),
	),
	array(
		'slug'           => 'bishamon-compaclift-x-series',
		'title'          => 'Bishamon CompacLift X Series Lift Tables',
		'excerpt'        => 'Compact scissor lift tables from Bishamon in manual, electric and air-hydraulic versions, 440 to 1,650 lb.',
		'content'        => "<p>CompacLift X Series tables are small-footprint scissor lift tables for light to medium duty work at production lines, assembly areas and workstations where space is tight. They come in four table sizes with single or double scissors, and capacities from 440 to 1,650 lb.</p>\n<p>Each size is offered in three versions, identified by the last letter of the model number: C for a manual hydraulic foot pump, E for electro-hydraulic with a hand control, and P for air-hydraulic with a foot pedal. An S in the model number marks a double scissor table.</p>",
		'line_code'      => 'X',
		'capacity_range' => '440–1,650 lbs',
		'key_features'   => array( 'Manual (C), electric (E) or air-hydraulic (P) operation', 'Single and double scissor models', 'Compact footprint for tight workstations', 'Baked enamel finish' ),
		'categories'     => array( 'Lift Tables > Scissor Lift Tables', 'Lift Tables > Hydraulic Lift Tables', 'Lift Tables > Double Scissor Lift Tables' ),
		'sources'        => array( $site . '/compaclift-en/' ),
	),
	array(
		'slug'           => 'bishamon-mobilift-series',
		'title'          => 'Bishamon MobiLift BX & BXB Mobile Lift Tables',
		'excerpt'        => 'Mobile scissor lift tables from Bishamon with manual foot pump or battery power, including double scissor and stainless steel models.',
		'content'        => "<p>MobiLift tables are portable scissor lift tables for raising, moving and positioning materials between work areas. BX models lift with a foot lever pump, while BXB models are battery powered with a removable push-button pendant and a battery compartment with charger.</p>\n<p>The range covers 220 to 1,760 lb, and includes double scissor models that raise the platform above 60 in. and stainless steel BXS models.</p>",
		'line_code'      => 'BX / BXB / BXS',
		'capacity_range' => '220–1,760 lbs',
		'key_features'   => array( 'Manual foot pump or battery push-button operation', 'Double scissor models reaching over 60 in.', 'Stainless steel models available', 'Captured scissor rollers, push handle and premium casters' ),
		'categories'     => array( 'Lift Tables > Mobile Lift Tables', 'Lift Tables > Scissor Lift Tables', 'Lift Tables > Hydraulic Lift Tables' ),
		'sources'        => array( $site . '/mobilift-en/' ),
	),
	array(
		'slug'           => 'bishamon-ez-loader',
		'title'          => 'Bishamon EZ Loader Pallet Positioners',
		'excerpt'        => 'Self-leveling pallet positioner lift tables that raise the load automatically as material is removed, rated 4,000 lb.',
		'content'        => "<p>The EZ Loader is a self-leveling pallet lift table: as material is added or removed, the platform moves to keep the top of the load at a comfortable working height, without controls. Unlike spring-based pallet positioners it uses a captive-air system for smooth platform movement.</p>\n<p>It is rated for 4,000 lb, self-levels loads from 250 to 3,500 lb over 20 in. of travel, and comes with a rotator ring or a fixed rectangular platform.</p>",
		'line_code'      => 'EZ Loader',
		'capacity_range' => '4,000 lbs',
		'key_features'   => array( 'Self-leveling as the load changes, no controls', 'Captive-air system instead of springs', 'Rotator ring or fixed platform versions', '20 in. of travel' ),
		'categories'     => array( 'Lift Tables > Pallet Lift Tables', 'Lift Tables > Pneumatic Lift Tables', 'Lift Tables > Rotating Lift Tables' ),
		'sources'        => array( $site . '/ez-loader-en/' ),
	),
	array(
		'slug'           => 'bishamon-ez-off-lifter',
		'title'          => 'Bishamon EZ-Off Lifter',
		'excerpt'        => 'Low profile rotating pallet lift table that a pallet truck can load directly, with a feet-clear safety stop.',
		'content'        => "<p>The EZ-Off Lifter is a low profile, electric-hydraulic pallet lift table with a rotating platform that lowers to 1.75 in., low enough to be loaded and unloaded with a pallet truck. Its 40.5 in. rotating platform turns on 32 ball bearings so the operator can bring any side of the pallet to the front.</p>\n<p>A patented feet-clear safety circuit stops the platform 9 in. above the floor, with warning lights and an audible alarm before it continues lowering.</p>",
		'line_code'      => 'EZO',
		'capacity_range' => '2,500 lbs',
		'key_features'   => array( 'Lowers to 1.75 in. for pallet truck loading', '40.5 in. rotating platform on 32 ball bearings', 'Feet-clear safety stop at 9 in.', 'Warning lights and audible alarm' ),
		'categories'     => array( 'Lift Tables > Pallet Lift Tables', 'Lift Tables > Low Profile Lift Tables', 'Lift Tables > Rotating Lift Tables', 'Lift Tables > Electric Lift Tables', 'Lift Tables > Hydraulic Lift Tables' ),
		'sources'        => array( $site . '/ezoff-en/' ),
	),
	array(
		'slug'           => 'bishamon-mobileveler-esx',
		'title'          => 'Bishamon MobiLeveler ESX Self-Leveling Carts',
		'excerpt'        => 'Mobile spring-powered lift tables that keep a load at working height automatically as items are added or removed.',
		'content'        => "<p>The MobiLeveler ESX is a mobile, self-leveling lift table: springs raise and lower the platform automatically as items are added or removed, keeping the load at a comfortable working height with no power or controls.</p>\n<p>Single- and dual-spring models are available, and a gauged weight selector knob adjusts spring tension to match the load.</p>",
		'line_code'      => 'ESX',
		'capacity_range' => 'Self-levels 66–880 lbs',
		'key_features'   => array( 'Spring-powered self-leveling, no power required', 'Gauged weight selector knob sets spring tension', 'Single- and dual-spring models', 'Mobile on casters' ),
		'categories'     => array( 'Lift Tables > Mobile Lift Tables' ),
		'sources'        => array( $site . '/mobileveleresx-en/' ),
	),
);

$products = array(
	// ALPHA.
	array(
		'slug' => 'bishamon-alpha-l22k', 'title' => 'Bishamon ALPHA L22K Electric Scissor Lift Table', 'line' => 'bishamon-alpha-series', 'menu_order' => 10,
		'power_source' => 'electric', 'voltage' => '115 VAC',
		'excerpt' => '2,200 lb stationary electric-hydraulic scissor lift table with fixed or rotating tops.',
		'content' => "<p>The L22K is the 2,200 lb model in Bishamon's ALPHA Series of stationary electric-hydraulic scissor lift tables. It is offered with a fixed 31 x 53 in. or 40 x 53 in. platform, or with a rotating top in 43 in. round, 44 x 44, 44 x 48 or 48 x 48 in. sizes.</p>\n<p>Fixed-platform models lower to about 8.5 in. and raise to about 40 in.; rotating-top models lower to 11.5 in. and raise to 41.5 in. At full capacity the table raises in about 22.5 seconds and lowers in about 11.</p>",
		'models' => rows( array(
			array( 'L22K-3153', 2200, '31 x 53 in', 8.75, 40, '' ),
			array( 'L22K-4053', 2200, '40 x 53 in', 8.5, 39.5, '' ),
			array( 'L22K-TT', 2200, '43 in dia. rotating', 11.5, 41.5, 'Rotating top' ),
			array( 'L22K-TT4444', 2200, '44 x 44 in rotating', 11.5, 41.5, 'Rotating top' ),
			array( 'L22K-TT4448', 2200, '44 x 48 in rotating', 11.5, 41.5, 'Rotating top' ),
			array( 'L22K-TT4848', 2200, '48 x 48 in rotating', 11.5, 41.5, 'Rotating top' ),
		) ),
		'specs' => array( array( 'Voltage', '115 VAC' ), array( 'Raise time at full capacity', 'About 22.5 sec' ), array( 'Lower time at full capacity', 'About 11 sec' ) ),
		'categories' => array( 'Lift Tables > Scissor Lift Tables', 'Lift Tables > Electric Lift Tables', 'Lift Tables > Hydraulic Lift Tables', 'Lift Tables > Rotating Lift Tables' ),
		'sources' => array( $site . '/alpha/' ),
	),
	array(
		'slug' => 'bishamon-alpha-l44k', 'title' => 'Bishamon ALPHA L44K Electric Scissor Lift Table', 'line' => 'bishamon-alpha-series', 'menu_order' => 20,
		'power_source' => 'electric', 'voltage' => '115 VAC',
		'excerpt' => '4,400 lb stationary electric-hydraulic scissor lift table with fixed or rotating tops.',
		'content' => "<p>The L44K is the 4,400 lb model in Bishamon's ALPHA Series. It comes with a fixed 40 x 52 in. platform that lowers to 9 in. and raises to 40.75 in., or with a rotating top in 43 in. round, 44 x 44, 44 x 48 or 48 x 48 in. sizes that lowers to 11.5 in. and raises to 41.5 in.</p>\n<p>At full capacity the table raises in about 33 seconds and lowers in about 16, from a 115V electric-hydraulic power unit.</p>",
		'models' => rows( array(
			array( 'L44K-4052', 4400, '40 x 52 in', 9, 40.75, '' ),
			array( 'L44K-TT', 4400, '43 in dia. rotating', 11.5, 41.5, 'Rotating top' ),
			array( 'L44K-TT4444', 4400, '44 x 44 in rotating', 11.5, 41.5, 'Rotating top' ),
			array( 'L44K-TT4448', 4400, '44 x 48 in rotating', 11.5, 41.5, 'Rotating top' ),
			array( 'L44K-TT4848', 4400, '48 x 48 in rotating', 11.5, 41.5, 'Rotating top' ),
		) ),
		'specs' => array( array( 'Voltage', '115 VAC' ), array( 'Raise time at full capacity', 'About 33 sec' ), array( 'Lower time at full capacity', 'About 16 sec' ) ),
		'categories' => array( 'Lift Tables > Scissor Lift Tables', 'Lift Tables > Electric Lift Tables', 'Lift Tables > Hydraulic Lift Tables', 'Lift Tables > Rotating Lift Tables' ),
		'sources' => array( $site . '/alpha/' ),
	),
	array(
		'slug' => 'bishamon-alpha-l66k', 'title' => 'Bishamon ALPHA L66K Heavy Duty Scissor Lift Table', 'line' => 'bishamon-alpha-series', 'menu_order' => 30,
		'power_source' => 'electric', 'voltage' => '115 VAC',
		'excerpt' => '6,600 lb heavy duty electric-hydraulic scissor lift table with up to 49 in. of travel.',
		'content' => "<p>The L66K is the highest-capacity table in Bishamon's ALPHA Series, rated for 6,600 lb. Fixed-platform models offer the most travel in the range: the L66K-4059 raises from 9.5 in. to 50 in., and the L66K-4078 from 11 in. to 60 in. on a 40 x 78 in. platform.</p>\n<p>Rotating-top versions lower to 12.5 in. and raise to 51 in. At full capacity the table raises in about 58 seconds and lowers in about 27.</p>",
		'models' => rows( array(
			array( 'L66K-4059', 6600, '40 x 59 in', 9.5, 50, '' ),
			array( 'L66K-4078', 6600, '40 x 78 in', 11, 60, '' ),
			array( 'L66K-TT', 6600, '43 in dia. rotating', 12.5, 51, 'Rotating top' ),
			array( 'L66K-TT4444', 6600, '', 12.5, 51, 'Rotating top' ),
			array( 'L66K-TT4448', 6600, '', 12.5, 51, 'Rotating top' ),
			array( 'L66K-TT4848', 6600, '48 x 48 in rotating', 12.5, 51, 'Rotating top' ),
		) ),
		'specs' => array( array( 'Voltage', '115 VAC' ), array( 'Raise time at full capacity', 'About 58 sec' ), array( 'Lower time at full capacity', 'About 27 sec' ) ),
		'categories' => array( 'Lift Tables > Heavy Duty Lift Tables', 'Lift Tables > Scissor Lift Tables', 'Lift Tables > Electric Lift Tables', 'Lift Tables > Hydraulic Lift Tables', 'Lift Tables > Rotating Lift Tables' ),
		'sources' => array( $site . '/alpha/' ),
		'notes' => 'Bishamon lists L66K-TT4444 as 44 x 48 and L66K-TT4448 as 48 x 48, which contradicts the model code convention used for L22K and L44K. Platform left blank for those two; verify with Bishamon.',
	),
	// EZ UP.
	array(
		'slug' => 'bishamon-ez-up-ezu-15', 'title' => 'Bishamon EZ UP EZU-15 Pneumatic Scissor Lift Table', 'line' => 'bishamon-ez-up-series', 'menu_order' => 40,
		'power_source' => 'pneumatic',
		'excerpt' => 'Air-powered scissor lift table rated 1,500 lb at 90 psi, with rectangular or round platform.',
		'content' => "<p>The EZU-15 is Bishamon's pneumatic scissor lift table, raised by shop air with no electrical supply. It is rated for 1,500 lb at 90 psi and 1,700 lb at 100 psi, and gives 20 in. of travel.</p>\n<p>The standard EZU-15 has a 28 x 48 in. platform that lowers to 9.6 in. The EZU-15-R and EZU-15-R-SS have a 43 in. round platform that lowers to 10.6 in.</p>",
		'models' => rows( array(
			array( 'EZU-15', 1700, '28 x 48 in', 9.6, 29.6, '1,500 lb at 90 psi' ),
			array( 'EZU-15-R', 1700, '43 in dia. round', 10.6, 30.6, '1,500 lb at 90 psi' ),
			array( 'EZU-15-R-SS', 1700, '43 in dia. round', 10.6, 30.6, '1,500 lb at 90 psi' ),
		) ),
		'specs' => array( array( 'Capacity at 90 psi', '1,500 lbs' ), array( 'Capacity at 100 psi', '1,700 lbs' ), array( 'Travel', '20 in' ) ),
		'categories' => array( 'Lift Tables > Pneumatic Lift Tables', 'Lift Tables > Scissor Lift Tables', 'Lift Tables > Pallet Lift Tables' ),
		'sources' => array( $site . '/ezup-en/' ),
	),
	// LoProfile.
	array(
		'slug' => 'bishamon-loprofile-lx', 'title' => 'Bishamon LoProfile LX Low Profile Scissor Lift Table', 'line' => 'bishamon-loprofile-lx-series', 'menu_order' => 50,
		'power_source' => 'electric',
		'excerpt' => 'Electric-hydraulic low profile lift table lowering to 2.9 to 4.3 in., rated 550 to 4,400 lb.',
		'content' => "<p>LoProfile LX tables fold down to between 2.9 and 4.3 in., so pallets and carts can be loaded without a pit or permanent ramp. All are electric-hydraulic.</p>\n<p>The LX-25 and LX-50 carry 550 and 1,100 lb on 23.5 in. wide platforms, in short (S) and long (L) versions with 18 or 27.1 in. of travel. The LX-100 carries 2,200 lb and the LX-200 4,400 lb, both with about 35 in. of travel, in narrow (N) and wide (W) platform versions up to 45.5 x 81 in.</p>",
		'models' => rows( array(
			array( 'LX-25S', 550, '23.5 x 32.5 in', 2.9, 20.9, '' ),
			array( 'LX-25L', 550, '23.5 x 40 in', 2.9, 30, '' ),
			array( 'LX-50S', 1100, '23.5 x 32.5 in', 2.9, 20.9, '' ),
			array( 'LX-50L', 1100, '23.5 x 40 in', 2.9, 30, '' ),
			array( 'LX-100W', 2200, '34.5 x 51 in', 3.3, 38.6, '' ),
			array( 'LX-100N', 2200, '24.5 x 51 in', 3.3, 38.6, '' ),
			array( 'LX-200WM', 4400, '45.5 x 61.5 in', 4.3, 39.4, '' ),
			array( 'LX-200N', 4400, '33.5 x 55.5 in', 4.3, 39.4, '' ),
			array( 'LX-200WL', 4400, '45.5 x 81 in', 4.3, 39.4, '' ),
		) ),
		'specs' => array( array( 'Power unit', '1/2 HP 115V (LX-25, LX-50); 1 HP (LX-100, LX-200)' ) ),
		'categories' => array( 'Lift Tables > Low Profile Lift Tables', 'Lift Tables > Scissor Lift Tables', 'Lift Tables > Electric Lift Tables', 'Lift Tables > Hydraulic Lift Tables', 'Lift Tables > Pallet Lift Tables' ),
		'sources' => array( $site . '/loprofile-en/' ),
	),
	// CompacLift.
	array(
		'slug' => 'bishamon-compaclift-x-manual', 'title' => 'Bishamon CompacLift X Manual Scissor Lift Table', 'line' => 'bishamon-compaclift-x-series', 'menu_order' => 60,
		'power_source' => 'manual',
		'excerpt' => 'Compact foot-pump scissor lift tables rated 440 to 1,650 lb, including a double scissor model.',
		'content' => "<p>The C models in Bishamon's CompacLift X Series are compact scissor lift tables raised with a removable foot pump, so they need no power supply. Capacities run from 440 to 1,650 lb, with platforms from 19.5 x 25.5 in. to 20 x 40 in.</p>\n<p>The X-30SC is a double scissor table that raises from 9.25 in. to 54.25 in. The pump strokes needed to reach full height range from 28 on the X-20C to 67 on the X-75C.</p>",
		'models' => rows( array(
			array( 'X-20C', 440, '19.5 x 25.5 in', 5, 21, '28 pumps to full height' ),
			array( 'X-25C', 550, '19.5 x 32 in', 5, 28, '42 pumps to full height' ),
			array( 'X-50C', 1100, '20 x 40 in', 6, 31.5, '46 pumps to full height' ),
			array( 'X-75C', 1650, '20 x 40 in', 6, 31.5, '67 pumps to full height' ),
			array( 'X-30SC', 660, '20 x 40 in', 9.25, 54.25, 'Double scissor; 54 pumps' ),
		) ),
		'specs' => array( array( 'Operation', 'Manual hydraulic foot pump' ) ),
		'categories' => array( 'Lift Tables > Scissor Lift Tables', 'Lift Tables > Manual Lift Tables', 'Lift Tables > Hydraulic Lift Tables', 'Lift Tables > Double Scissor Lift Tables' ),
		'sources' => array( $site . '/compaclift-en/' ),
	),
	array(
		'slug' => 'bishamon-compaclift-x-electric', 'title' => 'Bishamon CompacLift X Electric Scissor Lift Table', 'line' => 'bishamon-compaclift-x-series', 'menu_order' => 70,
		'power_source' => 'electric',
		'excerpt' => 'Compact electro-hydraulic scissor lift tables with hand control, rated 550 to 1,650 lb, including double scissor models.',
		'content' => "<p>The E models in the CompacLift X Series are compact electro-hydraulic scissor lift tables operated by push button from a hand control. Capacities run from 550 to 1,650 lb, and the X-75E raises a full load in about 17 seconds.</p>\n<p>Two double scissor models are included: the X-30SE, which raises from 9.25 in. to 54.25 in. on a 20 x 40 in. platform, and the X-50SE, a 1,100 lb table with a smaller 18 x 25.5 in. platform.</p>",
		'models' => rows( array(
			array( 'X-25E', 550, '19.5 x 32 in', 6, 28, '' ),
			array( 'X-50E', 1100, '20 x 40 in', 6, 31.5, '' ),
			array( 'X-75E', 1650, '20 x 40 in', 6, 31.5, '' ),
			array( 'X-30SE', 660, '20 x 40 in', 9.25, 54.25, 'Double scissor' ),
			array( 'X-50SE', 1100, '18 x 25.5 in', 8.25, 33.75, 'Double scissor' ),
		) ),
		'specs' => array( array( 'Operation', 'Electro-hydraulic, push-button hand control' ) ),
		'categories' => array( 'Lift Tables > Scissor Lift Tables', 'Lift Tables > Electric Lift Tables', 'Lift Tables > Hydraulic Lift Tables', 'Lift Tables > Double Scissor Lift Tables' ),
		'sources' => array( $site . '/compaclift-en/' ),
	),
	array(
		'slug' => 'bishamon-compaclift-x-air', 'title' => 'Bishamon CompacLift X Air-Hydraulic Scissor Lift Table', 'line' => 'bishamon-compaclift-x-series', 'menu_order' => 80,
		'power_source' => 'pneumatic',
		'excerpt' => 'Compact air-hydraulic scissor lift tables with foot pedal control, rated 550 to 1,650 lb, including double scissor models.',
		'content' => "<p>The P models in the CompacLift X Series are compact air-hydraulic scissor lift tables controlled with a foot pedal, for workstations with shop air but no convenient electrical supply. Capacities run from 550 to 1,650 lb.</p>\n<p>The range matches the electric version, including the X-30SP double scissor table that raises to 54.25 in. and the compact 1,100 lb X-50SP.</p>",
		'models' => rows( array(
			array( 'X-25P', 550, '19.5 x 32 in', 6, 28, '' ),
			array( 'X-50P', 1100, '20 x 40 in', 6, 31.5, '' ),
			array( 'X-75P', 1650, '20 x 40 in', 6, 31.5, '' ),
			array( 'X-30SP', 660, '20 x 40 in', 9.25, 54.25, 'Double scissor' ),
			array( 'X-50SP', 1100, '18 x 25.5 in', 8.25, 33.75, 'Double scissor' ),
		) ),
		'specs' => array( array( 'Operation', 'Air-hydraulic, foot pedal' ) ),
		'categories' => array( 'Lift Tables > Scissor Lift Tables', 'Lift Tables > Pneumatic Lift Tables', 'Lift Tables > Hydraulic Lift Tables', 'Lift Tables > Double Scissor Lift Tables' ),
		'sources' => array( $site . '/compaclift-en/' ),
	),
	// MobiLift.
	array(
		'slug' => 'bishamon-mobilift-bx', 'title' => 'Bishamon MobiLift BX Mobile Scissor Lift Table', 'line' => 'bishamon-mobilift-series', 'menu_order' => 90,
		'power_source' => 'manual',
		'excerpt' => 'Foot-pump mobile scissor lift tables rated 330 to 1,760 lb.',
		'content' => "<p>MobiLift BX tables are mobile scissor lift tables raised with a foot lever pump and lowered with a hand lever, rolling on premium casters with a push handle. Capacities run from 330 lb on the BX-15 to 1,760 lb on the BX-75.</p>\n<p>The BX-15 lowers to 8.4 in. and raises to 28.8 in. on a 17.7 x 27.6 in. platform, while the BX-50 and BX-75 share a 20.4 x 39.8 in. platform that raises from 17.2 in. to 39.3 in.</p>",
		'models' => rows( array(
			array( 'BX-15', 330, '17.7 x 27.6 in', 8.4, 28.8, '15 pumps to full height' ),
			array( 'BX-25', 660, '19.7 x 32 in', 11.3, 34.1, '25 pumps to full height' ),
			array( 'BX-50', 1100, '20.4 x 39.8 in', 17.2, 39.3, '50 pumps to full height' ),
			array( 'BX-75', 1760, '20.4 x 39.8 in', 17.2, 39.3, '67 pumps to full height' ),
		) ),
		'specs' => array( array( 'Operation', 'Foot lever pump, hand lever lowering' ) ),
		'categories' => array( 'Lift Tables > Mobile Lift Tables', 'Lift Tables > Scissor Lift Tables', 'Lift Tables > Manual Lift Tables', 'Lift Tables > Hydraulic Lift Tables' ),
		'sources' => array( $site . '/mobilift-en/' ),
	),
	array(
		'slug' => 'bishamon-mobilift-bx-double-scissor', 'title' => 'Bishamon MobiLift BX Double Scissor Mobile Lift Table', 'line' => 'bishamon-mobilift-series', 'menu_order' => 100,
		'power_source' => 'manual',
		'excerpt' => 'Foot-pump double scissor mobile lift tables reaching 62.1 in., rated 660 to 1,100 lb.',
		'content' => "<p>The double scissor MobiLift BX models stack two scissor mechanisms for extra height from a mobile base. The BX-30S and BX-50W raise a 20.4 x 39.8 in. platform from 17 in. to 62.1 in., carrying 660 and 1,100 lb.</p>\n<p>The BX-50S is a more compact 1,100 lb double scissor table with an 18.1 x 25.6 in. platform that raises from 16.5 in. to 39.5 in. All are raised with a foot pump.</p>",
		'models' => rows( array(
			array( 'BX-30S', 660, '20.4 x 39.8 in', 17, 62.1, '64 pumps to full height' ),
			array( 'BX-50S', 1100, '18.1 x 25.6 in', 16.5, 39.5, '66 pumps to full height' ),
			array( 'BX-50W', 1100, '20.4 x 39.8 in', 17, 62.1, '90 pumps to full height' ),
		) ),
		'specs' => array( array( 'Operation', 'Foot lever pump, hand lever lowering' ), array( 'Scissor type', 'Double' ) ),
		'categories' => array( 'Lift Tables > Double Scissor Lift Tables', 'Lift Tables > Mobile Lift Tables', 'Lift Tables > Scissor Lift Tables', 'Lift Tables > Manual Lift Tables', 'Lift Tables > Hydraulic Lift Tables' ),
		'sources' => array( $site . '/mobilift-en/' ),
	),
	array(
		'slug' => 'bishamon-mobilift-bxs-stainless', 'title' => 'Bishamon MobiLift BXS Stainless Steel Mobile Lift Table', 'line' => 'bishamon-mobilift-series', 'menu_order' => 110,
		'power_source' => 'manual',
		'excerpt' => 'Stainless steel foot-pump mobile scissor lift tables rated 220 and 440 lb.',
		'content' => "<p>MobiLift BXS tables are the stainless steel versions of Bishamon's mobile scissor lift tables, for environments where painted steel is not suitable. They are raised with a foot pump and rated for 220 and 440 lb.</p>\n<p>The BXS-10 raises from 8.4 in. to 28.8 in. on a 17.7 x 27.6 in. platform, and the BXS-20 from 11.3 in. to 34.1 in. on a 19.7 x 32 in. platform.</p>",
		'models' => rows( array(
			array( 'BXS-10', 220, '17.7 x 27.6 in', 8.4, 28.8, '15 pumps to full height' ),
			array( 'BXS-20', 440, '19.7 x 32 in', 11.3, 34.1, '25 pumps to full height' ),
		) ),
		'specs' => array( array( 'Construction', 'Stainless steel' ), array( 'Operation', 'Foot lever pump, hand lever lowering' ) ),
		'categories' => array( 'Lift Tables > Stainless Steel Lift Tables', 'Lift Tables > Mobile Lift Tables', 'Lift Tables > Scissor Lift Tables', 'Lift Tables > Manual Lift Tables', 'Lift Tables > Hydraulic Lift Tables' ),
		'sources' => array( $site . '/mobilift-en/' ),
	),
	array(
		'slug' => 'bishamon-mobilift-bxb-battery', 'title' => 'Bishamon MobiLift BXB Battery Mobile Scissor Lift Table', 'line' => 'bishamon-mobilift-series', 'menu_order' => 120,
		'power_source' => 'battery',
		'excerpt' => 'Battery-powered push-button mobile scissor lift tables rated 660 to 1,760 lb.',
		'content' => "<p>MobiLift BXB tables add battery power to Bishamon's mobile scissor lift tables, raising the platform with a removable push-button pendant instead of a foot pump. A battery compartment with charger is built into the base.</p>\n<p>Capacities run from 660 lb on the BX-30B to 1,760 lb on the BX-80B, and each model reaches full height in 10 to 12 seconds.</p>",
		'models' => rows( array(
			array( 'BX-30B', 660, '19.7 x 32.1 in', 11.5, 35, 'About 10 sec to full height' ),
			array( 'BX-50B', 1100, '20.5 x 39.8 in', 17.2, 40.4, 'About 10 sec to full height' ),
			array( 'BX-80B', 1760, '20.5 x 39.8 in', 17.2, 38.4, 'About 12 sec to full height' ),
		) ),
		'specs' => array( array( 'Operation', 'Battery, removable push-button pendant' ), array( 'Charger', 'Built into battery compartment' ) ),
		'categories' => array( 'Lift Tables > Mobile Lift Tables', 'Lift Tables > Scissor Lift Tables', 'Lift Tables > Battery Powered Lift Tables', 'Lift Tables > Hydraulic Lift Tables' ),
		'sources' => array( $site . '/mobilift-en/' ),
	),
	array(
		'slug' => 'bishamon-mobilift-bxb-double-scissor', 'title' => 'Bishamon MobiLift BXB Double Scissor Battery Mobile Lift Table', 'line' => 'bishamon-mobilift-series', 'menu_order' => 130,
		'power_source' => 'battery',
		'excerpt' => 'Battery-powered double scissor mobile lift tables reaching up to 63.8 in., rated 330 to 1,100 lb.',
		'content' => "<p>The double scissor BXB models combine battery push-button lifting with a double scissor mechanism for extra height from a mobile base. The BX-30SB raises from 17.5 in. to 63.8 in., and the BX-50WB carries 1,100 lb to 63.2 in.</p>\n<p>The BX-15WB is a lighter 330 lb model on a 19.7 x 32.1 in. platform that raises from 15.2 in. to 54.1 in.</p>",
		'models' => rows( array(
			array( 'BX-15WB', 330, '19.7 x 32.1 in', 15.2, 54.1, 'About 10 sec to full height' ),
			array( 'BX-30SB', 660, '20.5 x 39.8 in', 17.5, 63.8, 'About 12 sec to full height' ),
			array( 'BX-50WB', 1100, '20.5 x 39.8 in', 17.1, 63.2, 'About 17 sec to full height' ),
		) ),
		'specs' => array( array( 'Operation', 'Battery, removable push-button pendant' ), array( 'Scissor type', 'Double' ) ),
		'categories' => array( 'Lift Tables > Double Scissor Lift Tables', 'Lift Tables > Mobile Lift Tables', 'Lift Tables > Scissor Lift Tables', 'Lift Tables > Battery Powered Lift Tables', 'Lift Tables > Hydraulic Lift Tables' ),
		'sources' => array( $site . '/mobilift-en/' ),
	),
	// EZ Loader.
	array(
		'slug' => 'bishamon-ez-loader-pallet-positioner', 'title' => 'Bishamon EZ Loader Self-Leveling Pallet Positioner Lift Table', 'line' => 'bishamon-ez-loader', 'menu_order' => 140,
		'power_source' => 'pneumatic',
		'excerpt' => 'Self-leveling pallet lift table rated 4,000 lb that keeps the load at working height automatically.',
		'content' => "<p>The EZ Loader is a self-leveling pallet lift table. As material is loaded or unloaded, its captive-air system moves the platform to keep the top of the pallet at a comfortable working height, with no controls to operate. It is rated for 4,000 lb and self-levels loads between 250 and 3,500 lb over 20 in. of travel.</p>\n<p>The base models carry a rotator ring that lowers to 10.5 in. and raises to 30.5 in., so the pallet can be turned rather than reached across. The EZ Loader-2848 and -3648 have fixed 28 x 48 and 36 x 48 in. platforms that lower to 9.6 in.</p>",
		'models' => rows( array(
			array( 'EZ Loader', 4000, 'Rotator ring', 10.5, 30.5, 'Self-levels 250–3,500 lb' ),
			array( 'EZ Loader-E', 4000, 'Rotator ring', 10.5, 30.5, 'Self-levels 250–3,500 lb' ),
			array( 'EZ Loader-SS', 4000, 'Rotator ring', 10.5, 30.5, 'Self-levels 250–3,500 lb' ),
			array( 'EZ Loader-2848', 4000, '28 x 48 in', 9.6, 29.6, 'Self-levels 250–3,500 lb' ),
			array( 'EZ Loader-3648', 4000, '36 x 48 in', 9.6, 29.6, 'Self-levels 250–3,500 lb' ),
		) ),
		'specs' => array( array( 'Leveling', 'Automatic, captive-air system' ), array( 'Self-leveling range', '250–3,500 lbs' ), array( 'Travel', '20 in' ) ),
		'categories' => array( 'Lift Tables > Pallet Lift Tables', 'Lift Tables > Pneumatic Lift Tables', 'Lift Tables > Rotating Lift Tables' ),
		'sources' => array( $site . '/ez-loader-en/' ),
	),
	// EZ-Off.
	array(
		'slug' => 'bishamon-ez-off-lifter', 'title' => 'Bishamon EZ-Off Lifter Low Profile Rotating Pallet Lift Table', 'line' => 'bishamon-ez-off-lifter', 'menu_order' => 150,
		'power_source' => 'electric', 'voltage' => '115V 1PH',
		'excerpt' => '2,500 lb low profile rotating pallet lift table that lowers to 1.75 in. for pallet truck loading.',
		'content' => "<p>The EZ-Off Lifter is an electric-hydraulic pallet lift table that lowers to 1.75 in., so pallets up to 44 x 48 in. can be rolled on and off with a pallet truck. It raises to 30 in. and carries 2,500 lb on a 40.5 in. rotating platform.</p>\n<p>The EZO-25E is free standing with one ramp position, while the EZO-25E-3S anchors to the floor and offers three ramp positions. Both have a feet-clear safety circuit that stops the platform 9 in. above the floor before completing its descent.</p>",
		'models' => rows( array(
			array( 'EZO-25E', 2500, '40.5 in rotating', 1.75, 30, 'Free standing, 1 ramp position' ),
			array( 'EZO-25E-3S', 2500, '40.5 in rotating', 1.75, 30, 'Anchored to floor, 3 ramp positions' ),
		) ),
		'specs' => array( array( 'Voltage', '115V 1PH' ), array( 'Maximum pallet size', '44 x 48 in' ), array( 'Safety', 'Feet-clear stop at 9 in, warning lights and audible alarm' ) ),
		'categories' => array( 'Lift Tables > Pallet Lift Tables', 'Lift Tables > Low Profile Lift Tables', 'Lift Tables > Rotating Lift Tables', 'Lift Tables > Electric Lift Tables', 'Lift Tables > Hydraulic Lift Tables' ),
		'sources' => array( $site . '/ezoff-en/' ),
	),
	// MobiLeveler.
	array(
		'slug' => 'bishamon-mobileveler-esx', 'title' => 'Bishamon MobiLeveler ESX Self-Leveling Mobile Lift Table', 'line' => 'bishamon-mobileveler-esx', 'menu_order' => 160,
		'excerpt' => 'Spring-powered self-leveling mobile lift table for loads from 66 to 880 lb.',
		'content' => "<p>The MobiLeveler ESX is a mobile lift table that levels itself with springs, raising and lowering the platform as items are added or removed so the load stays at a comfortable working height. It needs no power, and a gauged weight selector knob sets spring tension to match the load.</p>\n<p>Three models cover different load ranges: the ESX-10 self-levels 66 to 220 lb, the ESX-21 175 to 460 lb, and the ESX-40 220 to 880 lb on a 20.5 x 39.8 in. platform.</p>",
		'models' => rows( array(
			array( 'ESX-10', 220, '17.8 x 27.6 in', 10.4, 26.2, 'Self-levels 66–220 lb' ),
			array( 'ESX-21', 460, '19.8 x 32.3 in', 13.8, 30.4, 'Self-levels 175–460 lb' ),
			array( 'ESX-40', 880, '20.5 x 39.8 in', 14.5, 31.1, 'Self-levels 220–880 lb' ),
		) ),
		'specs' => array( array( 'Leveling', 'Automatic, spring' ), array( 'Adjustment', 'Gauged weight selector knob' ) ),
		'categories' => array( 'Lift Tables > Mobile Lift Tables' ),
		'sources' => array( $site . '/mobileveleresx-en/' ),
	),
);

$data = array(
	'brand'    => 'bishamon',
	'defaults' => array( 'status' => 'publish' ),
	'product_defaults' => array( 'status' => 'draft' ),
	'lines'    => $lines,
	'products' => $products,
);

file_put_contents( $out, json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
echo count( $lines ) . ' lines, ' . count( $products ) . ' designs, ' . array_sum( array_map( fn( $p ) => count( $p['models'] ), $products ) ) . " models.\n";
