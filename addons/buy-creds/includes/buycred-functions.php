<?php
if ( ! defined( 'BONIPRESS_PURCHASE' ) ) exit;

/**
 * Get buyCRED Setup
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_get_buycred_settings' ) ) :
	function bonipress_get_buycred_settings() {

		$defaults = array(
			'types'      => array( BONIPRESS_DEFAULT_TYPE_KEY ),
			'checkout'   => 'page',
			'log'        => '%plural% purchase',
			'login'      => __( 'Please login to purchase %_plural%', 'bonipress' ),
			'custom_log' => 0,
			'thankyou'   => array(
				'use'        => 'page',
				'custom'     => '',
				'page'       => ''
			),
			'cancelled'  => array(
				'use'        => 'custom',
				'custom'     => '',
				'page'       => ''
			),
			'gifting'    => array(
				'members'    => 1,
				'authors'    => 1,
				'log'        => __( 'Gift purchase from %display_name%.', 'bonipress' )
			)
		);

		$settings = bonipress_get_addon_settings( 'buy_creds' );
		$settings = wp_parse_args( $settings, $defaults );

		return apply_filters( 'bonipress_get_buycred_settings', $settings );

	}
endif;

/**
 * Get Gateways
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_get_buycred_gateways' ) ) :
	function bonipress_get_buycred_gateways() {

		$installed = array();

		// PayPal Standard
		$installed['paypal-standard'] = array(
			'title'         => 'PayPal Payments Standard',
			'documentation' => 'http://codex.bonipress.me/chapter-iii/buycred/payment-gateways/paypal/',
			'callback'      => array( 'boniPRESS_PayPal_Standard' ),
			'icon'          => 'dashicons-admin-generic',
			'sandbox'       => true,
			'external'      => true,
			'custom_rate'   => true
		);

		// BitPay
		$installed['bitpay'] = array(
			'title'         => 'BitPay (Bitcoins)',
			'documentation' => 'http://codex.bonipress.me/chapter-iii/buycred/payment-gateways/bitpay/',
			'callback'      => array( 'boniPRESS_Bitpay' ),
			'icon'          => 'dashicons-admin-generic',
			'sandbox'       => true,
			'external'      => true,
			'custom_rate'   => true
		);

		// NetBilling
		$installed['netbilling'] = array(
			'title'         => 'NETBilling',
			'callback'      => array( 'boniPRESS_NETbilling' ),
			'documentation' => 'http://codex.bonipress.me/chapter-iii/buycred/payment-gateways/netbilling/',
			'icon'          => 'dashicons-admin-generic',
			'sandbox'       => true,
			'external'      => true,
			'custom_rate'   => true
		);

		// Skrill
		$installed['skrill'] = array(
			'title'         => 'Skrill (Moneybookers)',
			'callback'      => array( 'boniPRESS_Skrill' ),
			'documentation' => 'http://codex.bonipress.me/chapter-iii/buycred/payment-gateways/skrill/',
			'icon'          => 'dashicons-admin-generic',
			'sandbox'       => true,
			'external'      => true,
			'custom_rate'   => true
		);

		// Bank Transfers
		$installed['bank'] = array(
			'title'         => __( 'Bank Transfer', 'bonipress' ),
			'documentation' => 'http://codex.bonipress.me/chapter-iii/buycred/payment-gateways/bank-transfers/',
			'callback'      => array( 'boniPRESS_Bank_Transfer' ),
			'icon'          => 'dashicons-admin-generic',
			'sandbox'       => false,
			'external'      => false,
			'custom_rate'   => true
		);

		return apply_filters( 'bonipress_setup_gateways', $installed );

	}
endif;

/**
 * Get buyCRED Setup
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_get_requested_gateway_id' ) ) :
	function bonipress_get_requested_gateway_id() {

		$gateway_id = false;

		if ( isset( $_REQUEST['bonipress_call'] ) )
			$gateway_id = trim( $_REQUEST['bonipress_call'] );

		elseif ( isset( $_REQUEST['bonipress_buy'] ) && is_user_logged_in() )
			$gateway_id = trim( $_REQUEST['bonipress_buy'] );

		return apply_filters( 'bonipress_gateway_id', $gateway_id );

	}
endif;

/**
 * Get buyCRED Setup
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_get_buycred_sale_setup' ) ) :
	function bonipress_get_buycred_sale_setup( $point_type = BONIPRESS_DEFAULT_TYPE_KEY ) {

		$defaults = array(
			'min'  => '',
			'max'  => '',
			'time' => ''
		);

		$saved    = bonipress_get_option( 'buycred-setup-' . $point_type, $defaults );
		$settings = shortcode_atts( $defaults, $saved );

		return apply_filters( 'bonipress_get_buycred_sale_setup', $settings, $point_type );

	}
endif;

/**
 * Get Gateway References
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_get_buycred_gateway_refs' ) ) :
	function bonipress_get_buycred_gateway_refs( $point_type = BONIPRESS_DEFAULT_TYPE_KEY ) {

		$references = array(
			'buy_creds_with_paypal_standard',
			'buy_creds_with_skrill',
			'buy_creds_with_netbilling',
			'buy_creds_with_bitpay',
			'buy_creds_with_bank'
		);

		return apply_filters( 'bonipress_buycred_log_refs', $references, $point_type );

	}
endif;

/**
 * Purchase Limit Dropdown
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_purchase_limit_dropdown' ) ) :
	function bonipress_purchase_limit_dropdown( $name = '', $id = '', $selected = '' ) {

		$options = apply_filters( 'bonipress_buycred_limit_dropdown', array(
			''      => __( 'Kein Limit', 'bonipress' ),
			'day'   => __( '/ Tag', 'bonipress' ),
			'week'  => __( '/ Woche', 'bonipress' ),
			'month' => __( '/ Monat', 'bonipress' )
		) );

		$output  = '<select name="' . $name . '" id="' . $id . '" class="form-control">';
		foreach ( $options as $value => $label ) {
			$output .= '<option value="' . $value . '"';
			if ( $selected == $value ) $output .= ' selected="selected"';
			$output .= '>' . $label . '</option>';
		}
		$output .= '</select>';

		echo $output;

	}
endif;

/**
 * Get buyCRED Setup
 * Returns false if the user is excluded or when using this function with invalid values.
 * Else returns true if the user can buy as much as they wish else a point value if a max limit is enforced.
 * Note that this can result in a zero value being returned, if the user has reached their purchase limit but are not excluded.
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_user_can_buycred' ) ) :
	function bonipress_user_can_buycred( $user_id = 0, $point_type = BONIPRESS_DEFAULT_TYPE_KEY ) {

		$can_buy  = false;
		$total    = 0;
		$settings = bonipress_get_buycred_settings();
		$user_id  = absint( $user_id );

		// Need a valid ID, the point type must be enabled for sale
		if ( $user_id === 0 || ! in_array( $point_type, $settings['types'] ) ) return $can_buy;

		$bonipress   = bonipress( $point_type );
		$setup    = bonipress_get_buycred_sale_setup( $point_type );

		// We need to get the lowest possible value for this point type or the minimum amount we set in our settings
		$minimum  = $bonipress->number( ( ( $setup['min'] == '' ) ? $bonipress->get_lowest_value() : $setup['min'] ) );

		$can_buy  = true;

		// Incase we are enforcing a maximum we need to check how much we already purchased this period
		// So we can see how much is left on the limit
		if ( BONIPRESS_ENABLE_LOGGING && $setup['time'] != '' && $setup['max'] != '' && $setup['max'] > 0 ) {

			$maximum = $bonipress->number( $setup['max'] );

			$total   = bonipress_get_users_total_purchase( $user_id, $point_type, $setup['time'] );
			$total   = $bonipress->number( $total );

			if ( $total < $maximum )
				$can_buy = $bonipress->number( $maximum - $total );

			// Now that we have a "remaining" amount, we need to make sure it is more than the minimum
			if ( $can_buy < $minimum )
				$can_buy = 0;

		}

		return apply_filters( 'bonipress_user_can_buycred', $can_buy, $user_id, $point_type, $total );

	}
endif;

/**
 * Is Gateway Usable
 * Checks if a gateway (based on it's ID) is "usable" as in it is installed and active.
 * Note that this will not take into account if sandbox mode is used or not.
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_buycred_gateway_is_usable' ) ) :
	function bonipress_buycred_gateway_is_usable( $gateway_id ) {

		global $buycred_instance;

		$usable = false;
		if ( isset( $buycred_instance->active ) && array_key_exists( $gateway_id, $buycred_instance->active ) )
			$usable = true;

		return $usable;

	}
endif;

/**
 * Get Users Total Purchase
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_get_users_total_purchase' ) ) :
	function bonipress_get_users_total_purchase( $user_id = false, $point_type = BONIPRESS_DEFAULT_TYPE_KEY, $timeframe = 'all' ) {

		$from              = 0;
		$total             = 0;
		if ( ! BONIPRESS_ENABLE_LOGGING ) return $total;

		$user_id           = absint( $user_id );
		if ( $user_id === 0 || ! bonipress_point_type_exists( $point_type ) ) return $total;

		global $wpdb, $bonipress_log_table;

		if ( $timeframe != 'all' ) {

			$now = current_time( 'timestamp' );

			// By default we assume $timeframe = 'day'
			$today = strtotime( date( 'Y-m-d' ) . ' midnight', $now );

			// Per week
			if ( $timeframe == 'week' ) {

				$weekday   = date( 'w', $now );
				$thisweek  = strtotime( '-' . ( $weekday+1 ) . ' days midnight', $now );
				if ( get_option( 'start_of_week' ) == $weekday )
					$thisweek = $today;

				$from = $thisweek;

			}

			// Per month
			elseif ( $timeframe == 'month' )
				$from = strtotime( date( 'Y-m-01' ) . ' midnight', $now );

		}

		// First we need to count the completed purchases
		$completed         = $wpdb->get_var( $wpdb->prepare( "
			SELECT SUM(creds) 
			FROM {$bonipress_log_table} 
			WHERE user_id = %d 
				AND ctype = %s 
				AND ref LIKE %s 
				AND time > %d", $user_id, $point_type, 'buy_creds_with_%', $from ) );

		if ( $completed === NULL ) $completed = 0;

		// Multisite Master Template support
		$posts             = bonipress_get_db_column( 'posts' );
		$postmeta          = bonipress_get_db_column( 'postmeta' );

		// Next we need to tally up pending payments
		$pending           = $wpdb->get_var( $wpdb->prepare( "
			SELECT SUM( a.meta_value ) 
			FROM {$posts} p 
			LEFT JOIN {$postmeta} a 
				ON ( p.ID = a.post_id AND a.meta_key = 'amount' )
			LEFT JOIN {$postmeta} t 
				ON ( p.ID = t.post_id AND t.meta_key = 'point_type' )
			WHERE p.post_type = %s 
				AND p.post_status = 'publish' 
				AND p.post_author = %d
				AND t.meta_value = %s;", 'buycred_payment', $user_id, $point_type ) );

		if ( $pending === NULL ) $pending = 0;

		$total            = $completed + $pending;

		return apply_filters( 'bonipress_get_users_total_purchase', $total, $user_id, $point_type, $completed, $total );

	}
endif;

/**
 * Get Pending Payment
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'buycred_get_pending_payment_id' ) ) :
	function buycred_get_pending_payment_id( $payment_id = NULL ) {

		if ( $payment_id === NULL || $payment_id == '' ) return false;

		// In case we are using the transaction ID instead of the post ID.
		$post_id = false;
		if ( ! is_numeric( $payment_id ) ) {

			$post = bonipress_get_page_by_title( strtoupper( $payment_id ), OBJECT, 'buycred_payment' );
			if ( $post === NULL ) return false;

			$post_id = $post->ID;

		}
		else {
			$post_id = absint( $payment_id );
		}

		return $post_id;

	}
endif;

/**
 * Get Pending Payment
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'buycred_get_pending_payment' ) ) :
	function buycred_get_pending_payment( $payment_id = NULL ) {

		// Construct fake pending object ( when no pending payment object exists )
		if ( is_array( $payment_id ) ) {

			$pending_payment                 = new StdClass();
			$pending_payment->payment_id     = false;
			$pending_payment->public_id      = $payment_id['public_id'];
			$pending_payment->point_type     = $payment_id['point_type'];
			$pending_payment->amount         = $payment_id['amount'];
			$pending_payment->cost           = $payment_id['cost'];
			$pending_payment->currency       = $payment_id['currency'];
			$pending_payment->buyer_id       = $payment_id['buyer_id'];
			$pending_payment->recipient_id   = $payment_id['recipient_id'];
			$pending_payment->gateway_id     = $payment_id['gateway_id'];
			$pending_payment->transaction_id = $payment_id['transaction_id'];
			$pending_payment->cancel_url     = false;
			$pending_payment->pay_now_url    = false;

		}

		else {

			$payment_id = buycred_get_pending_payment_id( $payment_id );

			if ( $payment_id === false ) return false;

			$pending_payment                 = new StdClass();
			$pending_payment->payment_id     = absint( $payment_id );
			$pending_payment->public_id      = get_the_title( $payment_id );
			$pending_payment->point_type     = bonipress_get_post_meta( $payment_id, 'point_type', true );
			$pending_payment->amount         = bonipress_get_post_meta( $payment_id, 'amount', true );
			$pending_payment->cost           = bonipress_get_post_meta( $payment_id, 'cost', true );
			$pending_payment->currency       = bonipress_get_post_meta( $payment_id, 'currency', true );
			$pending_payment->buyer_id       = bonipress_get_post_meta( $payment_id, 'from', true );
			$pending_payment->recipient_id   = bonipress_get_post_meta( $payment_id, 'to', true );
			$pending_payment->gateway_id     = bonipress_get_post_meta( $payment_id, 'gateway', true );
			$pending_payment->transaction_id = $pending_payment->public_id;

			$pending_payment->cancel_url     = buycred_get_cancel_transaction_url( $pending_payment->public_id );

			$pending_payment->pay_now_url    = add_query_arg( array(
				'bonipress_buy' => $pending_payment->gateway_id,
				'amount'     => $pending_payment->amount,
				'revisit'    => $payment_id,
				'token'      => wp_create_nonce( 'bonipress-buy-creds' )
			), set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ) );

		}

		return apply_filters( 'buycred_get_pending_payment', $pending_payment, $payment_id );

	}
endif;

/**
 * Add Pending Comment
 * @since 1.7
 * @version 1.1
 */
if ( ! function_exists( 'buycred_add_pending_comment' ) ) :
	function buycred_add_pending_comment( $payment_id = NULL, $comment = NULL, $time = NULL ) {

		if ( ! BONIPRESS_BUY_PENDING_COMMENTS ) return true;

		$post_id = buycred_get_pending_payment_id( $payment_id );
		if ( $post_id === false ) return false;

		global $bonipress_modules;

		if ( $time === NULL || $time == 'now' )
			$time = current_time( 'mysql' );

		$author       = 'buyCRED';
		$gateway      = bonipress_get_post_meta( $post_id, 'gateway', true );
		$gateways     = bonipress_get_buycred_gateways();
		$author_url   = sprintf( 'buyCRED: %s %s', __( 'Unknown Gateway', 'bonipress' ), $gateway );
		$author_email = apply_filters( 'bonipress_buycred_comment_email', 'buycred-service@bonipress.me' );

		if ( array_key_exists( $gateway, $gateways ) )
			$author = sprintf( 'buyCRED: %s %s', $gateways[ $gateway ]['title'], __( 'Gateway', 'bonipress' ) );

		return wp_insert_comment( array(
			'comment_post_ID'      => $post_id,
			'comment_author'       => $author,
			'comment_author_email' => $author_email,
			'comment_content'      => $comment,
			'comment_type'         => 'buycred',
			'comment_author_IP'    => $_SERVER['REMOTE_ADDR'],
			'comment_date'         => $time,
			'comment_approved'     => 1,
			'user_id'              => 0
		) );

	}
endif;

/**
 * Get Cancel URL
 * @since 1.7
 * @version 1.0.1
 */
if ( ! function_exists( 'buycred_get_cancel_transaction_url' ) ) :
	function buycred_get_cancel_transaction_url( $transaction_id = NULL ) {

		$settings = bonipress_get_buycred_settings();
		$base     = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );

		// Cancel page
		if ( $settings['cancelled']['use'] == 'page' ) {

			if ( ! empty( $settings['cancelled']['page'] ) && $settings['cancelled']['page'] > 0 )
				$base = bonipress_get_permalink( $settings['cancelled']['page'] );

		}

		// Custom URL
		elseif ( $settings['cancelled']['use'] != 'page' && $settings['cancelled']['custom'] != '' ) {

			$base = esc_url_raw( $settings['cancelled']['custom'] );

		}

		// Override
		if ( isset( $_REQUEST['return_to'] ) && esc_url_raw( $_REQUEST['return_to'] ) != '' )
			$base = esc_url_raw( $_REQUEST['return_to'] );

		if ( $transaction_id !== NULL )
			$url = add_query_arg( array( 'buycred-cancel' => $transaction_id, '_token' => wp_create_nonce( 'buycred-cancel-pending-payment' ) ), $base );
		else
			$url = $base;

		return apply_filters( 'bonipress_buycred_cancel_url', $url, $transaction_id, $base );

	}
endif;

/**
 * Get Users Pending Payments
 * @since 1.7
 * @version 1.0.2
 */
if ( ! function_exists( 'buycred_get_users_pending_payments' ) ) :
	function buycred_get_users_pending_payments( $user_id = NULL, $point_type = '' ) {

		$user_id = absint( $user_id );
		if ( $user_id === 0 ) return false;

		$pending = bonipress_get_user_meta( $user_id, 'buycred_pending_payments', '', true );
		if ( ! is_array( $pending ) ) {

			global $wpdb;

			$pending = array();
			$table   = bonipress_get_db_column( 'posts' );
			$saved   = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} posts WHERE posts.post_type = 'buycred_payment' AND posts.post_author = %d AND posts.post_status = 'publish';", $user_id ) );

			if ( ! empty( $saved ) ) {

				foreach ( $saved as $entry ) {

					$point_type = bonipress_get_post_meta( $entry->ID, 'point_type', true );
					if ( $point_type == '' ) $point_type = BONIPRESS_DEFAULT_TYPE_KEY;

					if ( ! array_key_exists( $point_type, $pending ) )
						$pending[ $point_type ] = array();

					$pending[ $point_type ][] = buycred_get_pending_payment( (int) $entry->ID );

				}

			}

			else {

				if ( $point_type == '' )
					$pending[ BONIPRESS_DEFAULT_TYPE_KEY ] = array();
				else
					$pending[ $point_type ] = array();

			}

			bonipress_add_user_meta( $user_id, 'buycred_pending_payments', '', $pending, true );

		}

		if ( $point_type != '' && bonipress_point_type_exists( $point_type ) ) {

			if ( ! is_array( $pending ) || ! array_key_exists( $point_type, $pending ) )
				return false;

			return $pending[ $point_type ];

		}

		return $pending;

	}
endif;

/**
 * buyCRED Gateway Constructor
 * @since 1.7
 * @version 1.1
 */
if ( ! function_exists( 'buycred_gateway' ) ) :
	function buycred_gateway( $gateway_id = NULL ) {

		global $buycred_gateway, $bonipress_modules;

		if ( isset( $buycred_gateway )
			&& ( $buycred_gateway instanceof boniPRESS_Payment_Gateway )
			&& ( $gateway_id === $buycred_gateway->id )
		) {
			return $buycred_gateway;
		}

		$buycred_gateway = false;
		$installed       = $bonipress_modules['solo']['buycred']->get();
		if ( array_key_exists( $gateway_id, $installed ) ) {

			$class   = $installed[ $gateway_id ]['callback'][0];

			// Construct Gateway
			$buycred_gateway = new $class( $bonipress_modules['solo']['buycred']->gateway_prefs );

		}

		return $buycred_gateway;

	}
endif;

/**
 * Delete Pending Payment
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'buycred_trash_pending_payment' ) ) :
	function buycred_trash_pending_payment( $payment_id = NULL ) {

		$pending_payment = buycred_get_pending_payment( $payment_id );
		if ( $pending_payment === false ) return false;

		bonipress_delete_user_meta( $pending_payment->buyer_id, 'buycred_pending_payments' );

		return wp_trash_post( $pending_payment->payment_id );

	}
endif;

/**
 * Complete Pending Payment
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'buycred_complete_pending_payment' ) ) :
	function buycred_complete_pending_payment( $pending_id ) {

		$pending_payment = buycred_get_pending_payment( $pending_id );
		if ( $pending_payment === false ) return false;

		$gateway = buycred_gateway( $pending_payment->gateway_id );
		if ( $gateway === false ) return false;

		// Complete Payment
		$paid = $gateway->complete_payment( $pending_payment, $pending_payment->transaction_id );

		if ( $paid )
			return buycred_trash_pending_payment( $pending_id );

		return $paid;

	}
endif;

/**
 * Checkout Title
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'buycred_checkout_title' ) ) :
	function buycred_checkout_title() {

		global $buycred_instance;

		if ( $buycred_instance->gateway !== false )
			$buycred_instance->gateway->checkout_page_title();

	}
endif;

/**
 * Checkout Body
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'buycred_checkout_body' ) ) :
	function buycred_checkout_body() {

		global $buycred_instance;

		if ( $buycred_instance->gateway !== false )
			$buycred_instance->gateway->checkout_page_body();

	}
endif;

/**
 * Checkout Logo
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'buycred_checkout_logo' ) ) :
	function buycred_checkout_logo() {

		global $buycred_instance;

		if ( $buycred_instance->gateway !== false )
			$buycred_instance->gateway->checkout_logo();

	}
endif;

/**
 * Checkout Order
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'buycred_checkout_order' ) ) :
	function buycred_checkout_order() {

		global $buycred_instance;

		if ( $buycred_instance->gateway !== false )
			$buycred_instance->gateway->checkout_order();

	}
endif;

/**
 * Checkout Cancel
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'buycred_checkout_cancel' ) ) :
	function buycred_checkout_cancel() {

		global $buycred_instance;

		if ( $buycred_instance->gateway !== false )
			$buycred_instance->gateway->checkout_cancel();

	}
endif;

/**
 * Checkout Footer
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'buycred_checkout_footer' ) ) :
	function buycred_checkout_footer() {

		global $buycred_instance;

		if ( $buycred_instance->gateway !== false )
			$buycred_instance->gateway->checkout_page_footer();

	}
endif;
