<?php
/**
 * Free distributor listings from manufacturers' own dealer lists, 26 Sep 2026.
 *
 * Evidence standard, unchanged from the earlier imports: a company is listed
 * against a brand only where that pairing is in writing. Here the writing is
 * the manufacturer's own locator, which is the strongest kind -- the brand
 * vouches for the dealer.
 *
 *   A  Advance Lifts   advancelifts.com/find-distributor/ state PDFs (WA, NY, CA, TX, IN, IL)
 *   L  Lift Products   liftproducts.com/dealers.html (locator, swept at 50 US metros)
 *   W  Wesco           wescomfg.com/find-a-dealer/ (locator, 500 mi around 8 metros)
 *   B  Blue Giant      bluegiant.com/en/find-a-dealer/ (all US states; Ergonomics dealers only,
 *                      since that is the Blue Giant line lift tables belong to)
 *   S  Bishamon        bishamon.com/partner-page/ (US partners only)
 *   E  Econo Lift      econolift.net/territory-reps/ (manufacturers' rep firms)
 *
 * Selection, as agreed on 26 Sep 2026: material-handling dealers and rep firms,
 * not overhead-door or dock-only companies; about 30 per brand at most; a
 * company that several manufacturers list gets one listing carrying every one
 * of those brands. Multi-branch companies are placed at their headquarters
 * where confirmed, otherwise at the branch the manufacturer lists.
 *
 * Free tier: visible, never routed a lead. No email or phone is stored, and no
 * individual's name -- only the company, where it is, and what it carries.
 *
 * Existing listings are matched on a normalized company name and have the new
 * brands ADDED to what they already carry; nothing is removed.
 *
 * Run: wp eval-file 2026-09-26-manufacturer-locators.php          (dry run)
 *      wp eval-file 2026-09-26-manufacturer-locators.php apply
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$apply = in_array( 'apply', (array) ( $args ?? array() ), true );

$brand_codes = array(
	'A' => 'advance-lifts',
	'L' => 'lift-products',
	'W' => 'wesco-industrial-products',
	'B' => 'blue-giant',
	'S' => 'bishamon',
	'E' => 'econo-lift',
);

$sources = array(
	'A' => 'Advance Lifts distributor locator',
	'L' => 'Lift Products dealer locator',
	'W' => 'Wesco dealer locator',
	'B' => 'Blue Giant dealer locator (Ergonomics)',
	'S' => 'Bishamon partner page',
	'E' => 'Econo Lift territory reps (manufacturers\' representative)',
);

// Company, city, state, website (host only, or ''), brand codes, note.
$rows = array(
	// --- Listed by several manufacturers ---
	array( 'Motion Industries', 'Birmingham', 'AL', 'motion.com', 'ALW', 'HQ; branches on each locator' ),
	array( 'Indoff Incorporated', 'St. Louis', 'MO', 'indoff.com', 'ALS', 'HQ; Advance lists Frisco TX, Bishamon links Indoff FL' ),
	array( 'A Plus Warehouse Equipment & Supply', 'Lynn', 'MA', 'apluswhs.com', 'LWB', '' ),
	array( 'Northwest Handling Systems', 'Renton', 'WA', '', 'AL', 'also Spokane and Union Gap, WA' ),
	array( 'DACO Corporation', 'Seattle', 'WA', '', 'AL', 'Advance lists Kent, WA' ),
	array( 'Beaton Industrial', 'Utica', 'NY', 'beatonindustrial.com', 'AL', 'Advance factory-certified' ),
	array( 'Bastian Solutions', 'Carmel', 'IN', '', 'AL', 'HQ; many branches' ),
	array( 'DMI North', 'Granger', 'IN', '', 'AL', '' ),
	array( 'Alpha Material Handling', 'Midlothian', 'TX', 'alphamh.com', 'LB', '' ),
	array( 'General Rubber & Plastics', 'Lexington', 'KY', 'generalrubberplastics.com', 'LB', '' ),
	array( 'H&K Equipment', 'Coraopolis', 'PA', 'hkequipment.com', 'LB', 'also Meadville and Bridgeville, PA; Beverly, WV' ),
	array( 'Capital Equipment', 'Hartland', 'WI', '', 'LW', 'also Kaukauna, WI' ),
	array( 'LK Goodwin Co.', 'West Greenwich', 'RI', '', 'LW', 'Wesco lists Providence, RI' ),
	array( 'Riekes Equipment', 'Omaha', 'NE', '', 'LW', 'also West Fargo, ND' ),
	array( 'W.W. Cannon, Inc.', 'Dallas', 'TX', '', 'AW', '' ),
	array( 'Stac Material Handling', 'Jasper', 'IN', '', 'AW', '' ),
	array( 'Thomas Conveyor & Equipment Company', 'Oakbrook Terrace', 'IL', 'tceconveyors.com', 'AB', '' ),
	array( 'Hassel Material Handling', 'Thiensville', 'WI', 'hasselmaterialhandling.com', 'WB', 'Wesco lists Milwaukee, WI' ),
	array( 'Lynch Material Handling', 'Broomfield', 'CO', 'lmhco.com', 'WB', '' ),

	// --- Advance Lifts ---
	array( 'Norlift Inc.', 'Spokane Valley', 'WA', '', 'A', '' ),
	array( 'Arbon Equipment Corporation', 'Milwaukee', 'WI', '', 'A', 'HQ; Advance lists branches in WA, NY, CA and IL' ),
	array( 'Engineered Products', 'Seattle', 'WA', '', 'A', '' ),
	array( 'The Jennings Company', 'Long Island City', 'NY', 'thejenningscompany.com', 'A', '' ),
	array( 'Insley McEntee Equipment', 'Rochester', 'NY', '', 'A', '' ),
	array( 'Buffalo Material Handling', 'Depew', 'NY', 'buffalomaterialshandling.com', 'A', '' ),
	array( 'McKinley Equipment Corporation', 'Irvine', 'CA', '', 'A', '' ),
	array( 'Hankin Specialty Equipment', 'Rancho Cordova', 'CA', '', 'A', '' ),
	array( 'Material Handling Solutions', 'Mission', 'TX', '', 'A', '' ),
	array( 'Medley Material Handling', 'Amarillo', 'TX', '', 'A', '' ),
	array( 'Smock Material Handling Co.', 'Indianapolis', 'IN', '', 'A', '' ),
	array( 'Wolter', 'Goshen', 'IN', '', 'A', '' ),
	array( 'Robert Dietrick Co., Inc.', 'Fishers', 'IN', '', 'A', '' ),
	array( 'B & C Industrial Products', 'Garrett', 'IN', 'bandcip.com', 'A', 'publishes an Advance Lifts page' ),
	array( 'Wiese USA', 'Indianapolis', 'IN', '', 'A', 'also Peru, IL' ),
	array( 'Paul Reilly Co.', 'Glendale Heights', 'IL', '', 'A', '' ),
	array( 'Industrial Kinetics Inc.', 'Downers Grove', 'IL', '', 'A', '' ),
	array( 'Rockford Industrial Equipment', 'Rockford', 'IL', '', 'A', '' ),

	// --- Lift Products ---
	array( 'Applied Industrial Technologies', 'Cleveland', 'OH', '', 'L', 'HQ; 26 branches on the locator' ),
	array( 'Associated Solutions', 'Indianapolis', 'IN', '', 'L', 'also Addison, IL and Eagan, MN' ),
	array( 'Associates Material Handling', 'Denver', 'CO', '', 'L', '' ),
	array( 'California Caster', 'Oakland', 'CA', '', 'L', '' ),
	array( 'Cranston Material Handling', 'McKees Rocks', 'PA', '', 'L', '' ),
	array( 'Custom Handling', 'St. Paul', 'MN', '', 'L', '' ),
	array( 'G & W Equipment', 'Raleigh', 'NC', '', 'L', '' ),
	array( 'GTS Corporation', 'Orlando', 'FL', '', 'L', '' ),
	array( 'Material Handling Technologies', 'Morrisville', 'NC', '', 'L', '' ),
	array( 'Mesa Equipment', 'Albuquerque', 'NM', '', 'L', '' ),
	array( 'Mohler Material Handling', 'St. Louis', 'MO', '', 'L', '' ),
	array( 'Handling Systems, Inc.', 'Nashville', 'TN', '', 'L', 'existing listing; Lift Products lists Lebanon, TN' ),

	// --- Wesco ---
	array( 'F.E. Bennett Co.', 'Portland', 'OR', '', 'W', 'existing listing' ),
	array( 'Advanced Handling Equipment', 'Atlanta', 'GA', '', 'W', '' ),
	array( 'Aloi Materials Handling', 'Rochester', 'NY', '', 'W', '' ),
	array( 'American Material Handling Corp.', 'Raynham', 'MA', '', 'W', '' ),
	array( 'Berry Material Handling', 'Wichita', 'KS', '', 'W', '' ),
	array( 'Bode Equipment Co.', 'Londonderry', 'NH', '', 'W', '' ),
	array( 'Carolina Material Handling', 'Greensboro', 'NC', '', 'W', '' ),
	array( 'CLD Handling Systems', 'Hilliard', 'OH', '', 'W', '' ),
	array( 'East Coast Material Handling Corp.', 'Tatamy', 'PA', '', 'W', '' ),
	array( 'Eastern Lift Truck', 'Maple Shade', 'NJ', '', 'W', 'also PA, DE and MD' ),
	array( 'Hawkeye Material Handling', 'Cedar Rapids', 'IA', '', 'W', '' ),
	array( 'Heubel Material Handling, Inc.', 'Earth City', 'MO', '', 'W', '' ),
	array( 'IBT Inc.', 'Merriam', 'KS', '', 'W', '' ),
	array( 'Lift Inc.', 'Mechanicsburg', 'PA', '', 'W', '' ),
	array( 'Louisiana Lift & Equipment', 'Shreveport', 'LA', '', 'W', '' ),
	array( 'Material Handling Sales', 'Yarmouth', 'ME', '', 'W', '' ),
	array( 'Meyer Material Handling', 'Indianapolis', 'IN', '', 'W', '' ),
	array( 'RMH Systems', 'Bellevue', 'NE', '', 'W', '' ),
	array( 'Wisconsin Lift Truck', 'Brookfield', 'WI', '', 'W', '' ),

	// --- Blue Giant ---
	array( 'A M Industrial, Inc.', 'Middletown', 'CT', 'am-ind.com', 'B', 'also NJ, NH and PA' ),
	array( 'Air Components, Inc.', 'Wayland', 'MI', 'air-componentsinc.com', 'B', '' ),
	array( 'Axiom', 'Pewaukee', 'WI', 'axiomops.com', 'B', '' ),
	array( 'Dynamic Systems', 'Jackson', 'TN', 'dynamicsystemsllc.com', 'B', '' ),
	array( 'Fitzgerald Equipment', 'Rockford', 'IL', 'fitzgeraldequipment.com', 'B', '' ),
	array( 'Material Handling Resources', 'La Vergne', 'TN', 'mhrweb.com', 'B', '' ),
	array( 'Matrix Material Handling', 'Oklahoma City', 'OK', 'matrixok.com', 'B', '' ),
	array( 'Peddeca Material Handling Inc.', 'Woodstock', 'IL', '', 'B', '' ),
	array( 'Regional Material Handling', 'Columbia', 'SC', 'rmhinc.com', 'B', '' ),
	array( 'Rhino Tool House', 'DeKalb', 'IL', 'rhinotoolhouse.com', 'B', 'also IA, MI and WI' ),
	array( 'S&K Industrial', 'Winchester', 'KY', 'sandkindustrial.com', 'B', '' ),
	array( 'Schaefer Service Solutions', 'Louisville', 'KY', 'schaefercompany.com', 'B', '' ),
	array( 'Toyotalift Northeast', 'Cinnaminson', 'NJ', 'toyotaliftne.com', 'B', '' ),

	// --- Bishamon ---
	array( 'Ex-Factory', 'Charlotte', 'NC', 'exfactory.com', 'S', 'HQ; showroom in Zeeland, MI' ),
	array( 'Action Industrial Supply', 'Muskegon', 'MI', 'actionis.com', 'S', '' ),
	array( 'XPack Corporation', 'Guaynabo', 'PR', 'xpackpr.com', 'S', 'publishes a Bishamon page' ),
	array( 'Platforms and Ladders', 'St. Petersburg', 'FL', 'platformsandladders.com', 'S', 'Diverse Supply, Inc.' ),
	array( 'Dakota Storage Products', 'West Fargo', 'ND', 'dakotastorageproducts.com', 'S', '' ),

	// --- Econo Lift (rep firms) ---
	array( 'Brent Tuttle Associates', 'Marietta', 'GA', 'brenttuttleassociates.com', 'E', 'AL, FL, GA, MS, NC, SC, TN' ),
	array( 'Medcraft Sales', '', 'CA', '', 'E', 'AZ, CA, NV, OR, UT; city unconfirmed' ),
	array( 'inRep Corporation', 'Nashua', 'NH', 'inrepcorp.com', 'E', 'New England and Mid-Atlantic' ),
	array( 'Spreda Material Handling Consultants', 'Waupaca', 'WI', 'spredamh.com', 'E', 'IA, northern IL, MN, ND, SD, WI' ),
	array( 'Midwestern Sales Company', 'Plainfield', 'IL', 'midwesternsales.com', 'E', 'southern IL, KS, MO, NE' ),
	array( 'Pro-Rep, Inc.', 'New Philadelphia', 'OH', 'prorepinc.com', 'E', 'IN, KY, MI, OH, PA, WV' ),
);

/** "Handling Systems, Inc." and "Handling Systems Inc" are the same company. */
$norm = static function ( $name ) {
	$n = strtolower( html_entity_decode( $name ) );
	$n = str_replace( '&', ' and ', $n );
	$n = preg_replace( '/[^a-z0-9 ]+/', ' ', $n );
	$n = preg_replace( '/\b(the|inc|incorporated|co|company|corp|corporation|llc|ltd)\b/', ' ', $n );
	return trim( preg_replace( '/\s+/', ' ', $n ) );
};

$brand_ids = array();
foreach ( $brand_codes as $code => $slug ) {
	$post = get_page_by_path( $slug, OBJECT, EDC_Post_Types::BRAND );
	if ( ! $post ) {
		fwrite( STDERR, "Unknown brand slug: $slug\n" );
		return;
	}
	$brand_ids[ $code ] = (int) $post->ID;
}

$existing = array();
foreach ( get_posts( array( 'post_type' => EDC_Post_Types::DISTRIBUTOR, 'post_status' => 'any', 'posts_per_page' => -1 ) ) as $p ) {
	$existing[ $norm( $p->post_title ) ] = $p;
}

$created = 0;
$updated = 0;
$seen    = array();

foreach ( $rows as $row ) {
	list( $name, $city, $state, $site, $codes, $note ) = $row;
	$key = $norm( $name );

	if ( isset( $seen[ $key ] ) ) {
		fwrite( STDERR, "Duplicate row: $name\n" );
		continue;
	}
	$seen[ $key ] = true;

	$ids    = array();
	$source = array();
	foreach ( str_split( $codes ) as $code ) {
		$ids[]    = $brand_ids[ $code ];
		$source[] = $sources[ $code ];
	}
	$source = implode( '; ', $source ) . ( $note ? ' (' . $note . ')' : '' ) . '. Verified 26 Sep 2026.';

	$post  = $existing[ $key ] ?? null;
	$place = trim( $city . ', ' . $state, ', ' );

	printf( "%-7s %-40s %-24s %s\n", $post ? 'update' : 'create', $name, $place, $codes );

	if ( ! $apply ) {
		continue;
	}

	if ( $post ) {
		$post_id = (int) $post->ID;
		$before  = array_map( 'intval', (array) edc_get_field( 'brand_ids', $post_id, array() ) );
		edc_update_field( 'brand_ids', array_values( array_unique( array_merge( $before, $ids ) ) ), $post_id );
		$prior = (string) get_post_meta( $post_id, '_edc_import_source', true );
		update_post_meta( $post_id, '_edc_import_source', trim( $prior . ' | ' . $source, ' |' ) );
		++$updated;
	} else {
		$post_id = wp_insert_post(
			array(
				'post_type'   => EDC_Post_Types::DISTRIBUTOR,
				'post_title'  => $name,
				'post_name'   => sanitize_title( $name ),
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			fwrite( STDERR, "  !! $name: " . $post_id->get_error_message() . "\n" );
			continue;
		}

		edc_update_field( 'listing_tier', 'free', $post_id );
		edc_update_field( 'city', $city, $post_id );
		edc_update_field( 'state', $state, $post_id );
		edc_update_field( 'country', 'United States', $post_id );
		edc_update_field( 'website_url', $site ? 'https://' . $site . '/' : '', $post_id );
		edc_update_field( 'brand_ids', $ids, $post_id );
		edc_update_field( 'public_email', '', $post_id );
		edc_update_field( 'lead_email', '', $post_id );
		update_post_meta( $post_id, '_edc_import_source', $source );
		++$created;
	}

	// Builds the brand index and rank the directory actually reads.
	EDC_Sync::sync_distributor( $post_id );
}

echo str_repeat( '-', 60 ) . "\n";
printf( "%s: %d to create or created, %d to update or updated\n", $apply ? 'APPLIED' : 'DRY RUN', $apply ? $created : count( array_filter( $rows, static function ( $r ) use ( $existing, $norm ) { return ! isset( $existing[ $norm( $r[0] ) ] ); } ) ), $apply ? $updated : count( array_filter( $rows, static function ( $r ) use ( $existing, $norm ) { return isset( $existing[ $norm( $r[0] ) ] ); } ) ) );

if ( $apply ) {
	echo "\nDistributors per brand:\n";
	foreach ( get_posts( array( 'post_type' => EDC_Post_Types::BRAND, 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ) as $b ) {
		$q = new WP_Query(
			array(
				'post_type'      => EDC_Post_Types::DISTRIBUTOR,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( array( 'key' => '_edc_brand_index', 'value' => '|' . $b->ID . '|', 'compare' => 'LIKE' ) ),
			)
		);
		printf( "  %-30s %3d\n", $b->post_title, $q->found_posts );
	}
}
