<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * Register Importer: Log Entries
 * @since 1.4
 * @version 1.0
 */
register_importer(
	'bonips_import_log',
	sprintf( __( '%s Log Import', 'bonips' ), bonips_label() ),
	__( 'Import log entries via a CSV file.', 'bonips' ),
	'bonips_importer_log_entries'
);

/**
 * Load Importer: Log Entries
 * @since 1.4
 * @version 1.0
 */
function bonips_importer_log_entries() {
	require_once( ABSPATH . 'wp-admin/includes/import.php' );

	if ( ! class_exists( 'WP_Importer' ) ) {
		$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
		if ( file_exists( $class_wp_importer ) )
			require $class_wp_importer;
	}

	require_once( boniPS_INCLUDES_DIR . 'importers/bonips-log-entries.php' );
	
	$importer = new boniPS_Importer_Log_Entires();
	$importer->load();
}

/**
 * Register Importer: Balances
 * @since 1.4.2
 * @version 1.0
 */
register_importer(
	'bonips_import_balance',
	sprintf( __( '%s Balance Import', 'bonips' ), bonips_label() ),
	__( 'Import balances.', 'bonips' ),
	'bonips_importer_point_balances'
);

/**
 * Load Importer: Point Balances
 * @since 1.4
 * @version 1.0
 */
function bonips_importer_point_balances() {
	require_once( ABSPATH . 'wp-admin/includes/import.php' );

	if ( ! class_exists( 'WP_Importer' ) ) {
		$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
		if ( file_exists( $class_wp_importer ) )
			require $class_wp_importer;
	}

	require_once( boniPS_INCLUDES_DIR . 'importers/bonips-balances.php' );
	
	$importer = new boniPS_Importer_Balances();
	$importer->load();
}

/**
 * Register Importer: CubePoints
 * @since 1.4
 * @version 1.0
 */
register_importer(
	'bonips_import_cp',
	sprintf( __( '%s CubePoints Import', 'bonips' ), bonips_label() ),
	__( 'Import CubePoints log entries and / or balances.', 'bonips' ),
	'bonips_importer_cubepoints'
);

/**
 * Load Importer: CubePoints
 * @since 1.4
 * @version 1.0
 */
function bonips_importer_cubepoints() {
	require_once( ABSPATH . 'wp-admin/includes/import.php' );

	global $wpdb;

	// No use continuing if there is no log to import
	if ( $wpdb->query( $wpdb->prepare( "SHOW TABLES LIKE %s;", $wpdb->prefix . 'cp' ) ) == 0 ) {
		echo '<p>' . __( 'No CubePoints log exists.', 'bonips' ) . '</p>';
		return;
	}

	if ( ! class_exists( 'WP_Importer' ) ) {
		$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
		if ( file_exists( $class_wp_importer ) )
			require $class_wp_importer;
	}

	require_once( boniPS_INCLUDES_DIR . 'importers/bonips-cubepoints.php' );
	
	$importer = new boniPS_Importer_CubePoints();
	$importer->load();
}
?>