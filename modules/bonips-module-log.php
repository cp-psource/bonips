<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * boniPS_Log_Module class
 * @since 0.1
 * @version 1.2
 */
if ( ! class_exists( 'boniPS_Log_Module' ) ) :
	class boniPS_Log_Module extends boniPS_Module {

		public $user        = NULL;
		public $screen      = NULL;
		public $log_columns = array();

		/**
		 * Construct
		 */
		public function __construct( $type = BONIPS_DEFAULT_TYPE_KEY ) {

			parent::__construct( 'boniPS_Log_Module', array(
				'module_name' => 'log',
				'labels'      => array(
					'menu'        => __( 'Protokoll', 'bonips' ),
					'page_title'  => __( 'Protokoll', 'bonips' )
				),
				'screen_id'   => BONIPS_SLUG,
				'cap'         => 'editor',
				'accordion'   => true,
				'register'    => false,
				'menu_pos'    => 10
			), $type );

		}

		/**
		 * Init
		 * @since 0.1
		 * @version 1.2
		 */
		public function module_init() {

			$this->current_user_id = get_current_user_id();

			add_action( 'bonips_set_current_account',      array( $this, 'populate_current_account' ) );
			add_action( 'bonips_get_account',              array( $this, 'populate_account' ) );

			add_filter( 'bonips_add_finished',             array( $this, 'update_user_references' ), 90, 2 );
			add_action( 'bonips_add_menu',                 array( $this, 'my_history_menu' ) );

			// Handle deletions
			add_action( 'before_delete_post',              array( $this, 'post_deletions' ) );
			add_action( 'delete_comment',                  array( $this, 'comment_deletions' ) );

			// If we do not want to delete log entries, attempt to hardcode the users
			// details with their last known details.
			if ( isset( $this->core->delete_user ) && ! $this->core->delete_user )
				add_action( 'delete_user', array( $this, 'user_deletions' ) );

			add_action( 'wp_ajax_bonips-delete-log-entry', array( $this, 'action_delete_log_entry' ) );
			add_action( 'wp_ajax_bonips-update-log-entry', array( $this, 'action_update_log_entry' ) );

		}

		/**
		 * Admin Init
		 * @since 1.4
		 * @version 1.1
		 */
		public function module_admin_init() {

			add_action( 'admin_notices',               array( $this, 'admin_notices' ) );
			add_action( 'bonips_delete_point_type',    array( $this, 'delete_point_type' ) );

			$screen_id = 'toplevel_page_' . BONIPS_SLUG;
			if ( $this->bonips_type != BONIPS_DEFAULT_TYPE_KEY )
				$screen_id .= '_' . $this->bonips_type;

			$this->set_columns();

			add_filter( "manage_{$screen_id}_columns", array( $this, 'log_columns' ) );

		}

		/**
		 * Populate Current Account
		 * @since 1.8
		 * @version 1.0
		 */
		public function populate_current_account() {

			global $bonips_current_account;

			if ( isset( $bonips_current_account )
				&& ( $bonips_current_account instanceof boniPS_Account )
				&& ( isset( $bonips_current_account->history ) && in_array( $this->bonips_type, $bonips_current_account->history ) )
			) return;

			if ( ! empty( $bonips_current_account->point_types ) && in_array( $this->bonips_type, $bonips_current_account->point_types ) && $bonips_current_account->balance[ $this->bonips_type ] !== false ) {

				$bonips_current_account->balance[ $this->bonips_type ]->history = new boniPS_History( $bonips_current_account->user_id, $this->bonips_type );

			}

			if ( ! isset( $bonips_current_account->history ) )
				$bonips_current_account->history = array( $this->bonips_type );
			else
				$bonips_current_account->history[] = $this->bonips_type;

		}

		/**
		 * Populate Account
		 * @since 1.8
		 * @version 1.0
		 */
		public function populate_account() {

			global $bonips_account;

			if ( isset( $bonips_account )
				&& ( $bonips_account instanceof boniPS_Account )
				&& ( isset( $bonips_account->history ) && in_array( $this->bonips_type, $bonips_account->history ) )
			) return;

			if ( ! empty( $bonips_account->point_types ) && in_array( $this->bonips_type, $bonips_account->point_types ) && $bonips_account->balance[ $this->bonips_type ] !== false ) {

				$bonips_account->balance[ $this->bonips_type ]->history = new boniPS_History( $bonips_account->user_id, $this->bonips_type );

			}

			if ( ! isset( $bonips_account->history ) )
				$bonips_account->history = array( $this->bonips_type );
			else
				$bonips_account->history[] = $this->bonips_type;

		}

		/**
		 * Set Columns
		 * Sets the table columns that are shown in the log.
		 * @since 1.7
		 * @version 1.0
		 */
		protected function set_columns() {

			$column_headers    = array(
				'cb'       => '',
				'username' => __( 'Benutzer', 'bonips' ),
				'ref'      => __( 'Referenz', 'bonips' ),
				'time'     => __( 'Datum', 'bonips' ),
				'creds'    => '%plural%',
				'entry'    => __( 'Eintrag', 'bonips' )
			);

			$column_headers    = apply_filters( 'bonips_log_column_headers', $column_headers, $this, true );
			$column_headers    = apply_filters( 'bonips_log_column_' . $this->bonips_type . '_headers', $column_headers, $this );

			$columns = array();
			foreach ( $column_headers as $column_id => $column_name )
				$columns[ $column_id ] = $this->core->template_tags_general( $column_name );

			$this->log_columns = $columns;

		}

		/**
		 * Delete Point Type
		 * Deletes log entries for a particular point type when the point type is deleted.
		 * @since 1.7
		 * @version 1.1
		 */
		public function delete_point_type( $point_type = NULL ) {

			if ( $point_type !== $this->bonips_type || ! $this->core->user_is_point_admin() ) return;

			global $wpdb, $bonips_log_table;

			// Delete all entries of this point type
			$wpdb->delete(
				$bonips_log_table,
				array( 'ctype' => $this->bonips_type ),
				array( '%s' )
			);

			// Remove user histories
			$wpdb->delete(
				$wpdb->usermeta,
				array( 'meta_key' => bonips_get_meta_key( $point_type, '_history' ) ),
				array( '%s' )
			);

		}

		/**
		 * Update User References
		 * Removes the saved reference count and sum for re-calculation at earliest convinience.
		 * @since 1.7
		 * @version 1.0
		 */
		public function update_user_references( $result, $request ) {

			if ( $result !== false && strlen( $request['entry'] ) > 0 )
				bonips_delete_user_meta( $request['user_id'], 'bonips-log-count' );

			if ( $result === false || $request['type'] != $this->bonips_type ) return $result;

			bonips_delete_user_meta( $request['user_id'], 'bonips_ref_counts-' . $this->bonips_type );
			bonips_delete_user_meta( $request['user_id'], 'bonips_ref_sums-' . $this->bonips_type );

			return $result;

		}

		/**
		 * Delete Log Entry Action
		 * @since 1.4
		 * @version 1.1
		 */
		public function action_delete_log_entry() {

			// Security
			check_ajax_referer( 'bonips-delete-log-entry', 'token' );

			// Access
			if ( ! $this->core->user_is_point_admin() )
				wp_send_json_error( 'Zugriff verweigert' );

			$row_id = absint( $_POST['row'] );
			if ( $row_id === 0 )
				wp_send_json_error( 'Unknown Row ID' );

			$point_type = sanitize_key( $_POST['ctype'] );
			if ( ! bonips_point_type_exists( $point_type ) )
				wp_send_json_error( 'Unbekannter Punkttyp' );

			elseif ( $point_type != $this->bonips_type ) return;

			do_action( 'bonips_delete_log_entry', $row_id, $point_type );

			// Delete Row
			global $wpdb, $bonips_log_table;

			$user_id = $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$bonips_log_table} WHERE id = %d;", $row_id ) );
			if ( $user_id !== NULL ) {

				bonips_delete_user_meta( $user_id, $this->bonips_type, '_history' );

				$wpdb->delete( $bonips_log_table, array( 'id' => $row_id ), array( '%d' ) );

			}

			do_action( 'bonips_deleted_log_entry', $user_id, $row_id, $point_type );

			// Respond
			wp_send_json_success( __( 'Zeile gelöscht', 'bonips' ) );

		}

		/**
		 * Update Log Entry Action
		 * @since 1.4
		 * @version 1.2
		 */
		public function action_update_log_entry() {

			// Security
			check_ajax_referer( 'bonips-update-log-entry', 'token' );

			// Access
			if ( ! $this->core->user_is_point_editor() )
				wp_send_json_error( array( 'message' => 'Zugriff verweigert' ) );

			// Make sure we handle our own point type only
			$point_type       = sanitize_key( $_POST['ctype'] );
			if ( ! bonips_point_type_exists( $point_type ) )
				wp_send_json_error( array( 'message' => 'Unbekannter Punkttyp' ) );

			if ( $point_type !== $this->bonips_type ) return;

			// We need a row id
			$entry_id         = absint( $_POST['rowid'] );
			if ( $entry_id === 0 )
				wp_send_json_error( array( 'message' => 'Ungültiger Protokolleintrag' ) );

			$screen           = sanitize_key( $_POST['screen'] );

			// Parse form submission
			parse_str( $_POST['form'], $post );

			// Apply defaults
			$request          = shortcode_atts( apply_filters( 'bonips_update_log_entry_request', array(
				'ref'   => NULL,
				'creds' => NULL,
				'entry' => 'current'
			), $post ), $post['bonips_manage_log'] );

			// Check reference
			$all_references   = bonips_get_all_references();
			if ( $request['ref'] == '' || ! array_key_exists( $request['ref'], $all_references ) )
				wp_send_json_error( array( 'message' => esc_attr__( 'Ungültige oder leere Referenz', 'bonips' ) ) );

			// Check entry
			$request['entry'] = wp_kses_post( $request['entry'] );
			if ( $request['entry'] == '' && ! $this->core->user_is_point_admin() )
				wp_send_json_error( array( 'message' => esc_attr__( 'Der Protokolleintrag darf nicht leer sein', 'bonips' ) ) );

			// Check amount
			$amount           = $this->core->number( $request['creds'] );
			if ( $amount === $this->core->zero() )
				wp_send_json_error( array( 'message' => esc_attr__( 'Betrag kann nicht Null sein', 'bonips' ) ) );

			global $wpdb, $bonips_log_table;

			// Get the current version of the entry
			$log_entry        = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$bonips_log_table} WHERE id = %d;", $entry_id ) );
			if ( ! isset( $log_entry->ref ) )
				wp_send_json_error( array( 'message' => esc_attr__( 'Protokolleintrag nicht gefunden', 'bonips' ) ) );

			// Prep creds format
			$format           = ( $this->core->format['decimals'] > 0 ) ? '%f' : '%d';

			do_action( 'bonips_update_log_entry', $entry_id, $point_type );

			// Do the actual update
			if ( ! $this->core->update_log_entry( $entry_id, array( 'ref' => $request['ref'], 'creds' => $amount, 'entry' => $request['entry'] ), array( '%s', $format, '%s' ) ) )
				wp_send_json_error( array( 'message' => esc_attr__( 'Could not save the new log entry', 'bonips' ) ) );

			bonips_update_users_history( $log_entry->user_id, $this->bonips_type, $log_entry->ref, $log_entry->ref_id, ( $amount - $log_entry->creds ) );

			// Reset totals if amount or reference was changed
			if ( $this->core->number( $log_entry->creds ) !== $amount || $log_entry->ref !== $request['ref'] ) {

				bonips_delete_user_meta( $log_entry->user_id, $log_entry->ctype, '_total' );
				bonips_delete_user_meta( $log_entry->user_id, 'bonips_ref_counts-' . $this->bonips_type );
				bonips_delete_user_meta( $log_entry->user_id, 'bonips_ref_sums-' . $this->bonips_type );

				bonips_delete_option( 'bonips-cache-total-' . $log_entry->ctype );

			}

			do_action( 'bonips_updated_log_entry', $log_entry->user_id, $entry_id, $point_type );

			$log                 = new boniPS_Query_Log( array( 'entry_id' => $entry_id, 'ctype' => $point_type ) );
			$log->is_admin       = true;
			$log->headers        = $this->log_columns;
			$log->hidden_headers = get_hidden_columns( $screen );

			wp_send_json_success( array(
				'message' => esc_attr__( 'Protokolleintrag erfolgreich aktualisiert', 'bonips' ),
				'results' => $log->get_the_entry( $log->results[0] )
			) );

		}

		/**
		 * Add Users Verlauf
		 * Adds in a dedicated log page where the current user can view their points
		 * history, if allowed in the wp-admin area.
		 * @since 0.1
		 * @version 1.1
		 */
		public function my_history_menu() {

			// Check if user should be excluded
			if ( $this->core->exclude_user() || apply_filters( 'bonips_admin_show_history_' . $this->bonips_type, true ) === false ) return;

			// Add Points Verlauf to Users menu
			$page = add_users_page(
				$this->core->plural() . ' ' . __( 'Verlauf', 'bonips' ),
				$this->core->plural() . ' ' . __( 'Verlauf', 'bonips' ),
				'read',
				$this->bonips_type . '-history',
				array( $this, 'my_history_page' )
			);

			// Load styles for this page
			add_action( 'admin_print_styles-' . $page, array( $this, 'settings_header' ) );
			add_action( 'load-' . $page,               array( $this, 'screen_options' ) );

		}

		/**
		 * Admin Notices
		 * @since 1.7
		 * @version 1.0
		 */
		public function admin_notices() {

			$screen = get_current_screen();

			if ( substr( $screen->id, 0, ( 14 + strlen( BONIPS_SLUG ) ) ) != 'toplevel_page_' . BONIPS_SLUG ) return;

			if ( isset( $_GET['deleted'] ) && isset( $_GET['ctype'] ) && $_GET['ctype'] == $this->bonips_type )
				echo '<div id="message" class="updated notice is-dismissible"><p>' . sprintf( _n( '1 Eintrag gelöscht', '%d Einträge gelöscht', absint( $_GET['deleted'] ), 'bonips' ), absint( $_GET['deleted'] ) ) . '</p><button type="button" class="notice-dismiss"></button></div>';

		}

		/**
		 * Log Columns
		 * @since 1.7
		 * @version 1.0
		 */
		public function log_columns() {

			return $this->log_columns;

		}

		/**
		 * Screen Actions
		 * @since 1.7
		 * @version 1.0.2
		 */
		public function screen_actions() {

			$screen = get_current_screen();

			// "My Verlauf" screen and not Log archive
			if ( substr( $screen->id, 0, ( 14 + strlen( BONIPS_SLUG ) ) ) != 'toplevel_page_' . BONIPS_SLUG ) {

				do_action( 'bonips_log_my_admin_actions', $this->bonips_type );
				return;

			}

			$settings_key = 'bonips_epp_' . $_GET['page'];

			// Update Entries per page option
			if ( isset( $_REQUEST['wp_screen_options']['option'] ) && isset( $_REQUEST['wp_screen_options']['value'] ) ) {

				if ( $_REQUEST['wp_screen_options']['option'] == $settings_key ) {
					$value = absint( $_REQUEST['wp_screen_options']['value'] );
					bonips_update_user_meta( $this->current_user_id, $settings_key, '', $value );
				}

				$hidden_columns  = get_hidden_columns( $screen );
				$hidden          = array();
				foreach ( $this->log_columns as $column_id => $column_name ) {

					if ( ! array_key_exists( $column_id . '-hide', $_POST ) )
						$hidden[] = $column_id;

				}

				update_user_option( $this->current_user_id, 'manage' . $screen->id . 'columnshidden', $hidden );

			}

			do_action( 'bonips_log_admin_actions', $this->bonips_type );

			// Make sure we only execute code for the current point type viewed
			if ( ! isset( $_GET['ctype'] ) || $_GET['ctype'] != $this->bonips_type ) return;

			// Bulk action - delete log entries
			if ( isset( $_GET['action'] ) && $_GET['action'] == 'delete' && isset( $_GET['entry'] ) ) {

				// First get a clean list of ids to delete
				$entry_ids = array();
				foreach ( (array) $_GET['entry'] as $id ) {
					$id = absint( $id );
					if ( $id === 0 || in_array( $id, $entry_ids ) ) continue;
					$entry_ids[] = $id;
				}

				// If we have a list, run through them
				$deleted = 0;
				if ( ! empty( $entry_ids ) ) {

					global $wpdb, $bonips_log_table;

					foreach ( $entry_ids as $entry_id ) {

						$wpdb->delete(
							$bonips_log_table,
							array( 'id' => $entry_id ),
							array( '%d' )
						);

						$deleted ++;

					}

				}

				// Redirect to the good news
				if ( $deleted > 0 ) {

					if ( $this->is_main_type )
						$url = add_query_arg( array( 'page' => BONIPS_SLUG, 'ctype' => $this->bonips_type ), admin_url( 'admin.php' ) );
					else
						$url = add_query_arg( array( 'page' => BONIPS_SLUG . '_' . $this->bonips_type, 'ctype' => $this->bonips_type ), admin_url( 'admin.php' ) );

					$url = add_query_arg( 'deleted', $deleted, $url );
					wp_safe_redirect( $url );
					exit;

				}

			}

		}

		/**
		 * Screen Options
		 * @since 1.4
		 * @version 1.0.1
		 */
		public function screen_options() {

			$this->screen_actions();

			// Prep Per Page
			$args = array(
				'label'   => __( 'Einträge', 'bonips' ),
				'default' => 10,
				'option'  => 'bonips_epp_' . $_GET['page']
			);
			add_screen_option( 'per_page', $args );

		}

		/**
		 * Log Header
		 * @since 0.1
		 * @version 1.3.1
		 */
		public function settings_header() {

			$screen        = get_current_screen();

			if ( substr( $screen->id, 0, ( 14 + strlen( BONIPS_SLUG ) ) ) != 'toplevel_page_' . BONIPS_SLUG ) return;

			$references    = bonips_get_all_references();
			$js_references = array();
			if ( ! empty( $references ) ) {
				foreach ( $references as $ref_id => $ref_label )
					$js_references[ $ref_id ] = esc_js( $ref_label );
			}

			wp_enqueue_style( 'bonips-bootstrap-grid' );
			wp_enqueue_style( 'bonips-edit-log' );

			wp_localize_script(
				'bonips-edit-log',
				'boniPSLog',
				array(
					'ajaxurl'    => admin_url( 'admin-ajax.php' ),
					'title'      => esc_attr__( 'Protokolleintrag bearbeiten', 'bonips' ),
					'close'      => esc_attr__( 'Schließen', 'bonips' ),
					'working'    => esc_attr__( 'Wird verarbeitet...', 'bonips' ),
					'messages'   => array(
						'delete'     => esc_attr__( 'Möchtest Du diesen Protokolleintrag wirklich löschen? Das kann nicht rückgängig gemacht werden!', 'bonips' ),
						'update'     => esc_attr__( 'Der Protokolleintrag wurde erfolgreich aktualisiert.', 'bonips' ),
						'error'      => esc_attr__( 'Der ausgewählte Protokolleintrag konnte nicht gelöscht werden.', 'bonips' ),
					),
					'tokens'     => array(
						'delete'     => wp_create_nonce( 'bonips-delete-log-entry' ),
						'update'     => wp_create_nonce( 'bonips-update-log-entry' ),
						'column'     => wp_create_nonce( 'bonips-show-hide-log-columns' )
					),
					'references' => $js_references,
					'ctype'      => $this->bonips_type,
					'screen'     => $screen->id,
					'page'       => $this->screen_id
				)
			);

			wp_enqueue_script( 'bonips-edit-log' );

		}

		/**
		 * Page Title
		 * @since 0.1
		 * @version 1.0
		 */
		public function page_title( $title = 'Log' ) {

			$title = apply_filters( 'bonips_admin_log_title', $title, $this );

			// Search Results
			if ( isset( $_GET['s'] ) && ! empty( $_GET['s'] ) )
				$search_for = ' <span class="subtitle">' . __( 'Suchergebnisse für', 'bonips' ) . ' "' . $_GET['s'] . '"</span>';

			elseif ( isset( $_GET['time'] ) && $_GET['time'] != '' ) {
				$time       = urldecode( $_GET['time'] );
				$check      = explode( ',', $time );
				$search_for = ' <span class="subtitle">' . sprintf( _x( 'Protokolleinträge von %s', 'z.B. Protokolleinträge vom 9. Februar 2019', 'bonips' ), date( 'F jS Y', $check[0] ) ) . '</span>';
			}

			else
				$search_for = '';

			echo $title . ' ' . $search_for;

		}

		/**
		 * Admin Page
		 * @since 0.1
		 * @version 1.4.2
		 */
		public function admin_page() {

			// Security
			if ( ! $this->core->user_is_point_editor() ) wp_die( 'Zugriff verweigert' );

			$per_page             = bonips_get_user_meta( $this->current_user_id, 'bonips_epp_' . $_GET['page'], '', true );
			if ( $per_page == '' ) $per_page = 10;

			$name                 = bonips_label( true );
			$search_args          = bonips_get_search_args();

			// Entries per page
			if ( ! array_key_exists( 'number', $search_args ) )
				$search_args['number'] = absint( $per_page );

			// Only entries for this point type
			if ( ! array_key_exists( 'ctype', $search_args ) )
				$search_args['ctype'] = $this->bonips_type;

			$search_args['cache_results'] = false;

			// Query Log
			$log                  = new boniPS_Query_Log( $search_args );
	
			$log->is_admin        = true;
			$log->hidden_headers  = get_hidden_columns( get_current_screen() );
			$log->headers         = $this->log_columns;

?>
<div class="wrap" id="boniPS-wrap">
	<h1><?php _e( 'Protokoll', 'bonips' ); if ( BONIPS_DEFAULT_LABEL === 'boniPS' ) : ?> <a href="https://n3rds.work/docs/bonips-protokoll/" class="page-title-action" target="_blank"><?php _e( 'Dokumentation', 'bonips' ); ?></a><?php endif; ?></h1>
<?php

			// This requirement is only checked on activation. If the library is disabled
			// after installation we need to warn the user. Every single feature in boniPS
			// that requires encryption will stop working:
			// Points for clicking on links
			// Exchange Shortcode
			$extensions = get_loaded_extensions();
			if ( ! in_array( 'mcrypt', $extensions ) && ! defined( 'BONIPS_DISABLE_PROTECTION' ) )
				echo '<div id="message" class="error below-h2"><p>' . __( 'Warnung. Die erforderliche Mcrypt PHP Library ist auf diesem Server nicht installiert! Bestimmte Hooks und Shortcodes funktionieren nicht richtig!', 'bonips' ) . '</p></div>';

			// Filter by dates
			$log->filter_dates( admin_url( 'admin.php?page=' . $this->screen_id ) );

?>

	<?php do_action( 'bonips_top_log_page', $this ); ?>

	<form method="get" action="">
		<input type="hidden" name="page" value="<?php echo $this->screen_id; ?>" />
<?php

			if ( array_key_exists( 'user', $search_args ) )
				echo '<input type="hidden" name="user" value="' . esc_attr( $search_args['user'] ) . '" />';

			if ( array_key_exists( 's', $search_args ) )
				echo '<input type="hidden" name="s" value="' . esc_attr( $search_args['s'] ) . '" />';

			if ( isset( $_GET['ref'] ) )
				echo '<input type="hidden" name="show" value="' . esc_attr( $_GET['ref'] ) . '" />';

			if ( isset( $_GET['show'] ) )
				echo '<input type="hidden" name="show" value="' . esc_attr( $_GET['show'] ) . '" />';

			if ( array_key_exists( 'order', $search_args ) )
				echo '<input type="hidden" name="order" value="' . esc_attr( $search_args['order'] ) . '" />';

			if ( array_key_exists( 'paged', $search_args ) )
				echo '<input type="hidden" name="paged" value="' . esc_attr( $search_args['paged'] ) . '" />';

			$log->search();

?>
		<input type="hidden" name="ctype" value="<?php if ( array_key_exists( 'ctype', $search_args ) ) echo esc_attr( $search_args['ctype'] ); else echo esc_attr( $this->bonips_type ); ?>" />

		<?php do_action( 'bonips_above_log_table', $this ); ?>

		<div class="tablenav top">

			<?php $log->table_nav( 'top', false ); ?>

		</div>

		<?php $log->display(); ?>

		<div class="tablenav bottom">

			<?php $log->table_nav( 'bottom', false ); ?>

		</div>

		<?php do_action( 'bonips_bellow_log_table', $this ); ?>

	</form>

	<?php do_action( 'bonips_bottom_log_page', $this ); ?>

</div>
<?php

			$this->log_editor();

		}

		/**
		 * My Verlauf Page
		 * @since 0.1
		 * @version 1.3.2
		 */
		public function my_history_page() {

			// Security
			if ( ! is_user_logged_in() ) wp_die( 'Zugriff verweigert' );

			$per_page                  = bonips_get_user_meta( $this->current_user_id, 'bonips_epp_' . $_GET['page'], '', true );
			if ( $per_page == '' ) $per_page = 10;

			$search_args               = bonips_get_search_args();

			// Entries per page
			if ( ! array_key_exists( 'number', $search_args ) )
				$search_args['number'] = absint( $per_page );

			// Only entries for this point type
			$search_args['ctype']      = $this->bonips_type;

			// Only entries for the current user
			$search_args['user_id']    = $this->current_user_id;

			$log                       = new boniPS_Query_Log( $search_args );
			$log->is_admin             = true;

			$log->table_headers();

			unset( $log->headers['username'] );

?>
<div class="wrap" id="boniPS-wrap">
	<h1><?php $this->page_title( sprintf( __( 'Mein %s Verlauf', 'bonips' ),  $this->core->plural() ) ); ?></h1>

	<?php $log->filter_dates( admin_url( 'users.php?page=' . $_GET['page'] ) ); ?>

	<?php do_action( 'bonips_top_my_log_page', $this ); ?>

	<form method="get" action="" name="bonips-mylog-form" novalidate>
		<input type="hidden" name="page" value="<?php echo esc_attr( $_GET['page'] ); ?>" />
<?php

			if ( array_key_exists( 's', $search_args ) )
				echo '<input type="hidden" name="s" value="' . esc_attr( $search_args['s'] ) . '" />';

			if ( array_key_exists( 'ref', $search_args ) )
				echo '<input type="hidden" name="ref" value="' . esc_attr( $search_args['ref'] ) . '" />';

			if ( isset( $_GET['show'] ) )
				echo '<input type="hidden" name="show" value="' . esc_attr( $_GET['show'] ) . '" />';

			elseif ( array_key_exists( 'time', $search_args ) )
				echo '<input type="hidden" name="time" value="' . esc_attr( $search_args['time'] ) . '" />';

			if ( array_key_exists( 'order', $search_args ) )
				echo '<input type="hidden" name="order" value="' . esc_attr( $search_args['order'] ) . '" />';

			if ( array_key_exists( 'paged', $search_args ) )
				echo '<input type="hidden" name="paged" value="' . esc_attr( $search_args['paged'] ) . '" />';

			$log->search();

?>

		<?php do_action( 'bonips_above_my_log_table', $this ); ?>

		<div class="tablenav top">

			<?php $log->table_nav( 'top', true ); ?>

		</div>

		<?php $log->display(); ?>

		<div class="tablenav bottom">

			<?php $log->table_nav( 'bottom', true ); ?>

		</div>

		<?php do_action( 'bonips_bellow_my_log_table', $this ); ?>

	</form>

	<?php do_action( 'bonips_bottom_my_log_page', $this ); ?>

</div>
<?php

		}

		/**
		 * Handle Post Deletions
		 * When a post is deleted in WordPress, we need to update all log entries
		 * that might be using post related template tags so we have something to show.
		 * @since 1.0.9.2
		 * @version 1.1
		 */
		public function post_deletions( $post_id ) {

			global $post_type, $wpdb, $bonips_log_table;

			// Ignore boniPS post types and added option to stop this
			if ( in_array( $post_type, get_bonips_post_types() ) || apply_filters( 'bonips_update_post_template_tags', true, $post_id, $this ) === false ) return;

			// Get all records where this post ID has been used as a post reference
			$records = $wpdb->get_results( $wpdb->prepare( "SELECT id, data FROM {$bonips_log_table} WHERE ref_id = %d AND data LIKE %s;", $post_id, '%s:8:"ref_type";s:4:"post";%' ) );

			// If we have results
			if ( ! empty( $records ) ) {

				// Loop though them
				foreach ( $records as $entry ) {

					// Check if the data column has a serialized array
					$check = @unserialize( $entry->data );
					if ( $check !== false && $entry->data !== 'b:0;' ) {

						// Unserialize
						$new_data               = unserialize( $entry->data );
						if ( array_key_exists( 'ID', $new_data ) && array_key_exists( 'post_title', $new_data ) ) continue;

						// Add details that will no longer be available
						$post                   = bonips_get_post( $post_id );
						$new_data['ID']         = $post->ID;
						$new_data['post_title'] = $post->post_title;
						$new_data['post_type']  = $post->post_type;

						// Save
						$wpdb->update(
							$bonips_log_table,
							array( 'data' => serialize( $new_data ) ),
							array( 'id'   => $entry->id ),
							array( '%s' ),
							array( '%d' )
						);

					}

				}

			}

		}

		/**
		 * Handle User Deletions
		 * @since 1.0.9.2
		 * @version 1.1
		 */
		public function user_deletions( $user_id ) {

			global $wpdb, $bonips_log_table;

			// Ignore boniPS post types and added option to stop this
			if ( apply_filters( 'bonips_update_user_template_tags', true, $user_id, $this ) === false ) return;

			// Check log
			$records = $wpdb->get_results( $wpdb->prepare( "SELECT id, data FROM {$bonips_log_table} WHERE user_id = %d AND data LIKE %s;", $user_id, '%s:8:"ref_type";s:4:"user";%' ) );

			// If we have results
			if ( ! empty( $records ) ) {

				// Loop though them
				foreach ( $records as $entry ) {

					// Check if the data column has a serialized array
					$check = @unserialize( $entry->data );
					if ( $check !== false && $entry->data !== 'b:0;' ) {

						// Unserialize
						$new_data                 = unserialize( $entry->data );
						if ( array_key_exists( 'ID', $new_data ) && array_key_exists( 'user_login', $new_data ) ) continue;

						// Add details that will no longer be available
						$user                     = get_userdata( $user_id );
						$new_data['ID']           = $user->ID;
						$new_data['user_login']   = $user->user_login;
						$new_data['display_name'] = $user->display_name;

						// Save
						$wpdb->update(
							$bonips_log_table,
							array( 'data' => serialize( $new_data ) ),
							array( 'id'   => $entry->id ),
							array( '%s' ),
							array( '%d' )
						);

					}

				}

			}

		}

		/**
		 * Handle Comment Deletions
		 * @since 1.0.9.2
		 * @version 1.1
		 */
		public function comment_deletions( $comment_id ) {

			global $wpdb, $bonips_log_table;

			// Ignore boniPS post types and added option to stop this
			if ( apply_filters( 'bonips_update_comment_template_tags', true, $comment_id, $this ) === false ) return;

			// Check log
			$records = $wpdb->get_results( $wpdb->prepare( "SELECT id, data FROM {$bonips_log_table} WHERE ref_id = %d AND data LIKE %s;", $comment_id, '%s:8:"ref_type";s:7:"comment";%' ) );

			// If we have results
			if ( ! empty( $records ) ) {

				// Loop though them
				foreach ( $records as $entry ) {

					// Check if the data column has a serialized array
					$check = @unserialize( $entry->data );
					if ( $check !== false && $entry->data !== 'b:0;' ) {

						// Unserialize
						$new_data               = unserialize( $entry->data );
						if ( array_key_exists( 'comment_ID', $new_data ) && array_key_exists( 'comment_post_ID', $new_data ) ) continue;

						// Add details that will no longer be available
						$comment                     = get_comment( $comment_id );
						$new_data['comment_ID']      = $comment->comment_ID;
						$new_data['comment_post_ID'] = $comment->comment_post_ID;

						// Save
						$wpdb->update(
							$bonips_log_table,
							array( 'data' => serialize( $new_data ) ),
							array( 'id'   => $entry->id ),
							array( '%s' ),
							array( '%d' )
						);

					}

				}

			}

		}

		/**
		 * Log Editor
		 * Renders the log editor modal that is controlled by the log-editor js script.
		 * @since 1.7
		 * @version 1.0
		 */
		public function log_editor() {

			$name = bonips_label( true );

?>
<div id="edit-bonips-log-entry" style="display: none;">
	<div class="bonips-container">
		<?php if ( $name == 'boniPS' ) : ?><img id="bonips-token-sitting" class="hidden-sm hidden-xs" src="<?php echo plugins_url( 'assets/images/token-sitting.png', boniPS_THIS ); ?>" alt="Token looking on" /><?php endif; ?>
		<form class="form" method="post" action="" id="bonips-editor-form">
			<input type="hidden" name="bonips_manage_log[id]" value="" id="bonips-edit-log-id" />

			<div class="row">
				<div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
					<label><?php _e( 'Benutzer', 'bonips' ); ?></label>
					<div id="bonips-user-to-show"></div>
				</div>
				<div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
					<label><?php _e( 'Datum', 'bonips' ); ?></label>
					<div id="bonips-date-to-show"></div>
				</div>
				<div class="col-lg-2 col-md-2 col-sm-4 col-xs-12">
					<label><?php echo $this->core->plural(); ?></label>
					<input type="text" name="bonips_manage_log[creds]" id="bonips-creds-to-show" class="form-control" placeholder="" value="" />
				</div>
				<div class="col-lg-4 col-md-4 col-sm-8 col-xs-12">
					<label><?php _e( 'Referenz', 'bonips' ); ?></label>
					<select name="bonips_manage_log[ref]" id="bonips-referece-to-show"></select>
				</div>
			</div>

			<div class="row">
				<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12" id="bonips-old-entry-to-show-wrapper">
					<label><?php _e( 'Originaleintrag', 'bonips' ); ?></label>
					<div id="bonips-old-entry-to-show"></div>
				</div>
				<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12" id="bonips-new-entry-to-show-wrapper">
					<label><?php _e( 'Protokoll Eintrag', 'bonips' ); ?></label>
					<input type="text" name="bonips_manage_log[entry]" id="bonips-new-entry-to-show" class="form-control" placeholder="" value="" />
					<span class="description" id="available-template-tags" style="display:none;"></span>
				</div>
			</div>

			<div class="row last">
				<div class="col-lg-2 col-md-3 col-sm-12 col-xs-12 text-center">
					<a href="javascript:void(0);" class="button button-primary button-large bonips-delete-row" id="bonips-delete-entry-in-editor" data-id=""><?php _e( 'Eintrag löschen', 'bonips' ); ?></a>
				</div>
				<div class="col-lg-1 col-md-1 col-sm-1 col-xs-12"><span id="bonips-editor-indicator" class="spinner"></span></div>
				<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12" id="bonips-editor-results"></div>
				<div class="col-lg-3 col-md-2 col-sm-11 col-xs-12 text-right">
					<input type="submit" id="bonips-editor-submit" class="button button-secondary button-large" value="<?php _e( 'Eintrag aktualisieren', 'bonips' ); ?>" />
				</div>
			</div>
		</form>
	</div>
</div>
<?php

		}

	}
endif;
