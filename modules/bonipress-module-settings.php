<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

/**
 * boniPRESS_Settings_Module class
 * @since 0.1
 * @version 1.5
 */
if ( ! class_exists( 'boniPRESS_Settings_Module' ) ) :
	class boniPRESS_Settings_Module extends boniPRESS_Module {

		/**
		 * Construct
		 */
		public function __construct( $type = BONIPRESS_DEFAULT_TYPE_KEY ) {

			parent::__construct( 'boniPRESS_Settings_Module', array(
				'module_name' => 'general',
				'option_id'   => 'bonipress_pref_core',
				'labels'      => array(
					'menu'        => __( 'Einstellungen', 'bonipress' ),
					'page_title'  => __( 'Einstellungen', 'bonipress' ),
					'page_header' => __( 'Einstellungen', 'bonipress' )
				),
				'screen_id'   => BONIPRESS_SLUG . '-settings',
				'accordion'   => true,
				'menu_pos'    => 998
			), $type );

		}

		/**
		 * Init
		 * @since 1.7
		 * @version 1.0
		 */
		public function module_init() {

			// Delete users log entries when the user is deleted
			if ( isset( $this->core->delete_user ) && $this->core->delete_user )
				add_action( 'delete_user', array( $this, 'action_delete_users_log_entries' ) );

			add_action( 'wp_ajax_bonipress-action-empty-log',       array( $this, 'action_empty_log' ) );
			add_action( 'wp_ajax_bonipress-action-reset-accounts',  array( $this, 'action_reset_balance' ) );
			add_action( 'wp_ajax_bonipress-action-export-balances', array( $this, 'action_export_balances' ) );
			add_action( 'wp_ajax_bonipress-action-generate-key',    array( $this, 'action_generate_key' ) );
			add_action( 'wp_ajax_bonipress-action-max-decimals',    array( $this, 'action_update_log_cred_format' ) );

		}

		/**
		 * Admin Init
		 * @since 1.3
		 * @version 1.0.1
		 */
		public function module_admin_init() {

			if ( isset( $_GET['do'] ) && $_GET['do'] == 'export' )
				$this->load_export();

		}

		/**
		 * Empty Log Action
		 * @since 1.3
		 * @version 1.4
		 */
		public function action_empty_log() {

			// Security
			check_ajax_referer( 'bonipress-management-actions', 'token' );

			// Access
			if ( ! is_user_logged_in() || ! $this->core->user_is_point_admin() )
				wp_send_json_error( 'Zugriff abgelehnt' );

			// Type
			if ( ! isset( $_POST['type'] ) )
				wp_send_json_error( 'Fehlender Punkttyp' );

			$type = sanitize_key( $_POST['type'] );

			global $wpdb, $bonipress_log_table;

			// If we only have one point type we truncate the log
			if ( count( $this->point_types ) == 1 && $type == BONIPRESS_DEFAULT_TYPE_KEY )
				$wpdb->query( "TRUNCATE TABLE {$bonipress_log_table};" );

			// Else we want to delete the selected point types only
			else
				$wpdb->delete(
					$bonipress_log_table,
					array( 'ctype' => $type ),
					array( '%s' )
				);

			// Count results
			$total_rows = $wpdb->get_var( "SELECT COUNT(*) FROM {$bonipress_log_table} WHERE ctype = '{$type}';" );
			$wpdb->flush();

			// Response
			wp_send_json_success( $total_rows );

		}

		/**
		 * Reset All Balances Action
		 * @since 1.3
		 * @version 1.4.1
		 */
		public function action_reset_balance() {

			// Type
			if ( ! isset( $_POST['type'] ) )
				wp_send_json_error( 'Fehlender Punkttyp' );

			$type = sanitize_key( $_POST['type'] );
			if ( $type != $this->bonipress_type ) return;

			// Security
			check_ajax_referer( 'bonipress-management-actions', 'token' );

			// Access
			if ( ! is_user_logged_in() || ! $this->core->user_is_point_admin() )
				wp_send_json_error( 'Access denied' );

			global $wpdb;

			$wpdb->delete(
				$wpdb->usermeta,
				array( 'meta_key' => bonipress_get_meta_key( $type, '' ) ),
				array( '%s' )
			);

			$wpdb->delete(
				$wpdb->usermeta,
				array( 'meta_key' => bonipress_get_meta_key( $type, '_total' ) ),
				array( '%s' )
			);

			do_action( 'bonipress_zero_balances', $type );

			// Response
			wp_send_json_success( __( 'Konten erfolgreich zurückgesetzt', 'bonipress' ) );

		}

		/**
		 * Export User Balances
		 * @filter bonipress_export_raw
		 * @since 1.3
		 * @version 1.2
		 */
		public function action_export_balances() {

			// Security
			check_ajax_referer( 'bonipress-management-actions', 'token' );

			global $wpdb;

			// Protokollvorlage
			$log  = sanitize_text_field( $_POST['log_temp'] );

			// Type
			if ( ! isset( $_POST['type'] ) )
				wp_send_json_error( 'Missing point type' );

			$type = sanitize_text_field( $_POST['type'] );

			// Identify users by
			switch ( $_POST['identify'] ) {

				case 'ID' :

					$SQL = "SELECT user_id AS user, meta_value AS balance FROM {$wpdb->usermeta} WHERE meta_key = %s;";

				break;

				case 'email' :

					$SQL = "SELECT user_email AS user, meta_value AS balance FROM {$wpdb->usermeta} LEFT JOIN {$wpdb->users} ON {$wpdb->usermeta}.user_id = {$wpdb->users}.ID WHERE {$wpdb->usermeta}.meta_key = %s;";

				break;

				case 'login' :

					$SQL = "SELECT user_login AS user, meta_value AS balance FROM {$wpdb->usermeta} LEFT JOIN {$wpdb->users} ON {$wpdb->usermeta}.user_id = {$wpdb->users}.ID WHERE {$wpdb->usermeta}.meta_key = %s;";

				break;

			}

			$query = $wpdb->get_results( $wpdb->prepare( $SQL, $type ) );

			if ( empty( $query ) )
				wp_send_json_error( __( 'Es wurden keine Benutzer zum Exportieren gefunden', 'bonipress' ) );

			$array = array();
			foreach ( $query as $result ) {
				$data = array(
					'bonipress_user'   => $result->user,
					'bonipress_amount' => $this->core->number( $result->balance ),
					'bonipress_ctype'  => $type
				);

				if ( ! empty( $log ) )
					$data = array_merge_recursive( $data, array( 'bonipress_log' => $log ) );

				$array[] = $data;
			}

			set_transient( 'bonipress-export-raw', apply_filters( 'bonipress_export_raw', $array ), 3000 );

			// Response
			wp_send_json_success( admin_url( 'admin.php?page=' . BONIPRESS_SLUG . '-settings&do=export' ) );

		}

		/**
		 * Generate Key Action
		 * @since 1.3
		 * @version 1.1
		 */
		public function action_generate_key() {

			// Security
			check_ajax_referer( 'bonipress-management-actions', 'token' );

			// Response
			wp_send_json_success( wp_generate_password( 16, true, true ) );

		}

		/**
		 * Update Log Cred Format Action
		 * Will attempt to modify the boniPRESS log's cred column format.
		 * @since 1.6
		 * @version 1.0.1
		 */
		public function action_update_log_cred_format() {

			// Security
			check_ajax_referer( 'bonipress-management-actions', 'token' );

			if ( ! isset( $_POST['decimals'] ) || $_POST['decimals'] == '' || $_POST['decimals'] > 20 )
				wp_send_json_error( __( 'Ungültiger Dezimalwert.', 'bonipress' ) );

			if ( ! $this->is_main_type )
				wp_send_json_error( 'Ungültige Verwendung' );

			$decimals = absint( $_POST['decimals'] );

			global $wpdb, $bonipress_log_table;

			if ( $decimals > 0 ) {
				$format = 'decimal';
				if ( $decimals > 4 )
					$cred_format = "decimal(32,$decimals)";
				else
					$cred_format = "decimal(22,$decimals)";
			}
			else {
				$format      = 'bigint';
				$cred_format = 'bigint(22)';
			}

			// Alter table
			$results = $wpdb->query( "ALTER TABLE {$bonipress_log_table} MODIFY creds {$cred_format} DEFAULT NULL;" );

			// If we selected no decimals and we have multiple point types, we need to update
			// their settings to also use no decimals.
			if ( $decimals == 0 && count( $this->point_types ) > 1 ) {
				foreach ( $this->point_types as $type_id => $label ) {

					$new_type_core = bonipress_get_option( 'bonipress_pref_core_' . $type_id );
					if ( ! isset( $new_type_core['format']['decimals'] ) ) continue;

					$new_type_core['format']['type']     = $format;
					$new_type_core['format']['decimals'] = 0;
					bonipress_update_option( 'bonipress_pref_core_' . $type_id, $new_type_core );

				}
			}

			// Save settings
			$new_core                       = $this->core->core;
			$new_core['format']['type']     = $format;
			$new_core['format']['decimals'] = $decimals;
			bonipress_update_option( 'bonipress_pref_core', $new_core );

			// Send the good news
			wp_send_json_success( array(
				'url'   => esc_url( add_query_arg( array( 'page' => BONIPRESS_SLUG . '-settings', 'open-tab' => 0 ), admin_url( 'admin.php' ) ) ),
				'label' => __( 'Protokoll aktualisiert', 'bonipress' )
			) );

		}

		/**
		 * Load Export
		 * Creates a CSV export file of the 'bonipress-export-raw' transient.
		 * @since 1.3
		 * @version 1.1
		 */
		public function load_export() {

			// Security
			if ( $this->core->user_is_point_editor() ) {

				$export = get_transient( 'bonipress-export-raw' );
				if ( $export === false ) return;

				if ( isset( $export[0]['bonipress_log'] ) )
					$headers = array( 'bonipress_user', 'bonipress_amount', 'bonipress_ctype', 'bonipress_log' );
				else
					$headers = array( 'bonipress_user', 'bonipress_amount', 'bonipress_ctype' );	

				require_once boniPRESS_ASSETS_DIR . 'libs/parsecsv.lib.php';
				$csv = new parseCSV();

				delete_transient( 'bonipress-export-raw' );
				$csv->output( true, 'bonipress-balance-export.csv', $export, $headers );

				die;

			}

		}

		/**
		 * Delete Users Log Entries
		 * Will remove a given users log entries.
		 * @since 1.4
		 * @version 1.1
		 */
		public function action_delete_users_log_entries( $user_id ) {

			global $wpdb, $bonipress_log_table;

			$wpdb->delete(
				$bonipress_log_table,
				array( 'user_id' => $user_id, 'ctype' => $this->bonipress_type ),
				array( '%d', '%s' )
			);

		}

		/**
		 * Scripts & Styles
		 * @since 1.7
		 * @version 1.0
		 */
		public function scripts_and_styles() {

			wp_register_script(
				'bonipress-type-management',
				plugins_url( 'assets/js/bonipress-type-management.js', boniPRESS_THIS ),
				array( 'jquery', 'jquery-ui-core', 'jquery-ui-dialog', 'jquery-effects-core', 'jquery-effects-slide' ),
				boniPRESS_VERSION . '.1'
			);

		}

		/**
		 * Settings Header
		 * Inserts the export styling
		 * @since 1.3
		 * @version 1.2.2
		 */
		public function settings_header() {

			global $wp_filter, $bonipress;

			// Allows to link to the settings page with a defined module to be opened
			// in the accordion. Request must be made under the "open-tab" key and should
			// be the module name in lowercase with the boniPRESS_ removed.
			$this->accordion_tabs = array( 'core' => 0, 'management' => 1, 'point-types' => 2, 'exports_module' => 3 );

			// Check if there are registered action hooks for bonipress_after_core_prefs
			$count = 3;
			if ( isset( $wp_filter['bonipress_after_core_prefs'] ) ) {

				// If remove access is enabled
				$settings = bonipress_get_remote();
				if ( $settings['enabled'] )
					$this->accordion_tabs['remote'] = $count++;

				foreach ( $wp_filter['bonipress_after_core_prefs'] as $priority ) {

					foreach ( $priority as $key => $data ) {

						if ( ! isset( $data['function'] ) ) continue;

						if ( ! is_array( $data['function'] ) )
							$this->accordion_tabs[ $data['function'] ] = $count++;

						else {

							foreach ( $data['function'] as $id => $object ) {

								if ( isset( $object->module_id ) ) {
									$module_id = str_replace( 'boniPRESS_', '', $object->module_id );
									$module_id = strtolower( $module_id );
									$this->accordion_tabs[ $module_id ] = $count++;
								}

							}

						}

					}

				}

			}

			// If the requested tab exists, localize the accordion script to open this tab.
			// For this to work, the variable "active" must be set to the position of the
			// tab starting with zero for "Core".
			if ( isset( $_REQUEST['open-tab'] ) && array_key_exists( $_REQUEST['open-tab'], $this->accordion_tabs ) )
				wp_localize_script( 'bonipress-accordion', 'boniPRESS', array( 'active' => $this->accordion_tabs[ $_REQUEST['open-tab'] ] ) );

			wp_localize_script(
				'bonipress-type-management',
				'boniPRESSmanage',
				array(
					'ajaxurl'       => admin_url( 'admin-ajax.php' ),
					'token'         => wp_create_nonce( 'bonipress-management-actions' ),
					'cache'         => wp_create_nonce( 'bonipress-clear-cache' ),
					'working'       => esc_attr__( 'Wird bearbeitet...', 'bonipress' ),
					'confirm_log'   => esc_attr__( 'Warnung! Alle Einträge in Deinem Log werden dauerhaft entfernt! Das kann nicht rückgängig gemacht werden!', 'bonipress' ),
					'confirm_clean' => esc_attr__( 'Alle Protokolleinträge gelöschter Benutzer werden dauerhaft gelöscht! Das kann nicht rückgängig gemacht werden!', 'bonipress' ),
					'confirm_reset' => esc_attr__( 'Warnung! Alle Benutzerguthaben werden auf Null gesetzt! Das kann nicht rückgängig gemacht werden!', 'bonipress' ),
					'done'          => esc_attr__( 'Erledigt!', 'bonipress' ),
					'export_close'  => esc_attr__( 'Close', 'bonipress' ),
					'export_title'  => $bonipress->template_tags_general( esc_attr__( '%singular% Salden exportieren', 'bonipress' ) ),
					'decimals'      => esc_attr__( 'Um die Anzahl der Dezimalstellen anzupassen, die Du verwenden möchtest, müssen wir Dein Protokoll aktualisieren. Es wird dringend empfohlen, dass Du Dein aktuelles Protokoll sicherst, bevor Du fortfährst!', 'bonipress' )
				)
			);
			wp_enqueue_script( 'bonipress-type-management' );

			wp_enqueue_style( 'bonipress-admin' );
			wp_enqueue_style( 'bonipress-bootstrap-grid' );
			wp_enqueue_style( 'bonipress-forms' );

			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );

		}

		/**
		 * Adjust Decimal Places Settings
		 * @since 1.6
		 * @version 1.0.2
		 */
		public function adjust_decimal_places() {

			// Main point type = allow db adjustment
			if ( $this->is_main_type ) {

?>
<div><input type="number" min="0" max="20" id="bonipress-adjust-decimal-places" class="form-control" value="<?php echo esc_attr( $this->core->format['decimals'] ); ?>" data-org="<?php echo $this->core->format['decimals']; ?>" size="8" /> <input type="button" style="display:none;" id="bonipress-update-log-decimals" class="button button-primary button-large" value="<?php _e( 'Datenbank aktualisieren', 'bonipress' ); ?>" /></div>
<?php

			}
			// Other point type.
			else {

				$default = bonipress();
				if ( $default->format['decimals'] == 0 ) {

?>
<div><?php _e( 'Keine Dezimalstellen', 'bonipress' ); ?></div>
<?php

				}
				else {

?>
<select name="<?php echo $this->field_name( array( 'format' => 'decimals' ) ); ?>" id="<?php echo $this->field_id( array( 'format' => 'decimals' ) ); ?>" class="form-control">
<?php

					echo '<option value="0"';
					if ( $this->core->format['decimals'] == 0 ) echo ' selected="selected"';
					echo '>' . __( 'Keine Dezimalstellen', 'bonipress' ) . '</option>';

					for ( $i = 1 ; $i <= $default->format['decimals'] ; $i ++ ) {
						echo '<option value="' . $i . '"';
						if ( $this->core->format['decimals'] == $i ) echo ' selected="selected"';
						echo '>' . $i . ' - 0.' . str_pad( '0', $i, '0' ) . '</option>';
					}

					$url = add_query_arg( array( 'page' => BONIPRESS_SLUG . '-settings', 'open-tab' => 0 ), admin_url( 'admin.php' ) );

?>
</select>
<p><span class="description"><?php printf( __( '<a href="%s">Klicke hier</a>, um Deine Standardeinstellung für Punkttypen zu ändern.', 'bonipress' ), esc_url( $url ) ); ?></span></p>
<?php

				}

			}

		}

		/**
		 * Admin Page
		 * @since 0.1
		 * @version 1.5
		 */
		public function admin_page() {

			// Security
			if ( ! $this->core->user_is_point_admin() ) wp_die( 'Zugriff abgelehnt' );

			// General Settings
			$general     = $this->general;
			$action_hook = ( ! $this->is_main_type ) ? $this->bonipress_type : '';
			$delete_user = ( isset( $this->core->delete_user ) ) ? $this->core->delete_user : 0;


?>
<div class="wrap bonipress-metabox" id="boniPRESS-wrap">
	<h1><?php _e( 'Einstellungen', 'bonipress' ); if ( BONIPRESS_DEFAULT_LABEL === 'boniPRESS' ) : ?> <a href="https://n3rds.work/docs/bonipress-dokumentation/" target="_blank" class="page-title-action"><?php _e( 'Dokumentation', 'bonipress' ); ?></a><?php endif; ?></h1>

	<?php $this->update_notice(); ?>


	<form method="post" action="options.php" class="form" name="bonipress-core-settings-form" novalidate>

		<?php settings_fields( $this->settings_name ); ?>

		<div class="list-items expandable-li" id="accordion">
			<h4><span class="dashicons dashicons-admin-settings static"></span><label><?php _e( 'Basis-Einstellungen', 'bonipress' ); ?></label></h4>
			<div class="body" style="display:none;">

				<div class="row">
					<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
						<h3><?php _e( 'Etiketten', 'bonipress' ); ?></h3>
						<div class="row">
							<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
								<div class="form-group">
									<label for="<?php echo $this->field_id( array( 'name' => 'singular' ) ); ?>"><?php _e( 'Einzahl', 'bonipress' ); ?></label>
									<input type="text" name="<?php echo $this->field_name( array( 'name' => 'singular' ) ); ?>" id="<?php echo $this->field_id( array( 'name' => 'singular' ) ); ?>" class="form-control" placeholder="<?php _e( 'Erforderlich', 'bonipress' ); ?>" value="<?php echo esc_attr( $this->core->name['singular'] ); ?>" />
								</div>
							</div>
							<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
								<div class="form-group">
									<label for="<?php echo $this->field_id( array( 'name' => 'plural' ) ); ?>"><?php _e( 'Mehrzahl', 'bonipress' ); ?></label>
									<input type="text" name="<?php echo $this->field_name( array( 'name' => 'plural' ) ); ?>" id="<?php echo $this->field_id( array( 'name' => 'plural' ) ); ?>" class="form-control" placeholder="<?php _e( 'Erforderlich', 'bonipress' ); ?>" value="<?php echo esc_attr( $this->core->name['plural'] ); ?>" />
								</div>
							</div>
						</div>
						<p><span class="description"><?php _e( 'Diese Beschriftungen werden im gesamten Administrationsbereich und bei der Präsentation von Punkten für Deine Benutzer verwendet.', 'bonipress' ); ?></span></p>
					</div>
					<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
						<h3><?php _e( 'Format', 'bonipress' ); ?></h3>
						<div class="row">
							<div class="col-lg-2 col-md-2 col-sm-12 col-xs-12">
								<div class="form-group">
									<label for="<?php echo $this->field_id( 'before' ); ?>"><?php _e( 'Präfix', 'bonipress' ); ?></label>
									<input type="text" name="<?php echo $this->field_name( 'before' ); ?>" id="<?php echo $this->field_id( 'before' ); ?>" class="form-control" value="<?php echo esc_attr( $this->core->before ); ?>" />
								</div>
							</div>
							<div class="col-lg-5 col-md-5 col-sm-12 col-xs-12">
								<div class="form-group">
									<label for="<?php echo $this->field_id( array( 'format' => 'separators' ) ); ?>-thousand"><?php _e( 'Trennzeichen', 'bonipress' ); ?></label>
									<div class="form-inline">
										<label>1</label> <input type="text" name="<?php echo $this->field_name( array( 'format' => 'separators' ) ); ?>[thousand]" id="<?php echo $this->field_id( array( 'format' => 'separators' ) ); ?>-thousand" placeholder="," class="form-control" size="2" value="<?php echo esc_attr( $this->core->format['separators']['thousand'] ); ?>" /> <label>000</label> <input type="text" name="<?php echo $this->field_name( array( 'format' => 'separators' ) ); ?>[decimal]" id="<?php echo $this->field_id( array( 'format' => 'separators' ) ); ?>-decimal" placeholder="." class="form-control" size="2" value="<?php echo esc_attr( $this->core->format['separators']['decimal'] ); ?>" /> <label>00</label>
									</div>
								</div>
							</div>
							<div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
								<div class="form-group">
									<label for=""><?php _e( 'Dezimalstellen', 'bonipress' ); ?></label>
									<?php $this->adjust_decimal_places(); ?>
								</div>
							</div>
							<div class="col-lg-2 col-md-2 col-sm-12 col-xs-12">
								<div class="form-group">
									<label for="<?php echo $this->field_id( 'after' ); ?>"><?php _e( 'Suffix', 'bonipress' ); ?></label>
									<input type="text" name="<?php echo $this->field_name( 'after' ); ?>" id="<?php echo $this->field_id( 'after' ); ?>" class="form-control" value="<?php echo esc_attr( $this->core->after ); ?>" />
								</div>
							</div>
						</div>
						<p><span class="description"><?php _e( 'Setze Dezimalstellen auf Null, wenn Du ganze Zahlen verwenden möchtest.', 'bonipress' ); ?></span></p>
						<?php if ( $this->is_main_type ) : ?>
						<p><strong><?php _e( 'Tipp', 'bonipress' ); ?>:</strong> <?php _e( 'Da dies Dein Hauptpunkttyp ist, ist der hier ausgewählte Wert die größte Anzahl von Dezimalstellen, die Deine Installation unterstützt.', 'bonipress' ); ?></span></p>
						<?php endif; ?>
					</div>
				</div>
				
				<div class="row">
					<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
						<h3><?php _e( 'Sicherheit', 'bonipress' ); ?></h3>
						<div class="row">
							<div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
								<div class="form-group">
									<label for="<?php echo $this->field_id( array( 'caps' => 'creds' ) ); ?>"><?php _e( 'Punkteditoren', 'bonipress' ); ?></label>
									<input type="text" name="<?php echo $this->field_name( array( 'caps' => 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'caps' => 'creds' ) ); ?>" class="form-control" placeholder="<?php _e( 'Erforderlich', 'bonipress' ); ?>" value="<?php echo esc_attr( $this->core->caps['creds'] ); ?>" />
									<p><span class="description"><?php _e( 'Die Fähigkeit von Benutzern, die Salden bearbeiten können.', 'bonipress' ); ?></span></p>
								</div>
							</div>
							<div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
								<div class="form-group">
									<label for="<?php echo $this->field_id( array( 'caps' => 'plugin' ) ); ?>"><?php _e( 'Punktadministratoren', 'bonipress' ); ?></label>
									<input type="text" name="<?php echo $this->field_name( array( 'caps' => 'plugin' ) ); ?>" id="<?php echo $this->field_id( array( 'caps' => 'plugin' ) ); ?>" class="form-control" placeholder="<?php _e( 'Erforderlich', 'bonipress' ); ?>" value="<?php echo esc_attr( $this->core->caps['plugin'] ); ?>" />
									<p><span class="description"><?php _e( 'Die Fähigkeit von Benutzern, die Einstellungen bearbeiten können.', 'bonipress' ); ?></span></p>
								</div>
							</div>
							<div class="col-lg-2 col-md-2 col-sm-12 col-xs-12">
								<div class="form-group">
									<?php if ( ! isset( $this->core->max ) ) $this->core->max(); ?>
									<label for="<?php echo $this->field_id( 'max' ); ?>"><?php _e( 'Max. Betrag', 'bonipress' ); ?></label>
									<input type="text" name="<?php echo $this->field_name( 'max' ); ?>" id="<?php echo $this->field_id( 'max' ); ?>" class="form-control" value="<?php echo esc_attr( $this->core->max ); ?>" />
									<p><span class="description"><?php _e( 'Der maximale Betrag, der in einer einzelnen Instanz ausgezahlt werden darf.', 'bonipress' ); ?></span></p>
								</div>
							</div>
							<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
								<div class="form-group">
									<label for="<?php echo $this->field_id( array( 'exclude' => 'list' ) ); ?>"><?php _e( 'Benutzer nach ID ausschließen', 'bonipress' ); ?></label>
									<input type="text" name="<?php echo $this->field_name( array( 'exclude' => 'list' ) ); ?>" id="<?php echo $this->field_id( array( 'exclude' => 'list' ) ); ?>" placeholder="<?php _e( 'Optional', 'bonipress' ); ?>" class="form-control" value="<?php echo esc_attr( $this->core->exclude['list'] ); ?>" />
									<p><span class="description"><?php _e( 'Durch Kommas getrennte Liste von Benutzer-IDs, die von der Verwendung dieses Punkttyps ausgeschlossen werden sollen.', 'bonipress' ); ?></span></p>
								</div>
								<div class="form-group">
									<div class="checkbox">
										<label for="<?php echo $this->field_id( array( 'exclude' => 'cred_editors' ) ); ?>"><input type="checkbox" name="<?php echo $this->field_name( array( 'exclude' => 'cred_editors' ) ); ?>" id="<?php echo $this->field_id( array( 'exclude' => 'cred_editors' ) ); ?>"<?php checked( $this->core->exclude['cred_editors'], 1 ); ?> value="1" /> <?php _e( 'Punkteditoren ausschließen', 'bonipress' ); ?></label>
									</div>
									<div class="checkbox">
										<label for="<?php echo $this->field_id( array( 'exclude' => 'plugin_editors' ) ); ?>"><input type="checkbox" name="<?php echo $this->field_name( array( 'exclude' => 'plugin_editors' ) ); ?>" id="<?php echo $this->field_id( array( 'exclude' => 'plugin_editors' ) ); ?>"<?php checked( $this->core->exclude['plugin_editors'], 1 ); ?> value="1" /> <?php _e( 'Punktadministratoren ausschließen', 'bonipress' ); ?></label>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
				
				<div class="row">
					<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
						<h3><?php _e( 'Andere Einstellungen', 'bonipress' ); ?></h3>
						<div class="form-group">
							<label for="<?php echo $this->field_id( 'delete_user' ); ?>"><input type="checkbox" name="<?php echo $this->field_name( 'delete_user' ); ?>" id="<?php echo $this->field_id( 'delete_user' ); ?>" <?php checked( $delete_user, 1 ); ?> value="1" /> <?php _e( 'Lösche Protokolleinträge, wenn der Benutzer gelöscht wird.', 'bonipress' ); ?></label>
						</div>
					</div>
					<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
						<?php do_action( 'bonipress_core_prefs' . $action_hook, $this ); ?>
					</div>
				</div>
			</div>
<?php

			global $wpdb, $bonipress_log_table;

			$total_rows  = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$bonipress_log_table} WHERE ctype = %s;", $this->bonipress_type ) );
			$reset_block = false;

			if ( get_transient( 'bonipress-accounts-reset' ) !== false )
				$reset_block = true;

?>
			<h4><span class="dashicons dashicons-dashboard static"></span><label><?php _e( 'Management', 'bonipress' ); ?></label></h4>
			<div class="body" style="display:none;">

				<div class="row">
					<div class="col-lg-5 col-md-5 col-sm-12 col-xs-12">
						<div class="form-group">
							<label>Log Table</label>
							<h1><?php echo esc_attr( $bonipress_log_table ); ?></h1>
						</div>
					</div>
					<div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
						<div class="form-group">
							<label><?php _e( 'Einträge', 'bonipress' ); ?></label>
							<h1><?php echo $total_rows; ?></h1>
						</div>
					</div>
					<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
						<div class="form-group">
							<label><?php _e( 'Aktionen', 'bonipress' ); ?></label>
							<div>
								<?php if ( ( ! bonipress_centralize_log() ) || ( bonipress_centralize_log() && $GLOBALS['blog_id'] == 1 ) ) : ?>
								<button type="button" id="bonipress-manage-action-empty-log" data-type="<?php echo $this->bonipress_type; ?>" class="button button-large large <?php if ( $total_rows == 0 ) echo '"disabled="disabled'; else echo 'button-primary'; ?>"><?php _e( 'Leeres Protokoll', 'bonipress' ); ?></button>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>

				<div class="row">
					<div class="col-lg-5 col-md-5 col-sm-12 col-xs-12">
						<div class="form-group">
							<label><?php _e( 'Balance Metaschlüssel', 'bonipress' ); ?></label>
							<h1><?php echo $this->core->cred_id; ?></h1>
						</div>
					</div>
					<div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
						<div class="form-group">
							<label><?php _e( 'Benutzer', 'bonipress' ); ?></label>
							<h1><?php echo $this->core->count_members(); ?></h1>
						</div>
					</div>
					<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
						<div class="form-group">
							<label><?php _e( 'Aktionen', 'bonipress' ); ?></label>
							<div>
								<button type="button" id="bonipress-manage-action-reset-accounts" data-type="<?php echo $this->bonipress_type; ?>" class="button button-large large <?php if ( $reset_block ) echo '" disabled="disabled'; else echo 'button-primary'; ?>"><?php _e( 'Setze alles auf Null', 'bonipress' ); ?></button> 
								<button type="button" id="bonipress-export-users-points" data-type="<?php echo $this->bonipress_type; ?>" class="button button-large large"><?php _e( 'Exportiere Guthaben', 'bonipress' ); ?></button>
							</div>
						</div>
					</div>
				</div>

				<?php do_action( 'bonipress_management_prefs' . $action_hook, $this ); ?>

			</div>

			<?php do_action( 'bonipress_after_management_prefs' . $action_hook, $this ); ?>

<?php

			if ( isset( $this->bonipress_type ) && $this->bonipress_type == BONIPRESS_DEFAULT_TYPE_KEY ) :

?>
			<h4><span class="dashicons dashicons-star-filled static"></span><label><?php _e( 'Punkttypen', 'bonipress' ); ?></label></h4>
			<div class="body" style="display:none;">
<?php

				if ( ! empty( $this->point_types ) ) {

					foreach ( $this->point_types as $type => $label ) {

						if ( $type == BONIPRESS_DEFAULT_TYPE_KEY ) {

?>
				<div class="row">
					<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
						<div class="form-group">
							<label><?php _e( 'Metaschlüssel', 'bonipress' ); ?></label>
							<input type="text" disabled="disabled" class="form-control" value="<?php echo esc_attr( $type ); ?>" class="readonly" />
						</div>
					</div>
					<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
						<div class="form-group">
							<label><?php _e( 'Beschriftung', 'bonipress' ); ?></label>
							<input type="text" disabled="disabled" class="form-control" value="<?php echo strip_tags( $label ); ?>" class="readonly" />
						</div>
					</div>
					<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
						<div class="form-group">
							<label>&nbsp;</label>
							<label><input type="checkbox" disabled="disabled" class="disabled" value="<?php echo esc_attr( $type ); ?>" /> <?php _e( 'Löschen', 'bonipress' ); ?></label>
						</div>
					</div>
				</div>
<?php

						}
						else {

?>
				<div class="row">
					<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
						<div class="form-group">
							<label><?php _e( 'Metaschlüssel', 'bonipress' ); ?></label>
							<input type="text" name="bonipress_pref_core[types][<?php echo esc_attr( $type ); ?>][key]" value="<?php echo esc_attr( $type ); ?>" class="form-control" />
						</div>
					</div>
					<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
						<div class="form-group">
							<label><?php _e( 'Beschriftung', 'bonipress' ); ?></label>
							<input type="text" name="bonipress_pref_core[types][<?php echo esc_attr( $type ); ?>][label]" value="<?php echo strip_tags( $label ); ?>" class="form-control" />
						</div>
					</div>
					<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
						<div class="form-group">
							<label>&nbsp;</label>
							<label for="bonipress-point-type-<?php echo esc_attr( $type ); ?>"><input type="checkbox" name="bonipress_pref_core[delete_types][]" id="bonipress-point-type-<?php echo esc_attr( $type ); ?>" value="<?php echo esc_attr( $type ); ?>" /> <?php _e( 'Löschen', 'bonipress' ); ?></label>
						</div>
					</div>
				</div>
<?php

						}

					}

				}

?>
				<h3><?php _e( 'Neuen Typ hinzufügen', 'bonipress' ); ?></h3>
				<div class="row">
					<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
						<div class="form-group">
							<label for="bonipress-new-ctype-key-value"><?php _e( 'Metaschlüssel', 'bonipress' ); ?></label>
							<input type="text" id="bonipress-new-ctype-key-value" name="bonipress_pref_core[types][new][key]" placeholder="<?php _e( 'Erforderlich', 'bonipress' ); ?>" value="" class="form-control" />
						</div>
					</div>
					<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
						<div class="form-group">
							<label for="bonipress-new-ctype-key-label"><?php _e( 'Beschriftung', 'bonipress' ); ?></label>
							<input type="text" id="bonipress-new-ctype-key-label" name="bonipress_pref_core[types][new][label]" placeholder="<?php _e( 'Erforderlich', 'bonipress' ); ?>" value="" class="form-control" />
						</div>
					</div>
					<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12"></div>
				</div>
				<p id="bonipress-ctype-warning"><strong><?php _e( 'Der Metaschlüssel muss klein geschrieben sein und darf nur Buchstaben oder Unterstriche enthalten. Alle anderen Zeichen werden gelöscht!', 'bonipress' ); ?></strong></p>
			</div>
<?php

			endif;

?>

			<?php do_action( 'bonipress_after_core_prefs' . $action_hook, $this ); ?>

		</div>

		<?php submit_button( __( 'Aktualisiere Einstellungen', 'bonipress' ), 'primary large', 'submit' ); ?>

	</form>

	<?php do_action( 'bonipress_bottom_settings_page' . $action_hook, $this ); ?>

	<div id="export-points" style="display:none;">
		<div class="bonipress-container">

			<div class="form bonipress-metabox">
				<div class="row">
					<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
						<div class="form-group">
							<label><?php _e( 'Benutzer identifizieren durch', 'bonipress' ); ?></label>
							<select id="bonipress-export-identify-by" class="form-control">
<?php

			// Identify users by...
			$identify = apply_filters( 'bonipress_export_by', array(
				'ID'    => __( 'Benutzer ID', 'bonipress' ),
				'email' => __( 'Benutzer Email', 'bonipress' ),
				'login' => __( 'Benutzer Login', 'bonipress' )
			) );

			foreach ( $identify as $id => $label )
				echo '<option value="' . $id . '">' . $label . '</option>';

?>
							</select>
							<span class="description"><?php _e( 'Verwende ID, wenn Du diesen Export als Backup Deiner aktuellen Webseite verwenden möchtest, während E-Mail empfohlen wird, wenn Du auf eine andere Webseite exportieren möchtest.', 'bonipress' ); ?></span>
						</div>
					</div>
					<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
						<div class="form-group">
							<label><?php _e( 'Protokolleintrag importieren', 'bonipress' ); ?></label>
							<input type="text" id="bonipress-export-log-template" value="" class="regular-text form-control" />
							<span class="description"><?php echo sprintf( __( 'Optionaler Protokolleintrag, der verwendet werden soll, wenn Du diese Datei in einer anderen %s-Installation importieren möchtest.', 'bonipress' ), bonipress_label() ); ?></span>
						</div>
					</div>
				</div>	

				<div class="row last">
					<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 text-right">
						<input type="button" id="bonipress-run-exporter" value="<?php _e( 'Exportieren', 'bonipress' ); ?>" data-type="<?php echo $this->bonipress_type; ?>" class="button button-large button-primary" />
					</div>
				</div>
			</div>

		</div>
	</div>

</div>
<?php

		}

		/**
		 * Maybe Whitespace
		 * Since we want to allow a single whitespace in the string and sanitize_text_field() removes this whitespace
		 * this little method will make sure that whitespace is still there and that we still can sanitize the field.
		 * @since 0.1
		 * @version 1.0
		 */
		public function maybe_whitespace( $string ) {

			if ( strlen( $string ) > 1 )
				return '';

			return $string;

		}

		/**
		 * Sanititze Settings
		 * @filter 'bonipress_save_core_prefs'
		 * @since 0.1
		 * @version 1.5.1
		 */
		public function sanitize_settings( $post ) {

			$new_data = array();

			if ( $this->bonipress_type == BONIPRESS_DEFAULT_TYPE_KEY ) {
				if ( isset( $post['types'] ) ) {

					$types = array( BONIPRESS_DEFAULT_TYPE_KEY => bonipress_label() );
					foreach ( $post['types'] as $item => $data ) {

						// Make sure it is not marked as deleted
						if ( isset( $post['delete_types'] ) && in_array( $item, $post['delete_types'] ) ) {

							do_action( 'bonipress_delete_point_type', $data['key'] );
							do_action( 'bonipress_delete_point_type_' . $data['key'] );
							continue;

						}

						// Skip if empty
						if ( empty( $data['key'] ) || empty( $data['label'] ) ) continue;

						// Add if not in array already
						if ( ! array_key_exists( $data['key'], $types ) ) {

							$key           = str_replace( array( ' ', '-' ), '_', $data['key'] );
							$key           = sanitize_key( $key );

							$types[ $key ] = sanitize_text_field( $data['label'] );

						}

					}

					bonipress_update_option( 'bonipress_types', $types );
					unset( $post['types'] );

					if ( isset( $post['delete_types'] ) )
						unset( $post['delete_types'] );

				}

				$new_data['format'] = $this->core->core['format'];

				if ( isset( $post['format']['type'] ) && $post['format']['type'] != '' )
					$new_data['format']['type'] = absint( $post['format']['type'] );

				if ( isset( $post['format']['decimals'] ) )
					$new_data['format']['decimals'] = absint( $post['format']['decimals'] );

			}
			else {

				$main_settings      = bonipress_get_option( 'bonipress_pref_core' );
				$new_data['format'] = $main_settings['format'];

				if ( isset( $post['format']['decimals'] ) ) {

					$new_decimals = absint( $post['format']['decimals'] );
					if ( $new_decimals <= $main_settings['format']['decimals'] )
						$new_data['format']['decimals'] = $new_decimals;

				}

			}

			// Format
			$new_data['cred_id'] = $this->bonipress_type;

			$new_data['format']['separators']['decimal']  = $this->maybe_whitespace( $post['format']['separators']['decimal'] );
			$new_data['format']['separators']['thousand'] = $this->maybe_whitespace( $post['format']['separators']['thousand'] );

			// Name
			$new_data['name']    = array(
				'singular'          => sanitize_text_field( $post['name']['singular'] ),
				'plural'            => sanitize_text_field( $post['name']['plural'] )
			);

			// Look
			$new_data['before']  = sanitize_text_field( $post['before'] );
			$new_data['after']   = sanitize_text_field( $post['after'] );

			// Capabilities
			$new_data['caps']    = array(
				'plugin'            => sanitize_text_field( $post['caps']['plugin'] ),
				'creds'             => sanitize_text_field( $post['caps']['creds'] )
			);

			// Max
			$new_data['max']     = $this->core->number( $post['max'] );

			// Make sure multisites uses capabilities that exists
			if ( in_array( $new_data['caps']['plugin'], array( 'create_users', 'delete_themes', 'edit_plugins', 'edit_themes', 'edit_users' ) ) && is_multisite() )
				$new_data['caps']['plugin'] = 'edit_theme_options';

			// Excludes
			$new_data['exclude'] = array(
				'plugin_editors'    => ( isset( $post['exclude']['plugin_editors'] ) ) ? $post['exclude']['plugin_editors'] : 0,
				'cred_editors'      => ( isset( $post['exclude']['cred_editors'] ) ) ? $post['exclude']['cred_editors'] : 0,
				'list'              => sanitize_text_field( $post['exclude']['list'] )
			);

			// Remove Exclude users balances
			if ( $new_data['exclude']['list'] != '' ) {

				$excluded_ids = wp_parse_id_list( $new_data['exclude']['list'] );
				if ( ! empty( $excluded_ids ) ) {
					foreach ( $excluded_ids as $user_id ) {

						$user_id = absint( $user_id );
						if ( $user_id == 0 ) continue;

						bonipress_delete_user_meta( $user_id, $this->bonipress_type );
						bonipress_delete_user_meta( $user_id, $this->bonipress_type, '_total' );

					}
				}

			}

			// User deletions
			$new_data['delete_user'] = ( isset( $post['delete_user'] ) ) ? $post['delete_user'] : 0;

			$action_hook             = '';
			if ( ! $this->is_main_type )
				$action_hook = $this->bonipress_type;

			return apply_filters( 'bonipress_save_core_prefs' . $action_hook, $new_data, $post, $this );

		}

	}
endif;
