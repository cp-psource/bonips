<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * PSeCommerce Payment Gateway
 * @since 1.1
 * @version 1.3
 */
if ( ! function_exists( 'bonips_init_psecommerce_gateway' ) ) {
	add_action( 'mp_load_gateway_plugins', 'bonips_init_psecommerce_gateway' );
	function bonips_init_psecommerce_gateway()
	{
		if ( ! class_exists( 'MP_Gateway_API' ) ) return;
		
		class MP_Gateway_boniPS extends MP_Gateway_API {

			var $plugin_name = 'bonips';
			var $admin_name = 'boniPS';
			var $public_name = 'boniPS';
			var $bonips_type = 'bonips_default';
			var $method_img_url = '';
			var $method_button_img_url = '';
			var $force_ssl = false;
			var $ipn_url;
			var $skip_form = false;

			/**
			 * Runs when your class is instantiated. Use to setup your plugin instead of __construct()
			 */
			function on_creation() {
				global $mp;
				$settings = get_option( 'mp_settings' );

				//set names here to be able to translate
				$this->admin_name = 'boniPS';

				$this->public_name = bonips_label( true );
				if ( isset( $settings['gateways']['bonips']['name'] ) && ! empty( $settings['gateways']['bonips']['name'] ) )
					$this->public_name = $settings['gateways']['bonips']['name'];

				$this->method_img_url = plugins_url( 'assets/images/cred-icon32.png', boniPS_THIS );
				if ( isset( $settings['gateways']['bonips']['logo'] ) && ! empty( $settings['gateways']['bonips']['logo'] ) )
					$this->method_img_url = $settings['gateways']['bonips']['logo'];

				$this->method_button_img_url = $this->public_name;
				
				if ( ! isset( $settings['gateways']['bonips']['type'] ) )
					$this->bonips_type = 'bonips_default';
				else
					$this->bonips_type = $settings['gateways']['bonips']['type'];

				$this->bonips = bonips( $this->bonips_type );
			}
		
			/**
			 * Use Exchange
			 * Checks to see if exchange is needed.
			 * @since 1.1
			 * @version 1.0
			 */
			function use_exchange() {
				global $mp;

				$settings = get_option( 'mp_settings' );
				if ( $settings['currency'] == 'POINTS' ) return false;
				return true;
			}

			/**
			 * Returns the current carts total.
			 * @since 1.2
			 * @version 1.0
			 */
			function get_cart_total( $cart = NULL ) {
				global $mp;

				// Get total
				$totals = array();
				foreach ( $cart as $product_id => $variations ) {
					foreach ( $variations as $data ) {
						$totals[] = $mp->before_tax_price( $data['price'], $product_id ) * $data['quantity'];
					}
				}
				$total = array_sum( $totals );

				// Apply Coupons
				if ( $coupon = $mp->coupon_value( $mp->get_coupon_code(), $total ) ) {
					$total = $coupon['new_total'];
				}

				// Shipping Cost
				if ( ( $shipping_price = $mp->shipping_price() ) !== false ) {
					$total = $total + $shipping_price;
				}

				// Tax
				if ( ( $tax_price = $mp->tax_price() ) !== false ) {
					$total = $total + $tax_price;
				}
			
				$settings = get_option( 'mp_settings' );
				if ( $this->use_exchange() )
					return $this->bonips->apply_exchange_rate( $total, $settings['gateways']['bonips']['exchange'] );
				else
					return $this->bonips->number( $total );
			}

			/**
			 * Return fields you need to add to the payment screen, like your credit card info fields
			 *
			 * @param array $cart. Contains the cart contents for the current blog, global cart if $mp->global_cart is true
			 * @param array $shipping_info. Contains shipping info and email in case you need it
			 * @since 1.1
			 * @version 1.1
			 */
			function payment_form( $cart, $shipping_info ) {
				global $mp;
			
				$settings = get_option( 'mp_settings' );
			
				if ( ! is_user_logged_in() ) {
					$message = str_replace( '%login_url_here%', wp_login_url( mp_checkout_step_url( 'checkout' ) ), $settings['gateways']['bonips']['visitors'] );
					$message = $this->bonips->template_tags_general( $message );
					return '<div id="mp-bonips-balance">' . $message . '</div>';
				}
			
				$balance = $this->bonips->get_users_cred( get_current_user_id(), $this->bonips_type );
				$total = $this->get_cart_total( $cart );
			
				// Low balance
				if ( $balance-$total < 0 ) {
					$message = $this->bonips->template_tags_user( $settings['gateways']['bonips']['lowfunds'], false, wp_get_current_user() );
					$instructions = '<div id="mp-bonips-balance">' . $message . '</div>';
					$red = ' style="color: red;"';
				}
				else {
					$instructions = $this->bonips->template_tags_general( $settings['gateways']['bonips']['instructions'] );
					$red = '';
				}
			
				// Return Cost
				return '
<div id="mp-bonips-balance">' . $instructions . '</div>
<div id="mp-bonips-cost">
<table style="width:100%;">
	<tr>
		<td class="info">' . __( 'Current Balance', 'bonips' ) . '</td>
		<td class="amount">' . $this->bonips->format_creds( $balance ) . '</td>
	</tr>
	<tr>
		<td class="info">' . __( 'Total Cost', 'bonips' ) . '</td>
		<td class="amount">' . $this->bonips->format_creds( $total ) . '</td>
	</tr>
	<tr>
		<td class="info">' . __( 'Balance After Purchase', 'bonips' ) . '</td>
		<td class="amount"' . $red . '>' . $this->bonips->format_creds( $balance-$total ) . '</td>
	</tr>
</table>
</div>';
			}

			/**
			 * Return the chosen payment details here for final confirmation. You probably don't need
			 * to post anything in the form as it should be in your $_SESSION var already.
			 *
			 * @param array $cart. Contains the cart contents for the current blog, global cart if $mp->global_cart is true
			 * @param array $shipping_info. Contains shipping info and email in case you need it
			 * @since 1.1
			 * @version 1.1
			 */
			function confirm_payment_form( $cart, $shipping_info ) {
				global $mp;

				$settings = get_option( 'mp_settings' );
				$user_id = get_current_user_id();
				$balance = $this->bonips->get_users_cred( get_current_user_id(), $this->bonips_type );
				$total = $this->get_cart_total( $cart );
			
				$table = '<table class="bonips-cart-cost"><thead><tr><th>' . __( 'Payment', 'bonips' ) . '</th></tr></thead>';
				if ( $balance-$total < 0 ) {
					$message = $this->bonips->template_tags_user( $settings['gateways']['bonips']['lowfunds'], false, wp_get_current_user() );
					$table .= '<tr><td id="mp-bonips-cost" style="color: red; font-weight: bold;"><p>' . $message . ' <a href="' . mp_checkout_step_url( 'checkout' ) . '">' . __( 'Go Back', 'bonips' ) . '</a></td></tr>';
				}
				else
					$table .= '<tr><td id="mp-bonips-cost" class="bonips-ok">' . $this->bonips->format_creds( $total ) . ' ' . __( 'will be deducted from your account.', 'bonips' ) . '</td></tr>';
			
				return $table . '</table>';
			}
		
			function process_payment_form( $cart, $shipping_info ) { }

			/**
			 * Use this to do the final payment. Create the order then process the payment. If
			 * you know the payment is successful right away go ahead and change the order status
			 * as well.
			 * Call $mp->cart_checkout_error($msg, $context); to handle errors. If no errors
			 * it will redirect to the next step.
			 *
			 * @param array $cart. Contains the cart contents for the current blog, global cart if $mp->global_cart is true
			 * @param array $shipping_info. Contains shipping info and email in case you need it
			 * @since 1.1
			 * @version 1.2.1
			 */
			function process_payment( $cart, $shipping_info ) {
				global $mp;
			
				$settings = get_option('mp_settings');
				$user_id = get_current_user_id();
				$insolvent = $this->bonips->template_tags_user( $settings['gateways']['bonips']['lowfunds'], false, wp_get_current_user() );
				$timestamp = time();

				// This gateway requires buyer to be logged in
				if ( ! is_user_logged_in() ) {
					$message = str_replace( '%login_url_here%', wp_login_url( mp_checkout_step_url( 'checkout' ) ), $settings['gateways']['bonips']['visitors'] );
					$mp->cart_checkout_error( $this->bonips->template_tags_general( $message ) );
				}

				// Make sure current user is not excluded from using boniPS
				if ( $this->bonips->exclude_user( $user_id ) )
					$mp->cart_checkout_error(
						sprintf( __( 'Sorry, but you can not use this gateway as your account is excluded. Please <a href="%s">select a different payment method</a>.', 'bonips' ), mp_checkout_step_url( 'checkout' ) )
					);

				// Get users balance
				$balance = $this->bonips->get_users_cred( $user_id, $this->bonips_type );
				$total = $this->get_cart_total( $cart );
			
				// Low balance or Insolvent
				if ( $balance <= $this->bonips->zero() || $balance-$total < $this->bonips->zero() ) {
					$mp->cart_checkout_error(
						$insolvent . ' <a href="' . mp_checkout_step_url( 'checkout' ) . '">' . __( 'Go Back', 'bonips' ) . '</a>'
					);
					return;
				}

				// Let others decline a store order
				$decline = apply_filters( 'bonips_decline_store_purchase', false, $cart, $this );
				if ( $decline !== false ) {
					$mp->cart_checkout_error( $decline );
					return;
				}

				// Create PSeCommerce order
				$order_id = $mp->generate_order_id();
				$payment_info['gateway_public_name'] = $this->public_name;
				$payment_info['gateway_private_name'] = $this->admin_name;
				$payment_info['status'][ $timestamp ] = __( 'Paid', 'bonips' );
				$payment_info['total'] = $total;
				$payment_info['currency'] = $settings['currency'];
				$payment_info['method'] = __( 'boniPS', 'bonips' );
				$payment_info['transaction_id'] = $order_id;
				$paid = true;
				$result = $mp->create_order( $order_id, $cart, $shipping_info, $payment_info, $paid );
				
				$order = get_page_by_title( $result, 'OBJECT', 'mp_order' );

				// Deduct cost
				$this->bonips->add_creds(
					'psecommerce_payment',
					$user_id,
					0-$total,
					$settings['gateways']['bonips']['log_template'],
					$order->ID,
					array( 'ref_type' => 'post' ),
					$this->bonips_type
				);
				
				// Profit Sharing
				if ( $settings['gateways']['bonips']['profit_share_percent'] > 0 ) {
					foreach ( $cart as $product_id => $variations ) {
						// Get Product
						$product = get_post( (int) $product_id );
						
						// Continue if product has just been deleted or owner is buyer
						if ( $product === NULL || $product->post_author == $cui ) continue;
						
						foreach ( $variations as $data ) {
							$price = $data['price'];
							$quantity = $data['quantity'];
							$cost = $price*$quantity;

							// Calculate Share
							$share = ( $settings['gateways']['bonips']['profit_share_percent'] / 100 ) * $cost;

							// Payout
							$this->bonips->add_creds(
								'store_sale',
								$product->post_author,
								$share,
								$settings['gateways']['bonips']['profit_share_log'],
								$product->ID,
								array( 'ref_type' => 'post' ),
								$this->bonips_type
							);
						}
					}
				}
			}
		
			function order_confirmation( $order ) { }

			/**
			 * Filters the order confirmation email message body. You may want to append something to
			 * the message. Optional
			 * @since 1.1
			 * @version 1.0
			 */
			function order_confirmation_email( $msg, $order ) {
				global $mp;
				$settings = get_option('mp_settings');

				if ( isset( $settings['gateways']['bonips']['email'] ) )
					$msg = $mp->filter_email( $order, $settings['gateways']['bonips']['email'] );
				else
					$msg = $settings['email']['new_order_txt'];

				return $msg;
			}

			/**
			 * Return any html you want to show on the confirmation screen after checkout. This
			 * should be a payment details box and message.
			 * @since 1.1
			 * @version 1.1
			 */
			function order_confirmation_msg( $content, $order ) {
				global $mp;
				$settings = get_option('mp_settings');

				$bonips = bonips();
				$user_id = get_current_user_id();
			
				return $content . str_replace(
					'TOTAL',
					$mp->format_currency( $order->mp_payment_info['currency'], $order->mp_payment_info['total'] ),
					$bonips->template_tags_user( $settings['gateways']['bonips']['confirmation'], false, wp_get_current_user() )
				);
			}

			/**
			 * boniPS Gateway Settings
			 * @since 1.1
			 * @version 1.3
			 */
			function gateway_settings_box( $settings ) {
				global $mp;
				$settings = get_option( 'mp_settings' );
				$bonips = bonips();
			
				$name = bonips_label( true );
				$settings['gateways']['bonips'] = shortcode_atts( array(
					'name'                 => $name . ' ' . $bonips->template_tags_general( __( '%_singular% Balance', 'bonips' ) ),
					'logo'                 => $this->method_button_img_url,
					'type'                 => 'bonips_default',
					'log_template'         => __( 'Payment for Order: #%order_id%', 'bonips' ),
					'exchange'             => 1,
					'profit_share_percent' => 0,
					'profit_share_log'     => __( 'Product Sale: %post_title%', 'bonips' ),
					'instructions'         => __( 'Pay using your account balance.', 'bonips' ),
					'confirmation'         => __( 'TOTAL amount has been deducted from your account. Your current balance is: %balance_f%', 'bonips' ),
					'lowfunds'             => __( 'Insufficient funds.', 'bonips' ),
					'visitors'             => __( 'You must be logged in to pay with %_plural%. Please <a href="%login_url_here%">login</a>.', 'bonips' ),
					'email'                => $settings['email']['new_order_txt']
				), ( isset( $settings['gateways']['bonips'] ) ) ? $settings['gateways']['bonips'] : array() ); ?>

<div id="mp_bonips_payments" class="postbox mp-pages-msgs">
	<h3 class="handle"><span><?php echo $name . ' ' . __( 'Settings', 'bonips' ); ?></span></h3>
	<div class="inside">
		<span class="description"><?php echo sprintf( __( 'Let your users pay for items in their shopping cart using their %s Account. Note! This gateway requires your users to be logged in when making a purchase!', 'bonips' ), $name ); ?></span>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="bonips-method-name"><?php _e( 'Method Name', 'bonips' ); ?></label></th>
				<td>
					<span class="description"><?php _e( 'Enter a public name for this payment method that is displayed to users - No HTML', 'bonips' ); ?></span>
					<p><input value="<?php echo esc_attr( $settings['gateways']['bonips']['name'] ); ?>" style="width: 100%;" name="mp[gateways][bonips][name]" id="bonips-method-name" type="text" /></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="bonips-method-logo"><?php _e( 'Gateway Logo URL', 'bonips' ); ?></label></th>
				<td>
					<p><input value="<?php echo esc_attr( $settings['gateways']['bonips']['logo'] ); ?>" style="width: 100%;" name="mp[gateways][bonips][logo]" id="bonips-method-logo" type="text" /></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="bonips-method-type"><?php _e( 'Point Type', 'bonips' ); ?></label></th>
				<td>
					<?php bonips_types_select_from_dropdown( 'mp[gateways][bonips][type]', 'bonips-method-type', $settings['gateways']['bonips']['type'] ); ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="bonips-log-template"><?php _e( 'Protokollvorlage', 'bonips' ); ?></label></th>
				<td>
					<span class="description"><?php echo $bonips->available_template_tags( array( 'general' ), '%order_id%, %order_link%' ); ?></span>
					<p><input value="<?php echo esc_attr( $settings['gateways']['bonips']['log_template'] ); ?>" style="width: 100%;" name="mp[gateways][bonips][log_template]" id="bonips-log-template" type="text" /></p>
				</td>
			</tr>
<?php
				// Exchange rate
				if ( $this->use_exchange() ) :
					$exchange_desc = __( 'How much is 1 %_singular% worth in %currency%?', 'bonips' );
					$exchange_desc = $bonips->template_tags_general( $exchange_desc );
					$exchange_desc = str_replace( '%currency%', $settings['currency'], $exchange_desc ); ?>

			<tr>
				<th scope="row"><label for="bonips-exchange-rate"><?php _e( 'Exchange Rate', 'bonips' ); ?></label></th>
				<td>
					<span class="description"><?php echo $exchange_desc; ?></span>
					<p><input value="<?php echo esc_attr( $settings['gateways']['bonips']['exchange'] ); ?>" size="8" name="mp[gateways][bonips][exchange]" id="bonips-exchange-rate" type="text" /></p>
				</td>
			</tr>
<?php			endif; ?>

			<tr>
				<td colspan="2"><h4><?php _e( 'Profit Sharing', 'bonips' ); ?></h4></td>
			</tr>
			<tr>
				<th scope="row"><label for="bonips-profit-sharing"><?php _e( 'Percentage', 'bonips' ); ?></label></th>
				<td>
					<span class="description"><?php _e( 'Option to share sales with the product owner. Use zero to disable.', 'bonips' ); ?></span>
					<p><input value="<?php echo esc_attr( $settings['gateways']['bonips']['profit_share_percent'] ); ?>" size="8" name="mp[gateways][bonips][profit_share_percent]" id="bonips-profit-sharing" type="text" /> %</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="bonips-profit-sharing-log"><?php _e( 'Protokollvorlage', 'bonips' ); ?></label></th>
				<td>
					<span class="description"><?php echo $bonips->available_template_tags( array( 'general', 'post' ) ); ?></span>
					<p><input value="<?php echo esc_attr( $settings['gateways']['bonips']['profit_share_log'] ); ?>" style="width: 100%;" name="mp[gateways][bonips][profit_share_log]" id="bonips-profit-sharing-log" type="text" /></p>
				</td>
			</tr>
			<tr>
				<td colspan="2"><h4><?php _e( 'Messages', 'bonips' ); ?></h4></td>
			</tr>
			<tr>
				<th scope="row"><label for="bonips-lowfunds"><?php _e( 'Insufficient Funds', 'bonips' ); ?></label></th>
				<td>
					<span class="description"><?php _e( 'Message to show when the user can not use this gateway.', 'bonips' ); ?></span>
					<p><input type="text" name="mp[gateways][bonips][lowfunds]" id="bonips-lowfunds" style="width: 100%;" value="<?php echo esc_attr( $settings['gateways']['bonips']['lowfunds'] ); ?>"><br />
					<span class="description"><?php echo $bonips->available_template_tags( array( 'general' ) ); ?></span></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="bonips-visitors"><?php _e( 'Visitors', 'bonips' ); ?></label></th>
				<td>
					<span class="description"><?php _e( 'Message to show to buyers that are not logged in.', 'bonips' ); ?></span>
					<p><input type="text" name="mp[gateways][bonips][visitors]" id="bonips-visitors" style="width: 100%;" value="<?php echo esc_attr( $settings['gateways']['bonips']['visitors'] ); ?>"><br />
					<span class="description"><?php echo $bonips->available_template_tags( array( 'general' ) ); ?></span></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="bonips-instructions"><?php _e( 'User Instructions', 'bonips' ); ?></label></th>
				<td>
					<span class="description"><?php _e( 'Information to show users before payment.', 'bonips' ); ?></span>
					<p><?php wp_editor( $settings['gateways']['bonips']['instructions'] , 'bonips-instructions', array( 'textarea_name' => 'mp[gateways][bonips][instructions]' ) ); ?><br />
					<span class="description"><?php echo $bonips->available_template_tags( array( 'general' ), '%balance% or %balance_f%' ); ?></span></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="bonips-confirmation"><?php _e( 'Confirmation Information', 'bonips' ); ?></label></th>
				<td>
					<span class="description"><?php _e( 'Information to display on the order confirmation page. - HTML allowed', 'bonips' ); ?></span>
					<p><?php wp_editor( $settings['gateways']['bonips']['confirmation'], 'bonips-confirmation', array( 'textarea_name' => 'mp[gateways][bonips][confirmation]' ) ); ?><br />
					<span class="description"><?php echo $bonips->available_template_tags( array( 'general' ), '%balance% or %balance_f%' ); ?></span></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="bonips-email"><?php _e( 'Order Confirmation Email', 'bonips' ); ?></label></th>
				<td>
					<span class="description"><?php echo sprintf( __( 'This is the email text to send to those who have made %s checkouts. It overrides the default order checkout email. These codes will be replaced with order details: CUSTOMERNAME, ORDERID, ORDERINFO, SHIPPINGINFO, PAYMENTINFO, TOTAL, TRACKINGURL. No HTML allowed.', 'bonips' ), $name ); ?></span>
					<p><textarea id="bonips-email" name="mp[gateways][bonips][email]" class="mp_emails_txt"><?php echo esc_textarea( $settings['gateways']['bonips']['email'] ); ?></textarea></p>
					<span class="description"><?php echo $bonips->available_template_tags( array( 'general' ), '%balance% or %balance_f%' ); ?></span>
				</td>
			</tr>
		</table>
	</div>
</div>
<?php
			}

			/**
			 * Filter Gateway Settings
			 * @since 1.1
			 * @version 1.3
			 */
			function process_gateway_settings( $settings ) {
				// Name (no html)
				$settings['gateways']['bonips']['name'] = stripslashes( wp_filter_nohtml_kses( $settings['gateways']['bonips']['name'] ) );

				// Protokollvorlage (no html)
				$settings['gateways']['bonips']['log_template'] = stripslashes( wp_filter_nohtml_kses( $settings['gateways']['bonips']['log_template'] ) );
				$settings['gateways']['bonips']['type'] = sanitize_text_field( $settings['gateways']['bonips']['type'] );
				$settings['gateways']['bonips']['logo'] = stripslashes( wp_filter_nohtml_kses( $settings['gateways']['bonips']['logo'] ) );

				// Exchange rate (if used)
				if ( $this->use_exchange() ) {
					// Decimals must start with a zero
					if ( $settings['gateways']['bonips']['exchange'] != 1 && substr( $settings['gateways']['bonips']['exchange'], 0, 1 ) != '0' ) {
						$settings['gateways']['bonips']['exchange'] = (float) '0' . $settings['gateways']['bonips']['exchange'];
					}
					// Decimal seperator must be punctuation and not comma
					$settings['gateways']['bonips']['exchange'] = str_replace( ',', '.', $settings['gateways']['bonips']['exchange'] );
				}
				else
					$settings['gateways']['bonips']['exchange'] = 1;
			
				$settings['gateways']['bonips']['profit_share_percent'] = stripslashes( trim( $settings['gateways']['bonips']['profit_share_percent'] ) );
				$settings['gateways']['bonips']['profit_share_log'] = stripslashes( wp_filter_nohtml_kses( $settings['gateways']['bonips']['profit_share_log'] ) );
			
				$settings['gateways']['bonips']['lowfunds'] = stripslashes( wp_filter_post_kses( $settings['gateways']['bonips']['lowfunds'] ) );
				$settings['gateways']['bonips']['visitors'] = stripslashes( wp_filter_post_kses( $settings['gateways']['bonips']['visitors'] ) );
				$settings['gateways']['bonips']['instructions'] = stripslashes( wp_filter_post_kses( $settings['gateways']['bonips']['instructions'] ) );
				$settings['gateways']['bonips']['confirmation'] = stripslashes( wp_filter_post_kses( $settings['gateways']['bonips']['confirmation'] ) );

				// Email (no html)
				$settings['gateways']['bonips']['email'] = stripslashes( wp_filter_nohtml_kses( $settings['gateways']['bonips']['email'] ) );

				return $settings;
			}
		}
		mp_register_gateway_plugin( 'MP_Gateway_boniPS', 'bonips', 'boniPS' );
	}
}

	

/**
 * Filter the boniPS Log
 * Parses the %order_id% and %order_link% template tags.
 * @since 1.1
 * @version 1.1
 */
if ( ! function_exists( 'bonips_psecommerce_parse_log' ) ) {
	add_filter( 'bonips_parse_log_entry_psecommerce_payment', 'bonips_psecommerce_parse_log', 90, 2 );
	function bonips_psecommerce_parse_log( $content, $log_entry )
	{
		// Prep
		global $mp;
		$bonips = bonips( $log_entry->ctype );
		$order = get_post( $log_entry->ref_id );
		$order_id = $order->post_title;
		$user_id = get_current_user_id();

		// Order ID
		$content = str_replace( '%order_id%', $order->post_title, $content );

		// Link to order if we can edit plugin or are the user who made the order
		if ( $user_id == $log_entry->user_id || $bonips->can_edit_plugin( $user_id ) ) {
			$track_link = '<a href="' . mp_orderstatus_link( false, true ) . $order_id . '/' . '">#' . $order->post_title . '/' . '</a>';
			$content = str_replace( '%order_link%', $track_link, $content );
		}
		else {
			$content = str_replace( '%order_link%', '#' . $order_id, $content );
		}

		return $content;
	}
}

/**
 * Parse Email Notice
 * @since 1.2.2
 * @version 1.0
 */
if ( ! function_exists( 'bonips_market_parse_email' ) ) {
	add_filter( 'bonips_email_before_send', 'bonips_market_parse_email' );
	function bonips_market_parse_email( $email )
	{
		if ( $email['request']['ref'] == 'psecommerce_payment' ) {
			$order = get_post( (int) $email['request']['ref_id'] );
			if ( isset( $order->id ) ) {
				$track_link = '<a href="' . mp_orderstatus_link( false, true ) . $order_id . '/' . '">#' . $order->post_title . '/' . '</a>';

				$content = str_replace( '%order_id%', $order->post_title, $email['request']['entry'] );
				$email['request']['entry'] = str_replace( '%order_link%', $track_link, $content );
			}
		}
		return $email;
	}
}
?>