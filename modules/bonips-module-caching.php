<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * boniPS_Caching_Module class
 * @since 1.8
 * @version 1.0
 */
if ( ! class_exists( 'boniPS_Caching_Module' ) ) :
	class boniPS_Caching_Module extends boniPS_Module {
		public $settings_name;
		public $add_to_core;
		public $accordion;
		public $cap;
		public $caching;

		/**
		 * Construct
		 */
		public function __construct( $type = BONIPS_DEFAULT_TYPE_KEY ) {

			parent::__construct( 'boniPS_Caching_Module', array(
				'module_name' => 'caching',
				'option_id'   => 'bonips_pref_core',
				'defaults'    => array(
					'history'      => 'off',
					'leaderboards' => 'off',
					'autodelete'   => 0
				),
				'accordion'   => false,
				'register'    => false,
				'add_to_core' => false
			), $type );

		}

		/**
		 * Load
		 * @since 1.8
		 * @version 1.0
		 */
		public function load() {

			if ( $this->bonips_type == BONIPS_DEFAULT_TYPE_KEY ) {

				add_filter( 'bonips_get_cached_log',         array( $this, 'get_log' ), 10, 2 );
				add_action( 'bonips_cache_log',              array( $this, 'cache_log' ), 10, 2 );

				add_filter( 'bonips_get_cached_leaderboard', array( $this, 'get_leaderboard' ), 10, 2 );
				add_action( 'bonips_cache_leaderboard',      array( $this, 'cache_leaderboard' ), 10, 2 );

			}

			add_action( 'bonips_update_user_balance',    array( $this, 'balance_change' ), 10, 4 );
			add_action( 'bonips_cron_reset_key',         array( $this, 'cron_tasks' ) );

			add_action( 'bonips_admin_init',             array( $this, 'module_admin_init' ) );

		}

		/**
		 * Admin Init
		 * @since 1.8
		 * @version 1.0
		 */
		public function module_admin_init() {

			$action_hook = ( $this->bonips_type == BONIPS_DEFAULT_TYPE_KEY ) ? '' : $this->bonips_type;

			add_action( 'bonips_after_management_prefs' . $action_hook, array( $this, 'after_general_settings' ), 10 );
			add_filter( 'bonips_save_core_prefs' . $action_hook,        array( $this, 'sanitize_extra_settings' ), 10, 3 );

			add_action( 'wp_ajax_bonips-action-clear-cache',            array( $this, 'action_clear_cache' ) );

		}

		/**
		 * Get Leaderboard
		 * @since 1.8
		 * @version 1.0
		 */
		public function get_leaderboard( $data, $leaderboard ) {

			if ( $this->caching['leaderboards'] == 'off' ) return false;

			$data = get_transient( $leaderboard->get_cache_key() );

			return $data;

		}

		/**
		 * Cache Leaderboard
		 * @since 1.8
		 * @version 1.0
		 */
		public function cache_leaderboard( $data, $leaderboard ) {

			if ( in_array( $this->caching['leaderboards'], array( 'day', 'manual' ) ) ) return;

			$key = $leaderboard->get_cache_key();

			set_transient( $key, $data, ( ( $this->caching['leaderboards'] == 'day' ) ? DAY_IN_SECONDS : WEEK_IN_SECONDS ) );

		}

		/**
		 * Get Log
		 * @since 1.8
		 * @version 1.0
		 */
		public function get_log( $data, $log ) {

			if ( $this->caching['history'] == 'off' ) return false;

			$data = get_transient( $log->get_cache_key() );

			return $data;

		}

		/**
		 * Cache Log
		 * @since 1.8
		 * @version 1.0
		 */
		public function cache_log( $data, $log ) {

			if ( in_array( $this->caching['history'], array( 'day', 'manual' ) ) ) return;

			$key = $log->get_cache_key();

			set_transient( $key, $data, ( ( $this->caching['history'] == 'day' ) ? DAY_IN_SECONDS : WEEK_IN_SECONDS ) );

		}

		/**
		 * Balance Change
		 * @since 1.8
		 * @version 1.0
		 */
		public function balance_change( $user_id, $current_balance, $amount, $point_type = BONIPS_DEFAULT_TYPE_KEY ) {

			if ( $this->caching['history'] == 'event' && $point_type == $this->bonips_type ) {

				$this->clear_cache( 'history' );

			}

			if ( $this->caching['leaderboards'] == 'event' && $point_type == $this->bonips_type ) {

				$this->clear_cache( 'leaderboards' );

			}

		}

		/**
		 * Cron Tasks
		 * @since 1.8
		 * @version 1.0
		 */
		public function cron_tasks() {

			if ( $this->caching['history'] == 'day' ) {

				$this->clear_cache( 'history' );

			}

			if ( $this->caching['leaderboards'] == 'day' ) {

				$this->clear_cache( 'leaderboards' );

			}

			if ( $this->caching['autodelete'] > 0 ) {

				$max_age   = ( $this->caching['autodelete'] * DAY_IN_SECONDS );
				$now       = current_time( 'timestamp' );

				// Times are stored as unix timestamps so we just deduct the seconds from now
				$timestamp = $now - $max_age;

				global $wpdb, $bonips;

				// Delete entries that are older than our $timestamp for this point type
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$bonips->log_table} WHERE time < %d AND ctype = %s;", $timestamp, $this->bonips_type ) );

			}

		}

		/**
		 * Action: Clear Cache
		 * @since 1.8
		 * @version 1.0
		 */
		public function action_clear_cache() {

			if ( ! isset( $_POST['ctype'] ) || $_POST['ctype'] != $this->bonips_type ) return;

			check_ajax_referer( 'bonips-clear-cache', 'token' );

			$cache      = sanitize_key( $_POST['cache'] );

			$this->clear_cache( $cache );



			wp_send_json_success( $description );

		}

		/**
		 * Clear Cache
		 * @since 1.8
		 * @version 1.0
		 */
		public function clear_cache( $cache_type = '' ) {

			if ( $cache_type == '' ) return false;

			$cache_id   = ( $cache_type == 'history' ) ? BONIPS_SLUG . '-cache-keys' : BONIPS_SLUG . '-cache-leaderboard-keys';
			$cache_id   = apply_filters( 'bonips_get_cache_id', $cache_id, $cache_type, $this );

			$cache_keys = bonips_get_option( $cache_id, array() );
			if ( empty( $cache_keys ) ) {

				foreach ( $cache_keys as $key )
					wp_cache_delete( $key, BONIPS_SLUG );

				foreach ( $cache_keys as $key )
					delete_transient( $key );

				bonips_update_option( $cache_id, array() );

			}

			// Let others play - generic
			do_action( 'bonips_cleared_cache', $cache_type, $this );

			// Let others play - point type specific
			do_action( 'bonips_cleared_cache_' . $this->bonips_type, $cache_type, $this );

		}

		/**
		 * Settings
		 * @since 1.8
		 * @version 1.0
		 */
		public function after_general_settings( $bonips = NULL ) {

?>
<h4><span class="dashicons dashicons-admin-tools static"></span><?php _e( 'Optimierung', 'bonips' ); ?></h4>
<div class="body" style="display:none;">

	<?php if ( $this->bonips_type == BONIPS_DEFAULT_TYPE_KEY ) : ?>
	<div class="row">
		<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
			<div class="form-group">
				<label><?php _e( 'Verlauf', 'bonips' ); ?></label>
				<div class="radio">
					<label for="<?php echo $this->field_id( 'caching-off' ); ?>"><input type="radio" name="<?php echo $this->field_name( array( 'caching', 'history' ) ); ?>" id="<?php echo $this->field_id( 'caching-off' ); ?>"<?php checked( $this->caching['history'], 'off' ); ?> value="off" /> <?php _e( 'Kein Caching', 'bonips' ); ?></label>
				</div>
				<div class="radio">
					<label for="<?php echo $this->field_id( 'caching-event' ); ?>"><input type="radio" name="<?php echo $this->field_name( array( 'caching', 'history' ) ); ?>" id="<?php echo $this->field_id( 'caching-event' ); ?>"<?php checked( $this->caching['history'], 'event' ); ?> value="event" /> <?php _e( 'Leere den Cache jedes Mal, wenn sich das Guthaben eines Benutzers ändert', 'bonips' ); ?></label>
				</div>
				<div class="radio">
					<label for="<?php echo $this->field_id( 'caching-day' ); ?>"><input type="radio" name="<?php echo $this->field_name( array( 'caching', 'history' ) ); ?>" id="<?php echo $this->field_id( 'caching-day' ); ?>"<?php checked( $this->caching['history'], 'day' ); ?> value="day" /> <?php _e( 'Lösche den Cache einmal am Tag', 'bonips' ); ?></label>
				</div>
				<div class="radio">
					<label for="<?php echo $this->field_id( 'caching-manual' ); ?>"><input type="radio" name="<?php echo $this->field_name( array( 'caching', 'history' ) ); ?>" id="<?php echo $this->field_id( 'caching-manual' ); ?>"<?php checked( $this->caching['history'], 'manual' ); ?> value="manual" /> <?php _e( 'Cache manuell löschen', 'bonips' ); ?></label>
				</div>
				<hr />
				<button type="button" data-cache="history" data-type="<?php echo esc_attr( $this->bonips_type ); ?>" class="button clear-type-cache-button"<?php if ( $this->caching['history'] == 'off' ) echo ' disabled="disabled"'; ?> id=""><?php _e( 'Cache jetzt löschen', 'bonips' ); ?></button>
			</div>
		</div>
		<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
			<div class="form-group">
				<label><?php _e( 'Bestenlisten', 'bonips' ); ?></label>
				<div class="radio">
					<label for="<?php echo $this->field_id( 'leaderboard-caching-off' ); ?>"><input type="radio" name="<?php echo $this->field_name( array( 'caching', 'leaderboards' ) ); ?>" id="<?php echo $this->field_id( 'leaderboard-caching-off' ); ?>"<?php checked( $this->caching['leaderboards'], 'off' ); ?> value="off" /> <?php _e( 'Kein Caching', 'bonips' ); ?></label>
				</div>
				<div class="radio">
					<label for="<?php echo $this->field_id( 'leaderboard-caching-event' ); ?>"><input type="radio" name="<?php echo $this->field_name( array( 'caching', 'leaderboards' ) ); ?>" id="<?php echo $this->field_id( 'leaderboard-caching-event' ); ?>"<?php checked( $this->caching['leaderboards'], 'event' ); ?> value="event" /> <?php _e( 'Leere den Cache jedes Mal, wenn sich das Guthaben eines Benutzers ändert', 'bonips' ); ?></label>
				</div>
				<div class="radio">
					<label for="<?php echo $this->field_id( 'leaderboard-caching-day' ); ?>"><input type="radio" name="<?php echo $this->field_name( array( 'caching', 'leaderboards' ) ); ?>" id="<?php echo $this->field_id( 'leaderboard-caching-day' ); ?>"<?php checked( $this->caching['leaderboards'], 'day' ); ?> value="day" /> <?php _e( 'Lösche den Cache einmal am Tag', 'bonips' ); ?></label>
				</div>
				<div class="radio">
					<label for="<?php echo $this->field_id( 'leaderboard-caching-manual' ); ?>"><input type="radio" name="<?php echo $this->field_name( array( 'caching', 'leaderboards' ) ); ?>" id="<?php echo $this->field_id( 'leaderboard-caching-manual' ); ?>"<?php checked( $this->caching['leaderboards'], 'manual' ); ?> value="manual" /> <?php _e( 'Cache manuell löschen', 'bonips' ); ?></label>
				</div>
				<hr />
				<button type="button" data-cache="leaderboards" data-type="<?php echo esc_attr( $this->bonips_type ); ?>" class="button clear-type-cache-button"<?php if ( $this->caching['leaderboards'] == 'off' ) echo ' disabled="disabled"'; ?> id=""><?php _e( 'Cache jetzt löschen', 'bonips' ); ?></button>
			</div>
		</div>
		<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'auto-delete' ); ?>"><?php _e( 'Protokolleinträge automatisch löschen', 'bonips' ); ?></label>
				<?php if ( ! BONIPS_ENABLE_LOGGING ) : ?>
				<p><span class="description"><?php _e( 'Protokoll deaktiviert', 'bonips' ); ?></span></p>
				<?php else : ?>
				<input type="text" name="<?php echo $this->field_name( array( 'caching', 'autodelete' ) ); ?>" id="<?php echo $this->field_id( 'auto-delete' ); ?>" value="<?php echo esc_attr( $this->caching['autodelete'] ); ?>" placeholder="days" class="form-control" />
				<p><span class="description"><?php printf( _x( "Option zum automatischen Löschen von Protokolleinträgen nach einer bestimmten Anzahl von Tagen. Bitte lese die %s, bevor Du diese Funktion verwendest, da ihre Verwendung Konsequenzen hat! Verwende zum Deaktivieren Null.", 'Dokumentation', 'bonips' ), sprintf( '<a href="https://github.com/cp-psource/docs/bonips-bestenlisten-caching/" target="_blank">%s</a>', __( 'Dokumentation', 'bonips' ) ) ); ?></span></p>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php else : ?>
	<div class="row">
		<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'auto-delete' ); ?>"><?php _e( 'Protokolleinträge automatisch löschen', 'bonips' ); ?></label>
				<?php if ( ! BONIPS_ENABLE_LOGGING ) : ?>
				<p><span class="description"><?php _e( 'Protokoll deaktiviert', 'bonips' ); ?></span></p>
				<?php else : ?>
				<input type="text" name="<?php echo $this->field_name( array( 'caching', 'autodelete' ) ); ?>" id="<?php echo $this->field_id( 'auto-delete' ); ?>" value="<?php echo esc_attr( $this->caching['autodelete'] ); ?>" placeholder="days" class="form-control" />
				<p><span class="description"><?php printf( _x( "ption zum automatischen Löschen von Protokolleinträgen nach einer bestimmten Anzahl von Tagen. Bitte lese die %s, bevor Du diese Funktion verwendest, da ihre Verwendung Konsequenzen hat! Verwende zum Deaktivieren Null.", 'documentation', 'bonips' ), sprintf( '<a href="https://github.com/cp-psource/docs/bonips-bestenlisten-caching/" target="_blank">%s</a>', __( 'Dokumentation', 'bonips' ) ) ); ?></span></p>
				<?php endif; ?>
			</div>
		</div>
		<div class="col-lg-8 col-md-8 col-sm-12 col-xs-12"></div>
	</div>
	<?php endif; ?>

</div>
<?php

		}

		/**
		 * Sanitize Settings
		 * @since 1.8
		 * @version 1.0
		 */
		public function sanitize_extra_settings( $new_data, $data, $core ) {

			$new_data['caching']['history']      = ( isset( $data['caching']['history'] ) ) ? sanitize_key( $data['caching']['history'] ) : 'off';

			// Turning off
			if ( $this->caching['history'] != 'off' && $new_data['caching']['history'] == 'off' ) {
				$this->clear_cache( 'history' );
			}

			$new_data['caching']['leaderboards'] = ( isset( $data['caching']['leaderboards'] ) ) ? sanitize_key( $data['caching']['leaderboards'] ) : 'off';

			// Turning off
			if ( $this->caching['leaderboards'] != 'off' && $new_data['caching']['leaderboards'] == 'off' ) {
				$this->clear_cache( 'leaderboards' );
			}

			$new_data['caching']['autodelete']   = absint( $data['caching']['autodelete'] );

			return $new_data;

		}

	}
endif;
