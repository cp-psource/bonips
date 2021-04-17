<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

/**
 * Get The Transfer Object
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_transfer' ) ) :
	function bonipress_transfer( $transfer_id = false ) {

		global $bonipress_transfer;

		$transfer_id     = sanitize_text_field( $transfer_id );

		if ( isset( $bonipress_transfer )
			&& ( $bonipress_transfer instanceof boniPRESS_Transfer )
			&& ( $transfer_id === $bonipress_transfer->transfer_id )
		) {
			return $bonipress_transfer;
		}

		$bonipress_transfer = new boniPRESS_Transfer( $transfer_id );

		do_action( 'bonipress_transfer' );

		return $bonipress_transfer;

	}
endif;

/**
 * Get Transfer
 * @see http://codex.bonipress.me/functions/bonipress_get_transfer/
 * @param $transfer_id (string) required transfer id to retreave.
 * @returns boniPRESS_Transfer object on success else false.
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_get_transfer' ) ) :
	function bonipress_get_transfer( $transfer_id = false ) {

		if ( $transfer === false ) return false;

		$transfer    = bonipress_transfer( $transfer_id );
		$transaction = $transfer->get_transfer();

		// Transaction not found
		if ( $transaction === false ) return false;

		// Populate object
		foreach ( $transaction as $key => $value )
			$transfer->$key = $value;

		return $transfer;

	}
endif;

/**
 * New Transfer
 * @see http://codex.bonipress.me/functions/bonipress_new_transfer/
 * @param $request (array) the required transfer request array.
 * @param $post (array) optional posted data from the transfer form.
 * @returns error code if transfer failed else an array or transfer details.
 * @since 1.7.6
 * @version 1.1
 */
if ( ! function_exists( 'bonipress_new_transfer' ) ) :
	function bonipress_new_transfer( $request = array(), $post = array() ) {

		$transfer       = bonipress_transfer();

		// Validate the request first
		$valid_transfer = $transfer->is_valid_transfer_request( $request, $post );
		if ( $valid_transfer !== true )
			return $valid_transfer;

		// Attempt to make the transfer
		return $transfer->new_transfer();

	}
endif;

/**
 * Refund Transfer
 * @see http://codex.bonipress.me/functions/bonipress_refund_transfer/
 * @param $transfer_id (string) required transfer id to refund.
 * @returns error message (string) or true on success.
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_refund_transfer' ) ) :
	function bonipress_refund_transfer( $transfer_id = false ) {

		if ( $transfer === false ) return false;

		$transfer    = bonipress_transfer( $transfer_id );
		$transaction = $transfer->get_transfer();

		// Transaction could not be found
		if ( $transaction === false ) return false;

		// Populate object
		foreach ( $transaction as $key => $value )
			$transfer->$key = $value;

		return $transfer->refund();

	}
endif;

/**
 * Get Transfer Limits
 * @see http://codex.bonipress.me/functions/bonipress_get_transfer_limits/
 * @param $settings (array) optional transfer settings.
 * @returns array of limits.
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_get_transfer_limits' ) ) :
	function bonipress_get_transfer_limits( $settings = false ) {

		if ( $settings === false )
			$settings = bonipress_get_addon_settings( 'transfers' );

		$limits = array(
			'none'    => __( 'No limits.', 'bonipress' ),
			'daily'   => __( 'Impose daily limit.', 'bonipress' ),
			'weekly'  => __( 'Impose weekly limit.', 'bonipress' ),
			'monthly' => __( 'Impose monthly limit.', 'bonipress' )
		);

		return apply_filters( 'bonipress_transfer_limits', $limits, $settings );

	}
endif;

/**
 * Get Transfer Limits
 * @see http://codex.bonipress.me/functions/bonipress_get_transfer_autofill_by/
 * @param $settings (array) optional transfer settings.
 * @returns array of autofill options.
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_get_transfer_autofill_by' ) ) :
	function bonipress_get_transfer_autofill_by( $settings = false ) {

		if ( $settings === false )
			$settings = bonipress_get_addon_settings( 'transfers' );

		$autofills = array(
			'user_login' => __( 'User Login (user_login)', 'bonipress' ),
			'user_email' => __( 'User Email (user_email)', 'bonipress' )
		);

		return apply_filters( 'bonipress_transfer_autofill_by', $autofills, $settings );

	}
endif;

/**
 * Get Transfer Recipient
 * @see http://codex.bonipress.me/functions/bonipress_get_transfer_recipient/
 * @param $value (int|string) a value that identifies a particular user in WordPress.
 * @returns false if no recipient was found else the users id (int).
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_get_transfer_recipient' ) ) :
	function bonipress_get_transfer_recipient( $value = '' ) {

		$settings     = bonipress_get_addon_settings( 'transfers' );
		$recipient_id = false;

		if ( ! empty( $value ) ) {

			// A numeric ID has been provided that we need to validate
			if ( is_numeric( $value ) ) {

				$user = get_userdata( $value );
				if ( isset( $user->ID ) )
					$recipient_id = $user->ID;

			}

			// A username has been provided
			elseif ( $settings['autofill'] == 'user_login' ) {

				$user = get_user_by( 'login', $value );
				if ( isset( $user->ID ) )
					$recipient_id = $user->ID;

			}

			// An email address has been provided
			elseif ( $settings['autofill'] == 'user_email' ) {

				$user = get_user_by( 'email', $value );
				if ( isset( $user->ID ) )
					$recipient_id = $user->ID;

			}

		}

		return apply_filters( 'bonipress_transfer_get_recipient', $recipient_id, $value, $settings );

	}
endif;

/**
 * User Can Transfer
 * @see http://codex.bonipress.me/functions/bonipress_user_can_transfer/
 * @param $user_id (int) requred user id
 * @param $amount (int) optional amount to check against balance
 * @returns true if no limit is set, 'limit' (string) if user is over limit else the amount of creds left
 * @since 0.1
 * @version 1.4.1
 */
if ( ! function_exists( 'bonipress_user_can_transfer' ) ) :
	function bonipress_user_can_transfer( $user_id = NULL, $amount = NULL, $type = BONIPRESS_DEFAULT_TYPE_KEY, $reference = NULL ) {

		if ( $user_id === NULL )
			$user_id = get_current_user_id();

		if ( $reference === NULL )
			$reference = 'transfer';

		if ( ! bonipress_point_type_exists( $type ) )
			$type = BONIPRESS_DEFAULT_TYPE_KEY;

		// Grab Settings
		$settings = bonipress_get_addon_settings( 'transfers' );
		$bonipress   = bonipress( $type );
		$zero     = $bonipress->zero();

		// Get users balance
		$balance  = $bonipress->get_users_balance( $user_id, $type );

		// Get Transfer Max
		$max      = apply_filters( 'bonipress_transfer_limit', $bonipress->number( $settings['limit']['amount'] ), $user_id, $amount, $settings, $reference );

		// If an amount is given, deduct this amount to see if the transaction
		// brings us over the account limit
		if ( $amount !== NULL )
			$balance = $bonipress->number( $balance - $amount );

		// Zero
		// The lowest amount a user can have on their account. By default, this
		// is zero. But you can override this via the bonipress_transfer_acc_limit hook.
		$account_limit = $bonipress->number( apply_filters( 'bonipress_transfer_acc_limit', $zero, $type, $user_id, $reference ) );

		// Check if we would go minus
		if ( $balance < $account_limit ) return 'low';

		// If there are no limits, return the current balance
		if ( $settings['limit']['limit'] == 'none' ) return $balance;

		// Else we have a limit to impose
		$now = current_time( 'timestamp' );
		$max = $bonipress->number( $settings['limit']['amount'] );

		// Daily limit
		if ( $settings['limit']['limit'] == 'daily' )
			$total = bonipress_get_total_by_time( 'today', 'now', $reference, $user_id, $type );

		// Weekly limit
		elseif ( $settings['limit']['limit'] == 'weekly' ) {
			$this_week = mktime( 0, 0, 0, date( 'n', $now ), date( 'j', $now ) - date( 'n', $now ) + 1 );
			$total     = bonipress_get_total_by_time( $this_week, 'now', $reference, $user_id, $type );
		}

		// Custom limits will need to return the result
		// here and now. Accepted answers are 'limit', 'low' or the amount left on limit.
		else {
			return apply_filters( 'bonipress_user_can_transfer', 'limit', $user_id, $amount, $settings, $reference );
		}

		// We are adding up point deducations.
		$total = abs( $total );

		if ( $amount !== NULL ) {

			$total = $bonipress->number( $total + $amount );

			// Transfer limit reached
			if ( $total > $max ) return 'limit';

		}

		else {

			// Transfer limit reached
			if ( $total >= $max ) return 'limit';

		}

		// Return whats remaining of limit
		return $bonipress->number( $max - $total );

	}
endif;

/**
 * Render Transfer Message
 * @see http://codex.bonipress.me/functions/bonipress_transfer_render_message/
 * @since 1.7.6
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_transfer_render_message' ) ) :
	function bonipress_transfer_render_message( $original = '', $data = array() ) {

		if ( empty( $original ) || empty( $data ) ) return $original;

		// Default message
		$message = apply_filters( 'bonipress_transfer_default_message', '-', $original, $data );

		// Get saved message
		if ( ! empty( $data ) && array_key_exists( 'message', $data ) && ! empty( $data['message'] ) )
			$message = $data['message'];

		$content = str_replace( '%transfer_message%', $message, $original );

		return apply_filters( 'bonipress_transfer_message', $content, $original, $message, $data );

	}
endif;

/**
 * Get Users Transfer Verlauf
 * @since 1.3.3
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_get_users_transfer_history' ) ) :
	function bonipress_get_users_transfer_history( $user_id, $type = BONIPRESS_DEFAULT_TYPE_KEY, $key = NULL ) {

		if ( $key === NULL )
			$key = 'bonipress_transactions';

		if ( $type != BONIPRESS_DEFAULT_TYPE_KEY && $type != '' )
			$key .= '_' . $type;

		$default = array(
			'frame'  => '',
			'amount' => 0
		);
		return bonipress_apply_defaults( $default, bonipress_get_user_meta( $user_id, $key, '', true ) );

	}
endif;

/**
 * Update Users Transfer Verlauf
 * @since 1.3.3
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_update_users_transfer_history' ) ) :
	function bonipress_update_users_transfer_history( $user_id, $history, $type = BONIPRESS_DEFAULT_TYPE_KEY, $key = NULL ) {

		if ( $key === NULL )
			$key = 'bonipress_transactions';

		if ( $type != BONIPRESS_DEFAULT_TYPE_KEY && $type != '' )
			$key .= '_' . $type;

		// Get current history
		$current = bonipress_get_users_transfer_history( $user_id, $type, $key );

		// Reset
		if ( $history === true )
			$new_history = array(
				'frame'  => '',
				'amount' => 0
			);

		// Update
		else $new_history = bonipress_apply_defaults( $current, $history );

		bonipress_update_user_meta( $user_id, $key, '', $new_history );

	}
endif;
