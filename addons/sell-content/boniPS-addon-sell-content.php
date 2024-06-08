<?php
/**
 * Addon: Sell Content
 * Addon URI: https://github.com/cp-psource/docs/bonips-erweiterungen-inhalt-verkaufen/
 * Version: 2.0.1
 */
if ( ! defined( 'boniPS_VERSION' ) ) exit;

define( 'boniPS_SELL',              __FILE__ );
define( 'boniPS_SELL_VERSION',      '1.5' );
define( 'BONIPS_SELL_DIR',          boniPS_ADDONS_DIR . 'sell-content/' );
define( 'BONIPS_SELL_ASSETS_DIR',   BONIPS_SELL_DIR . 'assets/' );
define( 'BONIPS_SELL_INCLUDES_DIR', BONIPS_SELL_DIR . 'includes/' );

require_once BONIPS_SELL_INCLUDES_DIR . 'bonips-sell-functions.php';
require_once BONIPS_SELL_INCLUDES_DIR . 'bonips-sell-shortcodes.php';

/**
 * boniPS_Sell_Content_Module class
 * @since 0.1
 * @version 2.0.1
 */
if ( ! class_exists( 'boniPS_Sell_Content_Module' ) ) :
	class boniPS_Sell_Content_Module extends boniPS_Module {

		public $current_user_id = 0;
		public $priority        = 10;
		public $psf_content     = '';

		/**
		 * Construct
		 */
		function __construct() {

			parent::__construct( 'boniPS_Sell_Content_Module', array(
				'module_name' => 'sell_content',
				'register'    => false,
				'defaults'    => array(
					'post_types'  => 'post,page',
						'filters'     => array(),
					'type'        => array( BONIPS_DEFAULT_TYPE_KEY ),
					'reload'      => 0,
					'working'     => 'Processing ...',
					'templates'   => array(
						'members'     => '<div class="text-center"><h3>Premium Content</h3><p>Buy access to this content.</p><p>%buy_button%</p></div>',
						'visitors'    => '<div class="text-center"><h3>Premium Content</h3><p>Login to buy access to this content.</p></div>',
						'cantafford'  => '<div class="text-center"><h3>Premium Content</h3><p>Buy access to this content.</p><p><strong>Insufficient Funds</strong></p></div>'
					)
				),
				'add_to_core' => true
			) );

			if ( ! is_array( $this->sell_content['type'] ) )
				$this->sell_content['type'] = array( $this->sell_content['type'] );

		}

		/**
		 * Module Init
		 * @since 0.1
		 * @version 1.2.1
		 */
		public function module_init() {

			$this->current_user_id = get_current_user_id();
			$this->priority        = apply_filters( 'bonips_sell_content_priority', 25, $this );

			// Email add-on support
			add_filter( 'bonips_get_email_events',         array( $this, 'email_notice_instance' ), 10, 2 );
			add_filter( 'bonips_email_before_send',        array( $this, 'email_notices' ), 40, 2 );

			// Setup Content Override
			add_action( 'template_redirect',               array( $this, 'template_redirect' ), 99990 );

			// Register shortcodes
			add_shortcode( BONIPS_SLUG . '_sell_this',             'bonips_render_sell_this' );
			add_shortcode( BONIPS_SLUG . '_sell_this_ajax',        'bonips_render_sell_this_ajax' );
			add_shortcode( BONIPS_SLUG . '_sales_history',         'bonips_render_sell_history' );
			add_shortcode( BONIPS_SLUG . '_content_sale_count',    'bonips_render_sell_count' );
			add_shortcode( BONIPS_SLUG . '_content_buyer_count',   'bonips_render_sell_buyer_count' );
			add_shortcode( BONIPS_SLUG . '_content_buyer_avatars', 'bonips_render_sell_buyer_avatars' );

			// Setup Script
			add_action( 'bonips_register_assets',          array( $this, 'register_assets' ) );
			add_action( 'bonips_front_enqueue_footer',     array( $this, 'enqueue_footer' ) );
			add_action( 'psf_template_redirect',              array( $this, 'psf_content' ), 10 ); 

		}

		/**
		 * Module Admin Init
		 * @since 1.7
		 * @version 1.0
		 */
		public function module_admin_init() {

			// Setup the "Sell This" Metaboxes
			$post_types = explode( ',', $this->sell_content['post_types'] );
			if ( ! empty( $post_types ) ) {

				foreach ( $post_types as $type ) {
					add_action( "add_meta_boxes_{$type}", array( $this, 'add_metabox' ) );
					add_action( "save_post_{$type}",      array( $this, 'save_metabox' ) );
				}

			}

			// User Override
			add_action( 'bonips_user_edit_after_balances', array( $this, 'sell_content_user_screen' ), 50 );

			add_action( 'personal_options_update',         array( $this, 'save_manual_profit_share' ), 50 );
			add_action( 'edit_user_profile_update',        array( $this, 'save_manual_profit_share' ), 50 );

		}

		/**
		 * Register Assets
		 * @since 1.7
		 * @version 1.0
		 */
		public function register_assets() {

			wp_register_script(
				'bonips-sell-this',
				plugins_url( 'assets/js/buy-content.js', boniPS_SELL ),
				array( 'jquery' ),
				'2.0.1',
				true
			);

		}

		/**
		 * Load Script
		 * @since 1.7
		 * @version 1.0.2
		 */
		public function enqueue_footer() {

			global $bonips_sell_this;

			// Only enqueue our script if it's needed
			if ( $bonips_sell_this === true ) {

				global $post;

				wp_localize_script(
					'bonips-sell-this',
					'boniPSBuyContent',
					array(
						'ajaxurl'    => esc_url( ( isset( $post->ID ) ) ? bonips_get_permalink( $post->ID ) : home_url( '/' ) ),
						'token'      => wp_create_nonce( 'bonips-buy-this-content' ),
						'working'    => esc_js( $this->sell_content['working'] ),
						'reload'     => $this->sell_content['reload'],
						'sweeterror' => __( 'Error', 'bonips' )
					)
				);

				wp_enqueue_script( 'bonips-sell-this' );

			}

		}

		/**
		 * Setup Content Filter
		 * We are using the template_redirect action to prevent this add-on having to run anywhere else but
		 * in the front-end of our website, since the the_content filter is used in soooo many places.
		 * As of 1.7.6, purchases are made via front-end submissions and not via admin-ajax.php
		 * @since 1.7
		 * @version 1.0.1
		 */
		public function template_redirect() {

			global $bonips_partial_content_sale;

			$bonips_partial_content_sale = false;

			// Handle purhchase requests
			$this->maybe_buy_content();

			// Unless we successfully bought the content, filter it
			add_filter( 'the_content', array( $this, 'the_content' ), $this->priority );

		}

		/**
		 * Maybe Buy Content
		 * Check if a purchase request has been made either via an AJAX submission.
		 * @since 1.7.6
		 * @version 1.0
		 */
		public function maybe_buy_content() {

			if ( is_user_logged_in() && ! bonips_is_admin() ) {

				if ( isset( $_POST['action'] ) && $_POST['action'] == 'bonips-buy-content' && isset( $_POST['postid'] ) && isset( $_POST['token'] ) && wp_verify_nonce( $_POST['token'], 'bonips-buy-this-content' ) ) {

					$post_id    = absint( $_POST['postid'] );
					$point_type = sanitize_key( $_POST['ctype'] );

					global $bonips_types;

					if ( ! array_key_exists( $point_type, $bonips_types ) || bonips_force_singular_session( $this->current_user_id, 'bonips-last-content-purchase' ) )
						wp_send_json( 'ERROR' );

					// If the content is for sale and we have not paid for it
					if ( bonips_post_is_for_sale( $post_id ) && ! bonips_user_paid_for_content( $this->current_user_id, $post_id ) ) {

						$content  = '';
						$post     = bonips_get_post( $post_id );
						$purchase = bonips_sell_content_new_purchase( $post, $this->current_user_id, $point_type );

						// Successfull purchase
						if ( $purchase === true ) {

							preg_match('/\[bonips_sell_this[^\]]*](.*)\[\/bonips_sell_this[^\]]*]/uis', $post->post_content , $match );

							$content = $post->post_content;
							if ( is_array( $match ) && array_key_exists( 1, $match ) )
								$content = $match[1];

							do_action( 'bonips_sell_before_content_render' );

							remove_filter( 'the_content', array( $this, 'the_content' ), $this->priority );
							$content = apply_filters( 'the_content', $content );
							$content = str_replace( ']]>', ']]&gt;', $content );
							$content = do_shortcode( $content );
							add_filter( 'the_content', array( $this, 'the_content' ), $this->priority );

						}

						// Something went wrong
						else {

							$content = $purchase;

						}

						// Let others play
						$content = apply_filters( 'bonips_content_purchase_ajax', $content, $purchase );

						if ( $purchase !== true )
							wp_send_json_error( $content );

						wp_send_json_success( $content );

					}

					wp_send_json( 'ERROR' );

				}

			}

		}

		/**
		 * The Content Overwrite
		 * Handles content sales by replacing the posts content with the appropriate template
		 * for those who have not paid. Admins and authors are excluded.
		 * @since 0.1
		 * @version 1.2.2
		 */
		public function the_content( $content ) {

			global $bonips_partial_content_sale, $bonips_sell_this;

			$post_id = bonips_sell_content_post_id();
			$post    = bonips_get_post( $post_id );

			// If content is for sale
			if ( bonips_post_is_for_sale( $post_id ) ) {

				$bonips_sell_this = true;

				// Parse shortcodes now just in case it has not been done already
				$_content = do_shortcode( $content );

				// Partial Content Sale - We have already done the work in the shortcode
				if ( $bonips_partial_content_sale === true )
					return $_content;

				// Logged in users
				if ( is_user_logged_in() ) {

					// Authors and admins do not pay
					if ( ! bonips_is_admin() && $post->post_author != $this->current_user_id ) {

						// In case we have not paid
						if ( ! bonips_user_paid_for_content( $this->current_user_id, $post_id ) ) {

							// Get Payment Options
							$payment_options = bonips_sell_content_payment_buttons( $this->current_user_id, $post_id );

							// User can buy
							if ( $payment_options !== false ) {

								$content = $this->sell_content['templates']['members'];
								$content = str_replace( '%buy_button%', $payment_options, $content );
								$content = bonips_sell_content_template( $content, $post, 'bonips-sell-entire-content', 'bonips-sell-unpaid' );

							}

							// Can not afford to buy
							else {

								$content = $this->sell_content['templates']['cantafford'];
								$content = bonips_sell_content_template( $content, $post, 'bonips-sell-entire-content', 'bonips-sell-insufficient' );

							}

						}

					}

				}

				// Visitors
				else {

					$content = $this->sell_content['templates']['visitors'];
					$content = bonips_sell_content_template( $content, $post, 'bonips-sell-entire-content', 'bonips-sell-visitor' );

				}

			}

			return $content;

		}

		public function psf_content() {

			global $bonips_partial_content_sale, $bonips_sell_this;

			$post_id = bonips_sell_content_post_id();
			$post    = bonips_get_post( $post_id );
			$content = '';

			// If content is for sale
			if ( bonips_post_is_for_sale( $post_id ) && ( psf_is_single_forum() || psf_is_single_topic() || psf_is_single_reply() ) ) {

				$bonips_sell_this = true;

				// Partial Content Sale - We have already done the work in the shortcode
				if ( $bonips_partial_content_sale === true ) return;

				// Logged in users
				if ( is_user_logged_in() ) {

					// Authors and admins do not pay
					if ( ! bonips_is_admin() && $post->post_author != $this->current_user_id ) {

						// In case we have not paid
						if ( ! bonips_user_paid_for_content( $this->current_user_id, $post_id ) ) {

							// Get Payment Options
							$payment_options = bonips_sell_content_payment_buttons( $this->current_user_id, $post_id );

							// User can buy
							if ( $payment_options !== false ) {

								$content = $this->sell_content['templates']['members'];
								$content = str_replace( '%buy_button%', $payment_options, $content );
								$content = bonips_sell_content_template( $content, $post, 'bonips-sell-entire-content', 'bonips-sell-unpaid' );
								$this->bonips_psf_sell_forum_actions();

							}

							// Can not afford to buy
							else {

								$content = $this->sell_content['templates']['cantafford'];
								$content = bonips_sell_content_template( $content, $post, 'bonips-sell-entire-content', 'bonips-sell-insufficient' );
								$this->bonips_psf_sell_forum_actions();

							}

						}

					}

				}

				// Visitors
				else {

					$content = $this->sell_content['templates']['visitors'];
					$content = bonips_sell_content_template( $content, $post, 'bonips-sell-entire-content', 'bonips-sell-visitor' );
					$this->bonips_psf_sell_forum_actions();

				}

			}

			$this->psf_content = $content;

		}


		public function bonips_psf_sell_forum_actions() {

			add_action( 'psf_template_before_single_forum', array( $this, 'psf_template_before_single' ) );
			add_action( 'psf_template_before_single_topic', array( $this, 'psf_template_before_single' ) );
			add_filter( 'psf_no_breadcrumb', 				array( $this, 'psf_remove_breadcrumb' ), 10 );
			add_filter( 'psf_get_forum_subscribe_link', 	array( $this, 'psf_remove_subscribe_link' ), 10 , 3 );
			add_filter( 'psf_get_topic_subscribe_link', 	array( $this, 'psf_remove_subscribe_link' ), 10 , 3 );
			add_filter( 'psf_get_topic_favorite_link', 		array( $this, 'psf_remove_subscribe_link' ), 10 , 3 );
			add_filter( 'psf_get_template_part', 			array( $this, 'psf_remove_templates' ), 10 , 3 );
			add_filter( 'psf_get_single_forum_description', array( $this, 'psf_get_single_description' ), 10 , 3 );
			add_filter( 'psf_get_single_topic_description', array( $this, 'psf_get_single_description' ), 10 , 3 );

		}

		public function psf_template_before_single() {

			echo $this->psf_content;

		}

		public function psf_remove_breadcrumb( $is_front ) {
			return true;
		}

		public function psf_remove_subscribe_link( $retval, $r, $args ) {
			return '';
		}

		public function psf_remove_templates( $templates, $slug, $name ) {

			if ( $slug == 'content' ) return $templates;

			return array('');
		}

		public function psf_get_single_description( $retstr, $r, $args ) {
			return '';
		}

		/**
		 * User Level Override
		 * @since 1.5
		 * @version 1.3.1
		 */
		public function sell_content_user_screen( $user ) {

			// Only visible to admins
			if ( ! bonips_is_admin() ) return;

			$bonips_types      = bonips_get_types( true );
			$available_options = array();

			foreach ( $bonips_types as $point_type_key => $label ) {

				$setup = array( 'name' => $label, 'enabled' => false, 'default' => 0, 'excluded' => true, 'override' => false, 'custom' => 0 );

				if ( ! empty( $this->sell_content['type'] ) && in_array( $point_type_key, $this->sell_content['type'] ) ) {

					$setup['enabled']  = true;
					$bonips            = bonips( $point_type_key );

					if ( ! $bonips->exclude_user( $user->ID ) ) {

						$setup['excluded'] = false;

						$settings          = bonips_get_option( 'bonips_sell_this_' . $point_type_key );

						$setup['default']  = $settings['profit_share'];

						$users_share = bonips_get_user_meta( $user->ID, 'bonips_sell_content_share_' . $point_type_key, '', true );
						if ( strlen( $users_share ) > 0 ) {

							$setup['override'] = true;
							$setup['custom']   = $users_share;

						}

					}

				}

				$available_options[ $point_type_key ] = $setup;

			}

			if ( empty( $available_options ) ) return;

?>
<p class="bonips-p"><?php _e( 'Users profit share when their content is purchased.', 'bonips' ); ?></p>
<table class="form-table bonips-inline-table">
	<tr>
		<th scope="row"><?php _e( 'Profit Share', 'bonips' ); ?></th>
		<td>
			<fieldset id="bonips-badge-list" class="badge-list">
				<legend class="screen-reader-text"><span><?php _e( 'Profit Share', 'bonips' ); ?></span></legend>
<?php

			foreach ( $available_options as $point_type => $data ) {

				// This point type is not for sale
				if ( ! $data['enabled'] ) {

?>
				<div class="bonips-wrapper buycred-wrapper disabled-option color-option">
					<div><?php printf( _x( '%s Profit Share', 'Points Name', 'bonips' ), $data['name'] ); ?></div>
					<div class="balance-row">
						<div class="balance-view"><?php _e( 'Disabled', 'bonips' ); ?></div>
						<div class="balance-desc"><em><?php _e( 'Not accepted as payment.', 'bonips' ); ?></em></div>
					</div>
				</div>
<?php

				}

				// This user is excluded from this point type
				elseif ( $data['excluded'] ) {

?>
				<div class="bonips-wrapper buycred-wrapper disabled-option color-option">
					<div><?php printf( _x( '%s Profit Share', 'Points Name', 'bonips' ), $data['name'] ); ?></div>
					<div class="balance-row">
						<div class="balance-view"><?php _e( 'Excluded', 'bonips' ); ?></div>
						<div class="balance-desc"><em><?php printf( _x( 'User can not pay using %s', 'Points Name', 'bonips' ), $data['name'] ); ?></em></div>
					</div>
				</div>
<?php

				}

				// Eligeble user
				else {

?>
				<div class="bonips-wrapper buycred-wrapper color-option selected">
					<div><?php printf( _x( '%s Profit Share', 'Buying Points', 'bonips' ), $data['name'] ); ?></div>
					<div class="balance-row">
						<div class="balance-view"><input type="text" size="8" name="bonips_sell_this[<?php echo $point_type; ?>]" class="half" placeholder="<?php echo esc_attr( $data['default'] ); ?>" value="<?php if ( $data['override'] ) echo esc_attr( $data['custom'] ); ?>" /> %</div>
						<div class="balance-desc"><em><?php _e( 'Leave empty to use the default.', 'bonips' ); ?></em></div>
					</div>
				</div>
<?php

				}

			}

?>
			</fieldset>
		</td>
	</tr>
</table>
<hr />
<?php

		}

		/**
		 * Save Override
		 * @since 1.5
		 * @version 1.2
		 */
		function save_manual_profit_share( $user_id ) {

			// Only visible to admins
			if ( ! bonips_is_admin() ) return;

			if ( isset( $_POST['bonips_sell_this'] ) && ! empty( $_POST['bonips_sell_this'] ) ) {

				foreach ( $_POST['bonips_sell_this'] as $point_type => $share ) {

					$share = sanitize_text_field( $share );

					bonips_delete_user_meta( $user_id, 'bonips_sell_content_share_' . $point_type );
					if ( $share != '' && is_numeric( $share ) )
						bonips_update_user_meta( $user_id, 'bonips_sell_content_share_' . $point_type, '', $share );

				}

			}

		}

		/**
		 * Enabled / Disabled Select Options
		 * @since 1.7
		 * @version 1.0
		 */
		protected function enabled_options( $selected = '' ) {

			$options = array(
				'disabled' => __( 'Disabled', 'bonips' ),
				'enabled'  => __( 'Enabled', 'bonips' )
			);

			$output = '';
			foreach ( $options as $value => $label ) {
				$output .= '<option value="' . $value . '"';
				if ( $selected == $value ) $output .= ' selected="selected"';
				$output .= '>' . $label . '</option>';
			}

			return $output;

		}

		/**
		 * Settings Page
		 * @since 0.1
		 * @version 1.4
		 */
		public function after_general_settings( $bonips = NULL ) {

			$post_types     = bonips_sell_content_post_types();
			$selected_types = explode( ',', $this->sell_content['post_types'] );

			$point_types    = bonips_get_types( true );

?>
<h4><span class="dashicons dashicons-admin-plugins static"></span><?php _e( 'Sell Content', 'bonips' ); ?></h4>
<div class="body" style="display:none;">

	<h3><?php _e( 'Post Types', 'bonips' ); ?></h3>
	<p><?php _e( 'Which post type(s) content field do you want to sell access to?', 'bonips' ); ?></p>
	<div id="bonips-sell-this-post-type-filter">
<?php

			if ( ! empty( $post_types ) ) {
				foreach ( $post_types as $post_type => $post_type_label ) {

					$selected = '';
					if ( in_array( $post_type, $selected_types ) )
						$selected = ' checked="checked"';

					$show_options = 'none';
					if ( in_array( $post_type, $selected_types ) )
						$show_options = 'block';

?>
	<div class="row">
		<div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
			<div class="checkbox">
				<label for="<?php echo $this->field_id( array( 'post_types' => $post_type ) ); ?>"><input type="checkbox" name="<?php echo $this->field_name( array( 'post_types' => $post_type ) ); ?>"<?php echo $selected; ?> id="<?php echo $this->field_id( array( 'post_types' => $post_type ) ); ?>" class="bonips-check-count" data-type="<?php echo $post_type; ?>" value="<?php echo $post_type; ?>" /> <?php echo esc_attr( $post_type_label ); ?></label>
			</div>
		</div>
		<div class="col-lg-9 col-md-9 col-sm-12 col-xs-12">
			<div id="<?php echo $this->field_id( array( 'post_types' => $post_type ) ); ?>-wrap" style="display: <?php echo $show_options; ?>;">
				<div class="row">
					<div class="col-lg-5 col-md-5 col-sm-6 col-xs-12">
						<div class="form-group">
							<select name="<?php echo $this->field_name( array( 'filters' => $post_type ) ); ?>[by]" class="form-control toggle-filter-menu" data-type="<?php echo $post_type; ?>">
<?php

					$settings = array( 'by' => 'all', 'list' => '' );
					if ( array_key_exists( $post_type, $this->sell_content['filters'] ) )
						$settings = $this->sell_content['filters'][ $post_type ];

					$options = bonips_get_post_type_options( $post_type );
					if ( ! empty( $options ) ) {
						foreach ( $options as $value => $option ) {

							echo '<option value="' . $value . '"';
							if ( $value == $settings['by'] ) echo ' selected="selected"';
							if ( $option['data'] != '' ) echo ' data-place="' . $option['data'] . '"';
							echo '>' . $option['label'] . '</option>';

						}
					}

?>
							</select>
						</div>
					</div>
					<div class="col-lg-7 col-md-7 col-sm-6 col-xs-12">
						<div id="post-type-filter-<?php echo $post_type; ?>" style="display: <?php if ( ! in_array( $settings['by'], array( 'all', 'manual' ) ) ) echo 'block'; else echo 'none'; ?>;">
							<div class="form-group">
								<input type="text" name="<?php echo $this->field_name( array( 'filters' => $post_type ) ); ?>[list]" value="<?php echo esc_attr( $settings['list'] ); ?>" placeholder="<?php if ( array_key_exists( $settings['by'], $options ) ) echo esc_attr( $options[ $settings['by'] ]['data'] ); ?>" class="form-control" />
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
<?php

				}
			}

?>
	</div>

	<h3><?php _e( 'Point Types', 'bonips' ); ?></h3>
	<p><?php _e( 'Which point type(s) can be used as payment for accessing content?', 'bonips' ); ?></p>
	<div class="row">
		<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
<?php

			if ( ! empty( $point_types ) ) {
				foreach ( $point_types as $point_type => $point_type_label ) {

					$selected = '';
					if ( in_array( $point_type, $this->sell_content['type'] ) )
						$selected = ' checked="checked"';

					if ( count( $point_types ) === 1 )
						$selected = ' checked="checked" disabled="disabled"';

?>
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'type' => $point_type ) ); ?>"><input type="checkbox" name="<?php echo $this->field_name( array( 'type' => $point_type ) ); ?>"<?php echo $selected; ?> id="<?php echo $this->field_id( array( 'type' => $point_type ) ); ?>" class="bonips-check-count" data-type="<?php echo $point_type; ?>" value="<?php echo $point_type; ?>" /> <?php echo esc_attr( $point_type_label ); ?></label>
			</div>
<?php

				}
			}

?>
		</div>
	</div>

<?php

			if ( ! empty( $point_types ) ) {
				foreach ( $point_types as $point_type => $point_type_label ) {

					$selected = 'none';
					if ( in_array( $point_type, $this->sell_content['type'] ) )
						$selected = 'block';

					if ( count( $point_types ) === 1 )
						$selected = 'block';

					$bonips     = bonips( $point_type );
					$type_setup = bonips_get_option( 'bonips_sell_this_' . $point_type );
					$type_setup = wp_parse_args( $type_setup, array(
						'status'         => 'disabled',
						'price'          => 0,
						'expire'         => 0,
						'profit_share'   => 0,
						'button_label'   => 'Pay %price%',
						'button_classes' => 'btn btn-primary btn-lg',
						'log_payment'    => 'Purchase of %link_with_title%',
						'log_sale'       => 'Sale of %link_with_title%'
					) );

					$expiration_label = apply_filters( 'bonips_sell_exp_title', __( 'Hour(s)', 'bonips' ), $point_type );

?>
	<div id="bonips-sell-<?php echo $point_type; ?>-wrap" style="display: <?php echo $selected; ?>;">
		<h3><?php printf( __( '%s Setup', 'bonips' ), $point_type_label ); ?></h3>
		<div class="row">
			<div class="col-lg-3 col-md-3 col-sm-3 col-xs-12">
				<div class="form-group">
					<label for="<?php echo $this->field_id( array( 'post_type_setup' => $point_type . '-status' ) ); ?>"><?php _e( 'Default Status', 'bonips' ); ?></label>
					<select name="<?php echo $this->field_name( array( 'post_type_setup' => $point_type ) ); ?>[status]" id="<?php echo $this->field_id( array( 'post_type_setup' => $point_type . '-status' ) ); ?>" class="form-control">
						<?php echo $this->enabled_options( $type_setup['status'] ); ?>
					</select>
				</div>
			</div>
			<div class="col-lg-3 col-md-3 col-sm-3 col-xs-12">
				<div class="form-group">
					<label for="<?php echo $this->field_id( array( 'post_type_setup' => $point_type . '-price' ) ); ?>"><?php _e( 'Default Price', 'bonips' ); ?></label>
					<input type="text" name="<?php echo $this->field_name( array( 'post_type_setup' => $point_type ) ); ?>[price]" id="<?php echo $this->field_id( array( 'post_type_setup' => $point_type . '-price' ) ); ?>" class="form-control" value="<?php echo esc_attr( $type_setup['price'] ); ?>" />
				</div>
			</div>
			<div class="col-lg-3 col-md-3 col-sm-3 col-xs-12">
				<div class="form-group">
					<label for="<?php echo $this->field_id( array( 'post_type_setup' => $point_type . '-expire' ) ); ?>"><?php _e( 'Expiration', 'bonips' ); ?></label>
					<input type="text" name="<?php echo $this->field_name( array( 'post_type_setup' => $point_type ) ); ?>[expire]" id="<?php echo $this->field_id( array( 'post_type_setup' => $point_type . '-expire' ) ); ?>" class="form-control" value="<?php echo esc_attr( $type_setup['expire'] ); ?>" />
					<p><span class="description"><?php printf( __( 'Option to automatically expire purchases after certain number of %s. Use zero to disable.', 'bonips' ), $expiration_label ); ?></span></p>
				</div>
			</div>
			<div class="col-lg-3 col-md-3 col-sm-3 col-xs-12">
				<div class="form-group">
					<label for="<?php echo $this->field_id( array( 'post_type_setup' => $point_type . '-profit-share' ) ); ?>"><?php _e( 'Profit Share', 'bonips' ); ?></label>
					<input type="text" name="<?php echo $this->field_name( array( 'post_type_setup' => $point_type ) ); ?>[profit_share]" id="<?php echo $this->field_id( array( 'post_type_setup' => $point_type . '-profit-share' ) ); ?>" class="form-control" value="<?php echo esc_attr( $type_setup['profit_share'] ); ?>" />
					<p><span class="description"><?php printf( __( 'Option to pay a percentage of each sale with the content author.', 'bonips' ), $expiration_label ); ?></span></p>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
				<div class="form-group">
					<label for="<?php echo $this->field_id( array( 'post_type_setup' => $point_type . '-button' ) ); ?>"><?php _e( 'Button Label', 'bonips' ); ?></label>
					<input type="text" name="<?php echo $this->field_name( array( 'post_type_setup' => $point_type ) ); ?>[button_label]" id="<?php echo $this->field_id( array( 'post_type_setup' => $point_type . '-button' ) ); ?>" class="form-control" placeholder="<?php _e( 'Required', 'bonips' ); ?>" value="<?php echo esc_attr( $type_setup['button_label'] ); ?>" />
					<p><span class="description"><?php echo $this->core->available_template_tags( array(), '%price%' ); ?></span></p>
				</div>
			</div>
			<div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
				<div class="form-group">
					<label for="<?php echo $this->field_id( array( 'post_type_setup' => $point_type . '-css' ) ); ?>"><?php _e( 'Button CSS Classes', 'bonips' ); ?></label>
					<input type="text" name="<?php echo $this->field_name( array( 'post_type_setup' => $point_type ) ); ?>[button_classes]" id="<?php echo $this->field_id( array( 'post_type_setup' => $point_type . '-css' ) ); ?>" class="form-control" value="<?php echo esc_attr( $type_setup['button_classes'] ); ?>" />
				</div>
			</div>
		</div>
		<h3><?php _e( 'Protokollvorlages', 'bonips' ); ?></h3>
		<div class="row">
			<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
				<div class="form-group">
					<label for="<?php echo $this->field_id( array( 'post_type_setup' => $point_type . '-log-pay' ) ); ?>"><?php _e( 'Payment log entry template', 'bonips' ); ?></label>
					<input type="text" name="<?php echo $this->field_name( array( 'post_type_setup' => $point_type ) ); ?>[log_payment]" id="<?php echo $this->field_id( array( 'post_type_setup' => $point_type . '-log-pay' ) ); ?>" class="form-control" placeholder="<?php _e( 'Required', 'bonips' ); ?>" value="<?php echo esc_attr( $type_setup['log_payment'] ); ?>" />
					<p><span class="description"><?php echo $this->core->available_template_tags( array( 'general', 'post' ) ); ?></span></p>
				</div>
			</div>
			<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
				<div class="form-group">
					<label for="<?php echo $this->field_id( array( 'post_type_setup' => $point_type . '-log-share' ) ); ?>"><?php _e( 'Profit Share payout log entry template', 'bonips' ); ?></label>
					<input type="text" name="<?php echo $this->field_name( array( 'post_type_setup' => $point_type ) ); ?>[log_sale]" id="<?php echo $this->field_id( array( 'post_type_setup' => $point_type . '-log-share' ) ); ?>" class="form-control" placeholder="<?php _e( 'Required', 'bonips' ); ?>" value="<?php echo esc_attr( $type_setup['log_sale'] ); ?>" />
					<p><span class="description"><?php echo $this->core->available_template_tags( array( 'general', 'post' ) ); ?></span></p>
				</div>
			</div>
		</div>
	</div>
<?php

				}
			}

?>

	<h3><?php _e( 'Transactions', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
			<div class="checkbox">
				<label for="<?php echo $this->field_id( 'reload' ); ?>"><input type="checkbox" name="<?php echo $this->field_name( 'reload' ); ?>" id="<?php echo $this->field_id( 'reload' ); ?>" <?php checked( $this->sell_content['reload'], 1 ); ?> value="1" /> <?php _e( 'Reload page after successful payments.', 'bonips' ); ?></label>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'working' ); ?>"><?php _e( 'Button Label', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( 'working' ); ?>" id="<?php echo $this->field_id( 'working' ); ?>" class="form-control" placeholder="<?php _e( 'Required', 'bonips' ); ?>" value="<?php echo esc_attr( $this->sell_content['working'] ); ?>" />
				<p><span class="description"><?php _e( 'Option to show a custom button label while the payment is being processed. HTML is allowed.', 'bonips' ); ?></span></p>
			</div>
		</div>
	</div>

	<div class="row">
		<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
			<h3><?php _e( 'Purchase Template', 'bonips' ); ?></h3>
			<p><span class="description"><?php _e( 'The content will be replaced with this template when viewed by a user that has not paid for the content but can afford to pay.', 'bonips' ); ?></span></p>
<?php

			wp_editor( $this->sell_content['templates']['members'], $this->field_id( array( 'templates' => 'members' ) ), array(
				'textarea_name' => $this->field_name( array( 'templates' => 'members' ) ),
				'textarea_rows' => 10
			) );

			echo '<p>' . $this->core->available_template_tags( array( 'post' ), '%buy_button%' ) . '</p>';

?>
		</div>
	</div>

	<div class="row">
		<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
			<h3><?php _e( 'Insufficient Funds Template', 'bonips' ); ?></h3>
			<p><span class="description"><?php _e( 'The content will be replaced with this template when viewed by a user that has not paid for the content and can not afford to pay.', 'bonips' ); ?></span></p>
<?php

			wp_editor( $this->sell_content['templates']['cantafford'], $this->field_id( array( 'templates' => 'cantafford' ) ), array(
				'textarea_name' => $this->field_name( array( 'templates' => 'cantafford' ) ),
				'textarea_rows' => 10
			) );

			echo '<p>' . $this->core->available_template_tags( array( 'post' ) ) . '</p>';

?>
		</div>
	</div>

	<div class="row">
		<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
			<h3><?php _e( 'Visitors Template', 'bonips' ); ?></h3>
			<p><span class="description"><?php _e( 'The content will be replaced with this template when viewed by someone who is not logged in on your website.', 'bonips' ); ?></span></p>
<?php

			wp_editor( $this->sell_content['templates']['visitors'], $this->field_id( array( 'templates' => 'visitors' ) ), array(
				'textarea_name' => $this->field_name( array( 'templates' => 'visitors' ) ),
				'textarea_rows' => 10
			) );

			echo '<p>' . $this->core->available_template_tags( array( 'post' ) ) . '</p>';

?>
		</div>
	</div>

</div>
<script type="text/javascript">
(function($) {

	var selectedposttypes  = <?php echo count( $selected_types ); ?>;
	var selectedpointtypes = <?php echo count( $this->sell_content['type'] ); ?>;
	
	$( '#boniPS-wrap .bonips-check-count' ).on('click', function(){

		if ( $(this).is( ':checked' ) ) {

			selectedposttypes++;
			$( '#bonips-sell-content-post-type-warning' ).hide();

			$( '#sellcontentprefsposttypes' + $(this).data( 'type' ) + '-wrap' ).show();

		}
		else {
			selectedposttypes--;
			if ( selectedposttypes <= 0 )
				$( '#bonips-sell-content-post-type-warning' ).show();
			else
				$( '#bonips-sell-content-post-type-warning' ).hide();

			$( '#sellcontentprefsposttypes' + $(this).data( 'type' ) + '-wrap' ).hide();
		}

	});
	
	$( '#boniPS-wrap .bonips-check-count' ).on('click', function(){

		if ( $(this).is( ':checked' ) ) {

			selectedpointtypes++;
			$( '#bonips-sell-content-point-type-warning' ).hide();

			$( '#bonips-sell-' + $(this).data( 'type' ) + '-wrap' ).show();

		}
		else {
			selectedpointtypes--;
			if ( selectedpointtypes <= 0 )
				$( '#bonips-sell-content-point-type-warning' ).show();
			else
				$( '#bonips-sell-content-point-type-warning' ).hide();

			$( '#bonips-sell-' + $(this).data( 'type' ) + '-wrap' ).hide();
		}

	});

	$( '#bonips-sell-this-post-type-filter' ).on( 'change', 'select.toggle-filter-menu', function(){

		var post_type      = $(this).data( 'type' );
		var selectedfilter = $(this).find( ':selected' );
		var placeholder    = selectedfilter.data( 'place' );

		if ( selectedfilter === undefined || selectedfilter.val() == 'all' || selectedfilter.val() == 'manual' ) {
			$( '#post-type-filter-' + post_type ).hide();
			$( '#post-type-filter-' + post_type + ' input' ).val( '' );
		}

		else {
			$( '#post-type-filter-' + post_type ).show();
		}

		if ( placeholder === undefined )
			$( '#post-type-filter-' + post_type + ' input' ).attr( 'placeholder', '' );

		else
			$( '#post-type-filter-' + post_type + ' input' ).attr( 'placeholder', placeholder );

	});

})( jQuery );
</script>
<?php

		}

		/**
		 * Sanitize & Save Settings
		 * @since 0.1
		 * @version 1.4
		 */
		public function sanitize_extra_settings( $new_data, $data, $general ) {

			$settings = $data['sell_content'];

			// Post Types
			$post_types = array();
			if ( array_key_exists( 'post_types', $settings ) && is_array( $settings['post_types'] ) && ! empty( $settings['post_types'] ) ) {

				foreach ( $settings['post_types'] as $post_type ) {
					$post_types[] = sanitize_text_field( $post_type );
				}

			}
			$new_data['sell_content']['post_types'] = implode( ',', $post_types );

			// Post Type Filter
			$filters = array();
			if ( array_key_exists( 'filters', $settings ) && is_array( $settings['filters'] ) && ! empty( $settings['filters'] ) ) {

				foreach ( $settings['filters'] as $post_type => $setup ) {

					if ( ! in_array( $post_type, $post_types ) ) continue;

					$filters[ $post_type ] = array( 'by' => 'all', 'list' => '' );

					$by = sanitize_text_field( $setup['by'] );
					if ( $by != '' ) {

						// Unless we selected all, we need to check the list
						if ( $by !== 'all' && $by !== 'manual' ) {

							// Clean up list by sanitizing and removing stray empty spaces
							$list = sanitize_text_field( $setup['list'] );
							if ( $list != '' ) {
								$_list = array();
								foreach ( explode( ',', $list ) as $object_slug ) {
									$object_slug = sanitize_text_field( $object_slug );
									$object_slug = trim( $object_slug );
									$_list[] = $object_slug;
								}
								$list = implode( ',', $_list );
							}

							$filters[ $post_type ]['by']   = $by;
							$filters[ $post_type ]['list'] = $list;

						}
						elseif ( $by === 'manual' ) {

							$filters[ $post_type ]['by'] = 'manual';

						}

					}

				}

			}
			$new_data['sell_content']['filters'] = $filters;

			// Point Types
			$point_types = array();
			if ( array_key_exists( 'type', $settings ) && is_array( $settings['type'] ) && ! empty( $settings['type'] ) ) {

				foreach ( $settings['type'] as $point_type ) {
					$point_types[] = sanitize_key( $point_type );
				}

			}
			if ( empty( $point_types ) )
				$point_types[] = BONIPS_DEFAULT_TYPE_KEY;

			$new_data['sell_content']['type'] = $point_types;

			// Point type default setup
			if ( array_key_exists( 'post_type_setup', $settings ) ) {
				foreach ( $settings['post_type_setup'] as $point_type => $setup ) {

					$new = wp_parse_args( $setup, array(
						'status'         => 'disabled',
						'price'          => 0,
						'expire'         => 0,
						'profit_share'   => 0,
						'button_label'   => '',
						'button_classes' => '',
						'log_payment'    => '',
						'log_sale'       => ''
					) );

					bonips_update_option( 'bonips_sell_this_' . $point_type, $new );

				}
			}

			$new_data['sell_content']['reload']                  = ( ( isset( $settings['reload'] ) ) ? absint( $settings['reload'] ) : 0 );
			$new_data['sell_content']['working']                 = wp_kses_post( $settings['working'] );

			// Templates
			$new_data['sell_content']['templates']['members']    = wp_kses_post( $settings['templates']['members'] );
			$new_data['sell_content']['templates']['visitors']   = wp_kses_post( $settings['templates']['visitors'] );
			$new_data['sell_content']['templates']['cantafford'] = wp_kses_post( $settings['templates']['cantafford'] );

			update_option( 'bonips_sell_content_one_seven_updated', time() );

			return $new_data;

		}

		/**
		 * Scripts & Styles
		 * @since 1.7
		 * @version 1.0
		 */
		public function scripts_and_styles() {

			$screen = get_current_screen();

			if ( in_array( $screen->id, explode( ',', $this->sell_content['post_types'] ) ) ) {
				wp_enqueue_style( 'bonips-bootstrap-grid' );
				wp_enqueue_style( 'bonips-forms' );
			}

		}

		/**
		 * Add Meta Box to Content
		 * @since 0.1
		 * @version 1.1
		 */
		public function add_metabox( $post ) {

			$settings = bonips_sell_content_settings();

			// Do not add the metabox unless we set this post type to be "manual"
			if ( empty( $settings['filters'][ $post->post_type ] ) || $settings['filters'][ $post->post_type ]['by'] !== 'manual' ) return;

			add_meta_box(
				'bonips-sell-content-setup',
				apply_filters( 'bonips_sell_this_label', __( 'Sell Content', 'bonips' ), $this ),
				array( $this, 'metabox' ),
				$post->post_type,
				'side',
				'high'
			);

			add_filter( 'postbox_classes_' . $post->post_type . '_bonips-sell-content-setup',  array( $this, 'metabox_classes' ) );

		}

		/**
		 * Sell Meta Box
		 * @since 0.1
		 * @version 1.2
		 */
		public function metabox( $post ) {

			$settings   = bonips_sell_content_settings();
			$expiration = apply_filters( 'bonips_sell_exp_title', __( 'Hour(s)', 'bonips' ) );
			$is_author  = ( ( $post->post_author == $this->current_user_id ) ? true : false );

?>
<style type="text/css">
#bonips-sell-content-setup .inside { padding: 0 !important; }
#bonips-sell-content-setup .inside .row { margin-bottom: 0; }
#bonips-sell-content-setup .inside .container-fluid { padding-left: 0; padding-right: 0; }
#bonips-sell-content-setup .inside .row .col-lg-12 .form-group { padding: 12px 12px 10px 12px; background-color: white; border-bottom: 1px solid #ddd; }
#bonips-sell-content-types .point-type-setup .cover { border-bottom: 1px solid #ddd; }
#bonips-sell-content-types .point-type-setup .cover > .row { padding-top: 6px; padding-bottom: 12px; }
#bonips-sell-content-types .point-type-setup:last-child .cover { border-bottom: none; }
</style>
<div id="bonips-sell-content-types" class="container-fluid">
	<input type="hidden" name="bonips-sell-this-setup-token" value="<?php echo wp_create_nonce( 'bonips-sell-this-content' ); ?>" />
<?php

			if ( ! empty( $settings['type'] ) ) {
				foreach ( $settings['type'] as $point_type ) {

					$setup  = bonips_get_option( 'bonips_sell_this_' . $point_type );

					if ( $setup['status'] === 'disabled' ) continue;

					$bonips     = bonips( $point_type );

					$suffix = '_' . $point_type;
					if ( $point_type == BONIPS_DEFAULT_TYPE_KEY )
						$suffix = '';

					$sale_setup = (array) bonips_get_post_meta( $post->ID, 'boniPS_sell_content' . $suffix );

					$sale_setup = empty($sale_setup) ? $sale_setup : $sale_setup[0];
					
					$sale_setup = shortcode_atts( array(
						'status' => 'disabled',
						'price'  => 0,
						'expire' => 0 
					), $sale_setup[0] );

					$expiration_description = __( 'Never expires', 'bonips' );
					if ( absint( $sale_setup['expire'] ) > 0 )
						$expiration_description = $sale_setup['expire'] . ' ' . $expiration;

?>
	<div class="form point-type-setup">
		<div class="row row-narrow">
			<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
				<div class="form-group slim">
					<label for="bonips-sell-this-<?php echo $point_type; ?>-status" class="slim"><input type="checkbox" name="bonips_sell_this[<?php echo $point_type; ?>][status]" id="bonips-sell-this-<?php echo $point_type; ?>-status"<?php if ( $sale_setup['status'] === 'enabled' ) echo ' checked="checked"'; ?> value="enabled" class="toggle-setup" data-type="<?php echo $point_type; ?>" /> <?php printf( __( 'Sell using %s', 'Point types name', 'bonips' ), $bonips->plural() ); ?></label>
				</div>
			</div>
		</div>
		<div class="cover">
			<div class="row row-narrow padded-row bonips-sell-setup-container" id="bonips-sell-content-<?php echo $point_type; ?>-wrap" style="display: <?php if ( $sale_setup['status'] === 'enabled' ) echo 'block'; else echo 'none'; ?>;">
				<div class="col-lg-6 col-md-6 col-sm-6 col-xs-6">
					<div class="form-group slim">
						<label for="bonips-sell-this-<?php echo $point_type; ?>-price"><?php _e( 'Price', 'bonips' ); ?></label>
						<input type="text" name="bonips_sell_this[<?php echo $point_type; ?>][price]" id="bonips-sell-this-<?php echo $point_type; ?>-price" class="form-control" value="<?php echo esc_attr( $sale_setup['price'] ); ?>" />
					</div>
				</div>
				<div class="col-lg-6 col-md-6 col-sm-6 col-xs-6">
					<div class="form-group slim">
						<label for="bonips-sell-this-<?php echo $point_type; ?>-expire"><?php _e( 'Expiration', 'bonips' ); ?></label>
						<input type="text" name="bonips_sell_this[<?php echo $point_type; ?>][expire]" id="bonips-sell-this-<?php echo $point_type; ?>-expire" class="form-control" value="<?php echo absint( $sale_setup['expire'] ); ?>" />
					</div>
				</div>
			</div>
		</div>
	</div>
<?php

				}
			}

?>
<script type="text/javascript">
(function($) {
	
	$( '#bonips-sell-content-types .toggle-setup' ).on('click', function(){

		if ( $(this).is( ':checked' ) ) {
			$( '#bonips-sell-content-types #bonips-sell-content-' + $(this).data( 'type' ) + '-wrap' ).slideDown();
		}
		else {
			$( '#bonips-sell-content-types #bonips-sell-content-' + $(this).data( 'type' ) + '-wrap' ).slideUp();
		}

	});

})( jQuery );
</script>
</div>
<?php

		}

		/**
		 * Save Sell Meta Box
		 * @since 0.1
		 * @version 1.1
		 */
		public function save_metabox( $post_id ) {

			// Minimum requirement
			if ( ! isset( $_POST['bonips_sell_this'] ) || ! is_array( $_POST['bonips_sell_this'] ) || empty( $_POST['bonips_sell_this'] ) ) return;

			// Verify nonce
			if ( isset( $_POST['bonips-sell-this-setup-token'] ) && wp_verify_nonce( $_POST['bonips-sell-this-setup-token'], 'bonips-sell-this-content' ) ) {

				$settings   = bonips_sell_content_settings();

				if ( ! empty( $settings['type'] ) ) {
					foreach ( $settings['type'] as $point_type ) {

						if ( ! array_key_exists( $point_type, $_POST['bonips_sell_this'] ) ) continue;

						$bonips     = bonips( $point_type );

						$new_setup  = array( 'status' => 'disabled', 'price' => 0, 'expire' => 0 );
						$submission = shortcode_atts( array(
							'status' => 'disabled',
							'price'  => 0,
							'expire' => 0
						), $_POST['bonips_sell_this'][ $point_type ] );

						if ( $submission['status'] == '' ) $submission['status'] = 'disabled';

						// If not empty and different from the general setup, save<
						if ( in_array( $submission['status'], array( 'enabled', 'disabled' ) ) )
							$new_setup['status'] = sanitize_key( $submission['status'] );

						// If not empty and different from the general setup, save<
						if ( strlen( $submission['price'] ) > 0 )
							$new_setup['price'] = $bonips->number( sanitize_text_field( $submission['price'] ) );

						// If not empty and different from the general setup, save<
						if ( strlen( $submission['expire'] ) > 0 )
							$new_setup['expire'] = absint( sanitize_text_field( $submission['expire'] ) );

						$suffix = '_' . $point_type;
						if ( $point_type == BONIPS_DEFAULT_TYPE_KEY )
							$suffix = '';

						bonips_update_post_meta( $post_id, 'boniPS_sell_content' . $suffix, $new_setup );

					}
				}

			}

		}

		/**
		 * Add Email Notice Instance
		 * @since 1.5.4
		 * @version 1.0
		 */
		public function email_notice_instance( $events, $request ) {

			if ( $request['ref'] == 'buy_content' ) {
				if ( $request['amount'] < 0 )
					$events[] = 'buy_content|negative';
				elseif ( $request['amount'] > 0 )
					$events[] = 'buy_content|positive';
			}

			return $events;

		}

		/**
		 * Support for Email Notices
		 * @since 1.1
		 * @version 1.0
		 */
		public function email_notices( $data ) {

			if ( $data['request']['ref'] == 'buy_content' ) {
				$message         = $data['message'];
				$data['message'] = $this->core->template_tags_post( $message, $data['request']['ref_id'] );
			}

			return $data;

		}

	}

endif;

/**
 * Load Sell Content Module
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'bonips_load_sell_content_addon' ) ) :
	function bonips_load_sell_content_addon( $modules, $point_types ) {

		$modules['solo']['content'] = new boniPS_Sell_Content_Module();
		$modules['solo']['content']->load();

		return $modules;

	}
endif;
add_filter( 'bonips_load_modules', 'bonips_load_sell_content_addon', 90, 2 );
