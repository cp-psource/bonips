<?php
/**
 * Plugin Name: BoniPress
 * Plugin URI: https://bonips.me
 * Description: Ein adaptives Punkteverwaltungssystem für WordPress-basierte Webseiten, Basiscode von myCred.
 * Version: 1.8.8
 * Tags: point, credit, loyalty program, engagement, reward, woocommerce rewards
 * Author: DerN3rd
 * Author URI: https://github.com/cp-psource
 * Requires at least: WP 4.9
 * Tested up to: WP 5.1
 * Text Domain: bonips
 * Domain Path: /lang
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */


require 'psource/psource-plugin-update/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/cp-psource/bonips',
	__FILE__,
	'bonips'
);

//Set the branch that contains the stable release.
$myUpdateChecker->setBranch('master');

if ( ! class_exists( 'boniPS_Core' ) ) :
	final class boniPS_Core {

		// Plugin Version
		public $version             = '1.8.8';

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
				_doing_it_wrong( 'boniPS_Core->define()', 'Konnte nicht definieren: ' . $name . ' wie es schon woanders definiert ist!', '1.7' );
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
				_doing_it_wrong( 'boniPS_Core->file()', 'Angeforderte Datei ' . $required_file . ' nicht gefunden.', '1.7' );
		}

		/**
		 * Construct
		 * @since 1.7
		 * @version 1.0
		 */
		public function __construct() {

			$this->define_constants();
			$this->includes();

			// Multisite Feature: If the site is blocked from using boniPS, exit now
			if ( bonips_is_site_blocked() ) return;

			// Register plugin hooks
			register_activation_hook(   boniPS_THIS, 'bonips_plugin_activation' );
			register_deactivation_hook( boniPS_THIS, 'bonips_plugin_deactivation' );
			register_uninstall_hook(    boniPS_THIS, 'bonips_plugin_uninstall' );

			// If boniPS is ready to be used
			if ( is_bonips_ready() ) {

				$this->internal();
				$this->wordpress();

				do_action( 'bonips_ready' );

			}

			// We need to run the setup
			else {

				// Load translation and register assets for the setup
				add_action( 'init',                    array( $this, 'load_plugin_textdomain' ), 10 );
				add_action( 'init',                    array( $this, 'register_assets' ), 20 );
				add_filter( 'bonips_maybe_install_db', '__return_false' );

				// Load the setup module
				$this->file( boniPS_INCLUDES_DIR . 'bonips-setup.php' );

				$setup = new boniPS_Setup();
				$setup->load();

			}

			// Plugin Related
			add_filter( 'plugin_action_links_bonips/bonips.php', array( $this, 'plugin_links' ), 10, 4 );
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
			$this->define( 'boniPS_VERSION',              $this->version );
			$this->define( 'boniPS_DB_VERSION',           '1.0' );
			$this->define( 'BONIPS_SLUG',                 'bonips' );
			$this->define( 'BONIPS_DEFAULT_LABEL',        'boniPS' );
			$this->define( 'BONIPS_DEFAULT_TYPE_KEY',     'bonips_default' );
			$this->define( 'BONIPS_SHOW_PREMIUM_ADDONS',  true );
			$this->define( 'BONIPS_FOR_OLDER_WP',         false );
			$this->define( 'BONIPS_MIN_TIME_LIMIT',       3 );
			$this->define( 'BONIPS_ENABLE_TOTAL_BALANCE', true );
			$this->define( 'BONIPS_ENABLE_LOGGING',       true );
			$this->define( 'BONIPS_ENABLE_SHORTCODES',    true );
			$this->define( 'BONIPS_ENABLE_HOOKS',         true );
			$this->define( 'BONIPS_UNINSTALL_LOG',        true );
			$this->define( 'BONIPS_UNINSTALL_CREDS',      true );
			$this->define( 'BONIPS_DISABLE_PROTECTION',   false );
			$this->define( 'BONIPS_CACHE_LEADERBOARDS',   false );
			$this->define( 'BONIPS_MAX_HISTORY_SIZE',     100 );

			// Not ok to override
			$this->define( 'boniPS_THIS',                 __FILE__, false );
			$this->define( 'boniPS_ROOT_DIR',             plugin_dir_path( boniPS_THIS ), false );
			$this->define( 'boniPS_ABSTRACTS_DIR',        boniPS_ROOT_DIR . 'abstracts/', false );
			$this->define( 'boniPS_ADDONS_DIR',           boniPS_ROOT_DIR . 'addons/', false );
			$this->define( 'boniPS_ASSETS_DIR',           boniPS_ROOT_DIR . 'assets/', false );
			$this->define( 'boniPS_INCLUDES_DIR',         boniPS_ROOT_DIR . 'includes/', false );
			$this->define( 'boniPS_LANG_DIR',             boniPS_ROOT_DIR . 'lang/', false );
			$this->define( 'boniPS_MODULES_DIR',          boniPS_ROOT_DIR . 'modules/', false );
			$this->define( 'boniPS_CLASSES_DIR',          boniPS_INCLUDES_DIR . 'classes/', false );
			$this->define( 'boniPS_IMPORTERS_DIR',        boniPS_INCLUDES_DIR . 'importers/', false );
			$this->define( 'boniPS_SHORTCODES_DIR',       boniPS_INCLUDES_DIR . 'shortcodes/', false );
			$this->define( 'boniPS_WIDGETS_DIR',          boniPS_INCLUDES_DIR . 'widgets/', false );
			$this->define( 'boniPS_HOOKS_DIR',            boniPS_INCLUDES_DIR . 'hooks/', false );
			$this->define( 'boniPS_PLUGINS_DIR',          boniPS_HOOKS_DIR . 'external/', false );

		}

		/**
		 * Include Plugin Files
		 * @since 1.7
		 * @version 1.1
		 */
		public function includes() {

			$this->file( boniPS_INCLUDES_DIR . 'bonips-functions.php' );

			$this->file( boniPS_CLASSES_DIR . 'class.query-log.php' );
			$this->file( boniPS_CLASSES_DIR . 'class.query-export.php' );
			$this->file( boniPS_CLASSES_DIR . 'class.query-leaderboard.php' );

			$this->file( boniPS_ABSTRACTS_DIR . 'bonips-abstract-hook.php' );
			$this->file( boniPS_ABSTRACTS_DIR . 'bonips-abstract-module.php' );
			$this->file( boniPS_ABSTRACTS_DIR . 'bonips-abstract-object.php' );

			// Multisite Feature - Option to block usage of boniPS on a particular site
			if ( ! bonips_is_site_blocked() ) {

				// Core
				$this->file( boniPS_INCLUDES_DIR . 'bonips-object.php' );
				$this->file( boniPS_INCLUDES_DIR . 'bonips-remote.php' );
				$this->file( boniPS_INCLUDES_DIR . 'bonips-protect.php' );
				$this->file( boniPS_INCLUDES_DIR . 'bonips-about.php' );

				// If boniPS has been setup and is ready to begin
				if ( bonips_is_installed() ) {

					// Modules
					$this->file( boniPS_MODULES_DIR . 'bonips-module-addons.php' );
					$this->file( boniPS_MODULES_DIR . 'bonips-module-settings.php' );
					$this->file( boniPS_MODULES_DIR . 'bonips-module-hooks.php' );
					$this->file( boniPS_MODULES_DIR . 'bonips-module-log.php' );
					$this->file( boniPS_MODULES_DIR . 'bonips-module-export.php' );
					$this->file( boniPS_MODULES_DIR . 'bonips-module-management.php' );
					$this->file( boniPS_MODULES_DIR . 'bonips-module-caching.php' );

					if ( is_multisite() ) {

						$this->file( boniPS_MODULES_DIR . 'bonips-module-network.php' );

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

			if ( BONIPS_ENABLE_HOOKS === false ) return;

			// Built-in Hooks
			$this->file( boniPS_HOOKS_DIR . 'bonips-hook-anniversary.php' );
			$this->file( boniPS_HOOKS_DIR . 'bonips-hook-comments.php' );
			$this->file( boniPS_HOOKS_DIR . 'bonips-hook-delete-content.php' );
			$this->file( boniPS_HOOKS_DIR . 'bonips-hook-link-clicks.php' );
			$this->file( boniPS_HOOKS_DIR . 'bonips-hook-logins.php' );
			$this->file( boniPS_HOOKS_DIR . 'bonips-hook-publishing-content.php' );
			$this->file( boniPS_HOOKS_DIR . 'bonips-hook-referrals.php' );
			$this->file( boniPS_HOOKS_DIR . 'bonips-hook-registrations.php' );
			$this->file( boniPS_HOOKS_DIR . 'bonips-hook-site-visits.php' );
			$this->file( boniPS_HOOKS_DIR . 'bonips-hook-view-content.php' );
			$this->file( boniPS_HOOKS_DIR . 'bonips-hook-watching-video.php' );

			// Supported plugins
			$this->file( boniPS_PLUGINS_DIR . 'bonips-hook-affiliatewp.php' );
			$this->file( boniPS_PLUGINS_DIR . 'bonips-hook-badgeOS.php' );
			$this->file( boniPS_PLUGINS_DIR . 'bonips-hook-PSForum.php' );
			$this->file( boniPS_PLUGINS_DIR . 'bonips-hook-buddypress-media.php' );
			$this->file( boniPS_PLUGINS_DIR . 'bonips-hook-buddypress.php' );
			$this->file( boniPS_PLUGINS_DIR . 'bonips-hook-contact-form7.php' );
			$this->file( boniPS_PLUGINS_DIR . 'bonips-hook-events-manager-light.php' );
			$this->file( boniPS_PLUGINS_DIR . 'bonips-hook-gravityforms.php' );
			$this->file( boniPS_PLUGINS_DIR . 'bonips-hook-invite-anyone.php' );
			$this->file( boniPS_PLUGINS_DIR . 'bonips-hook-jetpack.php' );
			$this->file( boniPS_PLUGINS_DIR . 'bonips-hook-simplepress.php' );
			$this->file( boniPS_PLUGINS_DIR . 'bonips-hook-woocommerce.php' );
			$this->file( boniPS_PLUGINS_DIR . 'bonips-hook-wp-favorite-posts.php' );
			$this->file( boniPS_PLUGINS_DIR . 'bonips-hook-wp-polls.php' );
			$this->file( boniPS_PLUGINS_DIR . 'bonips-hook-wp-postratings.php' );

		}

		/**
		 * Internal Setup
		 * @since 1.7
		 * @version 1.0
		 */
		private function internal() {

			$this->point_types = bonips_get_types( true );
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

			global $bonips, $bonips_log_table, $bonips_types, $bonips_modules, $bonips_label, $bonips_network;

			$bonips             = new boniPS_Settings();
			$bonips_log_table   = $bonips->log_table;
			$bonips_types       = $this->point_types;
			$bonips_label       = apply_filters( 'bonips_label', BONIPS_DEFAULT_LABEL );
			$bonips_modules     = $this->modules;
			$bonips_network     = bonips_get_settings_network();

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

			add_action( 'bonips_reset_key',  array( $this, 'cron_reset_key' ), 10 );
			add_action( 'bonips_reset_key',  array( $this, 'cron_delete_leaderboard_cache' ), 20 );

		}

		/**
		 * After Plugins Loaded
		 * Used to setup modules that are not replacable.
		 * @since 1.7
		 * @version 1.0
		 */
		public function after_plugin() {

			$this->modules['solo']['addons'] = new boniPS_Addons_Module();
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

			global $bonips, $bonips_modules;

			// Lets start with Multisite
			if ( is_multisite() ) {

				// Normally the is_plugin_active_for_network() function is only available in the admin area
				if ( ! function_exists( 'is_plugin_active_for_network' ) )
					$this->file( ABSPATH . '/wp-admin/includes/plugin.php' );

				// The network "module" is only needed if the plugin is activated network wide
				if ( is_plugin_active_for_network( 'bonips/bonips.php' ) ) {
					$this->modules['solo']['network'] = new boniPS_Network_Module();
					$this->modules['solo']['network']->load();
				}

			}

			// The log module can not be loaded if logging is disabled
			if ( BONIPS_ENABLE_LOGGING ) {

				// Attach the log to each point type we use
				foreach ( $this->point_types as $type => $title ) {
					$this->modules['type'][ $type ]['log'] = new boniPS_Log_Module( $type );
					$this->modules['type'][ $type ]['log']->load();
				}

			}

			// Option to disable hooks
			if ( BONIPS_ENABLE_HOOKS ) {

				$this->include_hooks();

				do_action( 'bonips_load_hooks' );

				// Attach hooks module to each point type we use
				foreach ( $this->point_types as $type => $title ) {
					$this->modules['type'][ $type ]['hooks'] = new boniPS_Hooks_Module( $type );
					$this->modules['type'][ $type ]['hooks']->load();
				}

			}

			// Attach each module to each point type we use
			foreach ( $this->point_types as $type => $title ) {

				$this->modules['type'][ $type ]['settings'] = new boniPS_Settings_Module( $type );
				$this->modules['type'][ $type ]['settings']->load();

				$this->modules['solo'][ $type ] = new boniPS_Caching_Module( $type );
				$this->modules['solo'][ $type ]->load();

			}

			// Attach the Management module to the main point type
			$this->modules['type'][ BONIPS_DEFAULT_TYPE_KEY ]['management'] = new boniPS_Management_Module();
			$this->modules['type'][ BONIPS_DEFAULT_TYPE_KEY ]['management']->load();

			// Attach BuddyPress module to the main point type only
			if ( class_exists( 'BuddyPress' ) ) {

				$this->file( boniPS_MODULES_DIR . 'bonips-module-buddypress.php' );
				$this->modules['type'][ BONIPS_DEFAULT_TYPE_KEY ]['buddypress'] = new boniPS_BuddyPress_Module( BONIPS_DEFAULT_TYPE_KEY );
				$this->modules['type'][ BONIPS_DEFAULT_TYPE_KEY ]['buddypress']->load();

			}

			$bonips_modules = $this->modules['type'];

			// The export module can not be loaded if logging is disabled
			if ( BONIPS_ENABLE_LOGGING ) {

				// Load Export module
				$this->modules['solo']['exports'] = new boniPS_Export_Module();
				$this->modules['solo']['exports']->load();

			}

			// Let third-parties register and load custom boniPS modules
			$bonips_modules = apply_filters( 'bonips_load_modules', $this->modules, $this->point_types );

			// Let others play
			do_action( 'bonips_pre_init' );

		}

		/**
		 * Load Shortcodes
		 * @since 1.7
		 * @version 1.1
		 */
		public function load_shortcodes() {

			if ( BONIPS_ENABLE_SHORTCODES ) {

				$this->file( boniPS_SHORTCODES_DIR . 'bonips_exchange.php' );
				$this->file( boniPS_SHORTCODES_DIR . 'bonips_hide_if.php' );
				$this->file( boniPS_SHORTCODES_DIR . 'bonips_leaderboard_position.php' );
				$this->file( boniPS_SHORTCODES_DIR . 'bonips_leaderboard.php' );
				$this->file( boniPS_SHORTCODES_DIR . 'bonips_my_balance.php' );
				$this->file( boniPS_SHORTCODES_DIR . 'bonips_send.php' );
				$this->file( boniPS_SHORTCODES_DIR . 'bonips_show_if.php' );
				$this->file( boniPS_SHORTCODES_DIR . 'bonips_total_balance.php' );

				// These shortcodes will not work if logging is disabled
				if ( BONIPS_ENABLE_LOGGING ) {

					$this->file( boniPS_SHORTCODES_DIR . 'bonips_best_user.php' );
					$this->file( boniPS_SHORTCODES_DIR . 'bonips_give.php' );
					$this->file( boniPS_SHORTCODES_DIR . 'bonips_history.php' );
					$this->file( boniPS_SHORTCODES_DIR . 'bonips_total_points.php' );
					$this->file( boniPS_SHORTCODES_DIR . 'bonips_total_since.php' );

				}

				// These shortcodes will not work if hooks are disabled
				if ( BONIPS_ENABLE_HOOKS ) {

					$this->file( boniPS_SHORTCODES_DIR . 'bonips_affiliate_id.php' );
					$this->file( boniPS_SHORTCODES_DIR . 'bonips_affiliate_link.php' );
					$this->file( boniPS_SHORTCODES_DIR . 'bonips_link.php' );
					$this->file( boniPS_SHORTCODES_DIR . 'bonips_video.php' );
					$this->file( boniPS_SHORTCODES_DIR . 'bonips_hook_table.php' );

				}

				do_action( 'bonips_load_shortcode' );

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
			do_action( 'bonips_init' );

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
			if ( ! did_action( 'init' ) || did_action( 'bonips_set_globals' ) ) return;

			if ( is_user_logged_in() )
				bonips_set_current_account();

			do_action( 'bonips_set_globals' );

		}

		/**
		 * Load Plugin Textdomain
		 * @since 1.7
		 * @version 1.0
		 */
		public function load_plugin_textdomain() {

			// Load Translation
			$locale = apply_filters( 'plugin_locale', get_locale(), 'bonips' );

			load_textdomain( 'bonips', WP_LANG_DIR . '/bonips/bonips-' . $locale . '.mo' );
			load_plugin_textdomain( 'bonips', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );

		}

		/**
		 * Register Assets
		 * @since 1.7
		 * @version 1.0
		 */
		public function register_assets() {

			// Styles
			wp_register_style( 'bonips-front',          plugins_url( 'assets/css/bonips-front.css', boniPS_THIS ),        array(), $this->version, 'all' );
			wp_register_style( 'bonips-admin',          plugins_url( 'assets/css/bonips-admin.css', boniPS_THIS ),        array(), $this->version, 'all' );
			wp_register_style( 'bonips-edit-balance',   plugins_url( 'assets/css/bonips-edit-balance.css', boniPS_THIS ), array(), $this->version, 'all' );
			wp_register_style( 'bonips-edit-log',       plugins_url( 'assets/css/bonips-edit-log.css', boniPS_THIS ),     array(), $this->version, 'all' );
			wp_register_style( 'bonips-bootstrap-grid', plugins_url( 'assets/css/bootstrap-grid.css', boniPS_THIS ),      array(), $this->version, 'all' );
			wp_register_style( 'bonips-forms',          plugins_url( 'assets/css/bonips-forms.css', boniPS_THIS ),        array(), $this->version, 'all' );

			// Scripts
			wp_register_script( 'bonips-send-points',   plugins_url( 'assets/js/send.js', boniPS_THIS ),                 array( 'jquery' ), $this->version, true );
			wp_register_script( 'bonips-accordion',     plugins_url( 'assets/js/bonips-accordion.js', boniPS_THIS ),     array( 'jquery', 'jquery-ui-core', 'jquery-ui-accordion' ), $this->version );
			wp_register_script( 'jquery-numerator',     plugins_url( 'assets/libs/jquery-numerator.js', boniPS_THIS ),   array( 'jquery' ), '0.2.1' );
			wp_register_script( 'bonips-mustache',      plugins_url( 'assets/libs/mustache.min.js', boniPS_THIS ),       array(), '2.2.1' );
			wp_register_script( 'bonips-widgets',       plugins_url( 'assets/js/bonips-admin-widgets.js', boniPS_THIS ), array( 'jquery', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-droppable' ), $this->version );
			wp_register_script( 'bonips-edit-balance',  plugins_url( 'assets/js/bonips-edit-balance.js', boniPS_THIS ),  array( 'jquery', 'jquery-ui-core', 'jquery-ui-dialog', 'jquery-effects-core', 'jquery-effects-slide', 'jquery-numerator' ), $this->version );
			wp_register_script( 'bonips-edit-log',      plugins_url( 'assets/js/bonips-edit-log.js', boniPS_THIS ),      array( 'jquery', 'jquery-ui-core', 'jquery-ui-dialog', 'jquery-effects-core', 'jquery-effects-slide', 'common' ), $this->version );

			do_action( 'bonips_register_assets' );

		}

		/**
		 * Setup Cron Jobs
		 * @since 1.7
		 * @version 1.0
		 */
		private function setup_cron_jobs() {

			// Add schedule if none exists
			if ( ! wp_next_scheduled( 'bonips_reset_key' ) )
				wp_schedule_event( time(), apply_filters( 'bonips_cron_reset_key', 'daily' ), 'bonips_reset_key' );

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
				BONIPS_SLUG . '-import-log',
				sprintf( __( '%s Protokollimport', 'bonips' ), bonips_label() ),
				__( 'Importiere Protokolleinträge über eine CSV-Datei.', 'bonips' ),
				array( $this, 'import_log_entries' )
			);

			/**
			 * Register Importer: Balances
			 * @since 1.4.2
			 * @version 1.0
			 */
			register_importer(
				BONIPS_SLUG . '-import-balance',
				sprintf( __( '%s Saldoimport', 'bonips' ), bonips_label() ),
				__( 'Importiere Salden über eine CSV-Datei.', 'bonips' ),
				array( $this, 'import_balances' )
			);

			/**
			 * Register Importer: CubePoints
			 * @since 1.4
			 * @version 1.0
			 */
			register_importer(
				BONIPS_SLUG . '-import-cp',
				sprintf( __( '%s CubePoints-Import', 'bonips' ), bonips_label() ),
				__( 'Importiere CubePoints-Protokolleinträge und/oder Salden.', 'bonips' ),
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
			if ( apply_filters( 'bonips_remove_widget_css', false ) === false )
				wp_enqueue_style( 'bonips-front' );

			// Let others play
			do_action( 'bonips_front_enqueue' );

		}

		/**
		 * Front Enqueue After
		 * Enqueuest that must run after content has loaded.
		 * @since 1.7
		 * @version 1.0
		 */
		public function enqueue_front_after() {

			global $bonips_sending_points;

			// boniPS Send Feature via the bonips_send shortcode
			if ( $bonips_sending_points === true || apply_filters( 'bonips_enqueue_send_js', false ) === true ) {

				$base = array(
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
					'token'   => wp_create_nonce( 'bonips-send-points' )
				);

				$language = apply_filters( 'bonips_send_language', array(
					'working' => esc_attr__( 'Wird bearbeitet...', 'bonips' ),
					'done'    => esc_attr__( 'Gesendet', 'bonips' ),
					'error'   => esc_attr__( 'Fehler, versuche es erneut', 'bonips' )
				) );

				wp_localize_script(
					'bonips-send-points',
					'boniPSsend',
					array_merge_recursive( $base, $language )
				);
				wp_enqueue_script( 'bonips-send-points' );

			}

			do_action( 'bonips_front_enqueue_footer' );

		}

		/**
		 * Admin Enqueue
		 * @since 1.7
		 * @version 1.0
		 */
		public function enqueue_admin_before() {

			// Let others play
			do_action( 'bonips_admin_enqueue' );

		}

		/**
		 * Widgets Init
		 * @since 1.7
		 * @version 1.0
		 */
		public function widgets_init() {

			// Balance widget
			$this->file( boniPS_WIDGETS_DIR . 'bonips-widget-balance.php' );
			register_widget( 'boniPS_Widget_Balance' );

			// Leaderboard widget
			$this->file( boniPS_WIDGETS_DIR . 'bonips-widget-leaderboard.php' );
			register_widget( 'boniPS_Widget_Leaderboard' );

			// If we have more than one point type, the wallet widget
			if ( count( $this->point_types ) > 1 ) {

				$this->file( boniPS_WIDGETS_DIR . 'bonips-widget-wallet.php' );
				register_widget( 'boniPS_Widget_Wallet' );

			}

			// Let others play
			do_action( 'bonips_widgets_init' );

		}

		/**
		 * Admin Init
		 * @since 1.7
		 * @version 1.0
		 */
		public function admin_init() {

			// Sudden change of version number indicates an update
			$bonips_version = get_option( 'bonips_version', $this->version );
			if ( $bonips_version != $this->version )
				do_action( 'bonips_reactivation', $bonips_version );

			// Dashboard Overview
			$this->file( boniPS_INCLUDES_DIR . 'bonips-overview.php' );

			// Importers
			if ( defined( 'WP_LOAD_IMPORTERS' ) )
				$this->register_importers();

			// Let others play
			do_action( 'bonips_admin_init' );

			// When the plugin is activated after an update, redirect to the about page
			// Checks for the _bonips_activation_redirect transient
			if ( get_transient( '_bonips_activation_redirect' ) === apply_filters( 'bonips_active_redirect', false ) )
				return;

			delete_transient( '_bonips_activation_redirect' );

			wp_safe_redirect( add_query_arg( array( 'page' => BONIPS_SLUG . '-about' ), admin_url( 'index.php' ) ) );
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

			$this->file( boniPS_IMPORTERS_DIR . 'bonips-log-entries.php' );
	
			$importer = new boniPS_Importer_Log_Entires();
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

			$this->file( boniPS_IMPORTERS_DIR . 'bonips-balances.php' );

			$importer = new boniPS_Importer_Balances();
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

			$this->file( boniPS_IMPORTERS_DIR . 'bonips-cubepoints.php' );

			$importer = new boniPS_Importer_CubePoints();
			$importer->load();

		}

		/**
		 * Admin Menu
		 * @since 1.7
		 * @version 1.0
		 */
		public function adjust_admin_menu() {

			global $bonips, $wp_version;

			$pages     = array();
			$name      = bonips_label( true );
			$menu_icon = 'dashicons-star-filled';

			if ( version_compare( $wp_version, '3.8', '<' ) )
				$menu_icon = '';

			// Add skeleton menus for each point type so modules can
			// insert their content under each of these menus
			foreach ( $this->point_types as $type_id => $title ) {

				$type_slug = BONIPS_SLUG;
				if ( $type_id != BONIPS_DEFAULT_TYPE_KEY )
					$type_slug = BONIPS_SLUG . '_' . trim( $type_id );

				$pages[] = add_menu_page(
					$title,
					$title,
					$bonips->get_point_editor_capability(),
					$type_slug,
					'',
					$menu_icon
				);

			}

			// Add about page
			$pages[]   = add_dashboard_page(
				sprintf( __( 'Über %s', 'bonips' ), $name ),
				sprintf( __( 'Über %s', 'bonips' ), $name ),
				'moderate_comments',
				BONIPS_SLUG . '-about',
				'bonips_about_page'
			);

			// Add styling to our admin screens
			$pages = apply_filters( 'bonips_admin_pages', $pages, $bonips );
			foreach ( $pages as $page )
				add_action( 'admin_print_styles-' . $page, array( $this, 'fix_admin_page_styles' ) );

			// Let others play
			do_action( 'bonips_add_menu', $bonips );

		}

		/**
		 * Toolbar
		 * @since 1.7
		 * @version 1.0.1
		 */
		public function adjust_toolbar( $wp_admin_bar ) {

			if ( ! is_user_logged_in() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || apply_filters( 'bonips_admin_show_balance', true ) === false ) return;

			global $bonips;

			$user_id      = get_current_user_id();
			$usable_types = bonips_get_usable_types( $user_id );
			$history_url  = admin_url( 'users.php' );

			if ( empty( $usable_types ) ) return;

			$using_buddypress = false;
			if ( function_exists( 'bp_loggedin_user_domain' ) )
				$using_buddypress = true;

			$main_label = __( 'Balance', 'bonips' );
			if ( count( $usable_types ) == 1 )
				$main_label = $bonips->plural();

			// BuddyPress
			if ( $using_buddypress ) {

				$wp_admin_bar->add_menu( array(
					'parent' => 'my-account-buddypress',
					'id'     => BONIPS_SLUG . '-account',
					'title'  => $main_label,
					'href'   => false
				) );

				if ( isset( $bonips->buddypress['history_url'] ) && ! empty( $bonips->buddypress['history_url'] ) )
					$history_url = bp_loggedin_user_domain() . $bonips->buddypress['history_url'] . '/';

				// Disable history url until the variable needed is setup
				else
					$history_url = '';

			}

			// Default
			else {

				$wp_admin_bar->add_menu( array(
					'parent' => 'my-account',
					'id'     => BONIPS_SLUG . '-account',
					'title'  => $main_label,
					'meta'   => array( 'class' => 'ab-sub-secondary' )
				) );

			}

			// Add balance and history link for each point type
			foreach ( $usable_types as $type_id ) {

				// Make sure we want to show the balance.
				if ( apply_filters( 'bonips_admin_show_balance_' . $type_id, true ) === false ) continue;

				if ( $type_id === BONIPS_DEFAULT_TYPE_KEY )
					$point_type = $bonips;
				else
					$point_type = bonips( $type_id );

				$history_url = add_query_arg( array( 'page' => $type_id . '-history' ), admin_url( 'users.php' ) );
				if ( $using_buddypress && isset( $bonips->buddypress['history_url'] )  )
					$history_url = add_query_arg( array( 'show-ctype' => $type_id ), bp_loggedin_user_domain() . $bonips->buddypress['history_url'] . '/' );

				$balance          = $point_type->get_users_balance( $user_id, $type_id );
				$history_url      = apply_filters( 'bonips_my_history_url', $history_url, $type_id, $point_type );
				$adminbar_menu_id = str_replace( '_', '-', $type_id );

				// Show balance
				$wp_admin_bar->add_menu( array(
					'parent' => BONIPS_SLUG . '-account',
					'id'     => BONIPS_SLUG . '-account-balance-' . $adminbar_menu_id,
					'title'  => $point_type->template_tags_amount( apply_filters( 'bonips_label_my_balance', '%plural%: %cred_f%', $user_id, $point_type ), $balance ),
					'href'   => false
				) );

				// Verlauf link
				if ( $history_url != '' && apply_filters( 'bonips_admin_show_history_' . $type_id, true ) === true )
					$wp_admin_bar->add_menu( array(
						'parent' => BONIPS_SLUG . '-account',
						'id'     => BONIPS_SLUG . '-account-history-' . $adminbar_menu_id,
						'title'  => sprintf( '%s %s', $point_type->plural(), __( 'Verlauf', 'bonips' ) ),
						'href'   => $history_url
					) );

			}
	
			// Let others play
			do_action( 'bonips_tool_bar', $wp_admin_bar, $bonips );

		}

		/**
		 * Cron: Reset Encryption Key
		 * @since 1.2
		 * @version 1.0
		 */
		public function cron_reset_key() {

			$protect = bonips_protect();
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
			if ( defined( 'BONIPS_CACHE_LEADERBOARDS' ) && BONIPS_CACHE_LEADERBOARDS === 'daily' ) {

				global $wpdb;

				$table = bonips_get_db_column( 'options' );
				$wpdb->query( "DELETE FROM {$table} WHERE option_name LIKE 'leaderboard-%';" );

			}

			do_action( 'bonips_cron_leaderboard_cache' );

		}

		/**
		 * FIX: Add admin page style
		 * @since 1.7
		 * @version 1.0
		 */
		public function fix_admin_page_styles() {

			wp_enqueue_style( 'bonips-admin' );

		}

		/**
		 * Plugin Links
		 * @since 1.7
		 * @version 1.0
		 */
		public function plugin_links( $actions, $plugin_file, $plugin_data, $context ) {

			// Link to Setup
			if ( ! bonips_is_installed() )
				$actions['_setup'] = '<a href="' . admin_url( 'plugins.php?page=' . BONIPS_SLUG . '-setup' ) . '">' . __( 'Einrichten', 'bonips' ) . '</a>';
			else
				$actions['_settings'] = '<a href="' . admin_url( 'admin.php?page=' . BONIPS_SLUG . '-settings' ) . '" >' . __( 'Einstellungen', 'bonips' ) . '</a>';

			ksort( $actions );
			return $actions;

		}

		/**
		 * Plugin Description Links
		 * @since 1.7
		 * @version 1.0.2
		 */
		public function plugin_description_links( $links, $file ) {

			if ( $file != plugin_basename( boniPS_THIS ) ) return $links;

			// Link to Setup
			if ( ! is_bonips_ready() ) {

				$links[] = '<a href="' . admin_url( 'plugins.php?page=' . BONIPS_SLUG . '-setup' ) . '">' . __( 'Einrichten', 'bonips' ) . '</a>';
				return $links;

			}

			// Usefull links
			$links[] = '<a href="http://codex.bonips.me/" target="_blank">Documentation</a>';
			$links[] = '<a href="https://bonips.me/store/" target="_blank">Store</a>';

			return $links;

		}

	}
endif;

function bonips_core() {
	return boniPS_Core::instance();
}
bonips_core();
