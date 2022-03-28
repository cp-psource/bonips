<?php
/**
 * Addon: buyCRED
 * Addon URI: http://codex.bonipress.me/chapter-iii/buycred/
 * Version: 1.6
 */
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

define( 'BONIPS_PURCHASE',              __FILE__ );
define( 'BONIPS_PURCHASE_VERSION',      '1.6' );
define( 'BONIPS_PURCHASE_DIR',          boniPRESS_ADDONS_DIR . 'buy-creds/' );
define( 'BONIPS_BUYCRED_ABSTRACT_DIR',  BONIPS_PURCHASE_DIR . 'abstracts/' );
define( 'BONIPS_BUYCRED_GATEWAYS_DIR',  BONIPS_PURCHASE_DIR . 'gateways/' );
define( 'BONIPS_BUYCRED_MODULES_DIR',   BONIPS_PURCHASE_DIR . 'modules/' );
define( 'BONIPS_BUYCRED_INCLUDES_DIR',  BONIPS_PURCHASE_DIR . 'includes/' );
define( 'BONIPS_BUYCRED_TEMPLATES_DIR', BONIPS_PURCHASE_DIR . 'templates/' );

if ( ! defined( 'BONIPS_BUY_PENDING_COMMENTS' ) )
	define( 'BONIPS_BUY_PENDING_COMMENTS', true );

if ( ! defined( 'BONIPS_BUY_KEY' ) )
	define( 'BONIPS_BUY_KEY', 'buycred_payment' );

/**
 * Load Dependencies
 */
require_once BONIPS_BUYCRED_ABSTRACT_DIR . 'bonipress-abstract-payment-gateway.php';

require_once BONIPS_BUYCRED_INCLUDES_DIR . 'buycred-functions.php';
require_once BONIPS_BUYCRED_INCLUDES_DIR . 'buycred-shortcodes.php';
require_once BONIPS_BUYCRED_INCLUDES_DIR . 'buycred-reward.php';

/**
 * Load Built-in Gateways
 * @since 1.4
 * @version 1.0
 */
require_once BONIPS_BUYCRED_GATEWAYS_DIR . 'paypal-standard.php';
require_once BONIPS_BUYCRED_GATEWAYS_DIR . 'bitpay.php';
require_once BONIPS_BUYCRED_GATEWAYS_DIR . 'netbilling.php';
require_once BONIPS_BUYCRED_GATEWAYS_DIR . 'skrill.php';
require_once BONIPS_BUYCRED_GATEWAYS_DIR . 'bank-transfer.php';

do_action( 'bonipress_buycred_load_gateways' );

/**
 * Load Modules
 * @since 1.7
 * @version 1.0
 */
require_once BONIPS_BUYCRED_MODULES_DIR . 'buycred-module-core.php';
require_once BONIPS_BUYCRED_MODULES_DIR . 'buycred-module-pending.php';
