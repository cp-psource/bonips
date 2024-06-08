<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * Hooks for Clicking on Links
 * @since 1.1
 * @version 1.3
 */
if ( ! class_exists( 'boniPS_Hook_Click_Links' ) ) :
	class boniPS_Hook_Click_Links extends boniPS_Hook {
		public $defaults;

		/**
		 * Construct
		 */
		function __construct( $hook_prefs, $type = BONIPS_DEFAULT_TYPE_KEY ) {

			parent::__construct( array(
				'id'       => 'link_click',
				'defaults' => array(
					'limit_by' => 'none',
					'creds'    => 1,
					'log'      => '%plural% for clicking on link to: %url%'
				)
			), $hook_prefs, $type );

		}

		/**
		 * Run
		 * @since 1.1
		 * @version 1.0
		 */
		public function run() {

			if ( ! is_user_logged_in() ) return;

			add_action( 'bonips_register_assets',      array( $this, 'register_script' ) );
			add_action( 'bonips_front_enqueue_footer', array( $this, 'enqueue_footer' ) );
			add_filter( 'bonips_parse_tags_link',      array( $this, 'parse_custom_tags' ), 10, 2 );

			if ( isset( $_POST['action'] ) && $_POST['action'] == 'bonips-click-points' && isset( $_POST['token'] ) && wp_verify_nonce( $_POST['token'], 'bonips-link-points' ) )
				$this->ajax_call_link_points();

		}

		/**
		 * Customize Limit Options
		 * @since 1.1
		 * @version 1.0
		 */
		public function custom_limit() {

			return array(
				'none' => __( 'No limit', 'bonips' ),
				'url'  => __( 'Once for each unique URL', 'bonips' ),
				'id'   => __( 'Once for each unique link id', 'bonips' )
			);

		}

		/**
		 * Parse Custom Tags in Log
		 * @since 1.1
		 * @version 1.1.1
		 */
		public function parse_custom_tags( $content, $log_entry ) {

			$data    = maybe_unserialize( $log_entry->data );
			$content = str_replace( '%url%', $data['link_url'], $content );
			$content = str_replace( '%id%',  $data['link_id'], $content );

			if ( isset( $data['link_title'] ) )
				$content = str_replace( '%title%',  $data['link_title'], $content );

			return $content;

		}

		/**
		 * Register Script
		 * @since 1.1
		 * @version 1.0
		 */
		public function register_script() {

			global $bonips_link_points;

			$bonips_link_points = false;

			wp_register_script(
				'bonips-link-points',
				plugins_url( 'assets/js/links.js', boniPS_THIS ),
				array( 'jquery' ),
				boniPS_VERSION . '.1',
				true
			);

		}

		/**
		 * WP Fotter
		 * @since 1.1
		 * @version 1.1
		 */
		public function enqueue_footer() {

			global $bonips_link_points;

			if ( $bonips_link_points === true ) {

				global $post;

				wp_localize_script(
					'bonips-link-points',
					'boniPSlink',
					array(
						'ajaxurl' => esc_url( isset( $post->ID ) ? bonips_get_permalink( $post->ID ) : home_url( '/' ) ),
						'token'   => wp_create_nonce( 'bonips-link-points' )
					)
				);
				wp_enqueue_script( 'bonips-link-points' );

			}

		}

		/**
		 * Custom Has Entry Check
		 * @since 1.1
		 * @version 1.1.2
		 */
		public function has_entry( $action = '', $reference = '', $user_id = '', $data = '', $type = '' ) {

			global $wpdb, $bonips_log_table;

			if ( $this->prefs['limit_by'] == 'url' ) {
				$reference = urldecode( $reference );
				$string = '%s:8:"link_url";s:' . strlen( $reference ) . ':"' . $reference . '";%';
			}
			elseif ( $this->prefs['limit_by'] == 'id' ) {
				$string = '%s:7:"link_id";s:' . strlen( $reference ) . ':"' . $reference . '";%';
			}
			else return false;

			$sql = "SELECT id FROM {$bonips_log_table} WHERE ref = %s AND user_id = %d AND data LIKE %s AND ctype = %s;";
			$wpdb->get_results( $wpdb->prepare( $sql, $action, $user_id, $string, $this->bonips_type ) );
			if ( $wpdb->num_rows > 0 ) return true;

			return false;

		}

		/**
		 * AJAX Call Handler
		 * @since 1.1
		 * @version 1.5
		 */
		public function ajax_call_link_points() {

			// We must be logged in
			if ( ! is_user_logged_in() ) return;

			// Make sure we only handle our own point type
			if ( ! isset( $_POST['ctype'] ) || $_POST['ctype'] != $this->bonips_type || ! isset( $_POST['url'] ) ) return;

			// Security
			check_ajax_referer( 'bonips-link-points', 'token' );

			// Current User
			$user_id = get_current_user_id();

			if ( bonips_force_singular_session( $user_id, 'bonips-last-linkclick' ) )
				wp_send_json( 101 );

			// Check if user should be excluded
			if ( $this->core->exclude_user( $user_id ) ) wp_send_json( 200 );

			// Token
			if ( ! isset( $_POST['key'] ) ) wp_send_json( 300 );
			$token = bonips_verify_token( $_POST['key'], 4 );
			if ( $token === false ) wp_send_json( 305 );

			list ( $amount, $point_type, $id, $url ) = $token;
			if ( $amount == '' || $point_type == '' || $id == '' || $url == '' ) wp_send_json( 310 );

			// Make sure the token is not abused
			if ( $url != urlencode( $_POST['url'] ) ) wp_send_json( 315 );

			// Bail now if this was not intenteded for this type
			if ( $point_type != $this->bonips_type ) return;

			// Amount
			if ( $amount == 0 )
				$amount = $this->prefs['creds'];
			else
				$amount = $this->core->number( $amount );

			if ( $amount == 0 || $amount == $this->core->zero() ) wp_send_json( 400 );

			$data = array(
				'ref_type'   => 'link',
				'link_url'   => esc_url_raw( $_POST['url'] ),
				'link_id'    => $id,
				'link_title' => ( isset( $_POST['etitle'] ) ) ? sanitize_text_field( $_POST['etitle'] ) : ''
			);

			// Limits
			if ( $this->prefs['limit_by'] == 'url' ) {
				if ( $this->has_clicked( $user_id, 'link_url', $data['link_url'] ) ) wp_send_json( 600 );
			}
			elseif ( $this->prefs['limit_by'] == 'id' ) {
				if ( $this->has_clicked( $user_id, 'link_id', $data['link_id'] ) ) wp_send_json( 700 );
			}

			// Execute
			$this->core->add_creds(
				'link_click',
				$user_id,
				$amount,
				$this->prefs['log'],
				'',
				$data,
				$point_type
			);

			// Report the good news
			wp_send_json( 'done' );

		}

		/**
		 * Has Clicked
		 * Checks if a user has received points for a link based on either
		 * an ID or URL.
		 * @since 1.3.3.1
		 * @version 1.0.1
		 */
		public function has_clicked( $user_id = NULL, $by = '', $check = '' ) {

			global $wpdb, $bonips_log_table;

			$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$bonips_log_table} WHERE ref = %s AND user_id = %d AND ctype = %s", 'link_click', $user_id, $this->bonips_type ) );
			if ( count( $rows ) == 0 ) return false;

			$reply = false;
			foreach ( $rows as $row ) {

				$data = maybe_unserialize( $row->data );
				if ( ! is_array( $data ) || ! isset( $data[ $by ] ) ) continue;

				if ( $data[ $by ] == $check ) {
					$reply = true;
					break;
				}

			}

			return $reply;

		}

		/**
		 * Preference for Link Click Hook
		 * @since 1.1
		 * @version 1.1
		 */
		public function preferences() {

			$prefs = $this->prefs;

?>
<div class="hook-instance">
	<div class="row">
		<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'creds' ); ?>"><?php echo $this->core->plural(); ?></label>
				<input type="text" name="<?php echo $this->field_name( 'creds' ); ?>" id="<?php echo $this->field_id( 'creds' ); ?>" value="<?php echo $this->core->number( $prefs['creds'] ); ?>" class="form-control" />
			</div>
		</div>
		<div class="col-lg-8 col-md-8 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'log' ); ?>"><?php _e( 'Protokollvorlage', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( 'log' ); ?>" id="<?php echo $this->field_id( 'log' ); ?>" placeholder="<?php _e( 'erforderlich', 'bonips' ); ?>" value="<?php echo esc_attr( $prefs['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general', 'user' ), '%url%, %title% or %id%' ); ?></span>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for=""><?php _e( 'Limits', 'bonips' ); ?></label>
<?php 

			add_filter( 'bonips_hook_impose_limits', array( $this, 'custom_limit' ) );
			$this->impose_limits_dropdown( 'limit_by', false );

?>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label><?php _e( 'Available Shortcode', 'bonips' ); ?></label>
				<p class="form-control-static"><a href="https://github.com/cp-psource/docs/bonips-shortcodes-bonips_link/" target="_blank">[bonips_link]</a></p>
			</div>
		</div>
	</div>
</div>
<?php

		}

	}
endif;
