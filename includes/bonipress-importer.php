<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

/**
 * Register Importer: Log Entries
 * @since 1.4
 * @version 1.0
 */
register_importer(
	'bonipress_import_log',
	sprintf( __( '%s Log Import', 'bonipress' ), bonipress_label() ),
	__( 'Import log entries via a CSV file.', 'bonipress' ),
	'bonipress_importer_log_entries'
);

/**
 * Load Importer: Log Entries
 * @since 1.4
 * @version 1.0
 */
function bonipress_importer_log_entries() {
	require_once( ABSPATH . 'wp-admin/includes/import.php' );

	if ( ! class_exists( 'WP_Importer' ) ) {
		$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
		if ( file_exists( $class_wp_importer ) )
			require $class_wp_importer;
	}

	require_once( boniPRESS_INCLUDES_DIR . 'importers/bonipress-log-entries.php' );
	
	$importer = new boniPRESS_Importer_Log_Entires();
	$importer->load();
}

/**
 * Register Importer: Balances
 * @since 1.4.2
 * @version 1.0
 */
register_importer(
	'bonipress_import_balance',
	sprintf( __( '%s Balance Import', 'bonipress' ), bonipress_label() ),
	__( 'Import balances.', 'bonipress' ),
	'bonipress_importer_point_balances'
);

/**
 * Load Importer: Point Balances
 * @since 1.4
 * @version 1.0
 */
function bonipress_importer_point_balances() {
	require_once( ABSPATH . 'wp-admin/includes/import.php' );

	if ( ! class_exists( 'WP_Importer' ) ) {
		$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
		if ( file_exists( $class_wp_importer ) )
			require $class_wp_importer;
	}

	require_once( boniPRESS_INCLUDES_DIR . 'importers/bonipress-balances.php' );
	
	$importer = new boniPRESS_Importer_Balances();
	$importer->load();
}

/**
 * Register Importer: CubePoints
 * @since 1.4
 * @version 1.0
 */
register_importer(
	'bonipress_import_cp',
	sprintf( __( '%s CubePoints Import', 'bonipress' ), bonipress_label() ),
	__( 'Import CubePoints log entries and / or balances.', 'bonipress' ),
	'bonipress_importer_cubepoints'
);

/**
 * Load Importer: CubePoints
 * @since 1.4
 * @version 1.0
 */
function bonipress_importer_cubepoints() {
	require_once( ABSPATH . 'wp-admin/includes/import.php' );

	global $wpdb;

	// No use continuing if there is no log to import
	if ( $wpdb->query( $wpdb->prepare( "SHOW TABLES LIKE %s;", $wpdb->prefix . 'cp' ) ) == 0 ) {
		echo '<p>' . __( 'No CubePoints log exists.', 'bonipress' ) . '</p>';
		return;
	}

	if ( ! class_exists( 'WP_Importer' ) ) {
		$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
		if ( file_exists( $class_wp_importer ) )
			require $class_wp_importer;
	}

	require_once( boniPRESS_INCLUDES_DIR . 'importers/bonipress-cubepoints.php' );
	
	$importer = new boniPRESS_Importer_CubePoints();
	$importer->load();
}
?>