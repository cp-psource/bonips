<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * Events Manager Pro Gateway
 * @since 1.3
 * @version 1.1
 */
if ( ! class_exists( 'EM_Gateway_boniPS' ) ) {
	class EM_Gateway_boniPS extends EM_Gateway {

		public $gateway = 'bonips';
		public $title = '';
		public $status = 1;
		public $status_txt = '';
		public $bonips_type = 'bonips_default';
		public $button_enabled = true;
		public $supports_multiple_bookings = true;

		public $registered_timer = 0;

		/**
		 * Construct 
		 */
		function __construct() {
			// Default settings
			$defaults = array(
				'setup'    => 'off',
				'type'     => 'bonips_default',
				'rate'     => 100,
				'share'    => 0,
				'log'      => array(
					'purchase' => __( 'Payment for tickets to %link_with_title%', 'bonips' ),
					'refund'   => __( 'Ticket refund for %link_with_title%', 'bonips' )
				),
				'refund'   => 0,
				'labels'   => array(
					'header' => __( 'Pay using your %_plural% balance', 'bonips' ),
					'button' => __( 'Pay Now', 'bonips' ),
					'link'   => __( 'Pay', 'bonips' )
				),
				'messages' => array(
					'success' => __( 'Thank you for your payment!', 'bonips' ),
					'error'   => __( "I'm sorry but you can not pay for these tickets using %_plural%", 'bonips' )
				)
			);

			// Settings
			$settings = get_option( 'bonips_eventsmanager_gateway_prefs' );
			$this->prefs = bonips_apply_defaults( $defaults, $settings );

			$this->bonips_type = $this->prefs['type'];

			// Load boniPS
			$this->core = bonips( $this->bonips_type );
			
			// Apply Whitelabeling
			$this->label = bonips_label();
			$this->title = strip_tags( $this->label );
			$this->status_txt = 'Paid using ' . strip_tags( $this->label );

			parent::__construct();
			
			if ( !$this->is_active() ) return;

			// Currency
			add_filter( 'em_get_currencies',               array( $this, 'add_currency' ) );
			if ( $this->single_currency() )
				add_filter( 'em_get_currency_formatted',   array( $this, 'format_price' ), 10, 4 );

			// Adjust Ticket Columns
			add_filter( 'em_booking_form_tickets_cols',       array( $this, 'ticket_columns' ), 10, 2 );
			add_action( 'em_booking_form_tickets_col_bonips', array( $this, 'ticket_col' ), 10, 2     );
			add_filter( 'em_bookings_table_cols_col_action', array( $this, 'bookings_table_actions' ), 10, 2 );
			
			// Refund
			if ( $this->prefs['refund'] != 0 )
				add_filter( 'em_booking_set_status', array( $this, 'refunds' ), 10, 2 );
		}
		
		/**
		 * Add Currency
		 * Adds "Points" as a form of currency
		 * @since 1.3
		 * @version 1.0
		 */
		public function add_currency( $currencies ) {
			$currencies->names['XMY'] = $this->core->plural();
			if ( empty( $this->core->before ) && !empty( $this->core->after ) ) {
				$currencies->symbols['XMY'] = $this->core->after;
				$currencies->symbols['XMY'] = $this->core->after;
			}
			elseif ( !empty( $this->core->before ) && empty( $this->core->after ) ) {
				$currencies->true_symbols['XMY'] = $this->core->before;
				$currencies->true_symbols['XMY'] = $this->core->after;
			}

			return $currencies;
		}
		
		/**
		 * Check if using Single Currency
		 * @since 1.3
		 * @version 1.0
		 */
		public function single_currency() {
			if ( $this->prefs['setup'] == 'single' ) return true;
			return false;
		}

		/**
		 * Format Price
		 * @since 1.3
		 * @version 1.0
		 */
		public function format_price( $formatted_price, $price, $currency, $format ) {
			if ( $currency == 'XMY' )
				return $this->core->format_creds( $price );
			else
				return $formatted_price;
		}
		
		/**
		 * Adjust Ticket Columns
		 * @since 1.3
		 * @version 1.0
		 */
		public function ticket_columns( $columns, $EM_Event ) {
			if ( ! $EM_Event->is_free() ) {
				unset( $columns['price'] );
				unset( $columns['type'] );
				unset( $columns['spaces'] );

				$columns['type'] = __( 'Ticket Type', 'bonips' );

				if ( $this->single_currency() ) {
					$columns['bonips'] = __( 'Price', 'bonips' );
				}
				else {
					$columns['price'] = __( 'Price', 'bonips' );
					$columns['bonips'] = $this->core->plural();
				}
				$columns['spaces'] = __( 'Spaces', 'bonips' );
			}

			$this->booking_cols = count( $columns );
			return $columns;
		}

		/**
		 * Adjust Ticket Column Content
		 * @since 1.3
		 * @version 1.0
		 */
		public function ticket_col( $EM_Ticket, $EM_Event ) {
			$ticket_price = $EM_Ticket->get_price( false );
			if ( $this->single_currency() )
				$price = $ticket_price;
			else
				$price = $this->prefs['rate']*$ticket_price;
			
			if ( empty( $ticket_price ) )
				$price = 0; ?>

<td class="em-bookings-ticket-table-points"><?php echo $this->core->format_creds( $price ); ?></td>
<?php
		}
		
		/**
		 * Shows button, not needed if using the new form display
		 * @since 1.3
		 * @version 1.0
		 */
		function booking_form_button() {
			if ( ! is_user_logged_in() ) return;
			$user_id = get_current_user_id();
			
			// Check for exclusion
			if ( $this->core->exclude_user( $user_id ) ) return;
			
			// Make sure we have points
			if ( $this->core->get_users_cred( $user_id, $this->bonips_type ) <= $this->core->format_number( 0 ) ) return;
			
			$button = get_option( 'em_'. $this->gateway . '_button', $this->title );
			ob_start();
			if ( preg_match( '/https?:\/\//', $button ) ) { ?>
			<input type="image" class="em-booking-submit em-gateway-button" id="em-gateway-button-<?php echo $this->gateway; ?>" src="<?php echo $button; ?>" alt="<?php echo $this->title; ?>" />
<?php } else { ?>
			<input type="submit" class="em-booking-submit em-gateway-button" id="em-gateway-button-<?php echo $this->gateway; ?>" value="<?php echo $button; ?>" />
<?php		}
			$output = ob_get_contents();
			ob_end_clean();
			
			return $output;
		}
		
		/**
		 * Add Booking
		 * @since 1.3
		 * @version 1.0
		 */
		function booking_add( $EM_Event, $EM_Booking, $post_validation = false ) {
			parent::booking_add( $EM_Event, $EM_Booking, $post_validation );
			if ( $post_validation && empty( $EM_Booking->booking_id ) ) {
				if ( get_option( 'dbem_multiple_bookings' ) && get_class( $EM_Booking ) == 'EM_Multiple_Booking' ) {
		    		add_filter( 'em_multiple_booking_save', array( &$this, 'booking_payment' ), 2, 2 );			    
				} else {
		    		add_filter( 'em_booking_save', array( &$this, 'booking_payment' ), 2, 2 );
				}
			}
		}
		
		/**
		 * Booking Payment
		 * @since 1.3
		 * @version 1.1
		 */
		function booking_payment( $result, $EM_Booking ) {
			global $wpdb, $wp_rewrite, $EM_Notices;
			//make sure booking save was successful before we try anything
			if ( $result ) {
				// Event is not free
				if ( $EM_Booking->get_price() > 0 ) {
					$ok = true;
					
					// User is excluded from using this gateway
					if ( $this->core->exclude_user( $EM_Booking->person->ID ) ) {
						$EM_Booking->add_error( __( 'You can not pay using this gateway.', 'bonips' ) );
						$ok = false;
					}
					// User can not afford to pay
					elseif ( ! $this->can_pay( $EM_Booking ) ) {
						$EM_Booking->add_error( $this->core->template_tags_general( $this->prefs['messages']['error'] ) );
						$ok = false;
					}
					// User has not yet paid (prefered)
					elseif ( ! $this->has_paid( $EM_Booking ) ) {
						// Price
						$price = $this->core->number( $EM_Booking->booking_price );
						if ( ! $this->single_currency() ) {
							$exchange_rate = $this->prefs['rate'];
							$price = $this->core->number( $exchange_rate*$price );
						}

						// Charge
						$this->core->add_creds(
							'ticket_purchase',
							$EM_Booking->person->ID,
							0-$price,
							$this->prefs['log']['purchase'],
							$EM_Booking->event->post_id,
							array( 'ref_type' => 'post', 'bid' => (int) $EM_Booking->booking_id ),
							$this->bonips_type
						);
						
						// Log transaction with EM
						$transaction_id = time() . $EM_Booking->person->ID;
						$EM_Booking->booking_meta[ $this->gateway ] = array( 'txn_id' => $transaction_id, 'amount' => $price );
						$this->record_transaction( $EM_Booking, $EM_Booking->get_price( false, false, true ), get_option( 'dbem_bookings_currency' ), current_time( 'mysql' ), $transaction_id, 'Completed', '' );
						
						// Profit sharing
						if ( $this->prefs['share'] != 0 ) {
							$event_post = get_post( (int) $EM_Booking->event->post_id );
							if ( $event_post !== NULL ) {
								$share = ( $this->prefs['share']/100 ) * $price;
								$this->core->add_creds(
									'ticket_sale',
									$event_post->post_author,
									$share,
									$this->prefs['log']['purchase'],
									$event_post->ID,
									array( 'ref_type' => 'post', 'bid' => (int) $EM_Booking->booking_id ),
									$this->bonips_type
								);
							}
						}
					}
					// Something went horribly wrong
					else {
						$ok = false;
					}
			
					// Successfull Payment
					if ( $ok ) {
						if ( ! get_option( 'em_' . $this->gateway . '_manual_approval', false ) || ! get_option( 'dbem_bookings_approval' ) ) {
							$EM_Booking->set_status( 1, false ); //Approve
						} else {
							$EM_Booking->set_status( 0, false ); //Set back to normal "pending"
						}
					}
					// Error in payment - delete booking
					else {
						// Delete any user that got registered for this event
						if ( ! is_user_logged_in() && get_option( 'dbem_bookings_anonymous' ) && !get_option( 'dbem_bookings_registration_disable' ) && ! empty( $EM_Booking->person_id ) ) {
							$EM_Person = $EM_Booking->get_person();
							if ( strtotime( $EM_Person->data->user_registered ) >= $this->registered_timer ) {
								if ( is_multisite() ) {
									include_once( ABSPATH . '/wp-admin/includes/ms.php' );
									wpmu_delete_user( $EM_Person->ID );
								} else {
									include_once( ABSPATH . '/wp-admin/includes/user.php' );
									wp_delete_user( $EM_Person->ID );
								}

								// remove email confirmation
								global $EM_Notices;
								$EM_Notices->notices['confirms'] = array();
							}
						}
						
						// Delete booking
						$EM_Booking->delete();
						return false;
					}
				}
			}
			return $result;
		}
		
		/**
		 * Refunds
		 * @since 1.3
		 * @version 1.1
		 */
		public function refunds( $result, $EM_Booking ) {
			// Cancellation = refund
			if ( $EM_Booking->booking_status == 3 && $EM_Booking->previous_status == 1 && $this->prefs['refund'] > 0 ) {

				// Make sure user has paid for this to refund
				if ( $this->has_paid( $EM_Booking ) ) {

					// Price
					if ( $this->single_currency() )
						$price = $this->core->number( $EM_Booking->booking_price );
					else
						$price = $this->core->number( $this->prefs['rate']*$EM_Booking->booking_price );
					
					// Refund
					if ( $this->prefs['refund'] != 100 )
						$refund = ( $this->prefs['refund'] / 100 ) * $price;
					else
						$refund = $price;
				
					// Charge
					$this->core->add_creds(
						'ticket_purchase_refund',
						$EM_Booking->person->ID,
						$refund,
						$this->prefs['log']['refund'],
						$EM_Booking->event->post_id,
						array( 'ref_type' => 'post', 'bid' => (int) $booking_id ),
						$this->bonips_type
					);
				}
			}
			return $result;
		}
		
		/**
		 * Customize Booking Table Actions
		 * @since 1.3
		 * @version 1.0
		 */
		function bookings_table_actions( $actions, $EM_Booking ) {
			if ( $EM_Booking->booking_status == 1 && $this->uses_gateway( $EM_Booking ) ) {
				return array(
					'reject'    => '<a class="em-bookings-reject" href="' . em_add_get_params( $url, array(
						'action'     => 'bookings_reject',
						'booking_id' => $EM_Booking->booking_id
					) ) . '">' . __( 'Reject', 'bonips' ) . '</a>',
					'delete'    => '<span class="trash"><a class="em-bookings-delete" href="' . em_add_get_params( $url, array(
						'action'     => 'bookings_delete',
						'booking_id' => $EM_Booking->booking_id
					) ) . '">' . __( 'Delete', 'bonips' ) . '</a></span>',
					'edit'      => '<a class="em-bookings-edit" href="' . em_add_get_params( $EM_Booking->get_event()->get_bookings_url(), array(
						'booking_id' => $EM_Booking->booking_id,
						'em_ajax'    => null,
						'em_obj'     => null
					) ) . '">' . __( 'Edit/View', 'bonips' ) . '</a>'
				);
			}
			
			return $actions;
		}
		
		/**
		 * Can Pay Check
		 * Checks if the user can pay for their booking.
		 * @since 1.2
		 * @version 1.1
		 */
		public function can_pay( $EM_Booking ) {
			$EM_Event = $EM_Booking->get_event();
			// You cant pay for free events
			if ( $EM_Event->is_free() ) return false;

			$balance = $this->core->get_users_cred( $EM_Booking->person->ID, $this->bonips_type );
			if ( $balance <= $this->core->zero() ) return false;

			$price = $this->core->number( $EM_Booking->booking_price );
			if ( $price == $this->core->zero() ) return true;
			if ( ! $this->single_currency() ) {
				$exchange_rate = $this->prefs['rate'];
				$price = $this->core->number( $exchange_rate*$price );
			}

			if ( $balance-$price < $this->core->zero() ) return false;

			return true;
		}
		
		/**
		 * Has Paid
		 * Checks if the user has paid for booking
		 * @since 1.3
		 * @version 1.2
		 */
		public function has_paid( $EM_Booking ) {
			if ( $this->core->has_entry(
				'ticket_purchase',
				$EM_Booking->event->post_id,
				$EM_Booking->person->ID,
				array(
					'ref_type' => 'post',
					'bid'      => (int) $EM_Booking->booking_id
				),
				$this->bonips_type
			) )
				return true;

			return false;
		}
		
		/**
		 * Getway Settings
		 * @since 1.3
		 * @version 1.2
		 */
		function mysettings() {
			global $page, $action;
			$gateway_link = admin_url( 'edit.php?post_type=' . EM_POST_TYPE_EVENT . '&page=events-manager-options#bookings' );
			
			if ( $this->prefs['setup'] == 'multi' )
				$box = 'display: block;';
			else
				$box = 'display: none;';

			$exchange_message = sprintf(
				__( 'How many %s is 1 %s worth?', 'bonips' ),
				$this->core->plural(),
				em_get_currency_symbol()
			);

			$bonips_types = bonips_get_types();

			do_action( 'bonips_em_before_settings', $this ); ?>

<h4><?php _e( 'Setup', 'bonips' ); ?></h4>
<table class="form-table">
	<?php if ( count( $bonips_types ) > 1 ) : ?>

	<tr>
		<th scope="row"><?php _e( 'Point Type', 'bonips' ); ?></th>
		<td>
			<?php bonips_types_select_from_dropdown( 'bonips_gateway[type]', 'bonips-gateway-type', $this->prefs['type'] ); ?>

		</td>
	</tr>
	<?php else : ?>

	<input type="hidden" name="bonips_gateway[type]" value="bonips_default" />
	<?php endif; ?>

	<tr>
		<th scope="row"><?php _e( 'Payments', 'bonips' ); ?></th>
		<td>
			<input type="radio" name="bonips_gateway[setup]" id="bonips-gateway-setup-off" value="off"<?php checked( $this->prefs['setup'], 'off' ); ?> /> <label for="bonips-gateway-setup-off"><?php echo $this->core->template_tags_general( __( 'Disabled - Users CAN NOT pay for tickets using %plural%.', 'bonips' ) ); ?></label><br />
			<input type="radio" name="bonips_gateway[setup]" id="bonips-gateway-setup-single" value="single"<?php checked( $this->prefs['setup'], 'single' ); ?> /> <label for="bonips-gateway-setup-single"><?php echo $this->core->template_tags_general( __( 'Single - Users can ONLY pay for tickets using %plural%.', 'bonips' ) ); ?></label><br />
			<input type="radio" name="bonips_gateway[setup]" id="bonips-gateway-setup-multi" value="multi"<?php checked( $this->prefs['setup'], 'multi' ); ?> /> <label for="bonips-gateway-setup-multi"><?php echo $this->core->template_tags_general( __( 'Multi - Users can pay for tickets using other gateways or %plural%.', 'bonips' ) ); ?></label>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php _e( 'Refunds', 'bonips' ); ?></th>
		<td>
			<input name="bonips_gateway[refund]" type="text" id="bonips-gateway-log-refund" value="<?php echo $this->prefs['refund']; ?>" size="5" /> %<br />
			<span class="description"><?php _e( 'The percentage of the paid amount to refund if a user cancels their booking. Use zero for no refunds. No refunds are given to "Rejected" bookings!', 'bonips' ); ?></span>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php _e( 'Profit Sharing', 'bonips' ); ?></th>
		<td>
			<input name="bonips_gateway[share]" type="text" id="bonips-gateway-profit-sharing" value="<?php echo $this->prefs['share']; ?>" size="5" /> %<br />
			<span class="description"><?php _e( 'Option to share sales with the product owner. Use zero to disable.', 'bonips' ); ?></span>
		</td>
	</tr>
</table>
<table class="form-table" id="bonips-exchange-rate" style="<?php echo $box; ?>">
	<tr>
		<th scope="row"><?php _e( 'Exchange Rate', 'bonips' ); ?></th>
		<td>
			<input name="bonips_gateway[rate]" type="text" id="bonips-gateway-rate" size="6" value="<?php echo $this->prefs['rate']; ?>" /><br />
			<span class="description"><?php echo $exchange_message; ?></span>
		</td>
	</tr>
</table>
<h4><?php _e( 'Protokollvorlages', 'bonips' ); ?></h4>
<table class="form-table">
	<tr>
		<th scope="row"><?php _e( 'Purchases', 'bonips' ); ?></th>
		<td>
			<input name="bonips_gateway[log][purchase]" type="text" id="bonips-gateway-log-purchase" style="width: 95%;" value="<?php echo $this->prefs['log']['purchase']; ?>" size="45" /><br />
			<span class="description"><?php echo $this->core->available_template_tags( array( 'general', 'post' ) ); ?>></span>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php _e( 'Refunds', 'bonips' ); ?></th>
		<td>
			<input name="bonips_gateway[log][refund]" type="text" id="bonips-gateway-log-refund" style="width: 95%;" value="<?php echo $this->prefs['log']['refund']; ?>" size="45" /><br />
			<span class="description"><?php echo $this->core->available_template_tags( array( 'general', 'post' ) ); ?></span>
		</td>
	</tr>
</table>
<script type="text/javascript">
jQuery(function($){
	$('input[name="bonips_gateway[setup]"]').change(function(){
		if ( $(this).val() == 'multi' ) {
			$('#bonips-exchange-rate').show();
		}
		else {
			$('#bonips-exchange-rate').hide();
		}
	});
});
</script>
<h4><?php _e( 'Labels', 'bonips' ); ?></h4>
<table class="form-table">
	<tr valign="top">
		<th scope="row"><?php _e( 'Payment Link Label', 'bonips' ); ?></th>
		<td>
			<input name="bonips_gateway[labels][link]" type="text" id="bonips-gateway-labels-link" style="width: 95%" value="<?php echo $this->prefs['labels']['link']; ?>" size="45" /><br />
			<span class="description"><?php _e( 'The payment link shows / hides the payment form under "My Bookings". No HTML allowed.', 'bonips' ); ?></span>
		</td>
	</tr>
	<tr valign="top">
		<th scope="row"><?php _e( 'Payment Header', 'bonips' ); ?></th>
		<td>
			<input name="bonips_gateway[labels][header]" type="text" id="bonips-gateway-labels-header" style="width: 95%" value="<?php echo $this->prefs['labels']['header']; ?>" size="45" /><br />
			<span class="description"><?php _e( 'Shown on top of the payment form. No HTML allowed.', 'bonips' ); ?></span>
		</td>
	</tr>
	<tr valign="top">
		<th scope="row"><?php _e( 'Button Label', 'bonips' ); ?></th>
		<td>
			<input name="bonips_gateway[labels][button]" type="text" id="bonips-gateway-labels-button" style="width: 95%" value="<?php echo $this->prefs['labels']['button']; ?>" size="45" /><br />
			<span class="description"><?php _e( 'The button label for payments. No HTML allowed!', 'bonips' ); ?></span>
		</td>
	</tr>
</table>
<h4><?php _e( 'Messages', 'bonips' ); ?></h4>
<table class='form-table'>
	<tr valign="top">
		<th scope="row"><?php _e( 'Successful Payments', 'bonips' ); ?></th>
		<td>
			<input type="text" name="bonips_gateway[messages][success]" id="bonips-gateway-messages-success" style="width: 95%;" value="<?php echo stripslashes( $this->prefs['messages']['success'] ); ?>" /><br />
			<span class="description"><?php _e( 'No HTML allowed!', 'bonips' ); ?><br /><?php echo $this->core->available_template_tags( array( 'general' ) ); ?></span>
		</td>
	</tr>
	<tr valign="top">
		<th scope="row"><?php _e( 'Insufficient Funds', 'bonips' ); ?></th>
		<td>
			<input type="text" name="bonips_gateway[messages][error]" id="bonips-gateway-messages-error" style="width: 95%;" value="<?php echo stripslashes( $this->prefs['messages']['error'] ); ?>" /><br />
			<span class="description"><?php _e( 'No HTML allowed!', 'bonips' ); ?><br /><?php echo $this->core->available_template_tags( array( 'general' ) ); ?></span>
		</td>
	</tr>
</table>
<?php		do_action( 'bonips_em_after_settings', $this );
		}
		
		/**
		 * Update Getway Settings
		 * @since 1.3
		 * @version 1.1
		 */
		function update() {
			parent::update();
			if ( ! isset( $_POST['bonips_gateway'] ) || ! is_array( $_POST['bonips_gateway'] ) ) return;

			// Prep
			$data = $_POST['bonips_gateway'];
			$new_settings = array();

			// Setup
			$new_settings['setup'] = $data['setup'];
			$new_settings['type'] = sanitize_text_field( $data['type'] );
			$new_settings['refund'] = abs( $data['refund'] );
			$new_settings['share'] = abs( $data['share'] );

			// Logs
			$new_settings['log']['purchase'] = trim( stripslashes( $data['log']['purchase'] ) );
			$new_settings['log']['refund'] = trim( stripslashes( $data['log']['refund'] ) );
			
			if ( $new_settings['setup'] == 'multi' )
				$new_settings['rate'] = sanitize_text_field( $data['rate'] );
			else
				$new_settings['rate'] = $this->prefs['rate'];

			// Override Pricing Options
			if ( $new_settings['setup'] == 'single' ) {
				update_option( 'dbem_bookings_currency_decimal_point', $this->core->format['separators']['decimal'] );
				update_option( 'dbem_bookings_currency_thousands_sep', $this->core->format['separators']['thousand'] );
				update_option( 'dbem_bookings_currency', 'XMY' );
				if ( empty( $this->core->before ) && !empty( $this->core->after ) )
					$format = '@ #';
				elseif ( !empty( $this->core->before ) && empty( $this->core->after ) )
					$format = '# @';
				update_option( 'dbem_bookings_currency_format', $format );
			}

			// Labels
			$new_settings['labels']['link'] = sanitize_text_field( stripslashes( $data['labels']['link'] ) );
			$new_settings['labels']['header'] = sanitize_text_field( stripslashes( $data['labels']['header'] ) );
			$new_settings['labels']['button'] = sanitize_text_field( stripslashes( $data['labels']['button'] ) );

			// Messages
			$new_settings['messages']['success'] = sanitize_text_field( stripslashes( $data['messages']['success'] ) );
			$new_settings['messages']['error'] = sanitize_text_field( stripslashes( $data['messages']['error'] ) );

			// Save Settings
			$current = $this->prefs;
			$this->prefs = bonips_apply_defaults( $current, $new_settings );
			update_option( 'bonips_eventsmanager_gateway_prefs', $this->prefs );

			// Let others play
			do_action( 'bonips_em_save_settings', $this );

			//default action is to return true
			return true;
		}
	}
}
?>