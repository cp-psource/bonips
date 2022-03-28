<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * Import: CubePoint Balances
 * @since 1.2
 * @version 1.3
 */
if ( ! class_exists( 'boniPS_Importer_CubePoints' ) ) :
	class boniPS_Importer_CubePoints extends WP_Importer {

		var $id            = '';
		var $file_url      = '';
		var $import_page   = '';
		var $delimiter     = '';
		var $posts         = array();
		var $imported      = 0;
		var $skipped       = 0;
		var $documentation = '';

		/**
		 * Construct
		 * @version 1.0
		 */
		public function __construct() {

			$this->import_page   = BONIPS_SLUG . '-import-cp';
			$this->delimiter     = empty( $_POST['delimiter'] ) ? ',' : (string) strip_tags( trim( $_POST['delimiter'] ) );
			$this->documentation = 'http://codex.bonips.me/chapter-ii/import-data/import-cubepoints/';

		}

		/**
		 * Registered callback function for the WordPress Importer
		 * Manages the three separate stages of the CSV import process
		 * @version 1.0
		 */
		public function load() {

			$this->header();

			$load = true;
			$step = ( ! isset( $_GET['step'] ) ) ? 0 : absint( $_GET['step'] );
			if ( $step > 1 ) $step = 0;

			switch ( $step ) {

				case 1 :

					if ( $this->check_cubepoints() ) {

						$load = $this->import();

					}

				break;

			}

			if ( $load )
				$this->greet();

			$this->footer();

		}

		/**
		 * UTF-8 encode the data if `$enc` value isn't UTF-8.
		 * @version 1.0
		 */
		public function format_data_from_csv( $data, $enc ) {
			return ( $enc == 'UTF-8' ) ? $data : utf8_encode( $data );
		}

		/**
		 * Checks CubePoints Installation
		 * @version 1.1
		 */
		public function check_cubepoints() {

			global $wpdb;

			$cubepoints = $wpdb->prefix . 'cp';
			if ( $wpdb->get_var( "SHOW TABLES LIKE '{$cubepoints}';" ) != $cubepoints ) {
				echo '<div class="error notice notice-error"><p>' . __( 'Could not find a CubePoints installation.', 'bonips' ) . '</p></div>';
				return false;
			}

			return true;

		}

		/**
		 * Import Function
		 * Handles the actual import based on a given file.
		 * @version 1.0
		 */
		public function import() {

			global $wpdb;

			$action     = $_POST['action'];
			$point_type = $_POST['type'];
			$cubepoints = $wpdb->prefix . 'cp';

			$show_greet = true;
			$loop       = 0;

			if ( ! bonips_point_type_exists( $point_type ) ) $point_type = BONIPS_DEFAULT_TYPE_KEY;
			$bonips     = bonips( $type );

			// Import Log
			if ( $action == 'log' || $action == 'both' ) {

				$entries = $wpdb->get_results( "SELECT * FROM {$cubepoints};" );
				if ( ! empty( $entries ) ) {
					foreach ( $entries as $entry ) {

						$reference = false;
						$log_entry = false;
						$ref_id    = false;
						$data      = '';

						if ( $entry->type == 'comment' ) {

							$reference = 'approved_comment';
							$log_entry = '%plural% for approved comment';

						}

						elseif ( $entry->type == 'comment_remove' ) {

							$reference = 'unapproved_comment';
							$log_entry = '%plural% for deleted comment';

						}

						elseif ( $entry->type == 'post' ) {

							$reference = 'publishing_content';
							$log_entry = '%plural% for publishing content';

						}

						elseif ( $entry->type == 'register' ) {

							$reference = 'registration';
							$log_entry = '%plural% for registration';

						}

						elseif ( $entry->type == 'addpoints' ) {

							$reference = 'manual';
							$log_entry = '%plural% via manual adjustment';

						}

						elseif ( $entry->type == 'dailypoints' ) {

							$reference = 'payout';
							$log_entry = 'Daily %plural%';

						}

						elseif ( $entry->type == 'donate_from' ) {

							$reference = 'transfer';
							$data      = maybe_unserialize( $entry->data );

							if ( isset( $data['to'] ) ) $ref_id = absint( $data['to'] );

							$log_entry = 'Transfer from %display_name%';
							$data      = array( 'ref_type' => 'user', 'tid' => 'TXID' . $entry->timestamp . $entry->uid );

						}

						elseif ( $entry->type == 'donate_to' ) {

							$reference = 'transfer';
							$data      = maybe_unserialize( $entry->data );

							if ( isset( $data['to'] ) ) $ref_id = absint( $data['to'] );

							$log_entry = 'Transfer to %display_name%';
							$data      = array( 'ref_type' => 'user', 'tid' => 'TXID' . $entry->timestamp . $entry->uid );

						}

						elseif ( $entry->type == 'pcontent' ) {

							$reference = 'buy_content';
							$log_entry = 'Purchase of %link_with_title%';
							$ref_id    = absint( $entry->data );
							$data      = array( 'ref_type' => 'post', 'purchase_id' => 'TXID' . $entry->timestamp );

						}

						elseif ( $entry->type == 'pcontent_author' ) {

							$reference = 'buy_content';
							$log_entry = 'Sale of %link_with_title%';

							$data      = maybe_unserialize( $entry->data );
							$ref_id    = absint( $data[0] );

							$data      = array( 'ref_type' => 'post', 'purchase_id' => 'TXID' . $entry->timestamp, 'buyer' => $data[1] );

						}

						elseif ( $entry->type == 'paypal' ) {

							$reference = 'buy_creds_with_paypal_standard';
							$log_entry = '%plural% purchase';

							$data      = maybe_unserialize( $entry->data );
							$data      = array( 'txn_id' => $data['txn_id'], 'payer_id' => $data['payer_email'] );

						}

						elseif ( $entry->type == 'post_comment' ) {

							$reference = 'approved_comment';
							$log_entry = '%plural% for approved comment';
							$data      = array( 'ref_type' => 'comment' );

						}

						elseif ( $entry->type == 'post_comment_remove' ) {

							$reference = 'unapproved_comment';
							$log_entry = '%plural% for deleted comment';
							$data      = array( 'ref_type' => 'comment' );

						}

						elseif ( $entry->type == 'youtube' ) {

							$reference = 'watching_video';
							$log_entry = '%plural% for viewing video';
							$data      = absint( $entry->data );

						}

						if ( $reference === false ) {
							$this->skipped ++;
							continue;
						}

						$entry_data = maybe_unserialize( $entry->data );
						if ( $ref_id === false && ! empty( $entry_data ) && ! is_array( $entry_data ) ) $ref_id = absint( $entry->data );
						if ( $ref_id === false ) $ref_id = 0;

						$bonips->add_to_log( $reference, $entry->uid, $entry->points, $log_entry, $ref_id, $data, $point_type );

						$loop ++;
						$this->imported++;

					}

				}

			}

			if ( $action == 'balance' || $action == 'both' ) {

				$rows = $wpdb->update(
					$wpdb->usermeta,
					array( 'meta_key' => bonips_get_meta_key( $point_type ) ),
					array( 'meta_key' => 'cpoints' ),
					array( '%s' ),
					array( '%s' )
				);

				$this->imported = $rows;

			}

			// Show Result
			if ( $this->imported == 0 ) {

				echo '<div class="error notice notice-error is-dismissible"><p>' . ( ( $action == 'balance' ) ? __( 'No balances were imported.', 'bonips' ) : __( 'No log entries were imported!', 'bonips' ) ) . '</p></div>';

			}
			else {

				$show_greet = false;
				echo '<div class="updated notice notice-success is-dismissible"><p>' . sprintf( __( 'Import complete - A total of <strong>%d</strong> balances were successfully imported. <strong>%d</strong> was skipped.', 'bonips' ), $this->imported, $this->skipped ) . '</p></div>';
				echo '<p><a href="' . admin_url( 'users.php' ) . '" class="button button-large button-primary">' . __( 'View Users', 'bonips' ) . '</a></p>';

			}

			do_action( 'import_end' );

			return $show_greet;

		}

		/**
		 * Render Screen Header
		 * @version 1.0
		 */
		public function header() {

			$label = __( 'Import CubePoints', 'bonips' );
			if ( BONIPS_DEFAULT_LABEL === 'boniPS' )
				$label .= ' <a href="' . $this->documentation . '" target="_blank" class="page-title-action">' . __( 'Documentation', 'bonips' ) . '</a>';

			echo '<div class="wrap"><h1>' . $label . '</h1>';

		}

		/**
		 * Render Screen Footer
		 * @version 1.0
		 */
		public function footer() {

			echo '</div>';

		}

		/**
		 * Greet Screen
		 * @version 1.1
		 */
		public function greet() {

			$action_url = add_query_arg( array( 'import' => $this->import_page, 'step' => 1 ), admin_url( 'admin.php' ) );

			// Make sure we have something to import
			if ( ! $this->check_cubepoints() ) :

				// $this->check_cubepoints() will render our error message

			else :

?>
<form id="import-setup" method="post" action="<?php echo esc_attr( wp_nonce_url( $action_url, 'import-upload' ) ); ?>">
	<table class="form-table">
		<tbody>
			<tr>
				<th>
					<label for="import-action"><?php _e( 'Import', 'bonips' ); ?></label>
				</th>
				<td>
					<select name="action" id="import-action">
						<option value=""><?php _e( 'Select what to import', 'bonips' ); ?></option>
						<option value="log"><?php _e( 'Log Entries Only', 'bonips' ); ?></option>
						<option value="balance"><?php _e( 'CubePoints Balances Only', 'bonips' ); ?></option>
						<option value="both"><?php _e( 'Log Entries and Balances', 'bonips' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th>
					<label for="bonips-type"><?php _e( 'Point Type', 'bonips' ); ?></label>
				</th>
				<td>

					<?php bonips_types_select_from_dropdown( 'type', 'bonips-type', BONIPS_DEFAULT_TYPE_KEY ); ?>

				</td>
			</tr>
		</tbody>
	</table>
	<p class="submit">
		<input type="submit" class="button button-primary" value="<?php _e( 'Import', 'bonips' ); ?>" />
	</p>
</form>
<?php

			endif;

		}

		/**
		 * Added to http_request_timeout filter to force timeout at 60 seconds during import
		 * @return int 60
		 */
		public function bump_request_timeout( $val ) {

			return 60;

		}

	}
endif;
