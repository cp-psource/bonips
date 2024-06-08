<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * boniPS_Install class
 * Used when the plugin is activated/de-activated or deleted. Installs core settings and
 * base templates, checks compatibility and uninstalls.
 * @since 0.1
 * @version 1.2
 */
if ( ! class_exists( 'boniPS_Install' ) ) :
	final class boniPS_Install {

		// Instnace
		protected static $_instance = NULL;

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
		 * Construct
		 */
		function __construct() { }

		/**
		 * Compat
		 * Check to make sure we reach minimum requirements for this plugin to work propery.
		 * @since 0.1
		 * @version 1.3
		 */
		public static function compat() {

			global $wpdb;

			$message = array();

			// WordPress check
			$wp_version = $GLOBALS['wp_version'];
			if ( version_compare( $wp_version, '4.0', '<' ) && BONIPS_FOR_OLDER_WP === false )
				$message[] = __( 'boniPS requires WordPress 4.0 or higher. Version detected:', 'bonips' ) . ' ' . $wp_version;

			// PHP check
			$php_version = phpversion();
			if ( version_compare( $php_version, '5.3', '<' ) )
				$message[] = __( 'boniPS requires PHP 5.3 or higher. Version detected: ', 'bonips' ) . ' ' . $php_version;

			// SQL check
			$sql_version = $wpdb->db_version();
			if ( version_compare( $sql_version, '5.0', '<' ) )
				$message[] = __( 'boniPS requires SQL 5.0 or higher. Version detected: ', 'bonips' ) . ' ' . $sql_version;

			// Not empty $message means there are issues
			if ( ! empty( $message ) ) {

				die( __( 'Sorry but your WordPress installation does not reach the minimum requirements for running boniPS. The following errors were given:', 'bonips' ) . "\n" . implode( "\n", $message ) );

			}

		}

		/**
		 * First time activation
		 * @since 0.1
		 * @version 1.4
		 */
		public static function activate() {

			$bonips = bonips();
			
			set_transient( '_bonips_activation_redirect', true, 60 );

			// Add general settings
			add_option( 'bonips_version',   boniPS_VERSION );
			add_option( 'bonips_key',       wp_generate_password( 12, true, true ) );
			add_option( 'bonips_pref_core', $bonips->defaults() );

			// Add add-ons settings
			add_option( 'bonips_pref_addons', array(
				'installed' => array(),
				'active'    => array()
			) );

			// Add hooks settings
			$option_id = apply_filters( 'bonips_option_id', 'bonips_pref_hooks' );
			add_option( $option_id, array(
				'installed'  => array(),
				'active'     => array(),
				'hook_prefs' => array()
			) );

			do_action( 'bonips_activation' );

			if ( isset( $_GET['activate-multi'] ) )
				return;

			set_transient( '_bonips_activation_redirect', true, 60 );

			flush_rewrite_rules();
		}

		/**
		 * Re-activation
		 * @since 0.1
		 * @version 1.4
		 */
		public static function reactivate() {
		
			$version = get_option( 'bonips_version', false );
			do_action( 'bonips_reactivation', $version );

			self::update_to_latest( $version );

			// Update version number
			update_option( 'bonips_version', boniPS_VERSION );

		}

		/**
		 * Update to Latest
		 * @since 1.7.6
		 * @version 1.0.1
		 */
		public static function update_to_latest( $version ) {

			global $wpdb;

			// Reset cached pending payments (buyCRED add-on)
			$wpdb->delete(
				$wpdb->usermeta,
				array( 'meta_key' => 'buycred_pending_payments' ),
				array( '%s' )
			);

			if ( version_compare( $version, boniPS_VERSION, '<' ) ) {

				/**
				 * Add support for showing all point types in the WooCommerce
				 * currency dropdown. If this is a currency store, we need to switch the currency code.
				 */
				$woo_currency = apply_filters( 'woocommerce_currency', get_option( 'woocommerce_currency', false ) );
				if ( $woo_currency === 'MYC' ) {

					$settings = get_option( 'woocommerce_bonips_settings', false );
					if ( $settings !== false && is_array( $settings ) && array_key_exists( 'point_type', $settings ) ) {

						update_option( 'woocommerce_currency', $settings['point_type'] );

					}

				}

			}

		}

		/**
		 * Uninstall
		 * Removes all boniPS related data from the database.
		 * @since 0.1
		 * @version 1.5.1
		 */
		public static function uninstall() {

			global $wpdb;

			$bonips_types = bonips_get_types();

			$option_id = apply_filters( 'bonips_option_id', 'bonips_pref_hooks' );
			// Options to delete
			$options_to_delete = array(
				'bonips_setup_completed',
				'bonips_pref_core',
				$option_id,
				'bonips_pref_addons',
				'bonips_pref_bank',
				'bonips_pref_remote',
				'bonips_types',
				'woocommerce_bonips_settings',
				'bonips_sell_content_one_seven_updated',
				'bonips_version',
				'bonips_version_db',
				'bonips_key',
				'bonips_network',
				'widget_bonips_widget_balance',
				'widget_bonips_widget_list',
				'widget_bonips_widget_transfer',
				'bonips_ref_hook_counter',
				'bonips_espresso_gateway_prefs',
				'bonips_eventsmanager_gateway_prefs',
				BONIPS_SLUG . '-cache-stats-keys',
				BONIPS_SLUG . '-cache-leaderboard-keys',
				BONIPS_SLUG . '-last-clear-stats'
			);

			foreach ( $bonips_types as $type => $label ) {
				$options_to_delete[] = 'bonips_pref_core_' . $type;
				$options_to_delete[] = 'bonips-cache-total-' . $type;
			}
			$options_to_delete = apply_filters( 'bonips_uninstall_options', $options_to_delete );

			if ( ! empty( $options_to_delete ) ) {

				// Multisite installations where we are not using the "Master Template" feature
				if ( is_multisite() && ! bonips_override_settings() ) {

					// Remove settings on all sites where boniPS was enabled
					$site_ids = get_sites( array( 'fields' => 'ids' ) );
					foreach ( $site_ids as $site_id ) {

						// Check if boniPS was installed
						$installed = get_blog_option( $site_id, 'bonips_setup_completed', false );
						if ( $installed === false ) continue;

						foreach ( $options_to_delete as $option_id )
							delete_blog_option( $site_id, $option_id );

					}

				}

				else {

					foreach ( $options_to_delete as $option_id )
						delete_option( $option_id );

				}

			}

			// Unschedule cron jobs
			$bonips_crons_to_clear = apply_filters( 'bonips_uninstall_schedules', array(
				'bonips_reset_key',
				'bonips_banking_recurring_payout',
				'bonips_banking_do_batch',
				'bonips_banking_interest_compound',
				'bonips_banking_do_compound_batch',
				'bonips_banking_interest_payout',
				'bonips_banking_interest_do_batch',
				'bonips_send_email_notices'
			) );

			if ( ! empty( $bonips_crons_to_clear ) ) {
				foreach ( $bonips_crons_to_clear as $schedule_id )
					wp_clear_scheduled_hook( $schedule_id );
			}

			// Delete all custom post types created by boniPS
			$post_types                  = get_bonips_post_types();
			$bonips_post_types_to_delete = apply_filters( 'bonips_uninstall_post_types', $post_types );

			if ( ! empty( $bonips_post_types_to_delete ) ) {
				foreach ( $bonips_post_types_to_delete as $post_type ) {

					$posts = new WP_Query( array( 'posts_per_page' => -1, 'post_type' => $post_type, 'fields' => 'ids' ) );
					if ( $posts->have_posts() ) {

						// wp_delete_post() will also handle all post meta deletions
						foreach ( $query->posts as $post_id )
							wp_delete_post( $post_id, true );

					}
					wp_reset_postdata();

				}
			}

			if ( ! defined( 'BONIPS_RANK_KEY' ) ) define( 'BONIPS_RANK_KEY', 'bonips_rank' );
			if ( ! defined( 'BONIPS_BADGE_KEY' ) ) define( 'BONIPS_BADGE_KEY', 'bonips_badge' );

			// Delete user meta
			// 'meta_key' => true (exact key) / false (use LIKE)
			$bonips_usermeta_to_delete = array(
				BONIPS_RANK_KEY                => true,
				'bonips-last-send'             => true,
				'bonips-last-linkclick'        => true,
				'bonips-last-transfer'         => true,
				'bonips_affiliate_link'        => true,
				'bonips_email_unsubscriptions' => true,
				'bonips_transactions'          => true,
				BONIPS_BADGE_KEY . '%'         => false,
				BONIPS_RANK_KEY . '%'          => false,
				'bonips_epp_%'                 => false,
				'bonips_payments_%'            => false,
				'bonips_comment_limit_post_%'  => false,
				'bonips_comment_limit_day_%'   => false,
				'bonips-last-clear-stats'      => true
			);

			if ( BONIPS_UNINSTALL_CREDS ) {

				foreach ( $bonips_types as $type => $label ) {

					$bonips_usermeta_to_delete[ $type ]                                = true;
					$bonips_usermeta_to_delete[ $type . '_total' ]                     = true;
					$bonips_usermeta_to_delete[ 'bonips_ref_counts-' . $type ]         = true;
					$bonips_usermeta_to_delete[ 'bonips_ref_sums-' . $type ]           = true;
					$bonips_usermeta_to_delete[ $type . '_comp' ]                      = true;
					$bonips_usermeta_to_delete[ 'bonips_banking_rate_' . $type ]       = true;
					$bonips_usermeta_to_delete[ 'bonips_buycred_rates_' . $type ]      = true;
					$bonips_usermeta_to_delete[ 'bonips_sell_content_share_' . $type ] = true;
					$bonips_usermeta_to_delete[ 'bonips_transactions_' . $type ]       = true;

				}

			}
			$bonips_usermeta_to_delete = apply_filters( 'bonips_uninstall_usermeta', $bonips_usermeta_to_delete );

			if ( ! empty( $bonips_usermeta_to_delete ) ) {
				foreach ( $bonips_usermeta_to_delete as $meta_key => $exact ) {

					if ( $exact )
						delete_metadata( 'user', 0, $meta_key, '', true );
					else
						$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s;", $meta_key ) );

				}
			}

			// Delete log table
			if ( BONIPS_UNINSTALL_LOG ) {

				if ( defined( 'BONIPS_LOG_TABLE' ) )
					$table_name = BONIPS_LOG_TABLE;

				else {

					if ( bonips_centralize_log() )
						$table_name = $wpdb->base_prefix . 'boniPS_log';
					else
						$table_name = $wpdb->prefix . 'boniPS_log';

				}

				if ( ! is_multisite() || ( is_multisite() && bonips_centralize_log() ) ) {

					$wpdb->query( "DROP TABLE IF EXISTS {$table_name};" );

				}
				else {

					$site_ids = get_sites( array( 'fields' => 'ids' ) );
					foreach ( $site_ids as $site_id ) {

						$site_id = absint( $site_id );
						if ( $site_id === 0 ) continue;

						$table = $wpdb->base_prefix . $site_id . '_boniPS_log';
						if ( $site === 1 )
							$table_name = $wpdb->base_prefix . 'boniPS_log';

						$wpdb->query( "DROP TABLE IF EXISTS {$table_name};" );

					}

				}

			}

			// Clear stats data (if enabled)
			if ( function_exists( 'bonips_delete_stats_data' ) )
				bonips_delete_stats_data();

			// Good bye.
			flush_rewrite_rules();

		}

	}
endif;

/**
 * Get Installer
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'bonips_installer' ) ) :
	function bonips_installer() {
		return boniPS_Install::instance();
	}
endif;
