<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * boniPS_Rank class
 * @since 1.8
 * @version 1.0
 */
if ( ! class_exists( 'boniPS_Transfer' ) ) :
	class boniPS_Transfer extends boniPS_Object {

		/**
		 * A unique Transaction ID
		 */
		public $transfer_id        = false;

		/**
		 * The reference used for this transfer
		 */
		public $reference          = '';

		/**
		 * Transfer Status
		 */
		public $status             = '';

		/**
		 * The senders ID
		 */
		public $sender_id          = false;

		/**
		 * The recipients ID
		 */
		public $recipient_id       = false;

		/**
		 * The point type object for this transfer
		 */
		public $point_type         = false;

		/**
		 * The requested transfer amount
		 */
		public $transfer_amount    = 0;

		/**
		 * The amount that was deducted from the sender
		 */
		public $transfer_charge    = 0;

		/**
		 * The amount the recipient received
		 */
		public $transfer_payout    = 0;

		/**
		 * The transfer data
		 */
		public $data               = array();

		/**
		 * The transfer message
		 */
		public $message            = '';

		/**
		 * The transfer add-on settings
		 */
		public $settings           = false;

		/**
		 * The current UNIX timestamp
		 */
		public $now                = 0;

		/**
		 * The transfer form arguments
		 */
		public $args               = array();

		/**
		 * The transfer request
		 */
		public $request            = array();

		/**
		 * Indicates if a transfer request is valid
		 */
		public $valid_request      = false;

		/**
		 * An array of point type IDs that can be transferred by a given user
		 */
		public $transferable_types = array();

		/**
		 * The available balances of the user making the transfer
		 */
		public $balances           = array();

		/**
		 * The transfer limits
		 */
		public $limits             = array();

		/**
		 * The minimum amount that must be transferred
		 */
		public $minimum            = 0;

		/**
		 * Array of transfer errors from a request
		 */
		public $errors             = array();

		/**
		 * Construct
		 */
		public function __construct( $transfer_id = false ) {

			parent::__construct();

			// Transfer ID
			if ( $transfer_id !== false ) {

				$this->transfer_id = sanitize_text_field( $transfer_id );

			}

			$this->populate();

		}

		/**
		 * Populate
		 * @since 1.8
		 * @version 1.0
		 */
		protected function populate() {

			$this->now      = current_time( 'timestamp' );

			$settings       = bonips_get_addon_settings( 'transfers' );

			if ( $settings === false )
				$settings = array(
					'types'      => array( BONIPS_DEFAULT_TYPE_KEY ),
					'logs'       => array(
						'sending'   => 'Übertragung von %plural% an %display_name%',
						'receiving' => 'Übertragung von %plural% von %display_name%'
					),
					'errors'     => array(
						'low'       => __( 'Du hast nicht genug %plural% zum Senden.', 'bonips' ),
						'over'      => __( 'Du hast Dein Übertragungslimit von %limit% überschritten.', 'bonips' )
					),
					'templates'  => array(
						'login'     => '',
						'balance'   => 'Dein aktuelles Guthaben beträgt %balance%',
						'limit'     => 'Dein aktuelles Transferlimit von %limit% beträgt %left%',
						'button'    => __( 'Transfer', 'bonips' )
					),
					'autofill'   => 'user_login',
					'reload'     => 1,
					'message'    => 0,
					'limit'      => array(
						'amount'    => 0,
						'limit'     => 'none'
					)
				);

			$this->settings = apply_filters( 'bonips_get_transfer_settings', $settings, $this );

		}

		/**
		 * Generate New Transfer ID
		 * @since 1.8
		 * @version 1.0
		 */
		public function generate_new_transfer_id( $sender_id = 0, $recipient_id = 0 ) {

			$transfer_id = 'TXID' . $this->now . $sender_id . $recipient_id;

			return apply_filters( 'bonips_new_transfer_id', $transfer_id, $sender_id, $recipient_id, $this );

		}

		/**
		 * Get Transfer
		 * @since 1.8
		 * @version 1.0
		 */
		public function get_transfer( $transfer_id = false ) {

			$transfer    = false;

			if ( $transfer_id === false )
				$transfer_id = $this->transfer_id;

			// If we pass on the two log entries we do not need to do a db query
			if ( is_array( $transfer_id ) && ! empty( $transfer_id ) && count( $transfer_id ) == 2 )
				$log_entries = $transfer_id;

			// Search the db based on the transfer id
			elseif ( $transfer_id !== false ) {

				global $wpdb, $bonips_log_table;

				$transfer_id  = sanitize_text_field( $transfer_id );
				$transfer_sql = '%"tid";s:' . strlen( $transfer_id ) . ':"' . $transfer_id . '";%';

				// Get the transfer log entries based on the transfer id order so we start with the sender followed by the recipient
				$log_entries  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$bonips_log_table} WHERE data LIKE %s ORDER BY creds ASC LIMIT 2;", $transfer_sql ) );

			}

			// Invalid usage of this function
			else return $transfer;

			// A valid transfer has two log entries, one for the sender and one for the recipient
			if ( ! empty( $log_entries ) && count( $log_entries ) == 2 ) {

				$transfer                  = new StdClass();
				$transfer->transfer_id     = $transfer_id;
				$transfer->reference       = $log_entries[0]->ref;
				$transfer->status          = ( $log_entries[0]->ref_id == 0 && $log_entries[1]->ref_id == 0 ) ? 'refunded' : 'completed';

				$transfer->sender_id       = $log_entries[0]->user_id;
				$transfer->recipient_id    = $log_entries[1]->user_id;

				$transfer->point_type      = new boniPS_Point_Type( $log_entries[0]->ctype );

				$transfer->transfer_charge = abs( $log_entries[0]->creds );
				$transfer->transfer_payout = abs( $log_entries[1]->creds );
				$transfer->transfer_amount = $transfer->transfer_charge;

				$transfer->data            = maybe_unserialize( $log_entries[0]->data );
				$transfer->message         = ( array_key_exists( 'message', $transfer->data ) && ! empty( $transfer->data['message'] ) ) ? $transfer->data['message'] : '';

			}

			return apply_filters( 'bonips_get_transfer', $transfer, $transfer_id, $this );

		}

		/**
		 * Get Transfer Statuses
		 * @since 1.8
		 * @version 1.0
		 */
		public function get_transfer_statuses() {

			return apply_filters( 'bonips_transfer_statuses', array(
				''           => __( 'Neu', '' ),
				'incomplete' => __( 'Unvollständig', '' ),
				'completed'  => __( 'Vollendet', '' ),
				'refunded'   => __( 'Erstattet', '' )
			) );

		}

		/**
		 * Get Transfer Status
		 * @since 1.8
		 * @version 1.0
		 */
		public function get_transfer_status() {

			return $this->status;

		}

		/**
		 * Display Transfer Status
		 * @since 1.8
		 * @version 1.0
		 */
		public function display_transfer_status( $status = NULL ) {

			if ( $status === NULL )
				$status = $this->get_transfer_status();

			$statuses = $this->get_transfer_statuses();
			if ( array_key_exists( $status, $statuses ) )
				$status = $statuses[ $status ];

			return $status;

		}

		/**
		 * New Transfer Instance
		 * Creates a new transfer instance.
		 * @since 1.8
		 * @version 1.0
		 */
		public function new_instance( $request = array() ) {

			global $bonips_do_transfer;

			extract( shortcode_atts( array(
				'sender_id'   => get_current_user_id(),
				'reference'   => 'transfer',
				'minimum'     => 0,
				'recipient'   => 0,
				'amount'      => '',
				'point_types' => implode( ',', $this->settings['types'] )
			), $request ) );

			$sender_id                = absint( $sender_id );

			$this->transfer_id        = $this->generate_new_transfer_id( $sender_id );
			$this->sender_id          = $sender_id;
			$this->reference          = sanitize_key( $reference );
			$this->minimum            = ( $minimum > 0 ) ? $minimum : false;

			// Recipient can be a single user, no user or or a comma separated list of IDs
			// Since this is a new request, this will be used by the recipient form field
			$recipient_id             = bonips_get_user_id( $recipient );
			if ( $recipient_id == 0 ) $recipient_id = false;

			$this->recipient_id       = $recipient_id;

			$transferable             = array();
			$requested_types          = ( ! empty( $point_types ) ) ? explode( ',', $point_types ) : $this->settings['types'];
			$usable_types             = bonips_get_usable_types( $sender_id );

			// Make sure we have "usable types". These are types we are not excluded from
			if ( ! empty( $usable_types ) ) {

				global $bonips_current_account;

				// In case we are the sender lets get existing data instead of running a new query
				$balances = ( bonips_is_current_account( $this->sender_id ) ) ? $bonips_current_account->balance : false;

				// Loop through each requested type
				foreach ( $requested_types as $type_id ) {

					// Trust no one
					$type_id                    = sanitize_key( $type_id );

					// Make sure the type exists, it is set to be transferrable and it is a point type we are not excluded from using
					if ( ! bonips_point_type_exists( $type_id ) || ! in_array( $type_id, $usable_types ) || ! in_array( $type_id, $this->settings['types'] ) ) continue;

					// The current users balances are already populated in the $bonips_current_account object, if this is the case, we do not need to ask for them again.
					$balance                    = ( $balances !== false && $balances[ $type_id ] !== false ) ? $balances[ $type_id ] : new boniPS_Balance( $this->sender_id, $type_id );

					// The lowest our balance can go when we transfer. This filter allows us to give credit to some
					// users, which in turn will allow their account to go minus.
					$balance->lowest            = apply_filters( 'bonips_transfer_acc_limit', $balance->point_type->number( 0 ), $type_id, $this->sender_id, $this->reference );

					// Populate balance
					$this->balances[ $type_id ] = $balance;

					// Save type id as transferable
					$transferable[]             = $type_id;

				}

			}
			$this->transferable_types = $transferable;

			// Amount to transfer, can be left empty, pre set to one particular amount or
			// a comma separated list of values
			$requested_amount         = ( ! empty( $amount ) ) ? explode( ',', $amount ) : 0;
			if ( is_array($requested_amount) && count( $requested_amount ) == 1 )
				$requested_amount = $requested_amount[0];

			$this->transfer_amount    = $requested_amount;

			// We can't make a transfer
			if ( empty( $this->transferable_types ) ) {

				$this->errors['excluded'] = 'Du hast keinen Zugriff auf diesen Punkttyp.';

				return false;

			}

			// Enforce minimum requirements
			if ( ! $this->user_can_transfer_minimum() ) {

				$this->errors['minimum'] = 'Du hast nicht genug Punkte, um eine Überweisung vorzunehmen.';

				return false;

			}

			// Enforce limits (if used)
			if ( $this->user_is_over_limit() ) {

				$this->errors['limit'] = 'Du hast Dein Überweisungslimit erreicht.';

				return false;

			}

			$bonips_do_transfer = true;

			return true;

		}

		/**
		 * User Can Transfer Minimum
		 * Checks if a user can transfer the minimum requirement.
		 * @since 1.8
		 * @version 1.0
		 */
		public function user_can_transfer_minimum() {

			$can_transfer = true;
			if ( ! empty( $this->transferable_types ) ) {

				$type_count = count( $this->transferable_types );
				$unusable   = 0;

				foreach ( $this->transferable_types as $type_id ) {

					$balance          = $this->balances[ $type_id ];
					$type             = $balance->point_type;

					// The minimum amount that must be transfered
					$balance->minimum = ( $this->minimum === false ) ? $type->get_lowest_value() : $type->number( $this->minimum );
					$balance->usable  = true;

					// Make sure we can transfer the minimum amount
					if ( ( $balance->current - $balance->minimum ) < $balance->lowest ) {
						$balance->usable = false;
						$unusable ++;
					}

					$this->balances[ $type_id ] = $balance;

				}

				// If no point types are usable, we can't do much
				if ( $unusable == $type_count )
					$can_transfer = false;

			}

			return $can_transfer;

		}

		/**
		 * Get Transfer Limit
		 * @since 1.8
		 * @version 1.0
		 */
		public function get_transfer_limit() {

			$limit = false;

			// If limit is set
			if ( $this->settings['limit']['limit'] != 'none' ) {

				// Create a limit object, since we love objects
				$limit         = new StdClass();
				$limit->label  = __( 'Tag', 'bonips' );
				$limit->amount = $this->settings['limit']['amount'];
				$limit->period = $this->settings['limit']['limit'];
				$limit->from   = 'today';
				$limit->until  = 'now';

				// Weekly limit
				if ( $limit->period == 'weekly' ) {
					$limit->label = __( 'Woche', 'bonips' );
					$limit->from  = mktime( 0, 0, 0, date( 'n', $this->now ), date( 'j', $this->now ) - date( 'n', $this->now ) + 1 );
				}

				// Monthly limit
				elseif ( $limit->period == 'monthly' ) {
					$limit->label = __( 'Monat', 'bonips' );
					$limit->from  = strtotime( date( 'Y-m-01' ) . ' midnight', $this->now );
				}

			}

			return $limit;

		}

		/**
		 * Get Transfer Limit Description
		 * @since 1.8
		 * @version 1.0
		 */
		public function get_transfer_limit_desc( $point_type = BONIPS_DEFAULT_TYPE_KEY ) {

			$description = __( 'Kein Limit', 'bonips' );
			if ( ! empty( $this->limits ) && array_key_exists( $point_type, $this->limits ) ) {

				$limit       = $this->limits[ $point_type ];
				$type_object = $this->balances[ $point_type ]->point_type;

				$template    = $this->settings['templates']['limit'];
				$description = str_replace( '%limit%', $limit->label, $template );
				$remaining   = $limit->amount - $this->balances[ $point_type ]->total;
				$description = str_replace( '%left%', $type_object->format( $remaining ), $description );

				//$description = sprintf( _x( 'Maximum %s / %s', 'Transfer limit / period', 'bonips' ), $type_object->format( $limit->amount ), $limit->label );

			}

			return $description;

		}

		/**
		 * User is Over Limit
		 * Checks if the sender is over the transfer limit (if used).
		 * @since 1.8
		 * @version 1.0
		 */
		public function user_is_over_limit() {

			$over_limit     = false;
			$transfer_limit = $this->get_transfer_limit();

			if ( ! empty( $this->transferable_types ) ) {

				$type_count = count( $this->transferable_types );
				$unusable   = 0;

				// Lets run through our balances to see if we are over the limit on anyone
				foreach ( $this->balances as $type_id => $balance ) {

					$total      = 0;
					$over_limit = false;
					$limit      = $transfer_limit;
					$type       = $balance->point_type;

					// If a limit is to be enforced
					if ( $limit !== false ) {

						// Format the limit amount
						$limit->amount = $type->number( $limit->amount );

						// Query our total for the limit period
						$total      = $this->total_sent( $this->reference, $this->sender_id, $type_id, $limit->from, $limit->until );

						// Determine if we are over or not
						$over_limit = ( $total >= $limit->amount ) ? true : false;

					}

					$this->balances[ $type_id ]->total      = $type->number( $total );
					$this->balances[ $type_id ]->over_limit = $over_limit;

					// Populate limit
					$this->limits[ $type_id ]               = $limit;

					if ( $over_limit )
						$unusable ++;

				}

				if ( $unusable == $type_count )
					$over_limit = true;

			}

			return $over_limit;

		}

		/**
		 * Is Valid Transfer Request
		 * Checks if a given request is valid and can be executed.
		 * @since 1.8
		 * @version 1.0
		 */
		public function is_valid_transfer_request( $request = array(), $posted = array() ) {

			$this->valid_request   = false;
			$this->request         = shortcode_atts( apply_filters( 'bonips_new_transfer_request', array(
				'token'        => NULL,
				'recipient_id' => NULL,
				'user_id'      => 'current',
				'ctype'        => BONIPS_DEFAULT_TYPE_KEY,
				'amount'       => NULL,
				'reference'    => 'transfer',
				'message'      => isset( $posted['message'] ) ? $posted['message'] : ''
			), $posted ), $request );

			// Security
			if ( ! wp_verify_nonce( $this->request['token'], 'bonips-new-transfer-' . $this->request['reference'] ) )
				return 'error_1';

			// Reference
			$reference             = sanitize_key( $this->request['reference'] );
			if ( $reference == '' ) $reference = 'transfer';
			$this->reference       = $reference;

			// Make sure we are transfering an existing point type
			if ( ! bonips_point_type_exists( $this->request['ctype'] ) || ! in_array( $this->request['ctype'], $this->settings['types'] ) )
				return 'error_10';

			$this->point_type      = $this->request['ctype'];

			// Senders ID
			$sender_id             = get_current_user_id();
			if ( $this->request['user_id'] != 'current' && absint( $this->request['user_id'] ) > 0 && absint( $this->request['user_id'] ) != $user_id )
				$sender_id = $this->request['user_id'];

			$this->sender_id       = absint( $sender_id );

			// Transfer recipient
			$recipient_id          = bonips_get_transfer_recipient( sanitize_text_field( $this->request['recipient_id'] ) );
			if ( $recipient_id === false )
				return 'error_3';

			$this->recipient_id    = absint( $recipient_id );

			// We are trying to transfer to ourselves
			if ( $this->recipient_id == $this->sender_id )
				return 'error_4';

			$bonips                = bonips( $this->point_type );

			if ( $bonips->exclude_user( $this->sender_id ) )
				return 'error_4';

			// The recipient is excluded from the point type
			if ( $bonips->exclude_user( $this->recipient_id ) )
				return 'error_4';

			// Amount can not be zero
			$amount                = $bonips->number( abs( $this->request['amount'] ) );
			if ( $amount < $bonips->get_lowest_value() )
				return 'error_5';

			$balance               = $bonips->get_users_balance( $this->sender_id );
			$lowest_balance        = apply_filters( 'bonips_transfer_acc_limit', $bonips->zero(), $this->point_type, $this->sender_id, $this->reference );
			if ( ( $balance - $amount ) < $lowest_balance )
				return 'error_7';

			// Enforce transfer limit
			$transfer_limit        = $this->get_transfer_limit();
			if ( $transfer_limit !== false ) {

				// Format the limit amount
				$transfer_limit->amount = $bonips->number( $transfer_limit->amount );

				// Query our total for the limit period plus add the amount we want to transfer now
				$total                  = $this->total_sent( $this->reference, $this->sender_id, $this->point_type, $transfer_limit->from, $transfer_limit->until );
				$total                 += $amount;

				// Determine if we are over or not
				if ( $total > $transfer_limit->amount )
					return 'error_8';

			}

			$this->transfer_amount = $amount;

			// If messages are allowed, enforce a max length
			if ( $this->settings['message'] > 0 ) {
				$message       = sanitize_text_field( $this->request['message'] );
				$message       = substr( $message, 0, $this->settings['message'] );
				$this->message = $message;
			}

			$this->transfer_id     = $this->generate_new_transfer_id( $this->sender_id, $this->recipient_id );
			$this->data            = apply_filters( 'bonips_transfer_data', array(
				'ref_type' => 'user',
				'tid'      => $this->transfer_id,
				'message'  => $this->message
			), $this->transfer_id, $this->request, $this->settings );

			// Prevent Duplicate transactions
			if ( $bonips->has_entry( $this->reference, $this->recipient_id, $this->sender_id, $this->data, $this->point_type ) )
				return 'error_9';

			$this->balances[ $this->point_type ] = $balance;

			$this->valid_request = apply_filters( 'bonips_is_valid_transfer_request', true, $this );

			return $this->valid_request;

		}

		/**
		 * Total Sent
		 * @since 1.8
		 * @version 1.0
		 */
		public function total_sent( $reference = 'transfer', $user_id = false, $point_type = '', $period_start = 0, $period_end = 0 ) {

			if ( $user_id === false || $period_start <= 0 || $period_end <= 0 ) return 0;

			global $wpdb, $bonips_log_table;

			$total = $wpdb->get_var( $wpdb->prepare( "
				SELECT SUM( creds ) 
				FROM {$bonips_log_table} 
				WHERE user_id = %d 
					AND ref = %s 
					AND ctype = %s 
					AND time BETWEEN %d AND %d;", $user_id, $reference, $point_type, $period_start, $period_end ) );

			if ( $total === NULL ) $total = 0;

			$total = abs( $total );

			return apply_filters( 'bonips_get_total_transfered', $total, $reference, $user_id, $point_type, $period_start, $period_end );

		}

		/**
		 * New Transfer
		 * Attepmts to execute a transfer request that has been validated.
		 * @since 1.8
		 * @version 1.0
		 */
		public function new_transfer() {

			if ( ! $this->valid_request ) return false;

			$result                = false;
			$bonips                = bonips( $this->point_type );
			$this->transfer_charge = apply_filters( 'bonips_transfer_charge', $this->transfer_amount, $this );
			$attempt_check         = ( $this->balances[ $this->point_type ] - $this->transfer_charge );

			// Backwards comp.
			$request = array(
				'transaction_id' => $this->transfer_id,
				'sender_id'      => $this->sender_id,
				'recipient_id'   => $this->recipient_id,
				'reference'      => $this->reference,
				'charge'         => $this->transfer_charge,
				'payout'         => $this->transfer_payout,
				'point_type'     => $this->point_type,
				'data'           => $this->data
			);

			// Let others play before we execute the transfer
			do_action( 'bonips_transfer_ready', $this->transfer_id, $request, $this->settings );

			// First take the amount from the sender
			if ( $bonips->add_creds(
				$this->reference,
				$this->sender_id,
				0 - $this->transfer_charge,
				$this->settings['logs']['sending'],
				$this->recipient_id,
				$this->data,
				$this->point_type
			) ) {

				// Then add the amount to the receipient
				if ( ! $bonips->has_entry( $this->reference, $this->sender_id, $this->recipient_id, $this->data, $this->point_type ) ) {

					$this->transfer_payout = apply_filters( 'bonips_transfer_payout', $this->transfer_amount, $this );

					if ( $this->transfer_payout !== false )
						$bonips->add_creds(
							$this->reference,
							$this->recipient_id,
							$this->transfer_payout,
							$this->settings['logs']['receiving'],
							$this->sender_id,
							$this->data,
							$this->point_type
						);

					// Let others play once transaction is completed
					do_action( 'bonips_transfer_completed', $this->transfer_id, $request, $this->settings, $this );

					// Return the good news
					$result        = array(
						'amount'      => $this->transfer_charge,
						'css'         => '.bonips-balance-' . $this->point_type,
						'balance'     => $bonips->format_creds( $attempt_check ),
						'zero'        => ( ( $attempt_check <= $bonips->zero() ) ? true : false )
					);

				}

			}

			return apply_filters( 'bonips_new_transfer', $result, $this->request, $attempt_check, $this );

		}

		/**
		 * Refund
		 * Attempts to refund a transfer.
		 * @since 1.8
		 * @version 1.0
		 */
		public function refund() {

			// New or refunded transfers can not be refunded.
			if ( in_array( $transfer->status, array( '', 'refunded' ) ) ) return false;



			return true;

		}

		/**
		 * Get Transfer Form
		 * Renders the transfer form based on our setup.
		 * @since 1.8
		 * @version 1.0
		 */
		public function get_transfer_form( $args = array() ) {

			$this->args = shortcode_atts( array(
				'button'          => '',
				'button_class'    => 'btn btn-primary btn-block btn-lg',
				'show_balance'    => 0,
				'show_limit'      => 0,
				'show_message'    => 1,
				'placeholder'     => '',
				'recipient_label' => __( 'Empfänger', 'bonips' ),
				'amount_label'    => __( 'Betrag', 'bonips' ),
				'balance_label'   => __( 'Guthaben', 'bonips' ),
				'message_label'   => __( 'Nachricht', 'bonips' )
			), $args );

			if ( $this->args['button'] == '' )
				$this->args['button'] = $this->settings['templates']['button'];

			ob_start();

?>
<div class="bonips-transfer-cred-wrapper"<?php if ( $this->reference != '' ) echo ' id="transfer-form-' . esc_attr( $this->reference ) . '"'; ?>>
	<form class="form bonips-transfer bonips-transfer-form" id="bonips-transfer-form-<?php echo esc_attr( $this->reference ); ?>" method="post" data-ref="<?php echo esc_attr( $this->reference ); ?>" action="">

		<?php do_action( 'bonips_transfer_form_start', $this->args, $this->settings ); ?>

		<div class="row">

			<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">

				<?php $this->get_transfer_recipient_field(); ?>

			</div>

			<?php $this->get_transfer_points_field(); ?>

		</div>

		<?php $this->get_transfer_message_field(); ?>

		<?php $this->get_transfer_extra_fields(); ?>

		<div class="row">

			<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
				<input type="hidden" name="bonips_new_transfer[token]" value="<?php echo wp_create_nonce( 'bonips-new-transfer-' . $this->reference ); ?>" />
				<input type="hidden" name="bonips_new_transfer[reference]" value="<?php echo esc_attr( $this->reference ); ?>" />
				<input type="submit" class="bonips-submit-transfer<?php echo ' ' . esc_attr( $this->args['button_class'] ); ?>" value="<?php echo esc_attr( $this->args['button'] ); ?>" />
			</div>

		</div>

		<?php do_action( 'bonips_transfer_form_end', $this->args, $this->settings ); ?>

	</form>
</div>
<?php

			$output = ob_get_contents();
			ob_end_clean();

			return do_shortcode( apply_filters( 'bonips_transfer_render', $output, $this->args, $this ) );

		}

		/**
		 * Get Transfer Recipient Field
		 * @since 1.8
		 * @version 1.0
		 */
		public function get_transfer_recipient_field( $return = false ) {

			// If recipient is set, pre-populate it with the recipients details
			$recipients = array();
			if ( $this->recipient_id !== false ) {

				if ( is_array( $this->recipient_id ) ) {

					foreach ( $this->recipient_id as $user_id ) {

						$user = get_userdata( $user_id );
						if ( ! isset( $user->ID ) ) continue;
						$recipients[ $user_id ] = $user;

					}

				}

				elseif ( is_numeric( $this->recipient_id ) ) {

					$user = get_userdata( $this->recipient_id );
					if ( isset( $user->ID ) )
						$recipients[ $this->recipient_id ] = $user;

				}

			}

			$placeholder = $this->args['placeholder'];
			if ( $this->args['placeholder'] == '' ) {

				if ( $this->settings['autofill'] == 'user_login' )
					$placeholder = __( 'Nutzername', 'bonips' );

				elseif ( $this->settings['autofill'] == 'user_email' )
					$placeholder = __( 'Email', 'bonips' );

				$placeholder = sprintf( apply_filters( 'bonips_transfer_to_placeholder', __( 'Empfänger %s', 'bonips' ), $this->settings, $this->args ), $placeholder );

			}

			$field = '<div class="form-group select-recipient-wrapper">';
			if ( $this->args['recipient_label'] != '' ) $field .= '<label>' . esc_html( $this->args['recipient_label'] ) . '</label>';

			// No recipient, one needs to be nominated
			if ( count( $recipients ) < 1 ) {
				$field .= '<input type="text" name="bonips_new_transfer[recipient_id]" value="" aria-required="true" class="bonips-autofill form-control" data-form="' . esc_attr( $this->reference ) . '" placeholder="' . $placeholder . '" />';
			}

			// One specific recipient is set
			elseif ( count( $recipients ) == 1 ) {

				$first_user = $recipients;
				$fist_user  = reset( $first_user );

				$value      = $fist_user->display_name;
				$autofill   = $this->settings['autofill'];
				if ( isset( $fist_user->$autofill ) )
					$value = $fist_user->$autofill;

				$field     .= '<p class="form-control-static">' . $value . '</p><input type="hidden" name="bonips_new_transfer[recipient_id]" value="' . esc_attr( $fist_user->ID ) . '" />';

			}

			// Multiple nominated recipients
			elseif ( count( $recipients ) > 1 ) {

				$field .= '<select name="bonips_new_transfer[recipient_id]" class="form-control">';
				foreach ( $recipients as $user_id => $user ) {

					$value    = $user->display_name;
					$autofill = $this->settings['autofill'];
					if ( isset( $user->$autofill ) )
						$value = $user->$autofill;

					$field .= '<option value="' . $user_id . '">' . $value . '</option>';

				}
				$field .= '</select>';

			}

			$field .= '</div>';

			$field  = apply_filters( 'bonips_transfer_to_field', $field, $this->settings, $this->args );

			if ( $return )
				return $field;

			echo $field;

		}

		/**
		 * Get Transfer Points Field
		 * @since 1.8
		 * @version 1.0
		 */
		public function get_transfer_points_field( $return = false ) {

			// Transfer of one particular point type
			if ( count( $this->transferable_types ) == 1 ) {

				$field  = '<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">';
				$field .= $this->get_transfer_amount_field( true );
				$field .= $this->get_transfer_point_type_field( true );
				$field .= '</div>';

			}

			// Select which point type to transfer
			else {

				$field  = '<div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">';
				$field .= $this->get_transfer_amount_field( true );
				$field .= '</div>';

				$field .= '<div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">';
				$field .= $this->get_transfer_point_type_field( true );
				$field .= '</div>';

			}

			if ( $return )
				return $field;

			echo $field;

		}

		/**
		 * Get Transfer Amount Field
		 * @since 1.8
		 * @version 1.0
		 */
		public function get_transfer_amount_field( $return = false ) {

			$type_id    = $this->transferable_types[0];
			$balance    = $this->balances[ $type_id ];
			$point_type = $balance->point_type;

			$field      = '<div class="form-group select-amount-wrapper">';
			if ( $this->args['amount_label'] != '' ) $field .= '<label>' . esc_html( $this->args['amount_label'] ) . '</label>';

			// User needs to nominate the amount
			if ( ! is_array( $this->transfer_amount ) && $this->transfer_amount == 0 )
				$field .= '<input type="text" name="bonips_new_transfer[amount]" placeholder="' . esc_attr( $balance->minimum ) . '" class="form-control" value="' . esc_attr( $balance->minimum ) . '" />';

			// Multiple amounts to pick from
			elseif ( is_array( $this->transfer_amount ) && count( $this->transfer_amount ) > 1 ) {

				$field .= '<select name="bonips_new_transfer[amount]" class="form-control">';

				foreach ( $this->transfer_amount as $amount )
					$field .= '<option value="' . esc_attr( $amount ) . '">' . esc_attr( $amount ) . '</option>';

				$field .= '</select>';

			}

			// An amount has been nominated
			else {

				$field .= '<input type="hidden" name="bonips_new_transfer[amount]" value="' . esc_attr( $this->transfer_amount ) . '" />';
				$field .= '<p class="form-control-static">' . esc_attr( $this->transfer_amount ) . '</p>';

			}

			$field .= '</div>';

			$field  = apply_filters( 'bonips_transfer_form_amount', $field, $this->args, $this->settings );

			if ( $return )
				return $field;

			echo $field;

		}

		/**
		 * Get Transfer Point Type Field
		 * @since 1.8
		 * @version 1.0
		 */
		public function get_transfer_point_type_field( $return = false ) {

			$field = '<input type="hidden" name="bonips_new_transfer[ctype]" value="' . esc_attr( $this->transferable_types[0] ) . '" />';

			if ( count( $this->transferable_types ) > 1 ) {

				$field  = '<div class="form-group select-point-type-wrapper">';

				if ( $this->args['balance_label'] != '' ) $field .= '<label>' . esc_html( $this->args['balance_label'] ) . '</label>';
				$field .= '<select name="bonips_new_transfer[ctype]" class="form-control">';

				foreach ( $this->balances as $type_id => $balance )
					$field .= '<option value="' . esc_attr( $type_id ) . '">' . $balance->point_type->plural . '</option>';

				$field .= '</select></div>';

			}

			if ( $return )
				return $field;

			echo $field;

		}

		/**
		 * Get Transfer Message Field
		 * @since 1.8
		 * @version 1.0
		 */
		public function get_transfer_message_field( $return = false ) {

			$field = '';

			if ( (bool) $this->args['show_message'] && $this->settings['message'] > 0 ) {

				$field = '<div class="form-group message-wrapper">';
				if ( $this->args['message_label'] != '' ) $field .= '<label>' . esc_html( $this->args['message_label'] ) . '</label>';

				$field .= '<input type="text" class="form-control" name="message" value="" />';

				$field .= '</div>';

			}

			$field = apply_filters( 'bonips_transfer_form_message', $field, $this->args, $this->settings );

			if ( $field != '' )
				$field = '<div class="row"><div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">' . $field . '</div></div>';

			if ( $return )
				return $field;

			echo $field;

		}

		/**
		 * Get Transfer Extra Fields
		 * @since 1.8
		 * @version 1.0
		 */
		public function get_transfer_extra_fields( $return = false ) {

			// Show Balance 
			$extras = array();
			if ( (bool) $this->args['show_balance'] && ! empty( $this->settings['templates']['balance'] ) ) {

				foreach ( $this->balances as $type_id => $balance ) {

					$template = $this->settings['templates']['balance'];

					$template = str_replace( '%balance%', $balance->point_type->format( $balance->current ), $template );
					$template = str_replace( '%singular%', $balance->point_type->singular, $template );
					$template = str_replace( '%plural%', $balance->point_type->plural, $template );

					$extras[] = $template;

				}

			}

			// Show Limits
			if ( (bool) $this->args['show_limit'] && ! empty( $this->settings['templates']['limit'] ) && $this->settings['limit']['limit'] != 'none' && count( $this->balances ) == 1 ) {

				foreach ( $this->balances as $type_id => $balance ) {

					$extras[] = $this->get_transfer_limit_desc( $type_id );

				}

			}

			$field = '';

			// Show extras
			if ( ! empty( $extras ) ) {

				$extras_count = count( $extras );
				$column_class = 'col-lg-12 col-md-12 col-sm-12 col-xs-12';
				if ( $extras_count == 2 )
					$column_class = 'col-lg-6 col-md-6 col-sm-12 col-xs-12';
				elseif ( $extras_count == 3 )
					$column_class = 'col-lg-4 col-md-4 col-sm-12 col-xs-12';
				elseif ( $extras_count == 4 )
					$column_class = 'col-lg-3 col-md-3 col-sm-12 col-xs-12';
				elseif ( $extras_count > 4 )
					$column_class = 'col-lg-2 col-md-2 col-sm-12 col-xs-12';

				$field .= '<div class="row">';
				foreach ( $extras as $extra_content ) {
					$field .= '<div class="' . $column_class . '">';
					$field .= do_shortcode( $extra_content );
					$field .= '</div>';
				}
				$field .= '</div>';

			}

			$field = apply_filters( 'bonips_transfer_form_extra', $field, $this->args, $this->settings );

			if ( $return )
				return $field;

			echo $field;

		}

		/**
		 * Get Error Message
		 * @since 1.8
		 * @version 1.0
		 */
		public function get_error_message( $return = false ) {

			$content = '';
			if ( ! empty( $this->errors ) ) {
				foreach ( $this->errors as $error_code => $message ) {

					$content = '<div class="alert alert-warning error-' . $error_code . '"><p>' . $message . '</p></div>';

				}
			}

			$content = apply_filters( 'bonips_transfer_form_errors', $content, $this );

			if ( $return )
				return $content;

			echo $content;

		}

	}
endif;
