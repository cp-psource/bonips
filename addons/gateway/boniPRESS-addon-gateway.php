<?php
/**
 * Addon: Gateway
 * Addon URI: http://codex.bonipress.me/chapter-iii/gateway/
 * Version: 1.4
 */
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

define( 'boniPRESS_GATE',               __FILE__ );
define( 'boniPRESS_GATE_DIR',           boniPRESS_ADDONS_DIR . 'gateway/' );
define( 'boniPRESS_GATE_ASSETS_DIR',    boniPRESS_GATE_DIR . 'assets/' );
define( 'boniPRESS_GATE_CART_DIR',      boniPRESS_GATE_DIR . 'carts/' );
define( 'boniPRESS_GATE_EVENT_DIR',     boniPRESS_GATE_DIR . 'event-booking/' );
define( 'boniPRESS_GATE_MEMBER_DIR',    boniPRESS_GATE_DIR . 'membership/' );
define( 'boniPRESS_GATE_AFFILIATE_DIR', boniPRESS_GATE_DIR . 'affiliate/' );

/**
 * Supported Carts
 */
require_once boniPRESS_GATE_CART_DIR . 'bonipress-woocommerce.php';
require_once boniPRESS_GATE_CART_DIR . 'bonipress-wpecommerce.php';

/**
 * Event Espresso
 */
function bonipress_load_event_espresso3() {

	if ( ! defined( 'EVENT_ESPRESSO_VERSION' ) ) return;

	require_once boniPRESS_GATE_EVENT_DIR . 'bonipress-eventespresso3.php';
	$gateway = new boniPRESS_Espresso_Gateway();
	$gateway->load();

}
add_action( 'bonipress_init', 'bonipress_load_event_espresso3' );

/**
 * Events Manager
 */
function bonipress_load_events_manager() {

	if ( ! defined( 'EM_VERSION' ) ) return;

	// Free only
	if ( ! class_exists( 'EM_Pro' ) ) {

		require_once boniPRESS_GATE_EVENT_DIR . 'bonipress-eventsmanager.php';
		$events = new boniPRESS_Events_Manager_Gateway();
		$events->load();

	}

}
add_action( 'bonipress_init', 'bonipress_load_events_manager' );
