<?php
/**
 * Addon: Gateway
 * Addon URI: http://codex.bonips.me/chapter-iii/gateway/
 * Version: 1.4
 */
if ( ! defined( 'boniPS_VERSION' ) ) exit;

define( 'boniPS_GATE',               __FILE__ );
define( 'boniPS_GATE_DIR',           boniPS_ADDONS_DIR . 'gateway/' );
define( 'boniPS_GATE_ASSETS_DIR',    boniPS_GATE_DIR . 'assets/' );
define( 'boniPS_GATE_CART_DIR',      boniPS_GATE_DIR . 'carts/' );
define( 'boniPS_GATE_EVENT_DIR',     boniPS_GATE_DIR . 'event-booking/' );
define( 'boniPS_GATE_MEMBER_DIR',    boniPS_GATE_DIR . 'membership/' );
define( 'boniPS_GATE_AFFILIATE_DIR', boniPS_GATE_DIR . 'affiliate/' );

/**
 * Supported Carts
 */
require_once boniPS_GATE_CART_DIR . 'bonips-woocommerce.php';
require_once boniPS_GATE_CART_DIR . 'bonips-wpecommerce.php';

/**
 * Event Espresso
 */
function bonips_load_event_espresso3() {

	if ( ! defined( 'EVENT_ESPRESSO_VERSION' ) ) return;

	require_once boniPS_GATE_EVENT_DIR . 'bonips-eventespresso3.php';
	$gateway = new boniPS_Espresso_Gateway();
	$gateway->load();

}
add_action( 'bonips_init', 'bonips_load_event_espresso3' );

/**
 * Events Manager
 */
function bonips_load_events_manager() {

	if ( ! defined( 'EM_VERSION' ) ) return;

	// Free only
	if ( ! class_exists( 'EM_Pro' ) ) {

		require_once boniPS_GATE_EVENT_DIR . 'bonips-eventsmanager.php';
		$events = new boniPS_Events_Manager_Gateway();
		$events->load();

	}

}
add_action( 'bonips_init', 'bonips_load_events_manager' );
