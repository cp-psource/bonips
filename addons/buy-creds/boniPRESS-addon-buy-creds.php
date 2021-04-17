<?php
/**
 * Addon: buyCRED
 * Addon URI: http://codex.bonipress.me/chapter-iii/buycred/
 * Version: 1.6
 */
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

define( 'BONIPRESS_PURCHASE',              __FILE__ );
define( 'BONIPRESS_PURCHASE_VERSION',      '1.6' );
define( 'BONIPRESS_PURCHASE_DIR',          boniPRESS_ADDONS_DIR . 'buy-creds/' );
define( 'BONIPRESS_BUYCRED_ABSTRACT_DIR',  BONIPRESS_PURCHASE_DIR . 'abstracts/' );
define( 'BONIPRESS_BUYCRED_GATEWAYS_DIR',  BONIPRESS_PURCHASE_DIR . 'gateways/' );
define( 'BONIPRESS_BUYCRED_MODULES_DIR',   BONIPRESS_PURCHASE_DIR . 'modules/' );
define( 'BONIPRESS_BUYCRED_INCLUDES_DIR',  BONIPRESS_PURCHASE_DIR . 'includes/' );
define( 'BONIPRESS_BUYCRED_TEMPLATES_DIR', BONIPRESS_PURCHASE_DIR . 'templates/' );

if ( ! defined( 'BONIPRESS_BUY_PENDING_COMMENTS' ) )
	define( 'BONIPRESS_BUY_PENDING_COMMENTS', true );

if ( ! defined( 'BONIPRESS_BUY_KEY' ) )
	define( 'BONIPRESS_BUY_KEY', 'buycred_payment' );

/**
 * Load Dependencies
 */
require_once BONIPRESS_BUYCRED_ABSTRACT_DIR . 'bonipress-abstract-payment-gateway.php';

require_once BONIPRESS_BUYCRED_INCLUDES_DIR . 'buycred-functions.php';
require_once BONIPRESS_BUYCRED_INCLUDES_DIR . 'buycred-shortcodes.php';

/**
 * Load Built-in Gateways
 * @since 1.4
 * @version 1.0
 */
require_once BONIPRESS_BUYCRED_GATEWAYS_DIR . 'paypal-standard.php';
require_once BONIPRESS_BUYCRED_GATEWAYS_DIR . 'bitpay.php';
require_once BONIPRESS_BUYCRED_GATEWAYS_DIR . 'netbilling.php';
require_once BONIPRESS_BUYCRED_GATEWAYS_DIR . 'skrill.php';
require_once BONIPRESS_BUYCRED_GATEWAYS_DIR . 'zombaio.php';
require_once BONIPRESS_BUYCRED_GATEWAYS_DIR . 'bank-transfer.php';

do_action( 'bonipress_buycred_load_gateways' );

/**
 * Load Modules
 * @since 1.7
 * @version 1.0
 */
require_once BONIPRESS_BUYCRED_MODULES_DIR . 'buycred-module-core.php';
require_once BONIPRESS_BUYCRED_MODULES_DIR . 'buycred-module-pending.php';
