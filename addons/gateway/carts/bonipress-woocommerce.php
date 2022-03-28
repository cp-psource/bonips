<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

/**
 * WooCommerce Payment Gateway
 * Custom Payment Gateway for WooCommerce.
 * @see http://docs.woothemes.com/document/payment-gateway-api/
 * @since 0.1
 * @version 1.5
 */
if ( ! function_exists( 'bonipress_init_woo_gateway' ) ) :
	function bonipress_init_woo_gateway() {

		if ( ! class_exists( 'WC_Payment_Gateway' ) || class_exists( 'WC_Gateway_boniPRESS' ) ) return;

		class WC_Gateway_boniPRESS extends WC_Payment_Gateway {

			public $bonipress;

			/**
			 * Constructor
			 */
			public function __construct() {

				$this->id                        = 'bonipress';
				$this->icon                      = '';
				$this->has_fields                = true;
				$this->method_title              = bonipress_label();
				$this->method_description        = __( 'Lasse Benutzer mit Punkten bezahlen.', 'bonipress' );
				
				$gateway_suppots = array(
					'products',
					'refunds'
				);

				$this->supports = apply_filters( 'bonipress_woocommerce_gateway_supports', $gateway_suppots );

				if ( ! $this->use_exchange() )
					$this->bonipress_type = get_woocommerce_currency();

				else {
					$this->bonipress_type = $this->get_option( 'point_type' );
					if ( ! bonipress_point_type_exists( $this->bonipress_type ) )
						$this->bonipress_type = BONIPS_DEFAULT_TYPE_KEY;
				}

				$this->bonipress                    = bonipress( $this->bonipress_type );

				// Load the settings.
				$this->init_form_fields();
				$this->init_settings();

				// Define user set variables
				$this->title                     = $this->get_option( 'title' );
				$this->description               = $this->get_option( 'description' );

				if ( $this->use_exchange() )
					$exchange_rate = (float) $this->get_option( 'exchange_rate' );
				else
					$exchange_rate = 1;

				if ( ! is_numeric( $exchange_rate ) )
					$exchange_rate = 1;

				$this->exchange_rate             = $exchange_rate;
				$this->log_template              = $this->get_option( 'log_template' );
				$this->log_template_refund       = $this->get_option( 'log_template_refund' );
				$this->profit_sharing_refund_log = $this->get_option( 'profit_sharing_refund_log' );

				$this->show_total                = $this->get_option( 'show_total' );
				$this->total_label               = $this->get_option( 'total_label' );
				$this->balance_format            = $this->get_option( 'balance_format' );

				$this->profit_sharing_percent    = $this->get_option( 'profit_sharing_percent' );
				$this->profit_sharing_log        = $this->get_option( 'profit_sharing_log' );

				// Actions
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
				add_action( 'woocommerce_thankyou_bonipress',                              array( $this, 'thankyou_page' ) );

			}

			/**
			 * Initialise Gateway Settings Form Fields
			 * @since 0.1
			 * @version 1.4.1
			 */
			function init_form_fields() {

				// Fields
				$fields['enabled']             = array(
					'title'   => __( 'Enable/Disable', 'bonipress' ),
					'type'    => 'checkbox',
					'label'   => sprintf( __( 'Enable %s Payment', 'bonipress' ), $this->method_title ),
					'default' => 'no',
					'description' => __( 'Users who are not logged in or excluded from using boniPRESS will not have access to this gateway!', 'bonipress' )
				);
				$fields['title']               = array(
					'title'       => __( 'Title', 'bonipress' ),
					'type'        => 'text',
					'description' => __( 'Title to show for this payment option.', 'bonipress' ),
					'default'     => __( 'Pay with boniPRESS', 'bonipress' ),
					'desc_tip'    => true
				);
				$fields['description']         = array(
					'title'       => __( 'Customer Message', 'bonipress' ),
					'type'        => 'textarea',
					'default'     => $this->bonipress->template_tags_general( 'Deduct the amount from your %_plural% balance.' )
				);
				$fields['log_template']        = array(
					'title'       => __( 'Protokollvorlage', 'bonipress' ),
					'type'        => 'text',
					'description' => $this->bonipress->available_template_tags( array( 'general' ), '%order_id%, %order_link%' ),
					'default'     => 'Payment for Order: #%order_id%'
				);
				$fields['log_template_refund'] = array(
					'title'       => __( 'Refund Protokollvorlage', 'bonipress' ),
					'type'        => 'text',
					'description' => $this->bonipress->available_template_tags( array( 'general' ), '%order_id%, %reason%' ),
					'default'     => 'Payment refund for order #%order_id% Reason: %reason%'
				);

				// Only add exchange rate if the currecy is not set to bonipress
				if ( $this->use_exchange() ) {

					$bonipress_types = bonipress_get_types();
					$fields['point_type']      = array(
						'title'       => __( 'Point Type', 'bonipress' ),
						'type'        => 'select',
						'label'       => __( 'Select the point type users can use to pay.', 'bonipress' ),
						'options'     => $bonipress_types,
						'default'     => BONIPS_DEFAULT_TYPE_KEY
					);

					$exchange_desc = $this->bonipress->template_tags_general( __( 'How much is 1 %_singular% worth in %currency%?', 'bonipress' ) );
					$exchange_desc = str_replace( '%currency%', get_woocommerce_currency(), $exchange_desc );

					$fields['exchange_rate']   = array(
						'title'       => __( 'Exchange Rate', 'bonipress' ),
						'type'        => 'text',
						'description' => $exchange_desc,
						'default'     => 1,
						'desc_tip'    => true
					);
					$fields['show_total']      = array(
						'title'       => __( 'Show Total', 'bonipress' ),
						'type'        => 'select',
						'label'       => $this->bonipress->template_tags_general( __( 'Show the final price in %_plural% .', 'bonipress' ) ),
						'options'     => array(
							''           => __( 'Do not show', 'bonipress' ),
							'cart'       => __( 'Show in Cart', 'bonipress' ),
							'checkout'   => __( 'Show on Checkout Page', 'bonipress' ),
							'all'        => __( 'Show in Cart and on Checkout Page', 'bonipress' )
						),
						'default'     => ''
					);
					$fields['total_label']     = array(
						'title'       => __( 'Label', 'bonipress' ),
						'type'        => 'text',
						'default'     => $this->bonipress->template_tags_general( __( 'Order Total in %_plural%', 'bonipress' ) ),
						'desc_tip'    => true
					);

				}

				else {

					$fields['point_type']      = array(
						'title'       => __( 'Point Type', 'bonipress' ),
						'type'        => 'currency',
						'description' => $this->bonipress->plural(),
						'default'     => $this->bonipress_type
					);

				}

				$fields['balance_format']            = array(
					'title'       => __( 'Balance Label', 'bonipress' ),
					'type'        => 'text',
					'description' => __( 'The label to use when presenting a user their balance on the checkout / cart pages. Leave empty to hide.', 'bonipress' ),
					'default'     => 'Your Balance',
					'desc_tip'    => false
				);
				$fields['profit_sharing_percent']    = array(
					'title'       => __( 'Profit Sharing', 'bonipress' ),
					'type'        => 'text',
					'description' => __( 'Option to share sales with the product owner. Use zero to disable.', 'bonipress' ),
					'default'     => 0,
					'desc_tip'    => true
				);
				$fields['profit_sharing_log']        = array(
					'title'       => __( 'Protokollvorlage', 'bonipress' ),
					'type'        => 'text',
					'description' => __( 'Log entry template for profit sharing.', 'bonipress' ) . ' ' . $this->bonipress->available_template_tags( array( 'general', 'post' ) ),
					'default'     => 'Sale of %post_title%'
				);
				$fields['profit_sharing_refund_log'] = array(
					'title'       => __( 'Refund Protokollvorlage', 'bonipress' ),
					'type'        => 'text',
					'description' => __( 'Log entry template for refunds of profit shares.', 'bonipress' ) . ' ' . $this->bonipress->available_template_tags( array( 'general', 'post' ) ),
					'default'     => 'Refund for order #%order_id%'
				);
				
				$this->form_fields = apply_filters( 'bonipress_woo_fields', $fields, $this );

			}

			/**
			 * Generate Text Input HTML.
			 * @since 1.7.6
			 * @version 1.0
			 */
			public function generate_text_html( $key, $data ) {

				$field_key = $this->get_field_key( $key );
				$defaults  = array(
					'title'             => '',
					'disabled'          => false,
					'class'             => '',
					'css'               => '',
					'placeholder'       => '',
					'type'              => 'text',
					'desc_tip'          => false,
					'description'       => '',
					'custom_attributes' => array(),
				);

				$data     = wp_parse_args( $data, $defaults );
				$currency = get_woocommerce_currency();

				ob_start();
?>
<tr valign="top">
	<th scope="row" class="titledesc">
		<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
		<?php echo $this->get_tooltip_html( $data ); ?>
	</th>
	<td class="forminp">
		<?php if ( $data['type'] == 'currency' ) : $bonipress = bonipress( $currency ); ?>
		<input type="hidden" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" value="<?php echo get_woocommerce_currency(); ?>" />
		<p><?php echo $bonipress->plural(); ?></p>
		<?php else : ?>
		<fieldset>
			<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
			<input class="input-text regular-input <?php echo esc_attr( $data['class'] ); ?>" type="<?php echo esc_attr( $data['type'] ); ?>" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" value="<?php echo esc_attr( $this->get_option( $key ) ); ?>" placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>" <?php disabled( $data['disabled'], true ); ?> <?php echo $this->get_custom_attribute_html( $data ); ?> />
			<?php echo $this->get_description_html( $data ); ?>
		</fieldset>
		<?php endif; ?>
	</td>
</tr>
<?php

				return ob_get_clean();

			}

			/**
			 * Use Exchange
			 * Checks to see if exchange is needed.
			 * @since 0.1
			 * @version 1.0
			 */
			function use_exchange() {

				$currency = get_woocommerce_currency();
				if ( bonipress_point_type_exists( $currency ) || $currency == 'MYC' ) return false;
				return true;

			}

			/**
			 * Admin Panel Options
			 * @since 0.1
			 * @version 1.1
			 */
			public function admin_options() {

?>
		<h3><?php printf( __( '%s Payment', 'bonipress' ), bonipress_label() ); ?></h3>
		<table class="form-table">
<?php

				// Generate the HTML For the settings form.
				$this->generate_settings_html();

?>
		</table>
<?php

			}

			/**
			 * Process Payment
			 * @since 0.1
			 * @version 1.4.3
			 */
			function process_payment( $order_id ) {

				global $woocommerce;

				// Make sure we are still logged in
				if ( ! is_user_logged_in() ) {
					wc_add_notice( $this->bonipress->template_tags_general( __( 'You must be logged in to pay with %_plural%', 'bonipress' ) ), 'error' );
					return;
				}

				$user_id     = get_current_user_id();

				// Make sure we have not been excluded
				if ( $this->bonipress->exclude_user( $user_id ) ) {
					wc_add_notice( $this->bonipress->template_tags_general( __( 'You can not use this gateway. Please try a different payment option.', 'bonipress' ) ), 'error' );
					return;
				}

				// Grab Order
				$order       = wc_get_order( $order_id );

				$order_total = ( version_compare( $woocommerce->version, '3.0', '>=' ) ) ? $order->get_total() : $order->order_total;

				// Cost
				$cost        = $order_total;
				if ( $this->use_exchange() )
					$cost = $this->bonipress->number( ( $order_total / $this->exchange_rate ) );

				$cost        = apply_filters( 'bonipress_woo_order_cost', $cost, $order, false, $this );

				// Check funds
				if ( $this->bonipress->get_users_balance( $user_id, $this->bonipress_type ) < $cost ) {
					$message = apply_filters( 'bonipress_woo_error_insufficient_funds', __( 'Insufficient funds.', 'bonipress' ) );
					wc_add_notice( $message, 'error' );
					return;
				}

				// Let others decline a store order
				$decline     = apply_filters( 'bonipress_decline_store_purchase', false, $order, $this );
				if ( $decline !== false ) {
					wc_add_notice( $decline, 'error' );
					return;
				}

				// Charge
				$this->bonipress->add_creds(
					'woocommerce_payment',
					$user_id,
					0 - $cost,
					$this->log_template,
					$order_id,
					array( 'ref_type' => 'post' ),
					$this->bonipress_type
				);

				$order->payment_complete();

				// Profit Sharing
				if ( $this->profit_sharing_percent > 0 ) {

					// Get Items
					$items = $order->get_items();

					// Loop though items
					foreach ( $items as $item ) {

						// Get Product
						$product    = bonipress_get_post( (int) $item['product_id'] );

						// Continue if product has just been deleted or owner is buyer
						if ( $product === NULL || $product->post_author == $user_id ) continue;

						// Calculate Share
						$percentage = apply_filters( 'bonipress_woo_profit_share', $this->profit_sharing_percent, $order, $product, $this );
						if ( $percentage == 0 ) continue;

						$share      = ( $percentage / 100 ) * $item['line_total'];

						// Payout
						$this->bonipress->add_creds(
							'store_sale',
							$product->post_author,
							$this->bonipress->number( $share ),
							$this->profit_sharing_log,
							$product->ID,
							array( 'ref_type' => 'post' ),
							$this->bonipress_type
						);

					}

				}

				// Let others play
				do_action( 'bonipress_paid_for_woo', $order, $user_id, $this );

				// Return the good news
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order )
				);

			}

			/**
			 * Process Refunds
			 * @since 1.5.4
			 * @version 1.0.3
			 */
			public function process_refund( $order_id, $amount = null, $reason = '' ) {

				$order       = wc_get_order( $order_id );

				$order_total = ( version_compare( $woocommerce->version, '3.0', '>=' ) ) ? $order->get_total() : $order->order_total;
				$user_id     = ( version_compare( $woocommerce->version, '3.0', '>=' ) ) ? $order->get_user_id() : $order->user_id;

				if ( $amount === NULL )
					$amount = $order_total;

				$refund      = $amount;
				if ( $this->use_exchange() )
					$refund = $this->bonipress->number( ( $refund / $this->exchange_rate ) );

				$this->bonipress->add_creds(
					'woocommerce_refund',
					$user_id,
					0 - $refund,
					$this->log_template_refund,
					$order_id,
					array( 'ref_type' => 'post', 'reason' => $reason ),
					$this->bonipress_type
				);

				$order->add_order_note( sprintf( _x( 'Refunded %s', '%s = Point amount formatted', 'bonipress' ), $this->bonipress->format_creds( $refund ) ) );

				// Profit Sharing
				if ( $this->profit_sharing_percent > 0 ) {

					// Get Items
					$items = $order->get_items();

					// Loop though items
					foreach ( $items as $item ) {

						// Get Product
						$product = bonipress_get_post( (int) $item['product_id'] );

						// Continue if product has just been deleted
						if ( $product === NULL ) continue;

						// Calculate Share
						$percentage = apply_filters( 'bonipress_woo_profit_share_refund', $this->profit_sharing_percent, $order, $product, $this );
						if ( $percentage == 0 ) continue;

						$share      = ( $percentage / 100 ) * $item['line_total'];

						// Payout
						$this->bonipress->add_creds(
							'store_sale_refund',
							$product->post_author,
							0 - $share,
							$this->profit_sharing_refund_log,
							$product->ID,
							array( 'ref_type' => 'post', 'order_id' => $order_id ),
							$this->bonipress_type
						);

					}

				}

				// Let others play
				do_action( 'bonipress_refunded_for_woo', $order, $amount, $reason, $this );

				return true;

			}

			/**
			 * Thank You Page
			 * @since 0.1
			 * @version 1.0
			 */
			function thankyou_page() {

				echo apply_filters( 'bonipress_woo_thank_you_message', '<p>' . __( 'Your account has successfully been charged.', 'bonipress' ) . '</p>' );

			}

		}

	}
endif;
add_action( 'after_setup_theme', 'bonipress_init_woo_gateway' );

/**
 * Register Gateway
 * @since 0.1
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_register_woo_gateway' ) ) :
	function bonipress_register_woo_gateway( $methods ) {

		$methods[] = 'WC_Gateway_boniPRESS';
		return $methods;

	}
endif;
add_filter( 'woocommerce_payment_gateways', 'bonipress_register_woo_gateway' );

/**
 * Available Gateways
 * "Removes" this gateway as a payment option if:
 * - User is not logged in
 * - User is excluded
 * - Users balance is too low
 *
 * @since 0.1
 * @version 1.3
 */
if ( ! function_exists( 'bonipress_woo_available_gateways' ) ) :
	function bonipress_woo_available_gateways( $gateways ) {

		if ( ! isset( $gateways['bonipress'] ) ) return $gateways;

		// Easy override
		if ( defined( 'SHOW_BONIPS_IN_WOOCOMMERCE' ) && SHOW_BONIPS_IN_WOOCOMMERCE ) return $gateways;

		// Check if we are logged in
		if ( ! is_user_logged_in() ) {

			unset( $gateways['bonipress'] );

			return $gateways;

		}

		$point_type = $gateways['bonipress']->get_option( 'point_type' );
		if ( ! bonipress_point_type_exists( $point_type ) )
			$point_type = BONIPS_DEFAULT_TYPE_KEY;

		// Get boniPRESS
		$bonipress     = bonipress( $point_type );
		$user_id    = get_current_user_id();

		// Check if we are excluded from boniPRESS usage
		if ( $bonipress->exclude_user( $user_id ) ) {

			unset( $gateways['bonipress'] );

			return $gateways;

		}

		global $woocommerce;

		// Calculate cost in CREDs
		$currency   = get_woocommerce_currency();

		$cost       = $woocommerce->cart->total;
		if ( ! bonipress_point_type_exists( $currency ) && $currency != 'MYC' )
			$cost = $bonipress->number( ( $woocommerce->cart->total / $gateways['bonipress']->get_option( 'exchange_rate' ) ) );

		$cost       = apply_filters( 'bonipress_woo_order_cost', $cost, $woocommerce->cart, true, $bonipress );

		// Check if we have enough points
		if ( $bonipress->get_users_balance( $user_id, $point_type ) < $cost ) {
			$gateways['bonipress']->enabled = 'no';
		}

		return $gateways;

	}
endif;
add_filter( 'woocommerce_available_payment_gateways', 'bonipress_woo_available_gateways' );

/**
 * Add Currency
 * Inserts all registered point types as a currency in WooCommerce.
 * @since 0.1
 * @version 1.2
 */
if ( ! function_exists( 'bonipress_woo_add_currency' ) ) :
	function bonipress_woo_add_currency( $currencies ) {

		$point_types = bonipress_get_types();

		if ( ! empty( $point_types ) ) {
			foreach ( $point_types as $type_id => $label ) {

				if ( $type_id == BONIPS_DEFAULT_TYPE_KEY )
					$type_id = 'MYC';

				$currencies[ $type_id ] = $label;

			}
		}

		return $currencies;

	}
endif;
add_filter( 'woocommerce_currencies', 'bonipress_woo_add_currency' );

/**
 * Currency Symbol
 * Appends the boniPRESS prefix or suffix to the amount.
 * @since 0.1
 * @version 1.2.1
 */
if ( ! function_exists( 'bonipress_woo_currency_symbol' ) ) :
	function bonipress_woo_currency_symbol( $currency_symbols ) {

		$point_types = bonipress_get_types();
		if ( ! empty( $point_types ) ) {
			foreach ( $point_types as $type_id => $label ) {

				$bonipress = bonipress( $type_id );
				$symbol = '';
				if ( ! empty( $bonipress->after ) )
					$symbol = $bonipress->after;
				elseif ( ! empty( $bonipress->before ) )
					$symbol = $bonipress->before;

				if ( $type_id == BONIPS_DEFAULT_TYPE_KEY )
					$type_id = 'MYC';

				$currency_symbols[ $type_id ] = $symbol;

			}
		}

		return $currency_symbols;

	}
endif;
add_filter( 'woocommerce_currency_symbols', 'bonipress_woo_currency_symbol' );

/**
 * Log Entry: Payment
 * @since 0.1
 * @version 1.4
 */
if ( ! function_exists( 'bonipress_woo_log_entry_payment' ) ) :
	function bonipress_woo_log_entry_payment( $content, $log_entry ) {

		$order_id   = absint( $log_entry->ref_id );
		$order_link = '#' . $order_id;

		if ( function_exists( 'wc_get_order' ) ) {

			$order = wc_get_order( $order_id );

			if ( $order !== false && is_object( $order ) )
				$order_link = '<a href="' . esc_url( $order->get_view_order_url() ) . '">#' . $order_id . '</a>';

		}

		$content   = str_replace( '%order_id%',   $order_id, $content );
		$content   = str_replace( '%order_link%', $order_link, $content );

		return $content;

	}
endif;
add_filter( 'bonipress_parse_log_entry_woocommerce_payment', 'bonipress_woo_log_entry_payment', 90, 2 );

/**
 * Log Entry: Refund
 * @since 1.5.4
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_woo_log_entry_refunds' ) ) :
	function bonipress_woo_log_entry_refunds( $content, $log_entry ) {

		$content = bonipress_woo_log_entry_payment( $content, $log_entry );

		$data    = maybe_unserialize( $log_entry->data );
		$reason  = '-';
		if ( isset( $data['reason'] ) && $data['reason'] != '' )
			$reason = $data['reason'];

		$content = str_replace( '%reason%', $reason, $content );

		return $content;

	}
endif;
add_filter( 'bonipress_parse_log_entry_woocommerce_refund', 'bonipress_woo_log_entry_refunds', 90, 2 );

/**
 * Log Entry: Profit Share Refund
 * @since 1.5.4
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_woo_log_entry_profit_refund' ) ) :
	function bonipress_woo_log_entry_profit_refund( $content, $log_entry ) {

		$data     = maybe_unserialize( $log_entry->data );
		$order_id = '';
		if ( isset( $data['order_id'] ) && $data['order_id'] != '' )
			$order_id = '#' . $data['order_id'];

		$content  = str_replace( '%order_id%', $order_id, $content );

		$reason   = '-';
		if ( isset( $data['reason'] ) && $data['reason'] != '' )
			$reason = $data['reason'];

		$content  = str_replace( '%reason%', $reason, $content );

		return $content;

	}
endif;
add_filter( 'bonipress_parse_log_entry_store_sale_refund', 'bonipress_woo_log_entry_profit_refund', 90, 2 );

/**
 * Parse Email Notice
 * @since 1.2.2
 * @version 1.0.2
 */
if ( ! function_exists( 'bonipress_woo_parse_email' ) ) :
	function bonipress_woo_parse_email( $email ) {

		if ( $email['request']['ref'] == 'woocommerce_payment' && function_exists( 'woocommerce_get_page_id' ) ) {

			if ( function_exists( 'wc_get_order' ) )
				$order = wc_get_order( (int) $email['request']['ref_id'] );
			else
				$order = new WC_Order( (int) $email['request']['ref_id'] );

			if ( isset( $order->id ) ) {

				$url     = esc_url( add_query_arg( 'order', $order->id, bonipress_get_permalink( woocommerce_get_page_id( 'view_order' ) ) ) );
				$content = str_replace( '%order_id%', $order->id, $email['request']['entry'] );

				$email['request']['entry'] = str_replace( '%order_link%', '<a href="' . esc_url( $url ) . '">#' . $order->id . '</a>', $content );

			}

		}

		return $email;

	}
endif;
add_filter( 'bonipress_email_before_send', 'bonipress_woo_parse_email', 10 );

/**
 * After Order Total
 * Potentially insert the cart cost in points and the users current balance.
 * @since 0.1
 * @version 1.3
 */
if ( ! function_exists( 'bonipress_woo_after_order_total' ) ) :
	function bonipress_woo_after_order_total() {

		if ( ! is_user_logged_in() ) return;

		// Only available for logged in non-excluded users
		global $woocommerce;

		$available_gateways = $woocommerce->payment_gateways->get_available_payment_gateways();
		if ( ! isset( $available_gateways['bonipress'] ) ) return;
		
		$point_type         = $available_gateways['bonipress']->get_option( 'point_type' );
		if ( $point_type === NULL )
			$point_type = BONIPS_DEFAULT_TYPE_KEY;

		$bonipress             = bonipress( $point_type );
		$user_id            = get_current_user_id();

		// Nothing to do if we are excluded
		if ( $bonipress->exclude_user( $user_id ) ) return;

		$show_total         = $available_gateways['bonipress']->get_option( 'show_total' );
		$balance            = $bonipress->get_users_balance( $user_id, $point_type );
		$balance_label      = $available_gateways['bonipress']->get_option( 'balance_format' );

		// If we should show the total in points
		if ( ( $show_total == 'both' || $show_total == 'all') 
			|| ( $show_total == 'cart' && is_cart() ) 
			|| ( $show_total == 'checkout' && is_checkout() ) 
		) {

			// Make sure boniPRESS is not the currency used
			$currency = get_woocommerce_currency();
			if ( ! bonipress_point_type_exists( $currency ) && $currency != 'MYC' ) {

				// Apply Exchange Rate
				$cost    = $bonipress->number( ( $woocommerce->cart->total / $available_gateways['bonipress']->get_option( 'exchange_rate' ) ) );
				$cost    = apply_filters( 'bonipress_woo_order_cost', $cost, $woocommerce->cart, true, $bonipress );
				

?>
<tr class="total">
	<th><strong><?php echo $bonipress->template_tags_general( $available_gateways['bonipress']->get_option( 'total_label' ) ); ?></strong></th>
	<td>
		<div class="current-balance order-total-in-points">
			<strong class="<?php if ( $balance < $cost ) echo 'bonipress-low-funds'; else echo 'bonipress-funds'; ?>"<?php if ( $balance < $cost ) echo ' style="color:red;"'; ?>><?php echo $bonipress->format_creds( $cost ); ?></strong> 
		</div>
	</td>
</tr>
<?php

			}

		}

		// If we should show the users balance
		if ( ! empty( $balance_label ) ) {

?>
<tr class="total">
	<th><strong><?php echo $bonipress->template_tags_general( $balance_label ); ?></strong></th>
	<td>
		<div class="current-balance">
			<?php echo $bonipress->format_creds( $balance ); ?>
		</div>
	</td>
</tr>
<?php

		}

	}
endif;
add_action( 'woocommerce_review_order_after_order_total', 'bonipress_woo_after_order_total' );
add_action( 'woocommerce_cart_totals_after_order_total',  'bonipress_woo_after_order_total' );
