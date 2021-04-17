<?php
/**
 * Addon: Import
 * Addon URI: http://bonipress.me/add-ons/import/
 * Version: 1.0.2
 * Description: With the Import add-on you can import CSV files, CubePoints or existing points under any custom user meta values.
 * Author: DerN3rd
 * Author URI: http://www.merovingi.com
 */
// Translate Header (by Dan bp-fr)
$bonipress_addon_header_translate = array(
	__( 'Import', 'bonipress' ),
	__( 'With the Import add-on you can import CSV files, CubePoints or existing points under any custom user meta values.', 'bonipress' )
);

if ( !defined( 'boniPRESS_VERSION' ) ) exit;

define( 'boniPRESS_IMPORT',         __FILE__ );
define( 'boniPRESS_IMPORT_VERSION', boniPRESS_VERSION . '.1' );
/**
 * boniPRESS_Import class
 *
 * Manages all available imports.
 * @since 0.1
 * @version 1.0
 */
if ( !class_exists( 'boniPRESS_Import' ) ) {
	class boniPRESS_Import extends boniPRESS_Module {

		public $errors = '';
		public $import_ok = false;

		/**
		 * Construct
		 */
		function __construct() {
			parent::__construct( 'boniPRESS_Import', array(
				'module_name' => 'import',
				'labels'      => array(
					'menu'        => __( 'Import', 'bonipress' ),
					'page_title'  => __( 'Import', 'bonipress' ),
					'page_header' => __( 'Import', 'bonipress' )
				),
				'screen_id'   => 'boniPRESS_page_import',
				'accordion'   => true,
				'register'    => false,
				'menu_pos'    => 90
			) );

			add_action( 'bonipress_help',           array( $this, 'help' ), 10, 2 );
		}

		/**
		 * Module Init
		 * @since 0.1
		 * @version 1.0
		 */
		public function module_init() {
			$installed = $this->get();

			// If an import is selected, run it
			if ( empty( $installed ) || !isset( $_REQUEST['selected-import'] ) ) return;
			if ( !array_key_exists( $_REQUEST['selected-import'], $installed ) ) return;

			$call = 'import_' . $_REQUEST['selected-import'];
			$this->$call();

			// Open accordion for import
			add_filter( 'bonipress_localize_admin', array( $this, 'accordion' ) );
		}

		/**
		 * Adjust Accordion
		 * Marks the given import as active.
		 * @since 0.1
		 * @version 1.0
		 */
		public function accordion() {
			$key = array_search( trim( $_REQUEST['selected-import'] ), array_keys( $this->installed ) );
			return array( 'active' => $key );
		}

		/**
		 * Get Imports
		 * @since 0.1
		 * @version 1.0
		 */
		public function get( $save = false ) {
			// Defaults
			$installed['csv'] = array(
				'title'        => __( 'CSV File', 'bonipress' ),
				'description'  => __( 'Import %_plural% from a comma-separated values (CSV) file.', 'bonipress' )
			);
			$installed['cubepoints'] = array(
				'title'       => __( 'CubePoints', 'bonipress' ),
				'description' => __( 'Import CubePoints', 'bonipress' )
			);
			$installed['custom'] = array(
				'title'       => __( 'Custom User Meta', 'bonipress' ),
				'description' => __( 'Import %_plural% from pre-existing custom user meta.', 'bonipress' )
			);
			$installed = apply_filters( 'bonipress_setup_imports', $installed );

			$this->installed = $installed;
			return $installed;
		}

		/**
		 * Update Users
		 * @param $data (array), required associative array of users and amounts to be added to their account.
		 * @since 0.1
		 * @version 1.1
		 */
		public function update_users( $data = array(), $verify = true ) {
			// Prep
			$id_user_by = 'id';
			if ( isset( $_POST['id_user_by'] ) )
				$id_user_by = $_POST['id_user_by'];

			$xrate = 1;
			if ( isset( $_POST['xrate'] ) )
				$xrate = $_POST['xrate'];

			$round = false;
			if ( isset( $_POST['round'] ) && $_POST['round'] != 'none' )
				$round = $_POST['round'];

			$precision = false;
			if ( isset( $_POST['precision'] ) && $_POST['precision'] != 0 )
				$precision = $_POST['precision'];

			// Loop
			$imports = $skipped = 0;
			foreach ( $data as $row ) {
				// bonipress_user and bonipress_amount are two mandatory columns!
				if ( !isset( $row['bonipress_user'] ) || empty( $row['bonipress_user'] ) ) {
					$skipped = $skipped+1;
					continue;
				}
				if ( !isset( $row['bonipress_amount'] ) || empty( $row['bonipress_amount'] ) ) {
					$skipped = $skipped+1;
					continue;
				}

				// Verify User exist
				if ( $verify === true ) {
					// Get User (and with that confirm user exists)
					$user = get_user_by( $id_user_by, $row['bonipress_user'] );

					// User does not exist
					if ( $user === false ) {
						$skipped = $skipped+1;
						continue;
					}

					// User ID
					$user_id = $user->ID;
					unset( $user );
				}
				else {
					$user_id = $row['bonipress_user'];
				}

				// Users is excluded
				if ( $this->core->exclude_user( $user_id ) ) {
					$skipped = $skipped+1;
					continue;
				}

				// Amount (can not be zero)
				$cred = $this->core->number( $row['bonipress_amount'] );
				if ( $cred == 0 ) {
					$skipped = $skipped+1;
					continue;
				}

				// If exchange rate is not 1 for 1
				if ( $xrate != 1 ) {
					// Cred = rate*amount
					$amount = $xrate * $row['bonipress_amount'];
					$cred = $this->core->round_value( $amount, $round, $precision );
				}

				// Adjust Balance
				$new_balance = $this->core->update_users_balance( $user_id, $cred );

				// First we check if the bonipress_log column is used
				if ( isset( $row['bonipress_log'] ) && !empty( $row['bonipress_log'] ) ) {
					$this->core->add_to_log( 'import', $user_id, $cred, $row['bonipress_log'] );
				}
				// Second we check if the log template is set
				elseif ( isset( $_POST['log_template'] ) && !empty( $_POST['log_template'] ) ) {
					$this->core->add_to_log( 'import', $user_id, $cred, sanitize_text_field( $_POST['log_template'] ) );
				}

				$imports = $imports+1;
			}

			// Pass on the news
			$this->imports = $imports;
			$this->skipped = $skipped;

			unset( $data );
		}

		/**
		 * CSV Importer
		 * Based on the csv-importer plugin. Thanks for teaching me something new.
		 *
		 * @see http://wordpress.org/extend/plugins/csv-importer/
		 * @since 0.1
		 * @version 1.0
		 */
		public function import_csv() {
			// We need a file. or else...
			if ( !isset( $_FILES['bonipress_csv'] ) || empty( $_FILES['bonipress_csv']['tmp_name'] ) ) {
				$this->errors = __( 'No file selected. Please select your CSV file and try again.', 'bonipress' );
				return;
			}

			// Grab CSV Data Fetcher
			require_once( boniPRESS_ADDONS_DIR . 'import/includes/File-CSV-DataSource.php' );

			// Prep
			$time_start = microtime( true );
			$csv = new File_CSV_DataSource();
			$file = $_FILES['bonipress_csv']['tmp_name'];
			$this->strip_BOM( $file );

			// Failed to load file
			if ( !$csv->load( $file ) ) {
				$this->errors = __( 'Failed to load file.', 'bonipress' );
				return;
			}

			// Equality for all
			$csv->symmetrize();

			// Update
			$this->update_users( $csv->connect() );

			// Unlink
			if ( file_exists( $file ) ) {
				@unlink( $file );
			}

			// Time
			$exec_time = microtime( true ) - $time_start;

			// Throw an error if there were no imports just skipps
			if ( $this->imports == 0 && $this->skipped != 0 ) {
				$this->errors = sprintf(
					__( 'Zero rows imported! Skipped %d entries. Import completed in %.2f seconds.', 'bonipress' ),
					$this->skipped,
					$exec_time
				);
				return;
			}

			// Throw an error if there were no imports and no skipps
			elseif ( $this->imports == 0 && $this->skipped == 0 ) {
				$this->errors = __( 'No valid records found in file. Make sure you have selected the correct way to identify users in the bonipress_user column!', 'bonipress' );
				return;
			}

			// The joy of success
			$this->import_ok = sprintf(
				__( 'Import successfully completed. A total of %d users were effected and %d entires were skipped. Import completed in %.2f seconds.', 'bonipress' ),
				$this->imports,
				$this->skipped,
				$exec_time
			);

			// Clean Up
			unset( $_FILES );
			unset( $csv );

			// Close accordion
			unset( $_POST );
		}

		/**
		 * Import CubePoints
		 * @since 0.1
		 * @version 1.2
		 */
		public function import_cubepoints() {
			$delete = false;
			if ( isset( $_POST['delete'] ) ) $delete = true;

			$meta_key = 'cpoints';
			$time_start = microtime( true );

			global $wpdb;

			// DB Query
			$SQL = "SELECT * FROM {$wpdb->usermeta} WHERE meta_key = %s;";
			$search = $wpdb->get_results( $wpdb->prepare( $SQL, $meta_key ) );

			// No results
			if ( $wpdb->num_rows == 0 ) {
				$this->errors = __( 'No CubePoints found.', 'bonipress' );
				return;
			}

			// Found something
			else {
				// Construct a new array for $this->update_users() to match the format used
				// when importing CSV files. User ID goes under 'bonipress_user' while 'bonipress_amount' holds the value.
				$data = array();
				foreach ( $search as $result ) {
					$data[] = array(
						'bonipress_user'   => $result->user_id,
						'bonipress_amount' => $result->meta_value,
						'bonipress_log'    => ( isset( $_POST['log_template'] ) ) ? sanitize_text_field( $_POST['log_template'] ) : ''
					);
				}

				// Update User without the need to verify the user
				$this->update_users( $data, false );

				// Delete old value if requested
				if ( $delete === true ) {
					foreach ( $search as $result ) {
						delete_user_meta( $result->user_id, $meta_key );
					}
				}
			}

			// Time
			$exec_time = microtime( true ) - $time_start;

			// Throw an error if there were no imports just skipps
			if ( $this->imports == 0 && $this->skipped != 0 ) {
				$this->errors = sprintf(
					__( 'Zero CubePoints imported! Skipped %d entries. Import completed in %.2f seconds.', 'bonipress' ),
					$this->skipped,
					$exec_time
				);
				return;
			}

			// Throw an error if there were no imports and no skipps
			elseif ( $this->imports == 0 && $this->skipped == 0 ) {
				$this->errors = __( 'No valid CubePoints founds.', 'bonipress' );
				return;
			}

			// The joy of success
			$this->import_ok = sprintf(
				__( 'Import successfully completed. A total of %d users were effected and %d entires were skipped. Import completed in %.2f seconds.', 'bonipress' ),
				$this->imports,
				$this->skipped,
				$exec_time
			);

			// Clean Up
			unset( $search );

			// Close Accordion
			unset( $_POST );
		}

		/**
		 * Import Custom User Meta
		 * @since 0.1
		 * @version 1.0.1
		 */
		public function import_custom() {
			if ( !isset( $_POST['meta_key'] ) || empty( $_POST['meta_key'] ) ) {
				$this->errors = __( 'Missing meta key. Not sure what I should be looking for.', 'bonipress' );
				return;
			}

			// Prep
			$delete = false;
			if ( isset( $_POST['delete'] ) ) $delete = true;

			$meta_key = $_POST['meta_key'];
			$time_start = microtime( true );

			global $wpdb;

			// DB Query
			$SQL = "SELECT * FROM {$pwbd->usermeta} WHERE meta_key = %s;";
			$search = $wpdb->get_results( $wpdb->prepare( $SQL, $meta_key ) );

			// No results
			if ( $wpdb->num_rows == 0 ) {
				$this->errors = sprintf( __( 'No rows found for the <strong>%s</strong> meta key.', 'bonipress' ), $meta_key );
				return;
			}

			// Found something
			else {
				// Construct a new array for $this->update_users() to match the format used
				// when importing CSV files. User ID goes under 'bonipress_user' while 'bonipress_amount' holds the value.
				$data = array();
				foreach ( $search as $result ) {
					$data[] = array(
						'bonipress_user'   => $result->user_id,
						'bonipress_amount' => $result->meta_value
					);
				}

				// Update User without the need to verify the user
				$this->update_users( $data, false );

				// Delete old value if requested
				if ( $delete === true ) {
					foreach ( $search as $result ) {
						delete_user_meta( $result->user_id, $meta_key );
					}
				}
			}

			// Time
			$exec_time = microtime( true ) - $time_start;

			// Throw an error if there were no imports just skipps
			if ( $this->imports == 0 && $this->skipped != 0 ) {
				$this->errors = sprintf(
					__( 'Zero rows imported! Skipped %d entries. Import completed in %.2f seconds.', 'bonipress' ),
					$this->skipped,
					$exec_time
				);
				return;
			}

			// Throw an error if there were no imports and no skipps
			elseif ( $this->imports == 0 && $this->skipped == 0 ) {
				$this->errors = __( 'No valid records founds.', 'bonipress' );
				return;
			}

			// The joy of success
			$this->import_ok = sprintf(
				__( 'Import successfully completed. A total of %d users were effected and %d entires were skipped. Import completed in %.2f seconds.', 'bonipress' ),
				$this->imports,
				$this->skipped,
				$exec_time
			);

			// Clean Up
			unset( $search );

			// Close Accordion
			unset( $_POST );
		}

		/**
		 * Admin Page
		 * @since 0.1
		 * @version 1.0
		 */
		public function admin_page() {
			// Security
			if ( !$this->core->can_edit_plugin( get_current_user_id() ) ) wp_die( __( 'Access Denied', 'bonipress' ) );

			// Available Imports
			if ( empty( $this->installed ) )
				$this->get(); ?>

	<div class="wrap list" id="boniPRESS-wrap">
		<div id="icon-boniPRESS" class="icon32"><br /></div>
		<h2><?php echo sprintf( __( '%s Import', 'bonipress' ), bonipress_label() ); ?></h2>
<?php
			// Errors
			if ( !empty( $this->errors ) ) {
				echo '<div class="error"><p>' . $this->errors . '</p></div>';
			}

			// Success
			elseif ( $this->import_ok !== false ) {
				echo '<div class="updated"><p>' . $this->import_ok . '</p></div>';
			} ?>

		<p><?php _e( 'Remember to de-activate this add-on once you are done importing!', 'bonipress' ); ?></p>
			<div class="list-items expandable-li" id="accordion">
<?php
			if ( !empty( $this->installed ) ) {
				foreach ( $this->installed as $id => $data ) {
					$call = $id . '_form';
					$this->$call( $data );
				}
			} ?>

			</div>
	</div>
<?php
			unset( $this );
		}

		/**
		 * CSV Import Form
		 * @since 0.1
		 * @version 1.0
		 */
		public function csv_form( $data ) {
			$max_upload = (int) ( ini_get( 'upload_max_filesize' ) );
			$max_post = (int) ( ini_get( 'post_max_size' ) );
			$memory_limit = (int) ( ini_get( 'memory_limit' ) );
			$upload_mb = min( $max_upload, $max_post, $memory_limit ); ?>

				<h4><div class="icon icon-active"></div><label><?php echo $data['title']; ?></label></h4>
				<div class="body" style="display:none;">
					<form class="add:the-list: validate" method="post" enctype="multipart/form-data">
						<input type="hidden" name="selected-import" value="csv" />
						<p><?php echo nl2br( $this->core->template_tags_general( $data['description'] ) ); ?></p>
						<label class="subheader" for="bonipress-csv-file"><?php _e( 'File', 'bonipress' ); ?></label>
						<ol>
							<li>
								<div><input type="file" name="bonipress_csv" id="bonipress-csv-file" value="" aria-required="true" /></div>
								<span class="description"><?php echo __( 'Maximum allowed upload size is ', 'bonipress' ) . $upload_mb . ' Mb<br />' . __( 'Required columns: <code>bonipress_user</code> and <code>bonipress_amount</code>. Optional columns: <code>bonipress_log</code>.', 'bonipress' ); ?></span>
							</li>
						</ol>
						<label class="subheader"><?php _e( 'Identify Users By', 'bonipress' ); ?></label>
						<ol>
							<li>
								<input type="radio" name="id_user_by" id="bonipress-csv-by-id" value="id" checked="checked" /><label for="bonipress-csv-by-id"><?php _e( 'ID', 'bonipress' ); ?></label><br />
								<input type="radio" name="id_user_by" id="bonipress-csv-by-login" value="login" /><label for="bonipress-csv-by-login"><?php _e( 'Username', 'bonipress' ); ?></label><br />
								<input type="radio" name="id_user_by" id="bonipress-csv-by-email" value="email" /><label for="bonipress-csv-by-email"><?php _e( 'Email', 'bonipress' ); ?></label>
							</li>
						</ol>
						<label class="subheader" for="bonipress-csv-xrate"><?php _e( 'Exchange Rate', 'bonipress' ); ?></label>
						<ol>
							<li>
								<div class="h2"><input type="text" name="xrate" id="bonipress-csv-xrate" value="<?php echo $this->core->format_number( 1 ); ?>" class="short" /> = <?php echo $this->core->format_creds( 1 ); ?></div>
								<span class="description"><?php _e( 'How much is 1 imported value worth?', 'bonipress' ); ?></span>
							</li>
						</ol>
						<ol class="inline">
							<li>
								<label><?php _e( 'Round', 'bonipress' ); ?></label><br />
								<input type="radio" name="round" id="bonipress-csv-round-none" value="none" checked="checked" /> <label for="bonipress-csv-round-none"><?php _e( 'None', 'bonipress' ); ?></label><br />
								<input type="radio" name="round" id="bonipress-csv-round-up" value="up" /> <label for="bonipress-csv-round-up"><?php _e( 'Round Up', 'bonipress' ); ?></label><br />
								<input type="radio" name="round" id="bonipress-csv-round-down" value="down" /> <label for="bonipress-csv-round-down"><?php _e( 'Round Down', 'bonipress' ); ?></label>
							</li>
							<?php if ( $this->core->format['decimals'] > 0 ) { ?>

							<li>
								<label for="bonipress-csv-precision"><?php _e( 'Precision', 'bonipress' ); ?></label>
								<div class="h2"><input type="text" name="precision" id="bonipress-csv-precision" value="1" class="short" /></div>
								<span class="description"><?php echo __( 'The optional number of decimal digits to round to. Use zero to round the nearest whole number.', 'bonipress' ); ?></span>
							</li>
							<?php } ?>

						</ol>
						<label class="subheader" for="bonipress-csv-log-template"><?php _e( 'Log Entry', 'bonipress' ); ?></label>
						<ol>
							<li>
								<div class="h2"><input type="text" name="log_template" id="bonipress-csv-log-template" value="" class="long" /></div>
								<span class="description"><?php _e( 'See the help tab for available template tags. Leave blank to disable.', 'bonipress' ); ?></span>
							</li>
						</ol>
						<ol>
							<li>
								<input type="submit" name="submit" id="bonipress-csv-submit" value="<?php _e( 'Run Import', 'bonipress' ); ?>" class="button button-primary button-large" />
							</li>
						</ol>
					</form>
				</div>
<?php
		}
		
		/**
		 * CubePoints Import Form
		 * @since 0.1
		 * @version 1.0
		 */
		public function cubepoints_form( $data ) {
			$quick_check = get_users( array(
				'meta_key' => 'cpoints',
				'fields'   => 'ID'
			) );
			$cp_users = count( $quick_check ); ?>

				<h4><div class="icon icon-<?php if ( $cp_users > 0 ) echo 'active'; else echo 'inactive'; ?>"></div><label><?php echo $data['title']; ?></label></h4>
				<div class="body" style="display:none;">
					<form class="add:the-list: validate" method="post" enctype="multipart/form-data">
						<input type="hidden" name="selected-import" value="cubepoints" />
						<p><?php

			if ( $cp_users > 0 )
				echo sprintf( __( 'Found %d users with CubePoints.', 'bonipress' ), $cp_users );
			else
				_e( 'No CubePoints found.', 'bonipress' ); ?></p>
						<label class="subheader" for="bonipress-cubepoints-user-meta-key"><?php _e( 'Meta Key', 'bonipress' ); ?></label>
						<ol>
							<li>
								<div class="h2"><input type="text" name="meta_key" id="bonipress-cubepoints-user-meta-key" value="cpoints" class="disabled medium" disabled="disabled" /></div>
							</li>
						</ol>
						<label class="subheader" for="bonipress-cubepoints-xrate"><?php _e( 'Exchange Rate', 'bonipress' ); ?></label>
						<ol>
							<li>
								<div class="h2"><input type="text" name="xrate" id="bonipress-cubepoints-xrate" value="<?php echo $this->core->format_number( 1 ); ?>" class="short" /><?php echo 'CubePoint'; ?> = <?php echo $this->core->format_creds( 1 ); ?></div>
							</li>
						</ol>
						<ol class="inline">
							<li>
								<label><?php _e( 'Round', 'bonipress' ); ?></label><br />
								<input type="radio" name="round" id="bonipress-cubepoints-round-none" value="none" checked="checked" /> <label for="bonipress-cubepoints-round-none"><?php _e( 'Do not round', 'bonipress' ); ?></label><br />
								<input type="radio" name="round" id="bonipress-cubepoints-round-up" value="up" /> <label for="bonipress-cubepoints-round-up"><?php _e( 'Round Up', 'bonipress' ); ?></label><br />
								<input type="radio" name="round" id="bonipress-cubepoints-round-down" value="down" /> <label for="bonipress-cubepoints-round-down"><?php _e( 'Round Down', 'bonipress' ); ?></label>
							</li>
							<?php if ( $this->core->format['decimals'] > 0 ) { ?>

							<li>
								<label for="bonipress-cubepoints-precision"><?php _e( 'Precision', 'bonipress' ); ?></label>
								<div class="h2"><input type="text" name="precision" id="bonipress-cubepoints-precision" value="1" class="short" /></div>
								<span class="description"><?php echo __( 'The optional number of decimal digits to round to. Use zero to round the nearest whole number.', 'bonipress' ); ?></span>
							</li>
							<?php } ?>

						</ol>
						<label class="subheader" for="bonipress-cubepoints-delete"><?php _e( 'After Import', 'bonipress' ); ?></label>
						<ol>
							<li>
								<input type="checkbox" name="delete" id="bonipress-cubepoints-delete" value="no" /> <label for="bonipress-cubepoints-delete"><?php _e( 'Delete users CubePoints balance.', 'bonipress' ); ?></label>
							</li>
						</ol>
						<label class="subheader" for="bonipress-cubepoints-log-template"><?php _e( 'Log Entry', 'bonipress' ); ?></label>
						<ol>
							<li>
								<div class="h2"><input type="text" name="log_template" id="bonipress-cubepoints-log-template" value="" class="long" /></div>
								<span class="description"><?php _e( 'See the help tab for available template tags. Leave blank to disable.', 'bonipress' ); ?></span>
							</li>
						</ol>
						<ol>
							<li>
								<input type="submit" name="submit" id="bonipress-cubepoints-submit" value="<?php _e( 'Run Import', 'bonipress' ); ?>" class="button button-primary button-large" />
							</li>
						</ol>
					</form>
				</div>
<?php
		}

		/**
		 * Custom User Meta Import Form
		 * @since 0.1
		 * @version 1.0
		 */
		public function custom_form( $data ) { ?>

				<h4><div class="icon icon-active"></div><label><?php echo $data['title']; ?></label></h4>
				<div class="body" style="display:none;">
					<form class="add:the-list: validate" method="post" enctype="multipart/form-data">
						<input type="hidden" name="selected-import" value="custom" />
						<p><?php echo nl2br( $this->core->template_tags_general( $data['description'] ) ); ?></p>
						<label class="subheader" for="bonipress-custom-user-meta-key"><?php _e( 'Meta Key', 'bonipress' ); ?></label>
						<ol>
							<li>
								<div class="h2"><input type="text" name="meta_key" id="bonipress-custom-user-meta-key" value="" class="medium" /></div>
							</li>
						</ol>
						<label class="subheader" for="bonipress-custom-xrate"><?php _e( 'Exchange Rate', 'bonipress' ); ?></label>
						<ol>
							<li>
								<div class="h2"><input type="text" name="xrate" id="bonipress-custom-xrate" value="<?php echo $this->core->format_number( 1 ); ?>" class="short" /> = <?php echo $this->core->format_creds( 1 ); ?></div>
							</li>
						</ol>
						<ol class="inline">
							<li>
								<label><?php _e( 'Round', 'bonipress' ); ?></label><br />
								<input type="radio" name="round" id="bonipress-custom-round-none" value="none" checked="checked" /> <label for="bonipress-custom-round-none"><?php _e( 'Do not round', 'bonipress' ); ?></label><br />
								<input type="radio" name="round" id="bonipress-custom-round-up" value="up" /> <label for="bonipress-custom-round-up"><?php _e( 'Round Up', 'bonipress' ); ?></label><br />
								<input type="radio" name="round" id="bonipress-custom-round-down" value="down" /> <label for="bonipress-custom-round-down"><?php _e( 'Round Down', 'bonipress' ); ?></label>
							</li>
							<?php if ( $this->core->format['decimals'] > 0 ) { ?>

							<li>
								<label for="bonipress-custom-precision"><?php _e( 'Precision', 'bonipress' ); ?></label>
								<div class="h2"><input type="text" name="precision" id="bonipress-custom-precision" value="1" class="short" /></div>
								<span class="description"><?php echo __( 'The optional number of decimal digits to round to. Use zero to round the nearest whole number.', 'bonipress' ); ?></span>
							</li>
							<?php } ?>

						</ol>
						<label class="subheader" for="bonipress-custom-log-template"><?php _e( 'Log Entry', 'bonipress' ); ?></label>
						<ol>
							<li>
								<div class="h2"><input type="text" name="log_template" id="bonipress-custom-log-template" value="" class="long" /></div>
								<span class="description"><?php _e( 'See the help tab for available template tags. Leave blank to disable.', 'bonipress' ); ?></span>
							</li>
						</ol>
						<label class="subheader" for="bonipress-custom-delete"><?php _e( 'After Import', 'bonipress' ); ?></label>
						<ol>
							<li>
								<input type="checkbox" name="delete" id="bonipress-custom-delete" value="no" /> <label for="bonipress-custom-delete"><?php _e( 'Delete the old value.', 'bonipress' ); ?></label>
							</li>
						</ol>
						<ol>
							<li>
								<input type="submit" name="submit" id="bonipress-custom-submit" value="<?php _e( 'Run Import', 'bonipress' ); ?>" class="button button-primary button-large" />
							</li>
						</ol>
					</form>
				</div>
<?php
			unset( $this );
		}

		/**
		 * Delete BOM from UTF-8 file.
		 * @see http://wordpress.org/extend/plugins/csv-importer/
		 * @param string $fname
		 * @return void
		 */
		public function strip_BOM( $fname ) {
			$res = fopen( $fname, 'rb' );
			if ( false !== $res ) {
				$bytes = fread( $res, 3 );
				if ( $bytes == pack( 'CCC', 0xef, 0xbb, 0xbf ) ) {
					fclose( $res );

					$contents = file_get_contents( $fname );
					if ( false === $contents ) {
						trigger_error( __( 'Failed to get file contents.', 'bonipress' ), E_USER_WARNING );
					}
					$contents = substr( $contents, 3 );
					$success = file_put_contents( $fname, $contents );
					if ( false === $success ) {
						trigger_error( __( 'Failed to put file contents.', 'bonipress' ), E_USER_WARNING );
					}
				} else {
					fclose( $res );
				}
			}
		}

		/**
		 * Contextual Help
		 * @since 0.1
		 * @version 1.0
		 */
		public function help( $screen_id, $screen ) {
			if ( $screen_id != 'bonipress_page_boniPRESS_page_import' ) return;

			$screen->add_help_tab( array(
				'id'		=> 'bonipress-import',
				'title'		=> __( 'Import', 'bonipress' ),
				'content'	=> '
<p>' . $this->core->template_tags_general( __( 'This add-on lets you import %_plural% either though a CSV-file or from your database. Remember that the import can take time depending on your file size or the number of users being imported.', 'bonipress' ) ) . '</p>'
			) );
			$screen->add_help_tab( array(
				'id'		=> 'bonipress-import-csv',
				'title'		=> __( 'CSV File', 'bonipress' ),
				'content'	=> '
<p><strong>' . __( 'CSV Import', 'bonipress' ) . '</strong></p>
<p>' . __( 'Imports using a comma-separated values file requires the following columns:', 'bonipress' ) . '</p>
<p><code>bonipress_user</code> ' . __( 'Column identifing the user. All rows must identify the user the same way, either using an ID, Username (user_login) or email. Users that can not be found will be ignored.', 'bonipress' ) . '<br />
<code>bonipress_amount</code> ' . __( 'Column with the amount to be imported. If set, an exchange rate is applied to this value before import.', 'bonipress' ) . '</p>
<p>' . __( 'Optionally you can also use the <code>bonipress_log</code> column to pre-define the log entry for each import.', 'bonipress' ) . '</p>'
			) );
			$screen->add_help_tab( array(
				'id'		=> 'bonipress-import-cube',
				'title'		=> __( 'Cubepoints', 'bonipress' ),
				'content'	=> '
<p><strong>' . __( 'Cubepoints Import', 'bonipress' ) . '</strong></p>
<p>' . __( 'When this page loads, the importer will automatically check if you have been using Cubepoints. If you have, you can import these with the option to delete the original Cubepoints once completed to help keep your database clean.', 'bonipress' ) . '</p>
<p>' . __( 'Before a value is imported, you can apply an exchange rate. To import without changing the value, use 1 as the exchange rate.', 'bonipress' ) . '</p>
<p>' . __( 'You can select to add a log entry for each import or leave the template empty to skip.', 'bonipress' ) . '</p>
<p>' . __( 'The Cubepoints importer will automatically disable itself if no Cubepoints installation exists.', 'bonipress' ) . '</p>'
			) );
			$screen->add_help_tab( array(
				'id'		=> 'bonipress-import-custom',
				'title'		=> __( 'Custom User Meta', 'bonipress' ),
				'content'	=> '
<p><strong>' . __( 'Custom User Meta Import', 'bonipress' ) . '</strong></p>
<p>' . __( 'You can import any type of points that have previously been saved in your database. All you need is the meta key under which it has been saved.', 'bonipress' ) . '</p>
<p>' . __( 'Before a value is imported, you can apply an exchange rate. To import without changing the value, use 1 as the exchange rate.', 'bonipress' ) . '</p>
<p>' . __( 'You can select to add a log entry for each import or leave the template empty to skip.', 'bonipress' ) . '</p>
<p>' . __( 'Please note that the meta key is case sensitive and can not contain whitespaces!', 'bonipress' ) . '</p>'
			) );
		}
	}
	$import = new boniPRESS_Import();
	$import->load();
}
?>