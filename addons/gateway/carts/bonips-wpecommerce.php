<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * WP E-Commerce Payment Gateway
 *
 * Custom Payment Gateway for WP E-Commerce.
 * @see http://getshopped.org/resources/docs/get-involved/writing-a-new-payment-gateway/
 * @since 1.3
 * @version 1.1
 */
if ( ! function_exists( 'bonips_init_wpecom_construct_gateway' ) ) :
	function bonips_init_wpecom_construct_gateway() {

		if ( ! class_exists( 'wpsc_merchant' ) ) return;

		global $nzshpcrt_gateways, $bonips_wpecom_settings;

		$bonips_wpecom_settings = shortcode_atts( array(
			'log'       => __( 'Payment for Order: #%order_id%', 'bonips' ),
			'type'      => BONIPS_DEFAULT_TYPE_KEY,
			'share'     => 0,
			'share_log' => __( 'Store sale', 'bonips' ),
			'rate'      => 1,
			'visitor'   => __( 'You must be logged in to use this gateway', 'bonips' ),
			'low_funds' => __( 'Insufficient Funds.', 'bonips' ),
			'message'   => __( 'Deduct the amount from your balance.', 'bonips' )
		), (array) get_option( 'bonips_wpecom_settings', '' ) );

		// Add gateway
		$nzshpcrt_gateways[] = array(
			'id'                     => 'bonips',
			'name'                   => bonips_label( true ),
			'has_recurring_billing'  => false,
			'wp_admin_cannot_cancel' => false,
			'requirements'           => array( 'php_version' => '5.2.4' ),
			'form'                   => 'bonips_wpecom_gateway_settings',
			'submit_function'        => 'bonips_wpecom_gateway_settings_save',
			'payment_type'           => 'bonips',
			// this may be legacy, not yet decided
			'internalname'           => 'bonips'
		);

		class boniPS_WP_E_Commerce_Gateway {

			public $core        = '';
			public $prefs       = array();
			public $bonips_type = BONIPS_DEFAULT_TYPE_KEY;

			/**
			 * Construct
			 */
			function __construct() {

				global $bonips_wpecom_settings;

				$this->prefs = $bonips_wpecom_settings;
				$type        = BONIPS_DEFAULT_TYPE_KEY;
				if ( isset( $bonips_wpecom_settings['type'] ) )
					$type = $bonips_wpecom_settings['type'];

				$this->core        = bonips( $type );
				$this->bonips_type = $type;

				add_action( 'wpsc_submit_checkout_gateway',          array( $this, 'process_gateway' ), 1, 2 );
				add_filter( 'wpsc_gateway_checkout_form_bonips',     array( $this, 'checkout_form' ) );
				add_filter( 'bonips_parse_log_entry_wpecom_payment', array( $this, 'parse_template_tags' ), 10, 2 );

			}

			/**
			 * Process Payment
			 * @since 1.3
			 * @version 1.1
			 */
			function process_gateway( $gateway, $purchase_log ) {

				if ( $gateway != 'bonips' ) return;

				// Prep
				$log_id = $purchase_log->get( 'id' );

				// Load Gateway
				$merchant_instance = new wpsc_merchant_bonips( $log_id, false, $this->prefs, $this->core, $this->bonips_type );
				$merchant_instance->construct_value_array();

				// Validate
				$merchant_instance->validate( $purchase_log );

				// Charge
				do_action_ref_array( 'wpsc_pre_submit_gateway', array( &$merchant_instance ) );
				$merchant_instance->submit();

			}

			/**
			 * Checkout Form
			 * @since 1.3
			 * @version 1.1
			 */
			function checkout_form() {

				$output  = '';
				if ( ! is_user_logged_in() )
					return '<tr><td>' . $this->core->template_tags_general( $this->prefs['visitor'] ) . '</td></tr>';

				$output .= '<tr><td><table width="100%"><thead><th class="cart-item">' . __( 'Item', 'bonips' ) . '</th><th class="cart-item-qt"></th><th class="cart-item-cost">' . $this->core->plural() . '</th></thead><tbody>';
				
				$total   = 0;
				while ( wpsc_have_cart_items() ) : wpsc_the_cart_item();

					$price = wpsc_cart_item_price( false );
					if ( $this->prefs['rate'] != 1 )
						$price = $this->prefs['rate']*$price;

					$total = $total+$price;

				endwhile;
				
				$output .= '<tr><td colspan="2">' . __( 'Total Cost', 'bonips' ) . '</td><td class="cart-item-cost">' . $this->core->format_creds( $total ) . '</td></tr>';
				$balance = $this->core->get_users_balance( get_current_user_id(), $this->bonips_type );
				
				if ( $balance < $total )
					$highlight = ' style="color:red;"';
				else
					$highlight = '';

				$output .= '<tr><td class="cart-item" colspan="2">' . __( 'Your current balance', 'bonips' ) . '</td><td class="cart-item-cost"' . $highlight . '>' . $this->core->format_creds( $balance ) . '</td></tr></tdody></table></tr>';

				if ( ! empty( $this->prefs['message'] ) )
					$output .= '<tr><td>' . $this->core->template_tags_general( $this->prefs['message'] ) . '</td></tr>';

				return apply_filters( 'bonips_wpecom_form', $output );

			}

			/**
			 * Parse Custom Template Tags
			 * @since 1.3
			 * @version 1.0
			 */
			function parse_template_tags( $content, $log_entry ) {

				if ( ! empty( $log_entry->data ) )
					$content = str_replace( '%order_id%', $log_entry->data, $content );
				else
					$content = str_replace( '%order_id%', 'missing', $content );

				return $content;

			}

		}

		new boniPS_WP_E_Commerce_Gateway();

		class wpsc_merchant_bonips extends wpsc_merchant {

			var $prefs          = array();
			var $core           = '';
			var $bonips_type    = BONIPS_DEFAULT_TYPE_KEY;
			var $cost           = 0;
			var $transaction_id = '';

			/**
			 * Construct
			 */
			function __construct( $purchase_id = NULL, $is_receiving = false, $prefs = NULL, $bonips = NULL, $type = BONIPS_DEFAULT_TYPE_KEY ) {

				parent::__construct( $purchase_id, $is_receiving );
				$this->prefs       = $prefs;
				$this->core        = $bonips;
				$this->bonips_type = $type;

			}

			/**
			 * Validate
			 * Checks to make sure the current user can use this gateway.
			 * @since 1.3
			 * @version 1.1
			 */
			function validate( $purchase_log ) {

				$error      = false;
				$user_id    = get_current_user_id();

				// Get cost
				$cart_total = $this->cart_data['total_price'];
				if ( $this->prefs['rate'] != 1 )
					$cart_total = $this->prefs['rate']*$cart_total;

				$cart_total = $this->core->number( $cart_total );
				$this->cost = $cart_total;

				// User is not logged in
				if ( ! is_user_logged_in() )
					$error = $this->core->template_tags_general( $this->prefs['visitor'] );

				// Else if user is excluded
				elseif ( $this->core->exclude_user( $user_id ) )
					$error = __( 'You can not use this gateway.', 'bonips' );

				// Else check balance
				else {

					// Rate
					$balance = $this->core->get_users_balance( $user_id, $this->bonips_type );
					if ( $balance < $this->cost ) {
						$error = $this->core->template_tags_general( $this->prefs['low_funds'] );
					}

				}

				// Let others decline a store order
				$decline = apply_filters( 'bonips_decline_store_purchase', $error, $purchase_log, $this );
				if ( $decline !== false ) {

					wpsc_delete_customer_meta( 'selected_gateway' );

					$this->set_error_message( $decline );
					$purchase_log->delete( $this->purchase_id );

					unset( $_SESSION['WpscGatewayErrorMessage'] );

					$this->return_to_checkout();

					exit;

				}

				// Prep for payment
				$this->user_id        = $user_id;
				$this->transaction_id = strtoupper( BONIPS_SLUG ) . $user_id . time();

			}

			/**
			 * Submit
			 * Charges the user for the purchase and if profit sharing is enabled
			 * each product owner.
			 * @since 1.3
			 * @version 1.3
			 */
			function submit() {

				// Since the wpsc_pre_submit_gateway action could change these values, we need to check
				if ( $this->cost > 0 && $this->user_id != 0 && !empty( $this->transaction_id ) ) {

					// Let other play before we start
					do_action_ref_array( 'bonips_wpecom_charg', array( &$this ) );

					// Charge
					$this->core->add_creds(
						'wpecom_payment',
						$this->user_id,
						0-$this->cost,
						$this->prefs['log'],
						'',
						$this->purchase_id,
						$this->bonips_type
					);

					// Update Order
					$this->set_transaction_details( $this->transaction_id, 3 );
					transaction_results( $this->cart_data['session_id'], false );

					// Payout Share
					if ( $this->prefs['share'] > 0 ) {

						// Loop though items
						foreach ( (array) $this->cart_items as $item ) {

							// Get product
							$product  = bonips_get_post( (int) $item['product_id'] );

							// Continue if product has just been deleted or owner is buyer
							if ( $product === NULL || $product->post_author == $this->user_id ) continue;

							// Calculate Cost
							$price    = $item['price'];
							$quantity = $item['quantity'];
							$cost     = $price*$quantity;

							// Calculate Share
							$percentage = apply_filters( 'bonips_wpecom_profit_share', $this->prefs['share'], $this, $product );
							if ( $percentage == 0 ) continue;

							$share    = ( $percentage / 100 ) * $cost;

							// Payout
							$this->core->add_creds(
								'store_sale',
								$product->post_author,
								$share,
								$this->prefs['share_log'],
								$product->ID,
								array( 'ref_type' => 'post' ),
								$this->bonips_type
							);

						}

					}

					// Let others play before we end
					do_action_ref_array( 'bonips_wpecom_charged', array( &$this ) );

					// Empty Cart, Redirect & Exit
					wpsc_empty_cart();

					$this->go_to_transaction_results( $this->cart_data['session_id'] );

					exit;

				}

				// Else save this as pending
				elseif ( ! empty( $this->transaction_id ) ) {

					$this->set_transaction_details( $this->transaction_id, 2 );

				}

			}

		}

	}
endif;
add_action( 'after_setup_theme', 'bonips_init_wpecom_construct_gateway' );

/**
 * Gateway Settings
 * @filter bonips_wpecom_settings
 * @since 1.3
 * @version 1.1
 */
if ( ! function_exists( 'bonips_wpecom_gateway_settings' ) ) :
	function bonips_wpecom_gateway_settings() {

		global $wpdb, $bonips_wpecom_settings;

		if ( ! isset( $bonips_wpecom_settings['type'] ) )
			$type = BONIPS_DEFAULT_TYPE_KEY;
		else
			$type = $bonips_wpecom_settings['type'];

		$bonips = bonips( $type );

		// Get current currency
		$currency_data     = $wpdb->get_results( "SELECT * FROM `" . WPSC_TABLE_CURRENCY_LIST . "` ORDER BY `country` ASC", ARRAY_A );
		$selected_currency = esc_attr( get_option( 'currency_type' ) );

		foreach ( $currency_data as $currency ) {

			if ( $selected_currency != $currency['id'] ) continue;

			if ( ! empty( $currency['symbol_html'] ) )
				$selected_currency = $currency['symbol_html'];
			else
				$selected_currency = $currency['code'];

			break;

		}

		$output = '
<tr>
	<td colspan="2"><strong>' . __( 'General', 'bonips' ) . '</strong></td>
</tr>
<tr>
	<td width="150">' . __( 'Protokollvorlage for Payments', 'bonips' ) . '</td>
	<td><input type="text" name="bonips_gateway[log]" value="' . esc_attr( $bonips_wpecom_settings['log'] ) . '" style="width:50%;" /><br /><span class="description">' . $bonips->available_template_tags( array( 'general' ), '%order_id%' ) . '</span></td>
</tr>';

		$bonips_types = bonips_get_types();
		if ( count( $bonips_types ) == 1 )
			$output .= '<input type="hidden" name="bonips_gateway[type]" value="bonips_default" />';
		else
			$output .= '
<tr>
	<td width="150">' . __( 'Point Type', 'bonips' ) . '</td>
	<td>' . bonips_types_select_from_dropdown( 'bonips_gateway[type]', 'bonips-point-type', $type, true ) . '</td>
</tr>';

		$output .= '
<tr>
	<td width="150">' . __( 'Exchange Rate', 'bonips' ) . '</td>
	<td><input type="text" name="bonips_gateway[rate]" value="' . esc_attr( $bonips_wpecom_settings['rate'] ) . '" style="width:50px;" /><br /><span class="description">' . sprintf( __( 'How much is 1 %s worth in %s', 'bonips' ), $selected_currency, $bonips->plural() ) . '</span></td>
</tr>
<tr>
	<td colspan="2"><strong>' . __( 'Profit Sharing', 'bonips' ) . '</strong></td>
</tr>
<tr>
	<td width="150">' . __( 'Payout', 'bonips' ) . '</td>
	<td><input type="text" name="bonips_gateway[share]" value="' . esc_attr( $bonips_wpecom_settings['share'] ) . '" style="width:50px;" /> %<br /><span class="description">' . __( 'Option to share sales with the product owner. Use zero to disable.', 'bonips' ) . '</span></td>
</tr>
<tr>
	<td width="150">' . __( 'Protokollvorlage', 'bonips' ) . '</td>
	<td><input type="text" name="bonips_gateway[share_log]" value="' . esc_attr( $bonips_wpecom_settings['share_log'] ) . '" style="width:50%;" /><br /><span class="description">' . $bonips->available_template_tags( array( 'general', 'post' ) ) . '</span></td>
</tr>
<tr>
	<td colspan="2"><strong>' . __( 'Messages', 'bonips' ) . '</strong></td>
</tr>
<tr>
	<td width="150">' . __( 'Instructions', 'bonips' ) . '</td>
	<td><input type="text" name="bonips_gateway[message]" value="' . esc_attr( $bonips_wpecom_settings['message'] ) . '" style="width:50%;" /><br /><span class="description">' . __( 'Optional instructions to show users when selecting this gateway. Leave empty to hide.', 'bonips' ) . '</span></td>
</tr>
<tr>
	<td width="150">' . __( 'Visitors', 'bonips' ) . '</td>
	<td><input type="text" name="bonips_gateway[visitor]" value="' . esc_attr( $bonips_wpecom_settings['visitor'] ) . '" style="width:50%;" /><br /><span class="description">' . __( 'Message to show visitors who are not logged in.', 'bonips' ) . '</span></td>
</tr>
<tr>
	<td width="150">' . __( 'Insufficient Funds', 'bonips' ) . '</td>
	<td><input type="text" name="bonips_gateway[low_funds]" value="' . esc_attr( $bonips_wpecom_settings['low_funds'] ) . '" style="width:50%;" /><br /><span class="description">' . $bonips->template_tags_general( __( 'Message to show when users does not have enough %plural% to pay using this gateway.', 'bonips' ) ) . '</span></td>
</tr>';

		return apply_filters( 'bonips_wpecom_settings', $output );

	}
endif;

/**
 * Save Gateway Settings
 * @filter bonips_wpecom_save_settings
 * @since 1.3
 * @version 1.1
 */
if ( ! function_exists( 'bonips_wpecom_gateway_settings_save' ) ) :
	function bonips_wpecom_gateway_settings_save() {

		if ( isset( $_POST['bonips_gateway'] ) ) {
			$new_settings = apply_filters( 'bonips_wpecom_save_settings', array(
				'log'       => sanitize_text_field( $_POST['bonips_gateway']['log'] ),
				'type'      => sanitize_key( $_POST['bonips_gateway']['type'] ),
				'share'     => abs( $_POST['bonips_gateway']['share'] ),
				'share_log' => sanitize_text_field( $_POST['bonips_gateway']['share_log'] ),
				'rate'      => sanitize_text_field( $_POST['bonips_gateway']['rate'] ),
				'visitor'   => sanitize_text_field( $_POST['bonips_gateway']['visitor'] ),
				'low_funds' => sanitize_text_field( $_POST['bonips_gateway']['low_funds'] ),
				'message'   => sanitize_text_field( $_POST['bonips_gateway']['message'] )
			) );
			update_option( 'bonips_wpecom_settings', $new_settings );
		}

		return true;

	}
endif;

/**
 * Parse Email Notice
 * @since 1.2.2
 * @version 1.0
 */
if ( ! function_exists( 'bonips_wpecom_parse_email' ) ) :
	function bonips_wpecom_parse_email( $email ) {

		if ( $email['request']['ref'] == 'wpecom_payment' )
			$email['request']['entry'] = str_replace( '%order_id%', $email['request']['data'], $email['request']['entry'] );

		return $email;

	}
endif;
add_filter( 'bonips_email_before_send', 'bonips_wpecom_parse_email', 30 );
