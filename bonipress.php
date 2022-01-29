<?php
/**
 * Plugin Name: BoniPress
 * Plugin URI: https://bonipress.me
 * Description: Ein adaptives Punkteverwaltungssystem für WordPress-basierte Webseiten, Basiscode von myCred.
 * Version: 1.8.7
 * Tags: point, credit, loyalty program, engagement, reward, woocommerce rewards
 * Author: DerN3rd
 * Author URI: https://n3rds.work
 * Requires at least: WP 4.8
 * Tested up to: WP 5.1
 * Text Domain: bonipress
 * Domain Path: /lang
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

require 'psource/psource-plugin-update/psource-plugin-updater.php';
$MyUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
	'https://n3rds.work//wp-update-server/?action=get_metadata&slug=bonipress', 
	__FILE__, 
	'bonipress' 
);

if ( ! class_exists( 'boniPRESS_Core' ) ) :
	final class boniPRESS_Core {

		// Plugin Version
		public $version             = '1.8.7';

		// Instnace
		protected static $_instance = NULL;

		// Current session
		public $session             = NULL;

		// Modules
		public $modules             = NULL;

		// Point Types
		public $point_types         = NULL;

		// Account Object
		public $account             = NULL;

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
		 * Not allowed
		 * @since 1.7
		 * @version 1.0
		 */
		public function __clone() { _doing_it_wrong( __FUNCTION__, 'Cheatin&#8217; huh?', '1.7' ); }

		/**
		 * Not allowed
		 * @since 1.7
		 * @version 1.0
		 */
		public function __wakeup() { _doing_it_wrong( __FUNCTION__, 'Cheatin&#8217; huh?', '1.7' ); }

		/**
		 * Get
		 * @since 1.7
		 * @version 1.0
		 */
		public function __get( $key ) {
			if ( in_array( $key, array( 'point_types', 'modules', 'account' ) ) )
				return $this->$key();
		}

		/**
		 * Define
		 * @since 1.7
		 * @version 1.0
		 */
		private function define( $name, $value, $definable = true ) {
			if ( ! defined( $name ) )
				define( $name, $value );
			elseif ( ! $definable && defined( $name ) )
				_doing_it_wrong( 'boniPRESS_Core->define()', 'Konnte nicht definieren: ' . $name . ' wie es schon woanders definiert ist!', '1.7' );
		}

		/**
		 * Require File
		 * @since 1.7
		 * @version 1.0
		 */
		public function file( $required_file ) {
			if ( file_exists( $required_file ) )
				require_once $required_file;
			else
				_doing_it_wrong( 'boniPRESS_Core->file()', 'Angeforderte Datei ' . $required_file . ' nicht gefunden.', '1.7' );
		}

		/**
		 * Construct
		 * @since 1.7
		 * @version 1.0
		 */
		public function __construct() {

			$this->define_constants();
			$this->includes();

			// Multisite Feature: If the site is blocked from using boniPRESS, exit now
			if ( bonipress_is_site_blocked() ) return;

			// Register plugin hooks
			register_activation_hook(   boniPRESS_THIS, 'bonipress_plugin_activation' );
			register_deactivation_hook( boniPRESS_THIS, 'bonipress_plugin_deactivation' );
			register_uninstall_hook(    boniPRESS_THIS, 'bonipress_plugin_uninstall' );

			// If boniPRESS is ready to be used
			if ( is_bonipress_ready() ) {

				$this->internal();
				$this->wordpress();

				do_action( 'bonipress_ready' );

			}

			// We need to run the setup
			else {

				// Load translation and register assets for the setup
				add_action( 'init',                    array( $this, 'load_plugin_textdomain' ), 10 );
				add_action( 'init',                    array( $this, 'register_assets' ), 20 );
				add_filter( 'bonipress_maybe_install_db', '__return_false' );

				// Load the setup module
				$this->file( boniPRESS_INCLUDES_DIR . 'bonipress-setup.php' );

				$setup = new boniPRESS_Setup();
				$setup->load();

			}

			// Plugin Related
			add_filter( 'plugin_action_links_bonipress/bonipress.php', array( $this, 'plugin_links' ), 10, 4 );
			add_filter( 'plugin_row_meta',                       array( $this, 'plugin_description_links' ), 10, 2 );

		}

		/**
		 * Define Constants
		 * First, we start with defining all requires constants if they are not defined already.
		 * @since 1.7
		 * @version 1.0.2
		 */
		private function define_constants() {

			// Ok to override
			$this->define( 'boniPRESS_VERSION',              $this->version );
			$this->define( 'boniPRESS_DB_VERSION',           '1.0' );
			$this->define( 'BONIPRESS_SLUG',                 'bonipress' );
			$this->define( 'BONIPRESS_DEFAULT_LABEL',        'boniPRESS' );
			$this->define( 'BONIPRESS_DEFAULT_TYPE_KEY',     'bonipress_default' );
			$this->define( 'BONIPRESS_SHOW_PREMIUM_ADDONS',  true );
			$this->define( 'BONIPRESS_FOR_OLDER_WP',         false );
			$this->define( 'BONIPRESS_MIN_TIME_LIMIT',       3 );
			$this->define( 'BONIPRESS_ENABLE_TOTAL_BALANCE', true );
			$this->define( 'BONIPRESS_ENABLE_LOGGING',       true );
			$this->define( 'BONIPRESS_ENABLE_SHORTCODES',    true );
			$this->define( 'BONIPRESS_ENABLE_HOOKS',         true );
			$this->define( 'BONIPRESS_UNINSTALL_LOG',        true );
			$this->define( 'BONIPRESS_UNINSTALL_CREDS',      true );
			$this->define( 'BONIPRESS_DISABLE_PROTECTION',   false );
			$this->define( 'BONIPRESS_CACHE_LEADERBOARDS',   false );
			$this->define( 'BONIPRESS_MAX_HISTORY_SIZE',     100 );

			// Not ok to override
			$this->define( 'boniPRESS_THIS',                 __FILE__, false );
			$this->define( 'boniPRESS_ROOT_DIR',             plugin_dir_path( boniPRESS_THIS ), false );
			$this->define( 'boniPRESS_ABSTRACTS_DIR',        boniPRESS_ROOT_DIR . 'abstracts/', false );
			$this->define( 'boniPRESS_ADDONS_DIR',           boniPRESS_ROOT_DIR . 'addons/', false );
			$this->define( 'boniPRESS_ASSETS_DIR',           boniPRESS_ROOT_DIR . 'assets/', false );
			$this->define( 'boniPRESS_INCLUDES_DIR',         boniPRESS_ROOT_DIR . 'includes/', false );
			$this->define( 'boniPRESS_LANG_DIR',             boniPRESS_ROOT_DIR . 'lang/', false );
			$this->define( 'boniPRESS_MODULES_DIR',          boniPRESS_ROOT_DIR . 'modules/', false );
			$this->define( 'boniPRESS_CLASSES_DIR',          boniPRESS_INCLUDES_DIR . 'classes/', false );
			$this->define( 'boniPRESS_IMPORTERS_DIR',        boniPRESS_INCLUDES_DIR . 'importers/', false );
			$this->define( 'boniPRESS_SHORTCODES_DIR',       boniPRESS_INCLUDES_DIR . 'shortcodes/', false );
			$this->define( 'boniPRESS_WIDGETS_DIR',          boniPRESS_INCLUDES_DIR . 'widgets/', false );
			$this->define( 'boniPRESS_HOOKS_DIR',            boniPRESS_INCLUDES_DIR . 'hooks/', false );
			$this->define( 'boniPRESS_PLUGINS_DIR',          boniPRESS_HOOKS_DIR . 'external/', false );

		}

		/**
		 * Include Plugin Files
		 * @since 1.7
		 * @version 1.1
		 */
		public function includes() {

			$this->file( boniPRESS_INCLUDES_DIR . 'bonipress-functions.php' );

			$this->file( boniPRESS_CLASSES_DIR . 'class.query-log.php' );
			$this->file( boniPRESS_CLASSES_DIR . 'class.query-export.php' );
			$this->file( boniPRESS_CLASSES_DIR . 'class.query-leaderboard.php' );

			$this->file( boniPRESS_ABSTRACTS_DIR . 'bonipress-abstract-hook.php' );
			$this->file( boniPRESS_ABSTRACTS_DIR . 'bonipress-abstract-module.php' );
			$this->file( boniPRESS_ABSTRACTS_DIR . 'bonipress-abstract-object.php' );

			// Multisite Feature - Option to block usage of boniPRESS on a particular site
			if ( ! bonipress_is_site_blocked() ) {

				// Core
				$this->file( boniPRESS_INCLUDES_DIR . 'bonipress-object.php' );
				$this->file( boniPRESS_INCLUDES_DIR . 'bonipress-remote.php' );
				$this->file( boniPRESS_INCLUDES_DIR . 'bonipress-protect.php' );
				$this->file( boniPRESS_INCLUDES_DIR . 'bonipress-about.php' );

				// If boniPRESS has been setup and is ready to begin
				if ( bonipress_is_installed() ) {

					// Modules
					$this->file( boniPRESS_MODULES_DIR . 'bonipress-module-addons.php' );
					$this->file( boniPRESS_MODULES_DIR . 'bonipress-module-settings.php' );
					$this->file( boniPRESS_MODULES_DIR . 'bonipress-module-hooks.php' );
					$this->file( boniPRESS_MODULES_DIR . 'bonipress-module-log.php' );
					$this->file( boniPRESS_MODULES_DIR . 'bonipress-module-export.php' );
					$this->file( boniPRESS_MODULES_DIR . 'bonipress-module-management.php' );
					$this->file( boniPRESS_MODULES_DIR . 'bonipress-module-caching.php' );

					if ( is_multisite() ) {

						$this->file( boniPRESS_MODULES_DIR . 'bonipress-module-network.php' );

					}

				}

			}

		}

		/**
		 * Internal Setup
		 * @since 1.8
		 * @version 1.0
		 */
		private function include_hooks() {

			if ( BONIPRESS_ENABLE_HOOKS === false ) return;

			// Built-in Hooks
			$this->file( boniPRESS_HOOKS_DIR . 'bonipress-hook-anniversary.php' );
			$this->file( boniPRESS_HOOKS_DIR . 'bonipress-hook-comments.php' );
			$this->file( boniPRESS_HOOKS_DIR . 'bonipress-hook-delete-content.php' );
			$this->file( boniPRESS_HOOKS_DIR . 'bonipress-hook-link-clicks.php' );
			$this->file( boniPRESS_HOOKS_DIR . 'bonipress-hook-logins.php' );
			$this->file( boniPRESS_HOOKS_DIR . 'bonipress-hook-publishing-content.php' );
			$this->file( boniPRESS_HOOKS_DIR . 'bonipress-hook-referrals.php' );
			$this->file( boniPRESS_HOOKS_DIR . 'bonipress-hook-registrations.php' );
			$this->file( boniPRESS_HOOKS_DIR . 'bonipress-hook-site-visits.php' );
			$this->file( boniPRESS_HOOKS_DIR . 'bonipress-hook-view-content.php' );
			$this->file( boniPRESS_HOOKS_DIR . 'bonipress-hook-watching-video.php' );

			// Supported plugins
			$this->file( boniPRESS_PLUGINS_DIR . 'bonipress-hook-affiliatewp.php' );
			$this->file( boniPRESS_PLUGINS_DIR . 'bonipress-hook-badgeOS.php' );
			$this->file( boniPRESS_PLUGINS_DIR . 'bonipress-hook-PSForum.php' );
			$this->file( boniPRESS_PLUGINS_DIR . 'bonipress-hook-buddypress-media.php' );
			$this->file( boniPRESS_PLUGINS_DIR . 'bonipress-hook-buddypress.php' );
			$this->file( boniPRESS_PLUGINS_DIR . 'bonipress-hook-contact-form7.php' );
			$this->file( boniPRESS_PLUGINS_DIR . 'bonipress-hook-events-manager-light.php' );
			$this->file( boniPRESS_PLUGINS_DIR . 'bonipress-hook-gravityforms.php' );
			$this->file( boniPRESS_PLUGINS_DIR . 'bonipress-hook-invite-anyone.php' );
			$this->file( boniPRESS_PLUGINS_DIR . 'bonipress-hook-jetpack.php' );
			$this->file( boniPRESS_PLUGINS_DIR . 'bonipress-hook-simplepress.php' );
			$this->file( boniPRESS_PLUGINS_DIR . 'bonipress-hook-woocommerce.php' );
			$this->file( boniPRESS_PLUGINS_DIR . 'bonipress-hook-wp-favorite-posts.php' );
			$this->file( boniPRESS_PLUGINS_DIR . 'bonipress-hook-wp-polls.php' );
			$this->file( boniPRESS_PLUGINS_DIR . 'bonipress-hook-wp-postratings.php' );

		}

		/**
		 * Internal Setup
		 * @since 1.7
		 * @version 1.0
		 */
		private function internal() {

			$this->point_types = bonipress_get_types( true );
			$this->modules     = array(
				'solo' => array(),
				'type' => array()
			);

			$this->pre_init_globals();

		}

		/**
		 * Pre Init Globals
		 * Globals that does not reply on external sources and can be loaded before init.
		 * @since 1.7
		 * @version 1.1
		 */
		private function pre_init_globals() {

			global $bonipress, $bonipress_log_table, $bonipress_types, $bonipress_modules, $bonipress_label, $bonipress_network;

			$bonipress             = new boniPRESS_Settings();
			$bonipress_log_table   = $bonipress->log_table;
			$bonipress_types       = $this->point_types;
			$bonipress_label       = apply_filters( 'bonipress_label', BONIPRESS_DEFAULT_LABEL );
			$bonipress_modules     = $this->modules;
			$bonipress_network     = bonipress_get_settings_network();

		}

		/**
		 * WordPress
		 * Next we hook into WordPress
		 * @since 1.7
		 * @version 1.0.1
		 */
		public function wordpress() {

			add_action( 'plugins_loaded',    array( $this, 'after_plugin' ), 999 );
			add_action( 'after_setup_theme', array( $this, 'after_theme' ), 50 );
			add_action( 'after_setup_theme', array( $this, 'load_shortcodes' ), 60 );
			add_action( 'init',              array( $this, 'init' ), 5 );
			add_action( 'widgets_init',      array( $this, 'widgets_init' ), 50 );
			add_action( 'admin_init',        array( $this, 'admin_init' ), 50 );

			add_action( 'bonipress_reset_key',  array( $this, 'cron_reset_key' ), 10 );
			add_action( 'bonipress_reset_key',  array( $this, 'cron_delete_leaderboard_cache' ), 20 );

		}

		/**
		 * After Plugins Loaded
		 * Used to setup modules that are not replacable.
		 * @since 1.7
		 * @version 1.0
		 */
		public function after_plugin() {

			$this->modules['solo']['addons'] = new boniPRESS_Addons_Module();
			$this->modules['solo']['addons']->load();
			$this->modules['solo']['addons']->run_addons();

		}

		/**
		 * After Themes Loaded
		 * Used to load internal features via modules.
		 * @since 1.7
		 * @version 1.1
		 */
		public function after_theme() {

			global $bonipress, $bonipress_modules;

			// Lets start with Multisite
			if ( is_multisite() ) {

				// Normally the is_plugin_active_for_network() function is only available in the admin area
				if ( ! function_exists( 'is_plugin_active_for_network' ) )
					$this->file( ABSPATH . '/wp-admin/includes/plugin.php' );

				// The network "module" is only needed if the plugin is activated network wide
				if ( is_plugin_active_for_network( 'bonipress/bonipress.php' ) ) {
					$this->modules['solo']['network'] = new boniPRESS_Network_Module();
					$this->modules['solo']['network']->load();
				}

			}

			// The log module can not be loaded if logging is disabled
			if ( BONIPRESS_ENABLE_LOGGING ) {

				// Attach the log to each point type we use
				foreach ( $this->point_types as $type => $title ) {
					$this->modules['type'][ $type ]['log'] = new boniPRESS_Log_Module( $type );
					$this->modules['type'][ $type ]['log']->load();
				}

			}

			// Option to disable hooks
			if ( BONIPRESS_ENABLE_HOOKS ) {

				$this->include_hooks();

				do_action( 'bonipress_load_hooks' );

				// Attach hooks module to each point type we use
				foreach ( $this->point_types as $type => $title ) {
					$this->modules['type'][ $type ]['hooks'] = new boniPRESS_Hooks_Module( $type );
					$this->modules['type'][ $type ]['hooks']->load();
				}

			}

			// Attach each module to each point type we use
			foreach ( $this->point_types as $type => $title ) {

				$this->modules['type'][ $type ]['settings'] = new boniPRESS_Settings_Module( $type );
				$this->modules['type'][ $type ]['settings']->load();

				$this->modules['solo'][ $type ] = new boniPRESS_Caching_Module( $type );
				$this->modules['solo'][ $type ]->load();

			}

			// Attach the Management module to the main point type
			$this->modules['type'][ BONIPRESS_DEFAULT_TYPE_KEY ]['management'] = new boniPRESS_Management_Module();
			$this->modules['type'][ BONIPRESS_DEFAULT_TYPE_KEY ]['management']->load();

			// Attach BuddyPress module to the main point type only
			if ( class_exists( 'BuddyPress' ) ) {

				$this->file( boniPRESS_MODULES_DIR . 'bonipress-module-buddypress.php' );
				$this->modules['type'][ BONIPRESS_DEFAULT_TYPE_KEY ]['buddypress'] = new boniPRESS_BuddyPress_Module( BONIPRESS_DEFAULT_TYPE_KEY );
				$this->modules['type'][ BONIPRESS_DEFAULT_TYPE_KEY ]['buddypress']->load();

			}

			$bonipress_modules = $this->modules['type'];

			// The export module can not be loaded if logging is disabled
			if ( BONIPRESS_ENABLE_LOGGING ) {

				// Load Export module
				$this->modules['solo']['exports'] = new boniPRESS_Export_Module();
				$this->modules['solo']['exports']->load();

			}

			// Let third-parties register and load custom boniPRESS modules
			$bonipress_modules = apply_filters( 'bonipress_load_modules', $this->modules, $this->point_types );

			// Let others play
			do_action( 'bonipress_pre_init' );

		}

		/**
		 * Load Shortcodes
		 * @since 1.7
		 * @version 1.1
		 */
		public function load_shortcodes() {

			if ( BONIPRESS_ENABLE_SHORTCODES ) {

				$this->file( boniPRESS_SHORTCODES_DIR . 'bonipress_exchange.php' );
				$this->file( boniPRESS_SHORTCODES_DIR . 'bonipress_hide_if.php' );
				$this->file( boniPRESS_SHORTCODES_DIR . 'bonipress_leaderboard_position.php' );
				$this->file( boniPRESS_SHORTCODES_DIR . 'bonipress_leaderboard.php' );
				$this->file( boniPRESS_SHORTCODES_DIR . 'bonipress_my_balance.php' );
				$this->file( boniPRESS_SHORTCODES_DIR . 'bonipress_send.php' );
				$this->file( boniPRESS_SHORTCODES_DIR . 'bonipress_show_if.php' );
				$this->file( boniPRESS_SHORTCODES_DIR . 'bonipress_total_balance.php' );

				// These shortcodes will not work if logging is disabled
				if ( BONIPRESS_ENABLE_LOGGING ) {

					$this->file( boniPRESS_SHORTCODES_DIR . 'bonipress_best_user.php' );
					$this->file( boniPRESS_SHORTCODES_DIR . 'bonipress_give.php' );
					$this->file( boniPRESS_SHORTCODES_DIR . 'bonipress_history.php' );
					$this->file( boniPRESS_SHORTCODES_DIR . 'bonipress_total_points.php' );
					$this->file( boniPRESS_SHORTCODES_DIR . 'bonipress_total_since.php' );

				}

				// These shortcodes will not work if hooks are disabled
				if ( BONIPRESS_ENABLE_HOOKS ) {

					$this->file( boniPRESS_SHORTCODES_DIR . 'bonipress_affiliate_id.php' );
					$this->file( boniPRESS_SHORTCODES_DIR . 'bonipress_affiliate_link.php' );
					$this->file( boniPRESS_SHORTCODES_DIR . 'bonipress_link.php' );
					$this->file( boniPRESS_SHORTCODES_DIR . 'bonipress_video.php' );
					$this->file( boniPRESS_SHORTCODES_DIR . 'bonipress_hook_table.php' );

				}

				do_action( 'bonipress_load_shortcode' );

			}

		}

		/**
		 * Init
		 * General plugin setup during the init hook.
		 * @since 1.7
		 * @version 1.0
		 */
		public function init() {

			// Let others play
			do_action( 'bonipress_init' );

			// Lets begin
			$this->post_init_globals();

			// Textdomain
			$this->load_plugin_textdomain();

			// Register Assets
			$this->register_assets();

			// Setup Cron
			$this->setup_cron_jobs();

			// Enqueue scripts & styles
			add_action( 'wp_enqueue_scripts',    array( $this, 'enqueue_front_before' ) );
			add_action( 'wp_footer',             array( $this, 'enqueue_front_after' ) );

			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_before' ) );

			// Admin bar and toolbar adjustments
			add_action( 'admin_menu',            array( $this, 'adjust_admin_menu' ), 9 );
			add_action( 'admin_bar_menu',        array( $this, 'adjust_toolbar' ) );

		}

		/**
		 * Post Init Globals
		 * Globals that needs to be defined after init. Mainly used for user related globals.
		 * @since 1.7
		 * @version 1.1
		 */
		private function post_init_globals() {

			// Just in case, this should never happen
			if ( ! did_action( 'init' ) || did_action( 'bonipress_set_globals' ) ) return;

			if ( is_user_logged_in() )
				bonipress_set_current_account();

			do_action( 'bonipress_set_globals' );

		}

		/**
		 * Load Plugin Textdomain
		 * @since 1.7
		 * @version 1.0
		 */
		public function load_plugin_textdomain() {

			// Load Translation
			$locale = apply_filters( 'plugin_locale', get_locale(), 'bonipress' );

			load_textdomain( 'bonipress', WP_LANG_DIR . '/bonipress/bonipress-' . $locale . '.mo' );
			load_plugin_textdomain( 'bonipress', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );

		}

		/**
		 * Register Assets
		 * @since 1.7
		 * @version 1.0
		 */
		public function register_assets() {

			// Styles
			wp_register_style( 'bonipress-front',          plugins_url( 'assets/css/bonipress-front.css', boniPRESS_THIS ),        array(), $this->version, 'all' );
			wp_register_style( 'bonipress-admin',          plugins_url( 'assets/css/bonipress-admin.css', boniPRESS_THIS ),        array(), $this->version, 'all' );
			wp_register_style( 'bonipress-edit-balance',   plugins_url( 'assets/css/bonipress-edit-balance.css', boniPRESS_THIS ), array(), $this->version, 'all' );
			wp_register_style( 'bonipress-edit-log',       plugins_url( 'assets/css/bonipress-edit-log.css', boniPRESS_THIS ),     array(), $this->version, 'all' );
			wp_register_style( 'bonipress-bootstrap-grid', plugins_url( 'assets/css/bootstrap-grid.css', boniPRESS_THIS ),      array(), $this->version, 'all' );
			wp_register_style( 'bonipress-forms',          plugins_url( 'assets/css/bonipress-forms.css', boniPRESS_THIS ),        array(), $this->version, 'all' );

			// Scripts
			wp_register_script( 'bonipress-send-points',   plugins_url( 'assets/js/send.js', boniPRESS_THIS ),                 array( 'jquery' ), $this->version, true );
			wp_register_script( 'bonipress-accordion',     plugins_url( 'assets/js/bonipress-accordion.js', boniPRESS_THIS ),     array( 'jquery', 'jquery-ui-core', 'jquery-ui-accordion' ), $this->version );
			wp_register_script( 'jquery-numerator',     plugins_url( 'assets/libs/jquery-numerator.js', boniPRESS_THIS ),   array( 'jquery' ), '0.2.1' );
			wp_register_script( 'bonipress-mustache',      plugins_url( 'assets/libs/mustache.min.js', boniPRESS_THIS ),       array(), '2.2.1' );
			wp_register_script( 'bonipress-widgets',       plugins_url( 'assets/js/bonipress-admin-widgets.js', boniPRESS_THIS ), array( 'jquery', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-droppable' ), $this->version );
			wp_register_script( 'bonipress-edit-balance',  plugins_url( 'assets/js/bonipress-edit-balance.js', boniPRESS_THIS ),  array( 'jquery', 'jquery-ui-core', 'jquery-ui-dialog', 'jquery-effects-core', 'jquery-effects-slide', 'jquery-numerator' ), $this->version );
			wp_register_script( 'bonipress-edit-log',      plugins_url( 'assets/js/bonipress-edit-log.js', boniPRESS_THIS ),      array( 'jquery', 'jquery-ui-core', 'jquery-ui-dialog', 'jquery-effects-core', 'jquery-effects-slide', 'common' ), $this->version );

			do_action( 'bonipress_register_assets' );

		}

		/**
		 * Setup Cron Jobs
		 * @since 1.7
		 * @version 1.0
		 */
		private function setup_cron_jobs() {

			// Add schedule if none exists
			if ( ! wp_next_scheduled( 'bonipress_reset_key' ) )
				wp_schedule_event( time(), apply_filters( 'bonipress_cron_reset_key', 'daily' ), 'bonipress_reset_key' );

		}

		/**
		 * Register Importers
		 * @since 1.7
		 * @version 1.0.1
		 */
		private function register_importers() {

			/**
			 * Register Importer: Log Entries
			 * @since 1.4
			 * @version 1.0
			 */
			register_importer(
				BONIPRESS_SLUG . '-import-log',
				sprintf( __( '%s Protokollimport', 'bonipress' ), bonipress_label() ),
				__( 'Importiere Protokolleinträge über eine CSV-Datei.', 'bonipress' ),
				array( $this, 'import_log_entries' )
			);

			/**
			 * Register Importer: Balances
			 * @since 1.4.2
			 * @version 1.0
			 */
			register_importer(
				BONIPRESS_SLUG . '-import-balance',
				sprintf( __( '%s Saldoimport', 'bonipress' ), bonipress_label() ),
				__( 'Importiere Salden über eine CSV-Datei.', 'bonipress' ),
				array( $this, 'import_balances' )
			);

			/**
			 * Register Importer: CubePoints
			 * @since 1.4
			 * @version 1.0
			 */
			register_importer(
				BONIPRESS_SLUG . '-import-cp',
				sprintf( __( '%s CubePoints-Import', 'bonipress' ), bonipress_label() ),
				__( 'Importiere CubePoints-Protokolleinträge und/oder Salden.', 'bonipress' ),
				array( $this, 'import_cubepoints' )
			);

		}

		/**
		 * Front Enqueue Before
		 * Enqueues scripts and styles that must run before content is loaded.
		 * @since 1.7
		 * @version 1.0
		 */
		public function enqueue_front_before() {

			// Widget Style (can be disabled)
			if ( apply_filters( 'bonipress_remove_widget_css', false ) === false )
				wp_enqueue_style( 'bonipress-front' );

			// Let others play
			do_action( 'bonipress_front_enqueue' );

		}

		/**
		 * Front Enqueue After
		 * Enqueuest that must run after content has loaded.
		 * @since 1.7
		 * @version 1.0
		 */
		public function enqueue_front_after() {

			global $bonipress_sending_points;

			// boniPRESS Send Feature via the bonipress_send shortcode
			if ( $bonipress_sending_points === true || apply_filters( 'bonipress_enqueue_send_js', false ) === true ) {

				$base = array(
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
					'token'   => wp_create_nonce( 'bonipress-send-points' )
				);

				$language = apply_filters( 'bonipress_send_language', array(
					'working' => esc_attr__( 'Wird bearbeitet...', 'bonipress' ),
					'done'    => esc_attr__( 'Gesendet', 'bonipress' ),
					'error'   => esc_attr__( 'Fehler, versuche es erneut', 'bonipress' )
				) );

				wp_localize_script(
					'bonipress-send-points',
					'boniPRESSsend',
					array_merge_recursive( $base, $language )
				);
				wp_enqueue_script( 'bonipress-send-points' );

			}

			do_action( 'bonipress_front_enqueue_footer' );

		}

		/**
		 * Admin Enqueue
		 * @since 1.7
		 * @version 1.0
		 */
		public function enqueue_admin_before() {

			// Let others play
			do_action( 'bonipress_admin_enqueue' );

		}

		/**
		 * Widgets Init
		 * @since 1.7
		 * @version 1.0
		 */
		public function widgets_init() {

			// Balance widget
			$this->file( boniPRESS_WIDGETS_DIR . 'bonipress-widget-balance.php' );
			register_widget( 'boniPRESS_Widget_Balance' );

			// Leaderboard widget
			$this->file( boniPRESS_WIDGETS_DIR . 'bonipress-widget-leaderboard.php' );
			register_widget( 'boniPRESS_Widget_Leaderboard' );

			// If we have more than one point type, the wallet widget
			if ( count( $this->point_types ) > 1 ) {

				$this->file( boniPRESS_WIDGETS_DIR . 'bonipress-widget-wallet.php' );
				register_widget( 'boniPRESS_Widget_Wallet' );

			}

			// Let others play
			do_action( 'bonipress_widgets_init' );

		}

		/**
		 * Admin Init
		 * @since 1.7
		 * @version 1.0
		 */
		public function admin_init() {

			// Sudden change of version number indicates an update
			$bonipress_version = get_option( 'bonipress_version', $this->version );
			if ( $bonipress_version != $this->version )
				do_action( 'bonipress_reactivation', $bonipress_version );

			// Dashboard Overview
			$this->file( boniPRESS_INCLUDES_DIR . 'bonipress-overview.php' );

			// Importers
			if ( defined( 'WP_LOAD_IMPORTERS' ) )
				$this->register_importers();

			// Let others play
			do_action( 'bonipress_admin_init' );

			// When the plugin is activated after an update, redirect to the about page
			// Checks for the _bonipress_activation_redirect transient
			if ( get_transient( '_bonipress_activation_redirect' ) === apply_filters( 'bonipress_active_redirect', false ) )
				return;

			delete_transient( '_bonipress_activation_redirect' );

			wp_safe_redirect( add_query_arg( array( 'page' => BONIPRESS_SLUG . '-about' ), admin_url( 'index.php' ) ) );
			die;
		}

		/**
		 * Load Importer: Log Entries
		 * @since 1.4
		 * @version 1.1
		 */
		public function import_log_entries() {

			$this->file( ABSPATH . 'wp-admin/includes/import.php' );

			if ( ! class_exists( 'WP_Importer' ) )
				$this->file( ABSPATH . 'wp-admin/includes/class-wp-importer.php' );

			$this->file( boniPRESS_IMPORTERS_DIR . 'bonipress-log-entries.php' );
	
			$importer = new boniPRESS_Importer_Log_Entires();
			$importer->load();

		}

		/**
		 * Load Importer: Point Balances
		 * @since 1.4
		 * @version 1.1
		 */
		public function import_balances() {

			$this->file( ABSPATH . 'wp-admin/includes/import.php' );

			if ( ! class_exists( 'WP_Importer' ) )
				$this->file( ABSPATH . 'wp-admin/includes/class-wp-importer.php' );

			$this->file( boniPRESS_IMPORTERS_DIR . 'bonipress-balances.php' );

			$importer = new boniPRESS_Importer_Balances();
			$importer->load();

		}

		/**
		 * Load Importer: CubePoints
		 * @since 1.4
		 * @version 1.1.1
		 */
		public function import_cubepoints() {

			$this->file( ABSPATH . 'wp-admin/includes/import.php' );

			if ( ! class_exists( 'WP_Importer' ) )
				$this->file( ABSPATH . 'wp-admin/includes/class-wp-importer.php' );

			$this->file( boniPRESS_IMPORTERS_DIR . 'bonipress-cubepoints.php' );

			$importer = new boniPRESS_Importer_CubePoints();
			$importer->load();

		}

		/**
		 * Admin Menu
		 * @since 1.7
		 * @version 1.0
		 */
		public function adjust_admin_menu() {

			global $bonipress, $wp_version;

			$pages     = array();
			$name      = bonipress_label( true );
			$menu_icon = 'dashicons-star-filled';

			if ( version_compare( $wp_version, '3.8', '<' ) )
				$menu_icon = '';

			// Add skeleton menus for each point type so modules can
			// insert their content under each of these menus
			foreach ( $this->point_types as $type_id => $title ) {

				$type_slug = BONIPRESS_SLUG;
				if ( $type_id != BONIPRESS_DEFAULT_TYPE_KEY )
					$type_slug = BONIPRESS_SLUG . '_' . trim( $type_id );

				$pages[] = add_menu_page(
					$title,
					$title,
					$bonipress->get_point_editor_capability(),
					$type_slug,
					'',
					$menu_icon
				);

			}

			// Add about page
			$pages[]   = add_dashboard_page(
				sprintf( __( 'Über %s', 'bonipress' ), $name ),
				sprintf( __( 'Über %s', 'bonipress' ), $name ),
				'moderate_comments',
				BONIPRESS_SLUG . '-about',
				'bonipress_about_page'
			);

			// Add styling to our admin screens
			$pages = apply_filters( 'bonipress_admin_pages', $pages, $bonipress );
			foreach ( $pages as $page )
				add_action( 'admin_print_styles-' . $page, array( $this, 'fix_admin_page_styles' ) );

			// Let others play
			do_action( 'bonipress_add_menu', $bonipress );

		}

		/**
		 * Toolbar
		 * @since 1.7
		 * @version 1.0.1
		 */
		public function adjust_toolbar( $wp_admin_bar ) {

			if ( ! is_user_logged_in() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || apply_filters( 'bonipress_admin_show_balance', true ) === false ) return;

			global $bonipress;

			$user_id      = get_current_user_id();
			$usable_types = bonipress_get_usable_types( $user_id );
			$history_url  = admin_url( 'users.php' );

			if ( empty( $usable_types ) ) return;

			$using_buddypress = false;
			if ( function_exists( 'bp_loggedin_user_domain' ) )
				$using_buddypress = true;

			$main_label = __( 'Balance', 'bonipress' );
			if ( count( $usable_types ) == 1 )
				$main_label = $bonipress->plural();

			// BuddyPress
			if ( $using_buddypress ) {

				$wp_admin_bar->add_menu( array(
					'parent' => 'my-account-buddypress',
					'id'     => BONIPRESS_SLUG . '-account',
					'title'  => $main_label,
					'href'   => false
				) );

				if ( isset( $bonipress->buddypress['history_url'] ) && ! empty( $bonipress->buddypress['history_url'] ) )
					$history_url = bp_loggedin_user_domain() . $bonipress->buddypress['history_url'] . '/';

				// Disable history url until the variable needed is setup
				else
					$history_url = '';

			}

			// Default
			else {

				$wp_admin_bar->add_menu( array(
					'parent' => 'my-account',
					'id'     => BONIPRESS_SLUG . '-account',
					'title'  => $main_label,
					'meta'   => array( 'class' => 'ab-sub-secondary' )
				) );

			}

			// Add balance and history link for each point type
			foreach ( $usable_types as $type_id ) {

				// Make sure we want to show the balance.
				if ( apply_filters( 'bonipress_admin_show_balance_' . $type_id, true ) === false ) continue;

				if ( $type_id === BONIPRESS_DEFAULT_TYPE_KEY )
					$point_type = $bonipress;
				else
					$point_type = bonipress( $type_id );

				$history_url = add_query_arg( array( 'page' => $type_id . '-history' ), admin_url( 'users.php' ) );
				if ( $using_buddypress && isset( $bonipress->buddypress['history_url'] )  )
					$history_url = add_query_arg( array( 'show-ctype' => $type_id ), bp_loggedin_user_domain() . $bonipress->buddypress['history_url'] . '/' );

				$balance          = $point_type->get_users_balance( $user_id, $type_id );
				$history_url      = apply_filters( 'bonipress_my_history_url', $history_url, $type_id, $point_type );
				$adminbar_menu_id = str_replace( '_', '-', $type_id );

				// Show balance
				$wp_admin_bar->add_menu( array(
					'parent' => BONIPRESS_SLUG . '-account',
					'id'     => BONIPRESS_SLUG . '-account-balance-' . $adminbar_menu_id,
					'title'  => $point_type->template_tags_amount( apply_filters( 'bonipress_label_my_balance', '%plural%: %cred_f%', $user_id, $point_type ), $balance ),
					'href'   => false
				) );

				// Verlauf link
				if ( $history_url != '' && apply_filters( 'bonipress_admin_show_history_' . $type_id, true ) === true )
					$wp_admin_bar->add_menu( array(
						'parent' => BONIPRESS_SLUG . '-account',
						'id'     => BONIPRESS_SLUG . '-account-history-' . $adminbar_menu_id,
						'title'  => sprintf( '%s %s', $point_type->plural(), __( 'Verlauf', 'bonipress' ) ),
						'href'   => $history_url
					) );

			}
	
			// Let others play
			do_action( 'bonipress_tool_bar', $wp_admin_bar, $bonipress );

		}

		/**
		 * Cron: Reset Encryption Key
		 * @since 1.2
		 * @version 1.0
		 */
		public function cron_reset_key() {

			$protect = bonipress_protect();
			if ( $protect !== false )
				$protect->reset_key();

		}

		/**
		 * Cron: Delete Leaderboard Cache
		 * @since 1.7.9.1
		 * @version 1.1
		 */
		public function cron_delete_leaderboard_cache() {

			// If leaderboards are cached daily, time to reset. This is the only option currently supported
			if ( defined( 'BONIPRESS_CACHE_LEADERBOARDS' ) && BONIPRESS_CACHE_LEADERBOARDS === 'daily' ) {

				global $wpdb;

				$table = bonipress_get_db_column( 'options' );
				$wpdb->query( "DELETE FROM {$table} WHERE option_name LIKE 'leaderboard-%';" );

			}

			do_action( 'bonipress_cron_leaderboard_cache' );

		}

		/**
		 * FIX: Add admin page style
		 * @since 1.7
		 * @version 1.0
		 */
		public function fix_admin_page_styles() {

			wp_enqueue_style( 'bonipress-admin' );

		}

		/**
		 * Plugin Links
		 * @since 1.7
		 * @version 1.0
		 */
		public function plugin_links( $actions, $plugin_file, $plugin_data, $context ) {

			// Link to Setup
			if ( ! bonipress_is_installed() )
				$actions['_setup'] = '<a href="' . admin_url( 'plugins.php?page=' . BONIPRESS_SLUG . '-setup' ) . '">' . __( 'Einrichten', 'bonipress' ) . '</a>';
			else
				$actions['_settings'] = '<a href="' . admin_url( 'admin.php?page=' . BONIPRESS_SLUG . '-settings' ) . '" >' . __( 'Einstellungen', 'bonipress' ) . '</a>';

			ksort( $actions );
			return $actions;

		}

		/**
		 * Plugin Description Links
		 * @since 1.7
		 * @version 1.0.2
		 */
		public function plugin_description_links( $links, $file ) {

			if ( $file != plugin_basename( boniPRESS_THIS ) ) return $links;

			// Link to Setup
			if ( ! is_bonipress_ready() ) {

				$links[] = '<a href="' . admin_url( 'plugins.php?page=' . BONIPRESS_SLUG . '-setup' ) . '">' . __( 'Einrichten', 'bonipress' ) . '</a>';
				return $links;

			}

			// Usefull links
			$links[] = '<a href="http://codex.bonipress.me/" target="_blank">Documentation</a>';
			$links[] = '<a href="https://bonipress.me/store/" target="_blank">Store</a>';

			return $links;

		}

	}
endif;

function bonipress_core() {
	return boniPRESS_Core::instance();
}
bonipress_core();
