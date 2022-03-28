<?php
/**
 * boniPS Bank Service - Recurring Payouts
 * @since 1.2
 * @version 1.1
 */
if ( !defined( 'boniPS_VERSION' ) ) exit;

if ( !class_exists( 'boniPS_Banking_Service_Payouts' ) ) {
	class boniPS_Banking_Service_Payouts extends boniPS_Service {

		/**
		 * Construct
		 */
		function __construct( $service_prefs ) {
			parent::__construct( array(
				'id'       => 'payouts',
				'defaults' => array(
					'amount'     => 10,
					'rate'       => 'daily',
					'log'        => __( 'Tägliche %_plural%', 'bonips' ),
					'excludes'   => '',
					'cycles'     => 0,
					'last_run'   => '',
					'run_time'   => 60
				)
			), $service_prefs );
		}

		/**
		 * Run
		 * @since 1.2
		 * @version 1.0
		 */
		public function run() {
			add_action( 'wp_loaded',                       array( $this, 'process' ) );
			add_action( 'bonips_banking_recurring_payout', array( $this, 'do_payouts' ) );
			add_action( 'bonips_banking_do_batch',         array( $this, 'do_payout_batch' ), 10, 3 );
		}

		/**
		 * Deactivation
		 * @since 1.2
		 * @version 1.0
		 */
		public function deactivate() {
			// Unschedule payouts
			wp_clear_scheduled_hook( 'bonips_banking_recurring_payout' );
		}

		/**
		 * Process
		 * Determines if we should run a payout or not.
		 * @since 1.2
		 * @version 1.1
		 */
		public function process() {
			// Get cycles
			$cycles = (int) $this->prefs['cycles'];
			// Zero cycles left, bail
			if ( $cycles == 0 ) return;
				
			// No amount = no payout
			if ( !isset( $this->prefs['amount'] ) || $this->prefs['amount'] == 0 ) return;

			$unow = date_i18n( 'U' );
			$now = $this->get_now( $this->prefs['rate'] );
			// No last run, save now as last run
			if ( empty( $this->prefs['last_run'] ) || $this->prefs['last_run'] === NULL ) {
				$last_run = $this->get_last_run( $unow, $this->prefs['rate'] );
				$this->save( $unow, $cycles );
			}
			// Last run
			else {
				$last_run = $this->get_last_run( $this->prefs['last_run'], $this->prefs['rate'] );
			}
			// If now or last run returns false bail
			if ( $now === false || $last_run === false ) return;

			// Is it time to run?
			if ( $this->time_to_run( $this->prefs['rate'], $last_run ) ) {
				// Cycles (-1 means no limit)
				if ( $cycles > 0-1 ) {
					$cycles = $cycles-1;
				}
				// Save
				$this->save( $unow, $cycles );

				// Schedule payouts
				if ( wp_next_scheduled( 'bonips_banking_recurring_payout', array( $cycles ) ) === false )
					wp_schedule_single_event( time(), 'bonips_banking_recurring_payout', array( $cycles ) );
			}
		}

		/**
		 * Payout
		 * In this first step, we start by gathering all user ID's.
		 * If the amount is higher then our threshold, we split up the ID's
		 * into batches and schedule then seperate. This is due to the maximum
		 * execution limit which will not be enough to handle a lot of users in one go.
		 * @since 1.2
		 * @version 1.1
		 */
		public function do_payouts( $cycle = NULL ) {
			// Make sure to clear any stray schedules to prevent duplicates
			wp_clear_scheduled_hook( 'bonips_banking_recurring_payout' );

			// Query
			$users = $this->get_users();
			$total = count( $users );
			$threshold = (int) apply_filters( 'bonips_do_banking_limit', 2000 );

			// If we are over the threshold we need to batch
			if ( (int) $total > $threshold ) {
				$batches = array_chunk( $users, $threshold );
				$time = time();

				$set = 0;
				foreach ( $batches as $batch_id => $batch ) {
					$set = $set+1;
					// Run time = current time + 60 seconds for each set
					$run_time = ( $time + ( 60*$set ) );
					if ( wp_next_scheduled( $run_time, 'bonips_banking_do_batch', array( $batch, $set, $cycle ) ) === false )
						wp_schedule_single_event( $run_time, 'bonips_banking_do_batch', array( $batch, $set, $cycle ) );
				}
				set_transient( 'bonips_banking_num_payout_batches', $set, HOUR_IN_SECONDS );
			}
			// Run single batch now
			else {
				$this->do_payout_batch( $users, NULL, $cycle );
			}
		}
		
		/**
		 * Do Batch
		 * Applies points to a batch of user ID's. This is also where we check for exclusions.
		 * @since 1.2
		 * @version 1.2.1
		 */
		public function do_payout_batch( $batch, $set = NULL, $cycle = NULL ) {
			if ( !empty( $batch ) && is_array( $batch ) ) {

				set_time_limit( $this->prefs['run_time'] );

				foreach ( $batch as $user_id ) {
					$user_id = intval( $user_id );

					// Add / Deduct points
					$this->core->update_users_balance( $user_id, $this->prefs['amount'] );
					$this->core->add_to_log(
						'payout',
						$user_id,
						$this->prefs['amount'],
						$this->prefs['log']
					);
				}
				// If multiple sets, check if this is the last one to deactivate
				if ( $set !== NULL ) {
					$total = get_transient( 'bonips_banking_num_payout_batches' );
					if ( $total !== false && $set == $total ) {
						delete_transient( 'bonips_banking_num_payout_batches' );

						if ( $cycle == 0 )
							$this->save( date_i18n( 'U' ), 0, true );
					}
				}
				// Single set, check if cycle is zero to deactivate
				elseif ( $set === NULL && $cycle == 0 )
					$this->save( date_i18n( 'U' ), 0, true );
			}
		}

		/**
		 * Save
		 * Saves the last run and the number of cycles run.
		 * @since 1.2
		 * @version 1.1.1
		 */
		public function save( $now = 0, $cycles = 0, $deactivate = false ) {
			// Update last run
			$this->prefs['last_run'] = $now;
			// Update cycles count
			$this->prefs['cycles'] = $cycles;

			// Get Bank settings
			$bank = get_option( 'bonips_pref_bank' );
			
			// Update settings
			$bank['service_prefs'][ $this->id ] = $this->prefs;

			// Deactivate this service if this is the last run
			if ( $cycles == 0 && $deactivate ) {
				// Should return the service id as a key for us to unset
				if ( ( $key = array_search( $this->id, $bank['active'] ) ) !== false ) {
					unset( $bank['active'][ $key ] );
				}
			}

			// Save new settings
			update_option( 'bonips_pref_bank', $bank );
		}

		/**
		 * Preference for Savings
		 * @since 1.2
		 * @version 1.1
		 */
		public function preferences() {
			$prefs = $this->prefs;

			// Last run
			$last_run = $prefs['last_run'];
			if ( empty( $last_run ) )
				$last_run = __( 'Noch nicht ausgeführt', 'bonips' );
			else
				$last_run = date_i18n( get_option( 'date_format' ) . ' : ' . get_option( 'time_format' ), $last_run ); ?>

					
					<label class="subheader"><?php _e( 'Benutzer bezahlen', 'bonips' ); ?></label>
					<ol class="inline">
						<li>
							<label><?php _e( 'Betrag', 'bonips' ); ?></label>
							<div class="h2"><?php if ( !empty( $this->core->before ) ) echo $this->core->before . ' '; ?><input type="text" name="<?php echo $this->field_name( 'amount' ); ?>" id="<?php echo $this->field_id( 'amount' ); ?>" value="<?php echo $this->core->format_number( $prefs['amount'] ); ?>" size="8" /><?php if ( !empty( $this->core->after ) ) echo ' ' . $this->core->after; ?></div>
							<span class="description"><?php _e( 'Kann nicht Null sein.', 'bonips' ); ?></span>
							<input type="hidden" name="<?php echo $this->field_name( 'last_run' ); ?>" value="<?php echo $prefs['last_run']; ?>" />
						</li>
						<li>
							<label for="<?php echo $this->field_id( 'rate' ); ?>"><?php _e( 'Intervall', 'bonips' ); ?></label><br />
							<?php $this->timeframe_dropdown( 'rate', false ); ?>

						</li>
						<li>
							<label><?php _e( 'Zyklen', 'bonips' ); ?></label>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( 'cycles' ); ?>" id="<?php echo $this->field_id( 'cycles' ); ?>" value="<?php echo $prefs['cycles']; ?>" size="8" /></div>
							<span class="description"><?php _e( 'Für unbegrenzt auf -1 setzen', 'bonips' ); ?></span>
						</li>
						<li>
							<label><?php _e( 'Letzter Lauf / Aktiviert', 'bonips' ); ?></label><br />
							<div class="h2"><?php echo $last_run; ?></div>
						</li>
						<li class="block"><strong><?php _e( 'Intervall', 'bonips' ); ?></strong><br /><?php echo $this->core->template_tags_general( __( 'Wähle aus, wie oft Du %_plural% vergeben möchtest. Beachte, dass bei Aktivierung dieses Dienstes die erste Auszahlung zu Beginn des nächsten Zeitraums erfolgt. Bei einem "täglichen" Intervall erfolgt die erste Auszahlung also als erstes am Morgen.', 'bonips' ) ); ?></li>
						<li class="block"><strong><?php _e( 'Cycles', 'bonips' ); ?></strong><br /><?php _e( 'In Zyklen kannst Du auswählen, wie viele Intervalle dieser Dienst ausgeführt werden soll. Jedes Mal, wenn ein Zyklus ausgeführt wird, verringert sich der Wert, bis er Null erreicht. In diesem Fall wird dieser Dienst selbst deaktiviert. Verwende -1, um unbegrenzt oft zu laufen.', 'bonips' ); ?></li>
						<li class="block"><strong><?php _e( 'Important', 'bonips' ); ?></strong><br /><?php _e( 'Du kannst Auszahlungen jederzeit stoppen, indem Du diesen Dienst deaktivierst. Denke daran, dass dieser Dienst fortgesetzt wird, wenn Du ihn deaktivierst, während noch Zyklen übrig sind, wenn er erneut aktiviert wird. Setze die Zyklen zum Zurücksetzen auf Null.', 'bonips' ); ?></li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( 'excludes' ); ?>"><?php _e( 'Ausgeschlossen', 'bonips' ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( 'excludes' ); ?>" id="<?php echo $this->field_id( 'excludes' ); ?>" value="<?php echo $prefs['excludes']; ?>" style="width: 65%;" /></div>
							<span class="description"><?php _e( 'Durch Kommas getrennte Liste von Benutzer-IDs, die von diesem Dienst ausgeschlossen werden sollen. Keine Leerzeichen erlaubt!', 'bonips' ); ?></span>
						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( 'log' ); ?>"><?php _e( 'Protokollvorlage', 'bonips' ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( 'log' ); ?>" id="<?php echo $this->field_id( 'log' ); ?>" value="<?php echo $prefs['log']; ?>" style="width: 65%;" /></div>
							<span class="description"><?php echo $this->core->available_template_tags( array( 'general' ) ); ?></span>
						</li>
					</ol>
					<label class="subheader" for="<?php echo $this->field_id( 'run_time' ); ?>"><?php _e( 'Laufzeit', 'bonips' ); ?></label>
					<ol>
						<li>
							<div class="h2"><input type="text" name="<?php echo $this->field_name( 'run_time' ); ?>" id="<?php echo $this->field_id( 'run_time' ); ?>" value="<?php echo $prefs['run_time']; ?>" size="4" /></div>
							<span class="description"><?php _e( 'Bei großen Webseiten kannst Du die Anzahl der Sekunden festlegen, die ein Prozess ausgeführt werden kann, wenn bei Auszahlungen Probleme mit der Zeitüberschreitung auftreten. Verwende Null für unbegrenzt, aber seie vorsichtig, insbesondere wenn Du Dich auf einem gemeinsam genutzten Server befinden.', 'bonips' ); ?></span>
						</li>
					</ol>
					<?php do_action( 'bonips_banking_recurring_payouts', $this->prefs ); ?>
<?php
		}

		/**
		 * Sanitise Preferences
		 * @since 1.2
		 * @version 1.1
		 */
		function sanitise_preferences( $post ) {
			// Amount
			$new_settings['amount'] = trim( $post['amount'] );

			// Rate
			$new_settings['rate'] = sanitize_text_field( $post['rate'] );

			// Cycles
			$new_settings['cycles'] = sanitize_text_field( $post['cycles'] );

			// Last Run
			$new_settings['last_run'] = $post['last_run'];
			$current_cycles = $this->prefs['cycles'];
			// Moving from -1 or 0 to any higher number indicates a new start. In these cases, we will
			// reset the last run timestamp to prevent this service from running right away.
			if ( ( $current_cycles == 0 || $current_cycles == 0-1 ) && $new_settings['cycles'] > 0 )
				$new_settings['last_run'] = '';

			// Excludes
			$excludes = str_replace( ' ', '', $post['excludes'] );
			$new_settings['excludes'] = sanitize_text_field( $excludes );

			// Log
			$new_settings['log'] = trim( $post['log'] );

			// Run Time
			$post['run_time'] = abs( $post['run_time'] );
			$new_settings['run_time'] = sanitize_text_field( $post['run_time'] );

			return apply_filters( 'bonips_banking_save_recurring', $new_settings, $this->prefs );
		}
	}
}
?>