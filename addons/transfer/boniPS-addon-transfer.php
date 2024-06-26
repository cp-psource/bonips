<?php
/**
 * Addon: Transfer
 * Version: 1.6
 */
if ( ! defined( 'boniPS_VERSION' ) ) exit;

define( 'boniPS_TRANSFER_VERSION', '1.6' );
define( 'boniPS_TRANSFER',         __FILE__ );
define( 'boniPS_TRANSFER_DIR',     boniPS_ADDONS_DIR . 'transfer/' );

require_once boniPS_TRANSFER_DIR . 'includes/bonips-transfer-functions.php';
require_once boniPS_TRANSFER_DIR . 'includes/bonips-transfer-object.php';
require_once boniPS_TRANSFER_DIR . 'includes/bonips-transfer-shortcodes.php';
require_once boniPS_TRANSFER_DIR . 'includes/bonips-transfer-widgets.php';

/**
 * boniPS_Transfer_Module class
 * Manages this add-on by hooking into boniPS where needed. Regsiters our custom shortcode and widget
 * along with scripts and styles needed. Also adds settings to the boniPS settings page.
 * @since 0.1
 * @version 1.3.1
 */
if ( ! class_exists( 'boniPS_Transfer_Module' ) ) :
	class boniPS_Transfer_Module extends boniPS_Module {
		public $transfers;

		/**
		 * Construct
		 */
		function __construct() {

			parent::__construct( 'boniPS_Transfer_Module', array(
				'module_name' => 'transfers',
				'defaults'    => array(
					'types'      => array( BONIPS_DEFAULT_TYPE_KEY ),
					'logs'       => array(
						'sending'   => 'Transfer of %plural% to %display_name%',
						'receiving' => 'Transfer of %plural% from %display_name%'
					),
					'errors'     => array(
						'low'       => 'You do not have enough %plural% to send.',
						'over'      => 'You have exceeded your %limit% transfer limit.'
					),
					'templates'  => array(
						'login'     => '',
						'balance'   => 'Your current balance is %balance%',
						'limit'     => 'Your current %limit% transfer limit is %left%',
						'button'    => 'Transfer'
					),
					'autofill'   => 'user_login',
					'reload'     => 1,
					'message'    => 0,
					'limit'      => array(
						'amount'    => 1000,
						'limit'     => 'none'
					)
				),
				'register'    => false,
				'add_to_core' => true
			) );

		}

		/**
		 * Init
		 * @since 0.1
		 * @version 1.0.1
		 */
		public function module_init() {

			add_filter( 'bonips_get_email_events',     array( $this, 'email_notice_instance' ), 10, 2 );
			add_filter( 'bonips_email_before_send',    array( $this, 'email_notices' ), 50, 2 );
			add_filter( 'bonips_parse_log_entry',      array( $this, 'render_message' ), 20, 2 );

			// Register Scripts & Styles
			add_action( 'bonips_front_enqueue',        array( $this, 'register_script' ), 30 );

			// Register Shortcode
			add_shortcode( BONIPS_SLUG . '_transfer',  'bonips_transfer_render' );

			// Potentially load script
			add_action( 'wp_footer',                   array( $this, 'maybe_load_script' ) );

			// Ajax Calls
			add_action( 'wp_ajax_bonips-new-transfer', array( $this, 'ajax_call_transfer' ) );

			if ( $this->transfers['autofill'] != 'none' )
				add_action( 'wp_ajax_bonips-autocomplete', array( $this, 'ajax_call_autocomplete' ) );


		}

		/**
		 * Register Widgets
		 * @since 1.7.6
		 * @version 1.0
		 */
		public function render_message( $content = '', $log = NULL ) {

			if ( ! isset( $log->data ) ) return $content;

			$data = (array) maybe_unserialize( $log->data );

			return bonips_transfer_render_message( $content, $data );

		}

		/**
		 * Register Widgets
		 * @since 0.1
		 * @version 1.0
		 */
		public function module_widgets_init() {

			register_widget( 'boniPS_Widget_Transfer' );

		}

		/**
		 * Enqueue Front
		 * @since 0.1
		 * @version 1.1
		 */
		public function register_script() {

			global $bonips_do_transfer;

			$bonips_do_transfer = false;

			// Register script
			wp_register_script(
				'bonips-transfer',
				plugins_url( 'assets/js/bonips-transfer.js', boniPS_TRANSFER ),
				array( 'jquery', 'jquery-ui-autocomplete' ),
				'1.7'
			);

		}

		/**
		 * Front Footer
		 * @filter 'bonips_transfer_messages'
		 * @since 0.1
		 * @version 1.2.1
		 */
		public function maybe_load_script() {

			global $bonips_do_transfer;

			if ( $bonips_do_transfer !== true ) return;

			// Autofill CSS
			echo '<style type="text/css">' . apply_filters( 'bonips_transfer_autofill_css', '.ui-autocomplete { position: absolute; z-index: 1000; cursor: default; padding: 0; margin-top: 2px; list-style: none; background-color: #ffffff; border: 1px solid #ccc; -webkit-box-shadow: 0 5px 10px rgba(0, 0, 0, 0.2); -moz-box-shadow: 0 5px 10px rgba(0, 0, 0, 0.2); box-shadow: 0 5px 10px rgba(0, 0, 0, 0.2); } .ui-autocomplete > li { padding: 3px 20px; } .ui-autocomplete > li:hover { background-color: #DDD; cursor: pointer; } .ui-autocomplete > li.ui-state-focus { background-color: #DDD; } .ui-helper-hidden-accessible { display: none; }', $this ) . '</style>';

			// Prep Script
			$base     = array(
				'ajaxurl'   => admin_url( 'admin-ajax.php' ),
				'user_id'   => get_current_user_id(),
				'working'   => esc_attr__( 'Processing...', 'bonips' ),
				'token'     => wp_create_nonce( 'bonips-autocomplete' ),
				'reload'    => $this->transfers['reload'],
				'autofill'  => $this->transfers['autofill']
			);

			// Messages
			$messages = apply_filters( 'bonips_transfer_messages', array(
				'completed' => esc_attr__( 'Transaction completed.', 'bonips' ),
				'error_1'   => esc_attr__( 'Security token could not be verified. Please contact your site administrator!', 'bonips' ),
				'error_2'   => esc_attr__( 'Communications error. Please try again later.', 'bonips' ),
				'error_3'   => esc_attr__( 'Recipient not found. Please try again.', 'bonips' ),
				'error_4'   => esc_attr__( 'Transaction declined by recipient.', 'bonips' ),
				'error_5'   => esc_attr__( 'Incorrect amount. Please try again.', 'bonips' ),
				'error_6'   => esc_attr__( 'This boniPS Add-on has not yet been setup! No transfers are allowed until this has been done!', 'bonips' ),
				'error_7'   => esc_attr__( 'Insufficient Funds. Please try a lower amount.', 'bonips' ),
				'error_8'   => esc_attr__( 'Transfer Limit exceeded.', 'bonips' ),
				'error_9'   => esc_attr__( 'Communications error. Please try again later.', 'bonips' ),
				'error_10'  => esc_attr__( 'The selected point type can not be transferred.', 'bonips' )
			) );

			wp_localize_script(
				'bonips-transfer',
				'boniPSTransfer',
				array_merge_recursive( $base, $messages )
			);

			wp_enqueue_script( 'bonips-transfer' );

		}

		/**
		 * AJAX Autocomplete
		 * @since 0.1
		 * @version 1.2.1
		 */
		public function ajax_call_autocomplete() {

			// Security
			check_ajax_referer( 'bonips-autocomplete' , 'token' );

			if ( ! is_user_logged_in() ) die;

			$results = array();
			$user_id = get_current_user_id();
			$string  = sanitize_text_field( $_REQUEST['string']['term'] );

			// Let other play
			do_action( 'bonips_transfer_autofill_find', $this->transfers, $this->core );

			global $wpdb;

			// Query
			$select     = sanitize_text_field( $this->transfers['autofill'] );
			$blog_users = $wpdb->get_results( $wpdb->prepare( "SELECT {$select}, ID FROM {$wpdb->users} WHERE ID != %d AND {$select} LIKE %s;", $user_id, '%' . $string . '%' ), 'ARRAY_N' );

			if ( $wpdb->num_rows > 0 ) {

				foreach ( $blog_users as $hit ) {

					if ( $this->core->exclude_user( $hit[1] ) ) continue;
					$results[] = $hit[0];

				}

			}

			wp_send_json( $results );

		}

		/**
		 * AJAX Transfer Creds
		 * @since 0.1
		 * @version 1.8
		 */
		public function ajax_call_transfer() {

			parse_str( $_POST['form'], $post );

			// Generate Transaction ID for our records
			$user_id        = get_current_user_id();

			if ( bonips_force_singular_session( $user_id, 'bonips-last-transfer' ) )
				wp_send_json_error( 'error_9' );

			$request = bonips_new_transfer( $post['bonips_new_transfer'], $post );
			if ( ! is_array( $request ) )
				wp_send_json_error( $request );

			// Transfer was successfull!
			wp_send_json_success( $request );

		}

		/**
		 * Settings Page
		 * @since 0.1
		 * @version 1.5
		 */
		public function after_general_settings( $bonips = NULL ) {

			// Settings
			$settings  = $this->transfers;

			if ( ! array_key_exists( 'message', $settings ) )
				$settings['message'] = 0;

			// Limits
			$limit     = $settings['limit']['limit'];
			$limits    = bonips_get_transfer_limits( $settings );

			// Autofill by
			$autofill  = $settings['autofill'];
			$autofills = bonips_get_transfer_autofill_by( $settings );

			$yes_no    = array(
				1 => __( 'Yes', 'bonips' ),
				0 => __( 'No', 'bonips' )
			);

			if ( ! isset( $settings['types'] ) )
				$settings['types'] = $this->default_prefs['types'];

?>
<h4><span class="dashicons dashicons-admin-plugins static"></span><?php _e( 'Transfers', 'bonips' ); ?></h4>
<div class="body" style="display:none;">

	<h3><?php _e( 'Features', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="bonips-transfer-type"><?php _e( 'Point Types', 'bonips' ); ?></label>

				<?php if ( count( $this->point_types ) > 1 ) : ?>

				<?php bonips_types_select_from_checkboxes( 'bonips_pref_core[transfers][types][]', 'bonips-transfer-type', $settings['types'] ); ?>

				<?php else : ?>

				<p class="form-control-static"><?php echo $this->core->plural(); ?></p>
				<input type="hidden" name="bonips_pref_core[transfers][types][]" value="<?php echo BONIPS_DEFAULT_TYPE_KEY; ?>" />

				<?php endif; ?>

			</div>
		</div>
		<div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'reload' ); ?>"><?php _e( 'Reload', 'bonips' ); ?></label>
				<select name="<?php echo $this->field_name( 'reload' ); ?>" id="<?php echo $this->field_id( 'reload' ); ?>" class="form-control">
<?php

			foreach ( $yes_no as $value => $label ) {
				echo '<option value="' . $value . '"';
				if ( $settings['reload'] == $value ) echo ' selected="selected"';
				echo '>' . $label . '</option>';
			}

?>
				</select>
				<p><span class="description"><?php _e( 'Should the page reload once a transfer has been completed?', 'bonips' ); ?></span></p>
			</div>
		</div>
		<div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'message' ); ?>"><?php _e( 'Message Length', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( 'message' ); ?>" id="<?php echo $this->field_id( 'message' ); ?>" class="form-control" value="<?php echo absint( $settings['message'] ); ?>" />
				<p><span class="description"><?php _e( 'The maximum length of messages users can attach to a transfer. Use zero to disable.', 'bonips' ); ?></span></p>
			</div>
		</div>
		<div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'autofill' ); ?>"><?php _e( 'Autofill Recipient', 'bonips' ); ?></label>
				<select name="<?php echo $this->field_name( 'autofill' ); ?>" id="<?php echo $this->field_id( 'autofill' ); ?>" class="form-control">
<?php

			foreach ( $autofills as $key => $label ) {
				echo '<option value="' . $key . '"';
				if ( $autofill == $key ) echo ' selected="selected"';
				echo '>' . $label . '</option>';
			}

?>
				</select>
				<p><span class="description"><?php _e( 'Select what user details recipients should be autofilled by.', 'bonips' ); ?></span></p>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'limit' => 'none' ) ); ?>"><?php _e( 'Limits', 'bonips' ); ?></label>
<?php

			// Loop though limits
			if ( ! empty( $limits ) ) {
				foreach ( $limits as $key => $description ) {

?>
				<div class="radio"><label for="<?php echo $this->field_id( array( 'limit' => $key ) ); ?>"><input type="radio" name="<?php echo $this->field_name( array( 'limit' => 'limit' ) ); ?>" id="<?php echo $this->field_id( array( 'limit' => $key ) ); ?>" <?php checked( $limit, $key ); ?> value="<?php echo $key; ?>" /> <?php echo $description; ?></label></div>
<?php

				}
			}

?>
			</div>
		</div>
		<div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'limit' => 'amount' ) ); ?>"><?php _e( 'Limit Amount', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'limit' => 'amount' ) ); ?>" id="<?php echo $this->field_id( array( 'limit' => 'amount' ) ); ?>" class="form-control" value="<?php echo $this->core->number( $settings['limit']['amount'] ); ?>" />
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'templates' => 'button' ) ); ?>"><?php _e( 'Default Button Label', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'templates' => 'button' ) ); ?>" id="<?php echo $this->field_id( array( 'templates' => 'button' ) ); ?>" class="form-control" value="<?php echo esc_attr( $settings['templates']['button'] ); ?>" />
				<p><span class="description"><?php _e( 'The default transfer button label. You can override this in the shortcode or widget if needed.', 'bonips' ); ?></span></p>
			</div>
		</div>
	</div>

	<h3><?php _e( 'Protokollvorlages', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'logs' => 'sending' ) ); ?>"><?php _e( 'Log template for sending', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'logs' => 'sending' ) ); ?>" id="<?php echo $this->field_id( array( 'logs' => 'sending' ) ); ?>" class="form-control" value="<?php echo esc_attr( $settings['logs']['sending'] ); ?>" />
				<p><span class="description"><?php echo $this->core->available_template_tags( array( 'general', 'user' ), '%transfer_message%' ); ?></span></p>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'logs' => 'receiving' ) ); ?>"><?php _e( 'Log template for receiving', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'logs' => 'receiving' ) ); ?>" id="<?php echo $this->field_id( array( 'logs' => 'receiving' ) ); ?>" class="form-control" value="<?php echo esc_attr( $settings['logs']['receiving'] ); ?>" />
				<p><span class="description"><?php echo $this->core->available_template_tags( array( 'general', 'user' ), '%transfer_message%' ); ?></span></p>
			</div>
		</div>
	</div>

	<h3><?php _e( 'Warning Messages', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'errors' => 'low' ) ); ?>"><?php _e( 'Insufficient Funds Warning', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'errors' => 'low' ) ); ?>" id="<?php echo $this->field_id( array( 'errors' => 'low' ) ); ?>" value="<?php echo esc_attr( $settings['errors']['low'] ); ?>" class="form-control" />
				<p><span class="description"><?php _e( 'Message to show the user if they try to send more then they can afford.', 'bonips' ); ?></span></p>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="bonips-transfer-log-receiving"><?php _e( 'Limit Reached Warning', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'errors' => 'over' ) ); ?>" id="<?php echo $this->field_id( array( 'errors' => 'over' ) ); ?>" value="<?php echo esc_attr( $settings['errors']['over'] ); ?>" class="form-control" />
				<p><span class="description"><?php _e( 'Message to show the user once they reach their transfer limit. Ignored if no limits are enforced.', 'bonips' ); ?></span></p>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
			<h3><?php _e( 'Visitors Template', 'bonips' ); ?></h3>
			<p><span class="description"><?php _e( 'The template to use when the transfer shortcode or widget is viewed by someone who is not logged in.', 'bonips' ); ?></span></p>
<?php

			wp_editor( $settings['templates']['login'], $this->field_id( array( 'templates' => 'login' ) ), array(
				'textarea_name' => $this->field_name( array( 'templates' => 'login' ) ),
				'textarea_rows' => 10
			) );

?>
		</div>
	</div>
	<div class="row">
		<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
			<h3><?php _e( 'Limit Template', 'bonips' ); ?></h3>
			<p><span class="description"><?php _e( 'The template to use if you select to show the transfer limit in the transfer shortcode or widget. Ignored if there is no limit enforced.', 'bonips' ); ?></span></p>
<?php

			wp_editor( $settings['templates']['limit'], $this->field_id( array( 'templates' => 'limit' ) ), array(
				'textarea_name' => $this->field_name( array( 'templates' => 'limit' ) ),
				'textarea_rows' => 10
			) );

			echo '<p>' . $this->core->available_template_tags( array( 'general' ), '%limit% %left%' ) . '</p>';

?>
		</div>
	</div>
	<div class="row">
		<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
			<h3><?php _e( 'Balance Template', 'bonips' ); ?></h3>
			<p><span class="description"><?php _e( 'The template to use if you select to show the users balance in the transfer shortcode or widget. Ignored if balances are not shown.', 'bonips' ); ?></span></p>
<?php

			wp_editor( $settings['templates']['balance'], $this->field_id( array( 'templates' => 'balance' ) ), array(
				'textarea_name' => $this->field_name( array( 'templates' => 'balance' ) ),
				'textarea_rows' => 10
			) );

			echo '<p>' . $this->core->available_template_tags( array( 'general' ), '%balance%' ) . '</p>';

?>
		</div>
	</div>
	<?php if ( BONIPS_SHOW_PREMIUM_ADDONS ) : ?>
	<hr />
	<div class="row">
		<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
			<p><strong>Tip:</strong> <?php printf( 'The %s add-on allows you charge a fee for creating transfers or put transfers on hold.', sprintf( '<a href="http://bonips.me/store/transfer-plus/" target="_blank">%s</a>', 'Transfer Plus' ) ); ?></p>
		</div>
	</div>
	<?php endif; ?>

</div>
<?php

		}

		/**
		 * Sanitize & Save Settings
		 * @since 0.1
		 * @version 1.4
		 */
		public function sanitize_extra_settings( $new_data, $data, $general ) {

			$new_data['transfers']['types']                = $data['transfers']['types'];
			$new_data['transfers']['reload']               = absint( $data['transfers']['reload'] );
			$new_data['transfers']['message']              = absint( $data['transfers']['message'] );
			$new_data['transfers']['autofill']             = sanitize_text_field( $data['transfers']['autofill'] );

			$new_data['transfers']['limit']['limit']       = sanitize_text_field( $data['transfers']['limit']['limit'] );
			$new_data['transfers']['limit']['amount']      = absint( $data['transfers']['limit']['amount'] );
			$new_data['transfers']['templates']['button']  = sanitize_text_field( $data['transfers']['templates']['button'] );

			$new_data['transfers']['logs']['sending']      = wp_kses_post( $data['transfers']['logs']['sending'] );
			$new_data['transfers']['logs']['receiving']    = wp_kses_post( $data['transfers']['logs']['receiving'] );

			$new_data['transfers']['errors']['low']        = sanitize_text_field( $data['transfers']['errors']['low'] );
			$new_data['transfers']['errors']['over']       = sanitize_text_field( $data['transfers']['errors']['over'] );

			$new_data['transfers']['templates']['login']   = wp_kses_post( $data['transfers']['templates']['login'] );
			$new_data['transfers']['templates']['limit']   = wp_kses_post( $data['transfers']['templates']['limit'] );
			$new_data['transfers']['templates']['balance'] = wp_kses_post( $data['transfers']['templates']['balance'] );

			return $new_data;

		}

		/**
		 * Get Recipient
		 * @since 1.3.2
		 * @version 1.2.1
		 */
		public function get_recipient( $to = '' ) {

			$recipient_id = false;
			if ( ! empty( $to ) ) {

				// A numeric ID has been provided that we need to validate
				if ( is_numeric( $to ) ) {

					$user = get_userdata( $to );
					if ( isset( $user->ID ) )
						$recipient_id = $user->ID;

				}

				// A username has been provided
				elseif ( $this->transfers['autofill'] == 'user_login' ) {

					$user = get_user_by( 'login', $to );
					if ( isset( $user->ID ) )
						$recipient_id = $user->ID;

				}

				// An email address has been provided
				elseif ( $this->transfers['autofill'] == 'user_email' ) {

					$user = get_user_by( 'email', $to );
					if ( isset( $user->ID ) )
						$recipient_id = $user->ID;

				}

			}

			return apply_filters( 'bonips_transfer_get_recipient', $recipient_id, $to, $this );

		}

		/**
		 * Add Email Notice Instance
		 * @since 1.5.4
		 * @version 1.0
		 */
		public function email_notice_instance( $events, $request ) {

			if ( $request['ref'] == 'transfer' ) {

				if ( $request['amount'] < 0 )
					$events[] = 'transfer|negative';

				elseif ( $request['amount'] > 0 )
					$events[] = 'transfer|positive';

			}

			return $events;

		}

		/**
		 * Support for Email Notices
		 * @since 1.1
		 * @version 1.1
		 */
		public function email_notices( $data ) {

			if ( $data['request']['ref'] == 'transfer' ) {
				$message = $data['message'];
				if ( $data['request']['ref_id'] == get_current_user_id() )
					$data['message'] = $this->core->template_tags_user( $message, false, wp_get_current_user() );
				else
					$data['message'] = $this->core->template_tags_user( $message, $data['request']['ref_id'] );
			}

			return $data;

		}

	}
endif;

/**
 * Load Transfer Module
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'bonips_load_transfer_addon' ) ) :
	function bonips_load_transfer_addon( $modules, $point_types ) {

		$modules['solo']['transfer'] = new boniPS_Transfer_Module();
		$modules['solo']['transfer']->load();

		return $modules;

	}
endif;
add_filter( 'bonips_load_modules', 'bonips_load_transfer_addon', 110, 2 );
