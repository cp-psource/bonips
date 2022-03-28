<?php
if ( ! defined( 'BONIPS_PURCHASE' ) ) exit;

/**
 * boniPRESS_buyCRED_Module class
 * @since 0.1
 * @version 1.4.1
 */
if ( ! class_exists( 'boniPRESS_buyCRED_Reward' ) ) :
	class boniPRESS_buyCRED_Reward {

		// Instnace
		protected static $_instance = NULL;

		/**
		 * Construct
		 */
		function __construct() {

			add_action( 'bonipress_admin_enqueue',  array( $this, 'register_assets' ) );
			add_filter( 'bonipress_setup_hooks',    array( $this, 'register_buycred_reward_hook' ), 10, 2 );
			add_action( 'bonipress_load_hooks',     array( $this, 'load_buycred_reward_hook' ) );
			add_filter( 'bonipress_all_references', array( $this, 'register_buycred_reward_refrence' ) );

		}

		/**
		 * Setup Instance
		 * @since 1.7
		 * @version 1.0
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Register Assets
		 * @since 1.8
		 * @version 1.0
		 */
		public function register_assets() {

			wp_enqueue_style( 'buycred-admin-style', plugins_url( 'assets/css/admin-style.css', BONIPS_PURCHASE ), array(), BONIPS_PURCHASE_VERSION, 'all' );
			wp_enqueue_script( 'buycred-admin-script', plugins_url( 'assets/js/admin-script.js', BONIPS_PURCHASE ), array( 'jquery' ), BONIPS_PURCHASE_VERSION, 'all' );

		}

		public function load_buycred_reward_hook() {
			require_once BONIPS_BUYCRED_INCLUDES_DIR . 'buycred-reward-hook.php';
		}

		public function register_buycred_reward_hook( $installed ) {

			$installed['buycred_reward'] = array(
				'title'       => __('Belohnung für den Kauf von %plural%', 'bonipress'),
				'description' => __('Fügt einen BoniPress-Hook für die buyCred-Belohnung hinzu.', 'bonipress'),
				'callback'    => array('boniPRESS_buyCRED_Reward_Hook')
			);

			return $installed;
		}


		public function register_buycred_reward_refrence( $list ) {

			$list['buycred_reward']  = __('Belohnung für den Kauf von buyCRED', 'bonipress');
			return $list;
		}

	}
endif;

function bonipress_buycred_reward_init() {
	return boniPRESS_buyCRED_Reward::instance();
}
bonipress_buycred_reward_init(); 