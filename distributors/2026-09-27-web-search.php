<?php
/**
 * Free distributor listings found by searching the web, 27 Sep 2026.
 *
 * For the brands the manufacturer locators left short of ten, each brand and
 * its model numbers were searched, and a seller was kept only where its own
 * website has a product page for that brand (the URL is in the source note).
 * Marketplaces (Amazon, eBay, Volition), used-equipment dealers, non-US
 * sellers and manufacturers already listed as brands were left out.
 *
 * Same rules as 2026-09-26-manufacturer-locators.php: free tier, no email or
 * phone stored, existing listings gain brands and never lose them.
 *
 * Run: wp eval-file 2026-09-27-web-search.php          (dry run)
 *      wp eval-file 2026-09-27-web-search.php apply
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$apply = in_array( 'apply', (array) ( $args ?? array() ), true );

$brand_codes = array(
	'S' => 'southworth-products',
	'V' => 'vestil',
	'P' => 'presto-lifts',
	'Q' => 'autoquip',
	'E' => 'ecoa',
	'L' => 'lexco',
	'U' => 'superlift',
	'J' => 'jet',
	'C' => 'econo-lift',
	'M' => 'american-lifts',
	'N' => 'lange-lift',
	'R' => 'lift-products',
	'B' => 'bishamon',
);

// Company, city, state, website host, brand codes, evidence.
$rows = array(
	array( 'Ergonomic Partners', 'Bridgeton', 'MO', 'ergonomicpartners.com', 'SB', 'ergonomicpartners.com/store/southworth-ls2-24-backsaver-lift-table-capacity-2000-lbs; /store/bishamon' ),
	array( 'WSI Machinery', 'Wauconda', 'IL', 'wsimachinery.com', 'S', 'wsimachinery.com/products/backsaver-hydraulic-lift-tables' ),
	array( 'Maybury Material Handling', 'East Longmeadow', 'MA', 'maybury.com', 'S', 'maybury.com/product/backsaver-lite-lift-table/' ),
	array( 'Stewart Handling', 'Riverside', 'CA', 'stewarthandling.com', 'S', 'stewarthandling.com/automatic-pallet-positioners/palletpal-pallet-positioners' ),
	array( 'Zoro', 'Buffalo Grove', 'IL', 'zoro.com', 'V', 'zoro.com/vestil-electric-hydraulic-lift-table-2k-24x48-ehlt-2448-2-43/' ),
	array( 'Hantover', 'Overland Park', 'KS', 'hantover.com', 'V', 'hantover.com/Vestil-EHLT-2448-2-43-24x48-Electric-Hyd-Lift-Table-2k/619554/p' ),
	array( 'Indiana Safety and Supply Company', 'Washington', 'IN', 'indianasafety.com', 'V', 'indianasafety.com/catalog/p/EHLT-2448-2-43/' ),
	array( 'Global Industrial', 'Port Washington', 'NY', 'globalindustrial.com', 'VP', 'globalindustrial.com Vestil and PrestoLifts product pages' ),
	array( 'T.P. Supply Company', 'Mount Airy', 'NC', 'tpsupplyco.com', 'P', 'tpsupplyco.com Presto XL24 series product page' ),
	array( 'Southern Tool', 'Miami', 'FL', 'southern-tool.com', 'PJ', 'southern-tool.com Presto_Scissor_Lifts and scissor_lift_tables (JET) pages; operated by Smith Hamilton, Inc.' ),
	array( 'IndustrialSafety.com', 'Southport', 'CT', 'industrialsafety.com', 'PE', 'industrialsafety.com Presto XL36-30 and ECOA CLT product pages' ),
	array( 'Material Lift Supply', 'Irvine', 'CA', 'materialliftsupply.com', 'P', 'materialliftsupply.com/product/presto-xl36-40' ),
	array( 'American Custom Lifts', 'Ridgecrest', 'CA', 'aclifts.com', 'QM', 'aclifts.com Series 35 and TorkLift product pages' ),
	array( 'Massco Industries', 'Spring', 'TX', 'masscoind.com', 'E', 'masscoind.com/collections/ecoa-heavy-duty-hydraulic-scissor-lift-tables' ),
	array( 'Industrial Products', 'New Orleans', 'LA', 'industrialproducts.com', 'E', 'industrialproducts.com/presto-ecoa-hh-series-heavy-duty-scissor-lifts.html' ),
	array( 'Metro Hydraulic Jack Co.', 'Newark', 'NJ', 'metrohydraulic.com', 'E', 'metrohydraulic.com/pdf/ecoa-in-plant-lift-equipment.pdf (ECOA catalog on its own site)' ),
	array( "Cherry's Industrial Equipment", 'Elk Grove Village', 'IL', 'cherrysind.com', 'U', 'cherrysind.com Superlift lift tables pages' ),
	array( 'The Safety Source', 'Clinton Township', 'MI', 'safetysourcellc.com', 'U', 'safetysourcellc.com Superlift SL SS30-30 product page' ),
	array( 'Light Tool Supply', 'Maplewood', 'NJ', 'lighttoolsupply.com', 'L', 'lighttoolsupply.com/492220/ (Lexco foot operated lift table)' ),
	array( 'Spill 911', 'Westfield', 'IN', 'spill911.com', 'L', 'spill911.com/products/wesco-492225 (Lexco)' ),
	array( 'Toolfetch', 'Elmsford', 'NY', 'toolfetch.com', 'L', 'toolfetch.com Wesco HT-2388-2F-A Lexco lift table' ),
	array( 'WiscoLift', 'Greenville', 'WI', 'wiscolift.com', 'CR', 'wiscolift.com Econo Lift product pages; Lift Products dealer locator (as Wisco-Lift)' ),
	array( 'Dailey Supply', 'Erie', 'PA', 'daileysupply.com', 'C', 'daileysupply.com DaileySupply-EconoLift-Catalog.pdf ("Econo Lift offered by Dailey Supply")' ),
	array( 'Northern Tool + Equipment', 'Burnsville', 'MN', 'northerntool.com', 'J', 'northerntool.com JET SLT-1100 product page' ),
	array( 'Tractor Supply Co.', 'Brentwood', 'TN', 'tractorsupply.com', 'J', 'tractorsupply.com JET DSLT-770 product page' ),
	array( 'The Home Depot', 'Atlanta', 'GA', 'homedepot.com', 'J', 'homedepot.com JET SLT-1100 and DSLT-770 product pages' ),
	array( 'The Tool Nut', 'Yorktown Heights', 'NY', 'toolnut.com', 'J', 'toolnut.com/jet-140780-slt-1100-jumbo-scissor-lift-table.html' ),
	array( 'JB Tools', 'Livonia', 'MI', 'jbtools.com', 'J', 'jbtools.com JET DSLT-770 product page' ),
	array( 'Burns Tools', 'Tiverton', 'RI', 'burnstools.com', 'J', 'burnstools.com/dslt-770-double-scissor-lift-table-770lb' ),
	array( 'MaxTool', 'Ontario', 'CA', 'maxtool.com', 'J', 'maxtool.com JET DSLT-770 product page' ),
	array( 'Beaver Industrial Supply', 'St. Louis', 'MO', 'beavertools.com', 'J', 'beavertools.com JET SLT-1100 and DSLT-770 product pages' ),
	array( 'Elite Metal Tools', 'Holland', 'MI', 'elitemetaltools.com', 'J', 'elitemetaltools.com JET DSLT-770 product page' ),

	// Existing listings gaining brands.
	array( 'Nationwide Industrial Supply', '', '', '', 'MJE', 'nationwideindustrialsupply.com American compact scissors lift, JET and ECOA department pages' ),
	array( 'DigitalBuyer.com', '', '', '', 'V', 'digitalbuyer.com Vestil EHLT-2448-2-43 product page' ),
	array( 'Custom Equipment Company', '', '', '', 'QM', 'custommhs.com Autoquip Series 35 and TorkLift product pages' ),
	array( 'Solution Dynamics, Inc.', '', '', '', 'M', 'lift-tables.net American Lift TorkLift pages' ),
	array( 'HOF Equipment Company', '', '', '', 'E', 'hofequipment.com ECOA CLT product page' ),
	array( 'F.E. Bennett Co.', '', '', '', 'N', 'febennett.com/product/lange-lift-manual-lift-tables/' ),
	array( 'Material Flow & Conveyor Systems', '', '', '', 'L', 'materialflow.com Lexco foot operated hydraulic lift tables page' ),
);

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
foreach ( get_posts( array( 'post_type' => EDC_Post_Types::DISTRIBUTOR, 'post_status' => 'publish', 'posts_per_page' => -1 ) ) as $p ) {
	$existing[ $norm( $p->post_title ) ] = $p;
}

$add_brands = static function ( $post_id, $ids, $note ) {
	$before = array_map( 'intval', (array) edc_get_field( 'brand_ids', $post_id, array() ) );
	edc_update_field( 'brand_ids', array_values( array_unique( array_merge( $before, $ids ) ) ), $post_id );
	update_post_meta( $post_id, '_edc_import_source', trim( get_post_meta( $post_id, '_edc_import_source', true ) . ' | ' . $note, ' |' ) );
};

$created = 0;
$updated = 0;

foreach ( $rows as list( $name, $city, $state, $site, $codes, $evidence ) ) {
	$ids  = array_map( static function ( $c ) use ( $brand_ids ) { return $brand_ids[ $c ]; }, str_split( $codes ) );
	$note = 'Web search 27 Sep 2026: ' . $evidence . '.';
	$post = $existing[ $norm( $name ) ] ?? null;

	printf( "%-7s %-38s %-22s %s\n", $post ? 'update' : 'create', $name, trim( "$city, $state", ', ' ), $codes );

	if ( ! $apply ) {
		continue;
	}

	if ( $post ) {
		$add_brands( (int) $post->ID, $ids, $note );
		EDC_Sync::sync_distributor( (int) $post->ID );
		++$updated;
		continue;
	}

	if ( ! $city ) {
		fwrite( STDERR, "  !! $name expected to exist but was not found\n" );
		continue;
	}

	$post_id = wp_insert_post( array( 'post_type' => EDC_Post_Types::DISTRIBUTOR, 'post_title' => $name, 'post_name' => sanitize_title( $name ), 'post_status' => 'publish' ), true );
	if ( is_wp_error( $post_id ) ) {
		fwrite( STDERR, "  !! $name: " . $post_id->get_error_message() . "\n" );
		continue;
	}
	edc_update_field( 'listing_tier', 'free', $post_id );
	edc_update_field( 'city', $city, $post_id );
	edc_update_field( 'state', $state, $post_id );
	edc_update_field( 'country', 'United States', $post_id );
	edc_update_field( 'website_url', 'https://' . $site . '/', $post_id );
	edc_update_field( 'brand_ids', $ids, $post_id );
	edc_update_field( 'public_email', '', $post_id );
	edc_update_field( 'lead_email', '', $post_id );
	update_post_meta( $post_id, '_edc_import_source', $note );
	EDC_Sync::sync_distributor( $post_id );
	++$created;
}

// California Caster's site now redirects to The Caster Guy (Green Bay, WI),
// which also sells ECOA: one listing, under the name the company uses now.
$cc = get_page_by_path( 'california-caster', OBJECT, EDC_Post_Types::DISTRIBUTOR );
echo ( $cc ? 'update' : 'skip  ' ) . "  California Caster -> The Caster Guy, Green Bay, WI, +ECOA\n";
if ( $apply && $cc ) {
	wp_update_post( array( 'ID' => $cc->ID, 'post_title' => 'The Caster Guy', 'post_name' => 'the-caster-guy' ) );
	edc_update_field( 'city', 'Green Bay', $cc->ID );
	edc_update_field( 'state', 'WI', $cc->ID );
	edc_update_field( 'street', '1450 Radisson Street', $cc->ID );
	edc_update_field( 'postal_code', '54302', $cc->ID );
	edc_update_field( 'website_url', 'https://thecasterguy.com/', $cc->ID );
	$add_brands( (int) $cc->ID, array( $brand_ids['E'] ), 'Renamed 27 Sep 2026: californiacaster.com redirects to The Caster Guy; thecasterguy.com hosts the Presto ECOA catalog.' );
	EDC_Sync::sync_distributor( (int) $cc->ID );
}

printf( "%s: %d created, %d updated\n", $apply ? 'APPLIED' : 'DRY RUN', $created, $updated );

if ( $apply ) {
	echo "\nDistributors per brand:\n";
	foreach ( get_posts( array( 'post_type' => EDC_Post_Types::BRAND, 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ) as $b ) {
		$q = new WP_Query( array( 'post_type' => EDC_Post_Types::DISTRIBUTOR, 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_edc_brand_index', 'value' => '|' . $b->ID . '|', 'compare' => 'LIKE' ) ) ) );
		printf( "  %-30s %3d\n", $b->post_title, $q->found_posts );
	}
}
