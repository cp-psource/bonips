<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

/**
 * boniPRESS_Management_Module class
 * This module is responsible for all point management in the WordPress admin areas Users section.
 * Replaces the bonipress-admin.php file.
 * @since 1.7
 * @version 1.0.1
 */
if ( ! class_exists( 'boniPRESS_Management_Module' ) ) :
	class boniPRESS_Management_Module extends boniPRESS_Module {

		public $manual_reference = 'manual';

		/**
		 * Construct
		 */
		public function __construct( $type = BONIPRESS_DEFAULT_TYPE_KEY ) {

			parent::__construct( 'boniPRESS_Management_Module', array(
				'module_name' => 'management',
				'accordion'   => false
			), $type );

		}

		/**
		 * Module Init
		 * @since 1.0
		 * @version 1.0.1
		 */
		public function module_init() {

			// Admin Styling
			add_action( 'admin_head',                           array( $this, 'admin_header' ) );

			// Custom Columns
			add_filter( 'manage_users_columns',                 array( $this, 'custom_user_column' ) );
			add_filter( 'manage_users_custom_column',           array( $this, 'custom_user_column_content' ), 10, 3 );

			// Sortable Column
			add_filter( 'manage_users_sortable_columns',        array( $this, 'sortable_points_column' ) );
			add_action( 'pre_user_query',                       array( $this, 'sort_by_points' ) );

			// Edit User
			add_action( 'personal_options',                     array( $this, 'show_my_balance' ) );
			add_action( 'personal_options_update',              array( $this, 'save_balance_adjustments' ), 40 );
			add_action( 'edit_user_profile_update',             array( $this, 'save_balance_adjustments' ), 40 );

			// Editor
			add_action( 'wp_ajax_bonipress-admin-editor',          array( $this, 'ajax_editor_balance_update' ) );
			add_action( 'wp_ajax_bonipress-admin-recent-activity', array( $this, 'ajax_get_recent_activity' ) );
			add_action( 'in_admin_footer',                      array( $this, 'admin_footer' ) );

			$this->manual_reference = apply_filters( 'bonipress_editor_selected_ref', $this->manual_reference, $this );

		}

		/**
		 * AJAX: Update Balance
		 * @since 1.7
		 * @version 1.0
		 */
		public function ajax_editor_balance_update() {

			// Security
			check_ajax_referer( 'bonipress-editor-token', 'token' );

			// Check current user
			$current_user    = get_current_user_id();
			if ( ! bonipress_is_admin( $current_user ) )
				wp_send_json_error( 'ERROR_1' );

			// Get the form
			parse_str( $_POST['form'], $post );
			unset( $_POST );

			$submitted       = $post['bonipress_manage_balance'];

			// Prep submission
			$type            = sanitize_text_field( $submitted['type'] );
			$user_id         = absint( $submitted['user_id'] );
			$amount          = sanitize_text_field( $submitted['amount'] );
			$reference       = sanitize_key( $submitted['ref'] );
			$custom_ref      = sanitize_key( $submitted['custom'] );
			$entry           = wp_kses_post( $submitted['entry'] );

			if ( ! bonipress_point_type_exists( $type ) || $type == BONIPRESS_DEFAULT_TYPE_KEY ) {
				$type   = BONIPRESS_DEFAULT_TYPE_KEY;
				$bonipress = $this->core;
			}
			else {
				$bonipress = bonipress( $type );
			}

			$result          = array(
				'current'       => 0,
				'total'         => 0,
				'decimals'      => (int) $bonipress->format['decimals'],
				'label'         => esc_attr__( 'Guthaben aktualisieren', 'bonipress' ),
				'results'       => '',
				'user_id'       => $user_id,
				'amount'        => $amount,
				'reference'     => $reference,
				'custom'        => $custom_ref,
				'entry'         => $entry,
				'type'          => $type
			);

			// Make sure we are not attempting to adjust the balance of someone who is excluded
			if ( $bonipress->exclude_user( $user_id ) ) {

				$result['results'] = __( 'Benutzer ist ausgeschlossen', 'bonipress' );
				wp_send_json_error( $result );

			}

			// Non admins must give a log entry
			if ( $bonipress->user_is_point_editor() && ! $bonipress->user_is_point_admin() && strlen( $entry ) == 0 ) {

				$result['results'] = __( 'Der Protokolleintrag darf nicht leer sein', 'bonipress' );
				wp_send_json_error( $result );

			}

			// Amount can not be zero
			if ( $amount == '' || $bonipress->number( $amount ) == $bonipress->zero() ) {

				$result['results'] = __( 'Betrag kann nicht Null sein', 'bonipress' );
				wp_send_json_error( $result );

			}

			// Format amount
			$amount          = $bonipress->number( $amount );

			// Reference
			$all_references  = bonipress_get_all_references();
			if ( $reference == 'bonipress_custom' ) {

				if ( $custom_ref != '' )
					$reference = $custom_ref;
				else
					$reference = $this->manual_reference;

			}
			elseif ( $reference == '' || ! array_key_exists( $reference, $all_references ) )
				$reference = $this->manual_reference;

			$current_balance = $bonipress->get_users_balance( $user_id, $type );

			// Data
			$data            = apply_filters( 'bonipress_manual_change', array( 'ref_type' => 'user' ), $this );

			// Just a balance change without a log entry
			if ( strlen( $entry ) == 0 ) {

				$success     = true;
				$new_balance = $bonipress->update_users_balance( $user_id, $amount, $type );

			}

			// Balance change with a log entry
			else {

				$success = $bonipress->add_creds(
					$reference,
					$user_id,
					$amount,
					$entry,
					get_current_user_id(),
					$data,
					$type
				);
				$new_balance = $current_balance + $amount;

			}

			if ( $success ) {

				$result['current'] = $new_balance;
				$result['total']   = bonipress_query_users_total( $user_id, $type );
				$result['results'] = __( 'Balance erfolgreich aktualisiert', 'bonipress' );

			}
			else {

				$result['results'] = __( 'Anfrage abgelehnt', 'bonipress' );

			}

			wp_send_json_success( $result );

		}

		/**
		 * AJAX: Recent Activity
		 * @since 1.7
		 * @version 1.0.1
		 */
		public function ajax_get_recent_activity() {

			// Security
			check_ajax_referer( 'bonipress-get-ledger', 'token' );

			$user_id = absint( $_POST['userid'] );
			$type    = sanitize_key( $_POST['type'] );

			if ( ! bonipress_point_type_exists( $type ) )
				$type = BONIPRESS_DEFAULT_TYPE_KEY;

			$ledger  = new boniPRESS_Query_Log( array(
				'user_id' => $user_id,
				'number'  => 5,
				'ctype'   => $type
			) );

			if ( empty( $ledger->results ) ) {

?>
<div class="row last">
	<div class="col-xs-12">
		<p><?php _e( 'Keine aktuelle Aktivität gefunden.', 'bonipress' ); ?></p>
	</div>
</div>
<?php

			}

			else {

?>
<div class="row ledger header">
	<div class="col-xs-4"><strong><?php _e( 'Datum', 'bonipress' ); ?></strong></div>
	<div class="col-xs-4"><strong><?php _e( 'Zeit', 'bonipress' ); ?></strong></div>
	<div class="col-xs-4"><strong><?php _e( 'Referenz', 'bonipress' ); ?></strong></div>
	<div class="col-xs-12"><strong><?php _e( 'Eintrag', 'bonipress' ); ?></strong></div>
</div>
<?php

				$date_format = get_option( 'date_format' );
				$time_format = get_option( 'time_format' );
				$references  = bonipress_get_all_references();

				foreach ( $ledger->results as $log_entry ) {

					$date = date( $date_format, $log_entry->time );
					$time = date( $time_format, $log_entry->time );

					if ( array_key_exists( $log_entry->ref, $references ) )
						$ref = $references[ $log_entry->ref ];
					else
						$ref = ucwords( strtolower( str_replace( '_', ' ', $log_entry->ref ) ) );

					$entry = $this->core->parse_template_tags( $log_entry->entry, $log_entry );

?>
<div class="row ledger">
	<div class="col-xs-4"><?php echo $date; ?></div>
	<div class="col-xs-4"><?php echo $time; ?></div>
	<div class="col-xs-4"><?php echo $ref; ?></div>
	<div class="col-xs-12"><?php echo $entry; ?></div>
</div>
<?php

				}

			}

			if ( $ledger->num_rows > 5 ) {

				$page = BONIPRESS_SLUG;
				if ( $type != BONIPRESS_DEFAULT_TYPE_KEY )
					$page .= '_' . $type;

?>
<div class="row ledger">
	<div class="col-xs-12">
		<div style="text-align:center; padding: 12px 0;"><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $page . '&user=' . $user_id ) ); ?>" style="width: auto !important;" class="button button-large button-secondary"><?php _e( 'Vollständigen Verlauf anzeigen', 'bonipress' ); ?></a></div>
	</div>
</div>
<?php

			}

			die;

		}

		/**
		 * Enqueue Scripts & Styles
		 * @since 0.1
		 * @version 1.4.1
		 */
		public function admin_header() {

			$screen = get_current_screen();
			if ( ! isset( $screen->id ) ) return;

			if ( $screen->id == 'users' ) {

				wp_enqueue_style( 'bonipress-bootstrap-grid' );
				wp_enqueue_style( 'bonipress-edit-balance' );

				wp_localize_script(
					'bonipress-edit-balance',
					'boniPRESSedit',
					array(
						'ajaxurl'     => admin_url( 'admin-ajax.php' ),
						'token'       => wp_create_nonce( 'bonipress-editor-token' ),
						'ledgertoken' => wp_create_nonce( 'bonipress-get-ledger' ),
						'defaulttype' => BONIPRESS_DEFAULT_TYPE_KEY,
						'title'       => esc_attr__( 'Benutzerguthaben bearbeiten', 'bonipress' ),
						'close'       => esc_attr__( 'Schließen', 'bonipress' ),
						'working'     => esc_attr__( 'Wird verarbeitet...', 'bonipress' ),
						'ref'         => $this->manual_reference,
						'loading'     => '<div id="bonipress-processing"><div class="loading-indicator"></div></div>'
					)
				);
				wp_enqueue_script( 'bonipress-edit-balance' );

			}

			elseif ( $screen->id == 'user-edit' ) {

				wp_enqueue_style( 'bonipress-bootstrap-grid' );
				wp_enqueue_style( 'bonipress-edit-balance' );

			}

			elseif ( $screen->id == 'profile' ) {

				wp_enqueue_style( 'bonipress-bootstrap-grid' );
				wp_enqueue_style( 'bonipress-edit-balance' );

			}

		}

		/**
		 * Customize Users Column Headers
		 * @since 0.1
		 * @version 1.1
		 */
		public function custom_user_column( $columns ) {

			global $bonipress_types;

			if ( count( $bonipress_types ) == 1 )
				$columns[ BONIPRESS_DEFAULT_TYPE_KEY ] = $this->core->plural();

			else {

				foreach ( $bonipress_types as $type => $label ) {
					if ( $type == BONIPRESS_DEFAULT_TYPE_KEY ) $label = $this->core->plural();
					$columns[ $type ] = $label;
				}

			}

			return $columns;

		}

		/**
		 * Sortable User Column
		 * @since 1.2
		 * @version 1.1
		 */
		public function sortable_points_column( $columns ) {

			$bonipress_types = bonipress_get_types();

			if ( count( $bonipress_types ) == 1 )
				$columns[ BONIPRESS_DEFAULT_TYPE_KEY ] = BONIPRESS_DEFAULT_TYPE_KEY;

			else {
				foreach ( $bonipress_types as $type => $label )
					$columns[ $type ] = $type;
			}

			return $columns;

		}

		/**
		 * Sort by Points
		 * @since 1.2
		 * @version 1.3
		 */
		public function sort_by_points( $query ) {

			if ( ! is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ! function_exists( 'get_current_screen' ) ) return;

			$screen = get_current_screen();
			if ( $screen === NULL || $screen->id != 'users' ) return;

			if ( isset( $query->query_vars['orderby'] ) ) {

				global $wpdb;

				$bonipress_types = bonipress_get_types();
				$cred_id      = $query->query_vars['orderby'];

				$order        = 'ASC';
				if ( isset( $query->query_vars['order'] ) )
					$order = $query->query_vars['order'];

				$bonipress       = $this->core;
				if ( isset( $_REQUEST['ctype'] ) && array_key_exists( $_REQUEST['ctype'], $bonipress_types ) )
					$bonipress = bonipress( $_REQUEST['ctype'] );

				// Sort by only showing users with a particular point type
				if ( $cred_id == 'balance' ) {

					$amount = $bonipress->zero();
					if ( isset( $_REQUEST['amount'] ) )
						$amount = $bonipress->number( $_REQUEST['amount'] );

					$query->query_from  .= " LEFT JOIN {$wpdb->usermeta} bonipress ON ({$wpdb->users}.ID = bonipress.user_id AND bonipress.meta_key = '{$bonipress->cred_id}')";
					$query->query_where .= " AND bonipress.meta_value = {$amount}";

				}

				// Sort a particular point type
				elseif ( array_key_exists( $cred_id, $bonipress_types ) ) {

					$query->query_from   .= " LEFT JOIN {$wpdb->usermeta} bonipress ON ({$wpdb->users}.ID = bonipress.user_id AND bonipress.meta_key = '{$cred_id}')";
					$query->query_orderby = "ORDER BY bonipress.meta_value+0 {$order} ";

				}

			}

		}

		/**
		 * Customize User Columns Content
		 * @filter 'bonipress_user_row_actions'
		 * @since 0.1
		 * @version 1.3.4
		 */
		public function custom_user_column_content( $value, $column_name, $user_id ) {

			global $bonipress_types;

			if ( ! array_key_exists( $column_name, $bonipress_types ) ) return $value;

			$bonipress   = bonipress( $column_name );

			// User is excluded
			if ( $bonipress->exclude_user( $user_id ) === true ) return __( 'Ausgeschlossen', 'bonipress' );

			$user     = get_userdata( $user_id );

			// Show balance
			$ubalance = $bonipress->get_users_balance( $user_id, $column_name );
			$balance  = '<div id="bonipress-user-' . $user_id . '-balance-' . $column_name . '">' . $bonipress->before . ' <span>' . $bonipress->format_number( $ubalance ) . '</span> ' . $bonipress->after . '</div>';

			// Show total
			$total    = bonipress_query_users_total( $user_id, $column_name );
			$balance .= '<div id="bonipress-user-' . $user_id . '-balance-' . $column_name . '"><small style="display:block;">' . sprintf( '<strong>%s</strong>: <span>%s</span>', __( 'Gesamt', 'bonipress' ), $bonipress->format_number( $total ) ) . '</small></div>';

			$balance  = apply_filters( 'bonipress_users_balance_column', $balance, $user_id, $column_name );

			$page     = BONIPRESS_SLUG;
			if ( $column_name != BONIPRESS_DEFAULT_TYPE_KEY )
				$page .= '_' . $column_name;

			// Row actions
			$row            = array();
			$row['history'] = '<a href="' . esc_url( admin_url( 'admin.php?page=' . $page . '&user=' . $user_id ) ) . '">' . __( 'Verlauf', 'bonipress' ) . '</a>';
			$row['adjust']  = '<a href="javascript:void(0)" class="bonipress-open-points-editor" data-userid="' . $user_id . '" data-current="' . $bonipress->format_number( $ubalance ) . '" data-total="' . $bonipress->format_number( $total ) . '" data-type="' . $column_name . '" data-username="' . $user->display_name . '" data-zero="' . $bonipress->zero() . '">' . __( 'Anpassen', 'bonipress' ) . '</a>';

			$rows     = apply_filters( 'bonipress_user_row_actions', $row, $user_id, $bonipress );
			$balance .= $this->row_actions( $rows );

			return $balance;

		}

		/**
		 * User Row Actions
		 * @since 1.5
		 * @version 1.0
		 */
		public function row_actions( $actions, $always_visible = false ) {

			$action_count = count( $actions );
			$i            = 0;

			if ( ! $action_count )
				return '';

			$out  = '<div class="' . ( $always_visible ? 'row-actions-visible' : 'row-actions' ) . '">';
			foreach ( $actions as $action => $link ) {
				++$i;
				( $i == $action_count ) ? $sep = '' : $sep = ' | ';
				$out .= "<span class='$action'>$link$sep</span>";
			}
			$out .= '</div>';

			return $out;

		}

		/**
		 * Insert Ballance into Profile
		 * @since 0.1
		 * @version 1.2.1
		 */
		public function show_my_balance( $user ) {

			$user_id      = $user->ID;
			$editor_id    = get_current_user_id();
			$bonipress_types = bonipress_get_types( true );
			$balances     = array();
			$load_script  = false;

			foreach ( $bonipress_types as $point_type_key => $label ) {

				$bonipress      = bonipress( $point_type_key );

				$row = array( 'name' => '', 'excluded' => true, 'raw' => '', 'formatted' => '', 'can_edit' => false );

				$row['name'] = $bonipress->plural();

				if ( ! $bonipress->exclude_user( $user_id ) ) {

					$balance          = $bonipress->get_users_balance( $user_id );

					$row['excluded']  = false;
					$row['raw']       = $balance;
					$row['formatted'] = $bonipress->format_creds( $balance );
					$row['can_edit']  = ( ( $bonipress->user_is_point_editor( $editor_id ) ) ? true : false );

					if ( $row['can_edit'] === true && $load_script === false )
						$load_script = true;

				}

				$balances[ $point_type_key ] = $row;

			}

			if ( empty( $balances ) ) return;

?>
</table>
<hr />
<div id="bonipress-edit-user-wrapper">
	<table class="form-table bonipress-inline-table">
		<tr>
			<th scope="row"><?php _e( 'Balances', 'bonipress' ); ?></th>
			<td>
				<fieldset id="bonipress-badge-list" class="badge-list">
					<legend class="screen-reader-text"><span><?php _e( 'Balance', 'bonipress' ); ?></span></legend>
<?php

			// Loop through each point type
			foreach ( $balances as $point_type => $data ) {

				// This user is excluded from this point type
				if ( $data['excluded'] ) {

?>
					<div class="bonipress-wrapper balance-wrapper disabled-option color-option">
						<div><?php echo $data['name']; ?></div>
						<div class="balance-row">
							<div class="balance-view"><?php _e( 'Ausgeschlossen', 'bonipress' ); ?></div>
							<div class="balance-edit">&nbsp;</div>
						</div>
<?php

				}

				// Eligeble user
				else {

?>
					<div class="bonipress-wrapper balance-wrapper color-option selected">
						<?php if ( $data['can_edit'] ) : ?><div class="toggle-bonipress-balance-editor"><a href="javascript:void(0);" data-type="<?php echo $point_type; ?>" data-view="<?php _e( 'Bearbeiten', 'bonipress' ); ?>" data-edit="<?php _e( 'Abbrechen', 'bonipress' ); ?>"><?php _e( 'Bearbeiten', 'bonipress' ); ?></a></div><?php endif; ?>
						<div><?php echo $data['name']; ?></div>
						<div class="balance-row" id="bonipress-balance-<?php echo $point_type; ?>">
							<div class="balance-view"><?php echo $data['formatted']; ?></div>
							<?php if ( $data['can_edit'] ) : ?><div class="balance-edit"><input type="text" name="bonipress_new_balance[<?php echo $point_type; ?>]" value="" placeholder="<?php echo $data['raw']; ?>" size="12" /></div><?php endif; ?>
						</div>
<?php

				}

?>
						<?php do_action( 'bonipress_user_edit_after_balance', $point_type, $user, $data ); ?>

					</div>
<?php

			}

?>
				</fieldset>
			</td>
		</tr>
	</table>
	<hr />
<?php

			foreach ( $balances as $point_type => $data )
				do_action( 'bonipress_user_edit_after_' . $point_type, $user );

			do_action( 'bonipress_user_edit_after_balances', $user );

			// No need to load the script if we can't edit balances
			if ( $load_script ) {

?>
</div>
<script type="text/javascript">
jQuery(function($){

	$( '.toggle-bonipress-balance-editor a' ).click(function(e){

		e.preventDefault();
		$(this).blur();

		var togglebutton = $(this);
		var pointtype    = togglebutton.data( 'type' );
		var balancebox   = $( '#bonipress-balance-' + pointtype );

		

		// View mode > Edit Mode
		if ( ! balancebox.hasClass( 'edit' ) ) {

			togglebutton.text( togglebutton.data( 'edit' ) );

			$( '#bonipress-balance-' + pointtype + ' .balance-view' ).hide();
			$( '#bonipress-balance-' + pointtype + ' .balance-edit' ).show();

			balancebox.addClass( 'edit' );

		}

		// Edit mode > View mode
		else {

			togglebutton.text( togglebutton.data( 'view' ) );

			$( '#bonipress-balance-' + pointtype + ' .balance-view' ).show();
			$( '#bonipress-balance-' + pointtype + ' .balance-edit' ).hide();
			$( '#bonipress-balance-' + pointtype + ' .balance-edit input' ).val( '' );

			balancebox.removeClass( 'edit' );

		}

	});

});
</script>
<?php

			}

?>
<table class="form-table">
<?php

		}

		/**
		 * Save Balance Changes
		 * @since 1.7.3
		 * @version 1.0
		 */
		public function save_balance_adjustments( $user_id ) {

			$editor_id = get_current_user_id();

			if ( isset( $_POST['bonipress_new_balance'] ) && is_array( $_POST['bonipress_new_balance'] ) && ! empty( $_POST['bonipress_new_balance'] ) ) {

				foreach ( $_POST['bonipress_new_balance'] as $point_type => $balance ) {

					$point_type = sanitize_key( $point_type );
					if ( ! bonipress_point_type_exists( $point_type ) ) continue;

					$bonipress = bonipress( $point_type );

					// User can not be excluded and we must be allowed to change balances
					if ( ! $bonipress->exclude_user( $user_id ) && $bonipress->user_is_point_editor( $editor_id ) ) {

						$balance = sanitize_text_field( $balance );

						// Empty = no changes
						if ( strlen( $balance ) > 0 ) {
							$bonipress->set_users_balance( $user_id, $balance );
						}

					}

				}

			}

		}

		/**
		 * Admin Footer
		 * Inserts the Inline Edit Form modal.
		 * @since 1.2
		 * @version 1.3.1
		 */
		public function admin_footer() {

			// Security
			if ( ! $this->core->user_is_point_editor() ) return;

			$screen = get_current_screen();

			if ( $screen->id == 'users' ) {

				global $bonipress;

				$references = bonipress_get_all_references();
				$name       = bonipress_label( true );

				ob_start();

?>
<div id="edit-bonipress-balance" style="display: none;">
	<?php if ( $name == 'boniPRESS' ) : ?><img id="bonipress-token-sitting" class="hidden-sm hidden-xs" src="<?php echo plugins_url( 'assets/images/token-sitting.png', boniPRESS_THIS ); ?>" alt="Token looking on" /><?php endif; ?>
	<div class="bonipress-container">
		<form class="form" method="post" action="" id="bonipress-editor-form">
			<input type="hidden" name="bonipress_manage_balance[type]" value="" id="bonipress-edit-balance-of-type" />
			<input type="hidden" name="bonipress_manage_balance[user_id]" value="" id="bonipress-edit-balance-of-user" />

			<div class="row">
				<div class="col-sm-2 col-xs-6">
					<div class="form-group">
						<label><?php _e( 'ID', 'bonipress' ); ?></label>
						<div id="bonipress-userid-to-show">&nbsp;</div>
					</div>
				</div>
				<div class="col-sm-4 col-xs-6">
					<div class="form-group">
						<label><?php _e( 'Nutzername', 'bonipress' ); ?></label>
						<div id="bonipress-username-to-show">&nbsp;</div>
					</div>
				</div>
				<div class="col-sm-3 col-xs-6">
					<div class="form-group">
						<label><?php _e( 'Aktueller Saldo', 'bonipress' ); ?></label>
						<div id="bonipress-current-to-show">&nbsp;</div>
					</div>
				</div>
				<div class="col-sm-3 col-xs-6">
					<div class="form-group">
						<label><?php _e( 'Gesamtsaldo', 'bonipress' ); ?></label>
						<div id="bonipress-total-to-show">&nbsp;</div>
					</div>
				</div>
			</div>

			<div class="row">
				<div class="col-sm-2 col-xs-12">
					<div class="form-group">
						<label><?php _e( 'Betrag', 'bonipress' ); ?></label>
						<input type="text" name="bonipress_manage_balance[amount]" id="bonipress-editor-amount" size="8" placeholder="0" value="" />
						<span class="description"><?php _e( 'Ein positiver oder negativer Wert', 'bonipress' ); ?>.</span>
					</div>
				</div>
				<div class="col-sm-5 col-xs-12">
					<div class="form-group">
						<label><?php _e( 'Referenz', 'bonipress' ); ?></label>
						<select name="bonipress_manage_balance[ref]" id="bonipress-editor-reference">
<?php

				foreach ( $references as $ref_id => $ref_label ) {
					echo '<option value="' . $ref_id . '"';
					if ( $ref_id == $this->manual_reference ) echo ' selected="selected"';
					echo '>' . $ref_label . '</option>';
				}

				echo '<option value="bonipress_custom">' . __( 'Protokolliere unter einer benutzerdefinierten Referenz', 'bonipress' ) . '</option>';

?>
						</select>
					</div>
					<div id="bonipress-custom-reference-wrapper" style="display: none;">
						<input type="text" name="bonipress_manage_balance[custom]" id="bonipress-editor-custom-reference" placeholder="<?php _e( 'Kleinbuchstaben ohne Leerzeichen', 'bonipress' ); ?>" class="regular-text" value="" />
					</div>
				</div>
				<div class="col-sm-5 col-xs-12">
					<div class="form-group">
						<label><?php _e( 'Protokoll Eintrag', 'bonipress' ); ?></label>
						<input type="text" name="bonipress_manage_balance[entry]" id="bonipress-editor-entry" placeholder="<?php _e( 'optional', 'bonipress' ); ?>" class="regular-text" value="" />
						<span class="description"><?php echo $bonipress->available_template_tags( array( 'general', 'amount' ) ); ?></span>
					</div>
				</div>
			</div>

			<div class="row last">
				<div class="col-sm-2 col-xs-3"><input type="submit" id="bonipress-editor-submit" class="button button-primary button-large" value="<?php _e( 'Aktualisieren', 'bonipress' ); ?>" /></div>
				<div class="col-sm-1 col-xs-1"><span id="bonipress-editor-indicator" class="spinner"></span></div>
				<div class="col-sm-6 col-xs-4" id="bonipress-editor-results"></div>
				<div class="col-sm-3 col-xs-4 text-right"><button type="button" class="button button-secondary button-large" id="load-users-bonipress-history"><?php _e( 'Letzte Aktivität', 'bonipress' ); ?></button></div>
			</div>
		</form>

		<div id="bonipress-users-mini-ledger" style="display: none;">
			<div class="border">
				<div id="bonipress-processing"><div class="loading-indicator"></div></div>
			</div>
		</div>
	</div>

	<div class="clear"></div>
</div>
<?php

				$content = ob_get_contents();
				ob_end_clean();

				echo apply_filters( 'bonipress_admin_inline_editor', $content );

			}

		}

	}
endif;
