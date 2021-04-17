<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

/**
 * boniPRESS_Admin class
 * Manages everything concerning the WordPress admin area.
 * @since 0.1
 * @version 1.2
 */
if ( ! class_exists( 'boniPRESS_Admin' ) ) {
	class boniPRESS_Admin {

		public $core;
		public $using_bp = false;

		/**
		 * Construct
		 * @since 0.1
		 * @version 1.0
		 */
		function __construct( $settings = array() ) {
			$this->core = bonipress();
		}

		/**
		 * Load
		 * @since 0.1
		 * @version 1.2
		 */
		public function load() {
			// Admin Styling
			add_action( 'admin_head',                 array( $this, 'admin_header' ) );
			add_action( 'admin_notices',              array( $this, 'admin_notices' ) );

			// Custom Columns
			add_filter( 'manage_users_columns',       array( $this, 'custom_user_column' )                );
			add_action( 'manage_users_custom_column', array( $this, 'custom_user_column_content' ), 10, 3 );

			// User Edit
			global $bp;

			// Check if BuddyPress is being used
			if ( is_object( $bp ) && isset( $bp->version ) && version_compare( $bp->version, '2.0', '>=' ) && bp_is_active( 'xprofile' ) )
				$this->using_bp = true;

			// Edit Profile
			if ( ! $this->using_bp )
				add_action( 'edit_user_profile', array( $this, 'user_nav' ) );
			else
				add_action( 'bp_members_admin_profile_nav', array( $this, 'bp_user_nav' ), 10, 2 );

			add_action( 'personal_options',   array( $this, 'show_my_balance' ) );
			add_filter( 'bonipress_admin_pages', array( $this, 'edit_profile_menu' ) );
			add_action( 'bonipress_init',        array( $this, 'edit_profile_actions' ) );

			// Sortable Column
			add_filter( 'manage_users_sortable_columns', array( $this, 'sortable_points_column' ) );
			add_action( 'pre_user_query',                array( $this, 'sort_by_points' )         );

			// Inline Editing
			add_action( 'wp_ajax_bonipress-inline-edit-users-balance', array( $this, 'inline_edit_user_balance' ) );
			add_action( 'in_admin_footer',                          array( $this, 'admin_footer' )             );
		}

		/**
		 * Profile Actions
		 * @since 1.5
		 * @version 1.0
		 */
		public function edit_profile_actions() {

			do_action( 'bonipress_edit_profile_action' );

			// Update Balance
			if ( isset( $_POST['bonipress_adjust_users_balance_run'] ) && isset( $_POST['bonipress_adjust_users_balance'] ) ) {

				extract( $_POST['bonipress_adjust_users_balance'] );

				if ( wp_verify_nonce( $token, 'bonipress-adjust-balance' ) ) {

					$ctype = sanitize_key( $ctype );
					$bonipress = bonipress( $ctype );

					// Enforce requirement for log entry
					if ( $bonipress->can_edit_creds() && ! $bonipress->can_edit_plugin() && $log == '' ) {
						wp_safe_redirect( add_query_arg( array( 'result' => 'log_error' ) ) );
						exit;
					}

					// Make sure we can edit creds
					if ( $bonipress->can_edit_creds() ) {

						// Prep
						$user_id = absint( $user_id );
						$amount = $bonipress->number( $amount );
						$data = apply_filters( 'bonipress_manual_change', array( 'ref_type' => 'user' ), $this );

						// Run
						$bonipress->add_creds(
							'manual',
							$user_id,
							$amount,
							$log,
							get_current_user_id(),
							$data,
							$ctype
						);

						wp_safe_redirect( add_query_arg( array( 'result' => 'balance_updated' ) ) );
						exit;

					}

				}

			}

			// Exclude
			elseif ( isset( $_GET['page'] ) && $_GET['page'] == 'bonipress-edit-balance' && isset( $_GET['action'] ) && $_GET['action'] == 'exclude' ) {

				$ctype = sanitize_key( $_GET['ctype'] );
				$bonipress = bonipress( $ctype );

				// Make sure we can edit creds
				if ( $bonipress->can_edit_creds() ) {

					// Make sure user is not already excluded
					$user_id = absint( $_GET['user_id'] );
					if ( ! $bonipress->exclude_user( $user_id ) ) {

						// Get setttings
						$options = $bonipress->core;

						// Get and clean up the exclude list
						$excludes = explode( ',', $options['exclude']['list'] );
						if ( ! empty( $excludes ) ) {
							$_excludes = array();
							foreach ( $excludes as $_user_id ) {
								$_user_id = sanitize_key( $_user_id );
								if ( $_user_id == '' ) continue;
								$_excludes[] = absint( $_user_id );
							}
							$excludes = $_excludes;
						}

						// If user ID is not yet in list
						if ( ! in_array( $user_id, $excludes ) ) {
							$excludes[] = $user_id;
							$options['exclude']['list'] = implode( ',', $excludes );

							$option_id = 'bonipress_pref_core';
							if ( $ctype != 'bonipress_default' )
								$option_id .= '_' . $ctype;

							bonipress_update_option( $option_id, $options );

							// Remove Users balance
							bonipress_delete_user_meta( $user_id, $ctype );

							global $wpdb;

							// Delete log entries
							$wpdb->delete(
								$bonipress->log_table,
								array( 'user_id' => $user_id, 'ctype' => $ctype ),
								array( '%d', '%s' )
							);

							wp_safe_redirect( add_query_arg( array( 'user_id' => $user_id, 'result' => 'user_excluded' ), admin_url( 'user-edit.php' ) ) );
							exit;
						}

					}

				}

			}

		}

		/**
		 * Admin Notices
		 * @since 1.4
		 * @version 1.1
		 */
		public function admin_notices() {

			// Manual Adjustments
			if ( isset( $_GET['page'] ) && $_GET['page'] == 'bonipress-edit-balance' && isset( $_GET['result'] ) ) {

				if ( $_GET['result'] == 'log_error' )
					echo '<div class="error"><p>' . __( 'Ein Protokolleintrag ist erforderlich, um das Guthaben dieses Benutzers anzupassen', 'bonipress' ) . '</p></div>';
				elseif ( $_GET['result'] == 'balance_updated' )
					echo '<div class="updated"><p>' . __( 'Benutzerguthaben gespeichert', 'bonipress' ) . '</p></div>';

			}

			// Exclusions
			elseif ( isset( $_GET['user_id'] ) && isset( $_GET['result'] ) ) {

				if ( $_GET['result'] == 'user_excluded' )
					echo '<div class="updated"><p>' . __( 'Benutzer ausgeschlossen', 'bonipress' ) . '</p></div>';

			}

			if ( get_option( 'bonipress_buycred_reset', false ) !== false )
				echo '<div class="error"><p>' . __( 'Alle Bonikauf-Zahlungsgateways wurden deaktiviert! Bitte überprüfe Deine Wechselkurseinstellungen und aktualisiere alle Premium-Zahlungsgateways!', 'bonipress' ) . '</p></div>';

			do_action( 'bonipress_admin_notices' );

		}

		/**
		 * Ajax: Inline Edit Users Balance
		 * @since 1.2
		 * @version 1.1
		 */
		public function inline_edit_user_balance() {
			// Security
			check_ajax_referer( 'bonipress-update-users-balance', 'token' );

			// Check current user
			$current_user = get_current_user_id();
			if ( ! bonipress_is_admin( $current_user ) )
				wp_send_json_error( 'ERROR_1' );

			// Type
			$type = sanitize_text_field( $_POST['type'] );

			$bonipress = bonipress( $type );

			// User
			$user_id = abs( $_POST['user'] );
			if ( $bonipress->exclude_user( $user_id ) )
				wp_send_json_error( array( 'error' => 'ERROR_2', 'message' => __( 'Benutzer ist ausgeschlossen', 'bonipress' ) ) );

			// Log entry
			$entry = trim( $_POST['entry'] );
			if ( $bonipress->can_edit_creds() && ! $bonipress->can_edit_plugin() && empty( $entry ) )
				wp_send_json_error( array( 'error' => 'ERROR_3', 'message' => __( 'Der Protokolleintrag darf nicht leer sein', 'bonipress' ) ) );

			// Amount
			if ( $_POST['amount'] == 0 || empty( $_POST['amount'] ) )
				wp_send_json_error( array( 'error' => 'ERROR_4', 'message' => __( 'Betrag kann nicht Null sein', 'bonipress' ) ) );
			else
				$amount = $bonipress->number( $_POST['amount'] );

			// Data
			$data = apply_filters( 'bonipress_manual_change', array( 'ref_type' => 'user' ), $this );

			// Execute
			$result = $bonipress->add_creds(
				'manual',
				$user_id,
				$amount,
				$entry,
				$current_user,
				$data,
				$type
			);

			if ( $result !== false )
				wp_send_json_success( $bonipress->get_users_cred( $user_id, $type ) );
			else
				wp_send_json_error( array( 'error' => 'ERROR_5', 'message' => __( 'Fehler beim Aktualisieren des Guthabens.', 'bonipress' ) ) );
		}

		/**
		 * Admin Header
		 * @since 0.1
		 * @version 1.3
		 */
		public function admin_header() {
			global $wp_version;

			// Old navigation menu
			if ( version_compare( $wp_version, '3.8', '<' ) ) {
				$image = plugins_url( 'assets/images/logo-menu.png', boniPRESS_THIS ); ?>

<!-- Support for pre 3.8 menus -->
<style type="text/css">
<?php foreach ( $bonipress_types as $type => $label ) { if ( $bonipress_type == 'bonipress_default' ) $name = ''; else $name = '_' . $type; ?>
#adminmenu .toplevel_page_boniPRESS<?php echo $name; ?> div.wp-menu-image { background-image: url(<?php echo $image; ?>); background-position: 1px -28px; }
#adminmenu .toplevel_page_boniPRESS<?php echo $name; ?>:hover div.wp-menu-image, 
#adminmenu .toplevel_page_boniPRESS<?php echo $name; ?>.current div.wp-menu-image, 
#adminmenu .toplevel_page_boniPRESS<?php echo $name; ?> .wp-menu-open div.wp-menu-image { background-position: 1px 0; }
<?php } ?>
</style>
<?php
			}

			$screen = get_current_screen();
			if ( $screen->id == 'users' ) {
				wp_enqueue_script( 'bonipress-inline-edit' );
				wp_enqueue_style( 'bonipress-inline-edit' );
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
				$columns['bonipress_default'] = $this->core->plural();
			else {
				foreach ( $bonipress_types as $type => $label ) {
					if ( $type == 'bonipress_default' ) $label = $this->core->plural();
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
				$columns['bonipress_default'] = 'bonipress_default';
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
				$cred_id = $query->query_vars['orderby'];

				$order = 'ASC';
				if ( isset( $query->query_vars['order'] ) )
					$order = $query->query_vars['order'];

				$bonipress = $this->core;
				if ( isset( $_REQUEST['ctype'] ) && array_key_exists( $_REQUEST['ctype'], $bonipress_types ) )
					$bonipress = bonipress( $_REQUEST['ctype'] );

				// Sort by only showing users with a particular point type
				if ( $cred_id == 'balance' ) {

					$amount = $bonipress->zero();
					if ( isset( $_REQUEST['amount'] ) )
						$amount = $bonipress->number( $_REQUEST['amount'] );

					$query->query_from .= "
					LEFT JOIN {$wpdb->usermeta} 
						ON ({$wpdb->users}.ID = {$wpdb->usermeta}.user_id AND {$wpdb->usermeta}.meta_key = '{$bonipress->cred_id}')";

					$query->query_where .= " AND meta_value = {$amount}";

				}

				// Sort a particular point type
				elseif ( array_key_exists( $cred_id, $bonipress_types ) ) {

					$query->query_from .= "
					LEFT JOIN {$wpdb->usermeta} 
						ON ({$wpdb->users}.ID = {$wpdb->usermeta}.user_id AND {$wpdb->usermeta}.meta_key = '{$cred_id}')";

					$query->query_orderby = "ORDER BY {$wpdb->usermeta}.meta_value+0 {$order} ";

				}

			}
		}

		/**
		 * Customize User Columns Content
		 * @filter 'bonipress_user_row_actions'
		 * @since 0.1
		 * @version 1.3.2
		 */
		public function custom_user_column_content( $value, $column_name, $user_id ) {
			global $bonipress_types;

			if ( ! array_key_exists( $column_name, $bonipress_types ) ) return $value;

			$bonipress = bonipress( $column_name );

			// User is excluded
			if ( $bonipress->exclude_user( $user_id ) === true ) return __( 'Ausgeschlossen', 'bonipress' );

			$user = get_userdata( $user_id );

			// Show balance
			$ubalance = $bonipress->get_users_cred( $user_id, $column_name );
			$balance = '<div id="bonipress-user-' . $user_id . '-balance-' . $column_name . '">' . $bonipress->before . ' <span>' . $bonipress->format_number( $ubalance ) . '</span> ' . $bonipress->after . '</div>';

			// Show total
			$total = bonipress_query_users_total( $user_id, $column_name );
			$balance .= '<small style="display:block;">' . sprintf( __( 'Gesamt: %s', 'bonipress' ), $bonipress->format_number( $total ) ) . '</small>';

			$page = 'boniPRESS';
			if ( $column_name != 'bonipress_default' )
				$page .= '_' . $column_name;

			// Row actions
			$row = array();
			$row['history'] = '<a href="' . admin_url( 'admin.php?page=' . $page . '&user_id=' . $user_id ) . '">' . __( 'Verlauf', 'bonipress' ) . '</a>';
			$row['adjust'] = '<a href="javascript:void(0)" class="bonipress-open-points-editor" data-userid="' . $user_id . '" data-current="' . $ubalance . '" data-type="' . $column_name . '" data-username="' . $user->display_name . '">' . __( 'Anpassen', 'bonipress' ) . '</a>';

			$rows = apply_filters( 'bonipress_user_row_actions', $row, $user_id, $bonipress );
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
			$i = 0;

			if ( !$action_count )
				return '';

			$out = '<div class="' . ( $always_visible ? 'row-actions-visible' : 'row-actions' ) . '">';
			foreach ( $actions as $action => $link ) {
				++$i;
				( $i == $action_count ) ? $sep = '' : $sep = ' | ';
				$out .= "<span class='$action'>$link$sep</span>";
			}
			$out .= '</div>';

			return $out;
		}

		/**
		 * Add Admin Page
		 * @since 1.5
		 * @version 1.0
		 */
		public function edit_profile_menu( $pages = array() ) {
			$pages[] = add_users_page(
				__( 'Guthaben bearbeiten', 'bonipress' ),
				__( 'Guthaben bearbeiten', 'bonipress' ),
				'read',
				'bonipress-edit-balance',
				array( $this, 'edit_profile_screen' )
			);
			return $pages;
		}

		/**
		 * User Nav
		 * @since 1.5
		 * @version 1.0
		 */
		public function user_nav( $user, $current = NULL ) {
			$types = bonipress_get_types();

			$tabs = array();
			$tabs[] = array(
				'label'   => __( 'Profil', 'bonipress' ),
				'url'     => add_query_arg( array( 'user_id' => $user->ID ), admin_url( 'user-edit.php' ) ),
				'classes' => ( $current === NULL ) ? 'nav-tab nav-tab-active' : 'nav-tab'
			);

			if ( $this->using_bp )
				$tabs[] = array(
					'label'   => __( 'Erweitertes Profil', 'bonipress' ),
					'url'     => add_query_arg( array( 'page' => 'bp-profile-edit', 'user_id' => $user->ID ), admin_url( 'users.php' ) ),
					'classes' => 'nav-tab'
				);

			foreach ( $types as $type => $label ) {
				$bonipress = bonipress( $type );
				if ( $bonipress->exclude_user( $user->ID ) ) continue;

				$classes = 'nav-tab';
				if ( isset( $_GET['ctype'] ) && $_GET['ctype'] == $type ) $classes .= ' nav-tab-active';

				$tabs[] = array(
					'label'   => $bonipress->plural(),
					'url'     => add_query_arg( array( 'page' => 'bonipress-edit-balance', 'user_id' => $user->ID, 'ctype' => $type ), admin_url( 'users.php' ) ),
					'classes' => $classes
				);
			}

			$tabs = apply_filters( 'bonipress_edit_profile_tabs', $tabs, $user, false );

?>
<style type="text/css">
div#edit-balance-page.wrap form#your-profile, div#profile-page.wrap form#your-profile { position:relative; }
div#edit-balance-page.wrap form#your-profile h3:first-of-type { margin-top:3em; }
div#profile-page.wrap form#your-profile h3:first-of-type { margin-top:6em; }
div#edit-balance-page.wrap form#your-profile ul#profile-nav { border-bottom:solid 1px #ccc; width:100%; }
div#profile-page.wrap form#your-profile ul#profile-nav { position:absolute; top:-6em; border-bottom:solid 1px #ccc; width:100%; }
div#edit-balance-page ul#profile-nav { border-bottom:solid 1px #ccc; width:100%; margin-top:1em; margin-bottom:1em; padding:1em 0; padding-bottom: 0; height:2.4em; }
ul#profile-nav li { margin-left:0.4em; float:left;font-weight: bold;font-size: 15px;line-height: 24px;}
ul#profile-nav li a {text-decoration: none;color:#888;}
ul#profile-nav li a:hover, ul#profile-nav li.nav-tab-active a {text-decoration: none;color:#000; }
</style>
<ul id="profile-nav" class="nav-tab-wrapper">

	<?php foreach ( $tabs as $tab ) echo '<li class="' . $tab['classes'] . '"><a href="' . $tab['url'] . '">' . $tab['label'] . '</a></li>'; ?>

</ul>
<?php
		}

		/**
		 * BuddyPress User Nav
		 * @since 1.5
		 * @version 1.0
		 */
		public function bp_user_nav( $active, $user ) {
			$types = bonipress_get_types();

			$tabs = array();
			foreach ( $types as $type => $label ) {
				$bonipress = bonipress( $type );
				if ( $bonipress->exclude_user( $user->ID ) ) continue;

				$tabs[] = array(
					'label'   => $bonipress->plural(),
					'url'     => add_query_arg( array( 'page' => 'bonipress-edit-balance', 'user_id' => $user->ID, 'ctype' => $type ), admin_url( 'users.php' ) ),
					'classes' => 'nav-tab'
				);
			}

			$tabs = apply_filters( 'bonipress_edit_profile_tabs', $tabs, $user, true );

			if ( ! empty( $tabs ) )
				foreach ( $tabs as $tab ) echo '<li class="' . $tab['classes'] . '"><a href="' . $tab['url'] . '">' . $tab['label'] . '</a></li>';
		}

		/**
		 * Edit Profile Screen
		 * @since 1.5
		 * @version 1.0
		 */
		public function edit_profile_screen() {
			if ( ! isset( $_GET['user_id'] ) ) return;

			$user_id = absint( $_GET['user_id'] );

			if ( ! isset( $_GET['ctype'] ) )
				$type = 'bonipress_default';
			else
				$type = sanitize_key( $_GET['ctype'] );

			$bonipress = bonipress( $type );

			// Security
			if ( ! $bonipress->can_edit_creds() )
				wp_die( __( 'Zugriff abgelehnt', 'bonipress' ) );

			// User is excluded
			if ( $bonipress->exclude_user( $user_id ) )
				wp_die( sprintf( __( 'Dieser Benutzer ist von der Verwendung von %s ausgeschlossen', 'bonipress' ), bonipress_label() ) );

			$user = get_userdata( $user_id );
			$balance = $bonipress->get_users_balance( $user_id );

			if ( $type == 'bonipress_default' )
				$log_slug = 'boniPRESS';
			else
				$log_slug = 'boniPRESS_' . $type;

			$history_url = add_query_arg( array( 'page' => $log_slug, 'user_id' => $user->ID ), admin_url( 'admin.php' ) );
			$exclude_url = add_query_arg( array( 'action' => 'exclude' ) ) ?>

<style type="text/css">
div#edit-balance-page table.table { width: 100%; margin-top: 24px; }
div#edit-balance-page table.table th { text-align: left; }
div#edit-balance-page table.table td { width: 33%; font-size: 24px; line-height: 48px; }
div#edit-balance-page table tr td table tr td { vertical-align: top; }
div#edit-balance-page table.form-table { border-top: 1px solid #ccc; }
div#edit-balance-page.wrap form#your-profile h3 { margin-top: 3em; }
</style>
<div class="wrap" id="edit-balance-page">
	<h2><?php
	_e( 'Benutzer bearbeiten', 'bonipress' );
	if ( current_user_can( 'create_users' ) ) { ?>
	<a href="user-new.php" class="add-new-h2"><?php echo esc_html_x( 'Benutzer hinzufügen', 'user', 'bonipress' ); ?></a>
<?php } elseif ( is_multisite() && current_user_can( 'promote_users' ) ) { ?>
	<a href="user-new.php" class="add-new-h2"><?php echo esc_html_x( 'Existierenden bearbeiten', 'user', 'bonipress' ); ?></a>
<?php }
	?></h2>
	<form id="your-profile" action="" method="post">
		<?php echo $this->user_nav( $user, $type ); ?>

		<div class="clear clearfix"></div>
		<table class="table">
			<thead>
				<tr>
					<th><?php _e( 'Aktuelles Guthaben', 'bonipress' ); ?></th>
					<th><?php printf( __( 'Gesamt %s akkumuliert', 'bonipress' ), $bonipress->plural() ); ?></th>
					<th><?php printf( __( 'Insgesamt %s ausgegeben', 'bonipress' ), $bonipress->plural() ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php echo $bonipress->format_creds( $balance ); ?></td>
					<td><?php echo $bonipress->format_creds( bonipress_get_users_total( $user->ID, $type ) ); ?></td>
					<td><?php echo $bonipress->format_creds( $this->get_users_total_spent( $user->ID, $type ) ); ?></td>
				</tr>
			</tbody>
		</table>
		<a href="<?php echo $history_url; ?>" class="button button-secondary"><?php _e( 'Siehe Verlauf', 'bonipress' ); ?></a>
		<a href="<?php echo $exclude_url; ?>" class="button button-primary" id="bonipress-exclude-this-user"><?php _e( 'Benutzer ausschließen', 'bonipress' ); ?></a>

		<?php do_action( 'bonipress_before_edit_profile', $user, $type ); ?>

		<h3><?php _e( 'Passe Guthaben an', 'bonipress' ); ?></h3>
		<?php $this->adjust_users_balance( $user ); ?>

		<?php do_action( 'bonipress_edit_profile', $user, $type ); ?>

	</form>
	<script type="text/javascript">
jQuery(function($) {
	$( 'a#bonipress-exclude-this-user' ).click(function(){
		if ( ! confirm( '<?php _e( 'Warnung! Wenn Du diesen Benutzer ausschließt, wird sein Guthaben zusammen mit allen Einträgen in Deinem Protokoll gelöscht! Das kann nicht rückgängig gemacht werden!', 'bonipress' ); ?>' ) )
			return false;
	});
});
	</script>
</div>
<?php
		}

		/**
		 * Get Users Total Accumulated
		 * @since 1.5
		 * @version 1.0
		 */
		public function get_users_total_accumulated( $user_id, $type ) {
			global $wpdb;

			return $wpdb->get_var( $wpdb->prepare( "
				SELECT SUM( creds ) 
				FROM {$this->core->log_table} 
				WHERE ctype = %s 
				AND user_id = %d 
				AND creds > 0;", $type, $user_id ) );
		}

		/**
		 * Get Users Total Spending
		 * @since 1.5
		 * @version 1.0
		 */
		public function get_users_total_spent( $user_id, $type ) {
			global $wpdb;

			return $wpdb->get_var( $wpdb->prepare( "
				SELECT SUM( creds ) 
				FROM {$this->core->log_table} 
				WHERE ctype = %s 
				AND user_id = %d 
				AND creds < 0;", $type, $user_id ) );
		}

		/**
		 * Insert Ballance into Profile
		 * @since 0.1
		 * @version 1.1
		 */
		public function show_my_balance( $user ) {
			$user_id = $user->ID;
			$bonipress_types = bonipress_get_types();

			foreach ( $bonipress_types as $type => $label ) {
				$bonipress = bonipress( $type );
				if ( $bonipress->exclude_user( $user_id ) ) continue;

				$balance = $bonipress->get_users_cred( $user_id, $type );
				$balance = $bonipress->format_creds( $balance ); ?>

<tr>
	<th scope="row"><?php echo $bonipress->template_tags_general( __( '%singular% Guthaben', 'bonipress' ) ); ?></th>
	<td><h2 style="margin:0;padding:0;"><?php echo $balance; ?></h2></td>
</tr>
<?php
			}
		}

		/**
		 * Adjust Users Balance
		 * @since 0.1
		 * @version 1.2
		 */
		public function adjust_users_balance( $user ) {
			if ( ! isset( $_GET['ctype'] ) )
				$type = 'bonipress_default';
			else
				$type = sanitize_key( $_GET['ctype'] );

			$bonipress = bonipress( $type );

			if ( $bonipress->can_edit_creds() && ! $bonipress->can_edit_plugin() )
				$req = '(<strong>' . __( 'erforderlich', 'bonipress' ) . '</strong>)'; 
			else
				$req = '(' . __( 'optional', 'bonipress' ) . ')'; ?>

<table class="form-table">
	<tr>
		<th scope="row"><label for="boniPRESS-manual-add-points"><?php _e( 'Betrag', 'bonipress' ) ?></label></th>
		<td id="boniPRESS-adjust-users-points">
			<input type="text" name="bonipress_adjust_users_balance[amount]" id="boniPRESS-manual-add-points" value="<?php echo $bonipress->zero(); ?>" size="8" />
			<input type="hidden" name="bonipress_adjust_users_balance[ctype]" value="<?php echo $type; ?>" />
			<input type="hidden" name="bonipress_adjust_users_balance[user_id]" value="<?php echo $user->ID; ?>" />
			<input type="hidden" name="bonipress_adjust_users_balance[token]" value="<?php echo wp_create_nonce( 'bonipress-adjust-balance' ); ?>" />
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="boniPRESS-manual-add-description"><?php _e( 'Log Eintrag', 'bonipress' ); ?> <?php echo $req; ?></label></th>
		<td>
			<input type="text" name="bonipress_adjust_users_balance[log]" id="boniPRESS-manual-add-description" value="" class="regular-text" /><br />
			<span class="description"><?php echo $bonipress->available_template_tags( array( 'general' ) ); ?></span><br /><br />
			<?php submit_button( __( 'Guthaben aktualisieren', 'bonipress' ), 'primary medium', 'bonipress_adjust_users_balance_run', false ); ?>
		</td>
	</tr>
</table>
<?php
		}

		/**
		 * Admin Footer
		 * Inserts the Inline Edit Form modal.
		 * @since 1.2
		 * @version 1.2
		 */
		public function admin_footer() {
			$screen = get_current_screen();
			if ( $screen->id != 'users' ) return;

			if ( $this->core->can_edit_creds() && ! $this->core->can_edit_plugin() )
				$req = '(<strong>' . __( 'erforderlich', 'bonipress' ) . '</strong>)'; 
			else
				$req = '(' . __( 'optional', 'bonipress' ) . ')';

			ob_start(); ?>

<div id="edit-bonipress-balance" style="display: none;">
	<div class="bonipress-adjustment-form">
		<p class="row inline" style="width: 20%"><label><?php _e( 'ID', 'bonipress' ); ?>:</label><span id="bonipress-userid"></span></p>
		<p class="row inline" style="width: 40%"><label><?php _e( 'Benutzer', 'bonipress' ); ?>:</label><span id="bonipress-username"></span></p>
		<p class="row inline" style="width: 40%"><label><?php _e( 'Aktuelles Guthaben', 'bonipress' ); ?>:</label> <span id="bonipress-current"></span></p>
		<div class="clear"></div>
		<input type="hidden" name="bonipress_update_users_balance[token]" id="bonipress-update-users-balance-token" value="<?php echo wp_create_nonce( 'bonipress-update-users-balance' ); ?>" />
		<input type="hidden" name="bonipress_update_users_balance[type]" id="bonipress-update-users-balance-type" value="" />
		<p class="row"><label for="bonipress-update-users-balance-amount"><?php _e( 'Betrag', 'bonipress' ); ?>:</label><input type="text" name="bonipress_update_users_balance[amount]" id="bonipress-update-users-balance-amount" value="" /><br /><span class="description"><?php _e( 'Ein positiver oder negativer Wert', 'bonipress' ); ?>.</span></p>
		<p class="row"><label for="bonipress-update-users-balance-entry"><?php _e( 'Protokoll Eintrag', 'bonipress' ); ?>:</label><input type="text" name="bonipress_update_users_balance[entry]" id="bonipress-update-users-balance-entry" value="" /><br /><span class="description"><?php echo $req; ?></span></p>
		<p class="row"><input type="button" name="bonipress-update-users-balance-submit" id="bonipress-update-users-balance-submit" value="<?php _e( 'Guthaben aktualisieren', 'bonipress' ); ?>" class="button button-primary button-large" /></p>
		<div class="clear"></div>
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
?>