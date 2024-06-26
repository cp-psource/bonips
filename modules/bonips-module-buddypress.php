<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * boniPS_BuddyPress_Module class
 * @since 0.1
 * @version 1.3.2
 */
if ( ! class_exists( 'boniPS_BuddyPress_Module' ) ) :
	class boniPS_BuddyPress_Module extends boniPS_Module {

		protected $hooks;
		protected $settings;

		/**
		 * Constructor
		 */
		public function __construct( $type = BONIPS_DEFAULT_TYPE_KEY ) {

			parent::__construct( 'boniPS_BuddyPress', array(
				'module_name' => 'buddypress',
				'defaults'    => array(
					'visibility'         => array(
						'balance' => 0,
						'history' => 0
					),
					'balance_location'   => '',
					'balance_template'   => '%plural% balance:',
					'history_location'   => '',
					'history_menu_title' => array(
						'me'      => __( "Wallet", 'bonips' ),
						'others'  => __( "%s's Wallet", 'bonips' )
					),
					'history_menu_pos'   => 99,
					'history_url'        => 'bonips-history',
					'history_num'        => 10
				),
				'register'    => false,
				'add_to_core' => true
			), $type );

			if ( ! is_admin() )
				add_action( 'bp_setup_nav', array( $this, 'setup_nav' ) );

		}

		/**
		 * Init
		 * @since 0.1
		 * @version 1.3
		 */
		public function module_init() {

			global $bp;

			add_filter( 'logout_url', array( $this, 'adjust_logout' ), 99, 2 );

			$this->selected_type = BONIPS_DEFAULT_TYPE_KEY;
			if ( isset( $_GET['show-ctype'] ) ) {
				$selected = sanitize_text_field( $_GET['show-ctype'] );
				if ( array_key_exists( $selected, $this->point_types ) )
					$this->selected_type = $selected;
			}

			if ( $this->buddypress['balance_location'] == 'top' || $this->buddypress['balance_location'] == 'both' )
				add_action( 'bp_before_member_header_meta',  array( $this, 'show_balance' ), 10 );
 
 			if ( $this->buddypress['balance_location'] == 'profile_tab' || $this->buddypress['balance_location'] == 'both' )
				add_action( 'bp_after_profile_loop_content', array( $this, 'show_balance_profile' ), 10 );

		}

		/**
		 * Adjust Logout Link
		 * If we are logging out from the points history page, we want to make
		 * sure we are redirected away from this page when we log out. All else
		 * the default logout link is used.
		 * @since 1.3.1
		 * @version 1.1.1
		 */
		public function adjust_logout( $logouturl, $redirect ) {

			if ( ! is_array( $redirect ) && preg_match( '/(' . $this->buddypress['history_url'] . ')/', $redirect, $match ) ) {

				global $bp;

				$url       = remove_query_arg( 'redirect_to', $logouturl );
				$logouturl = add_query_arg( array( 'redirect_to' => urlencode( $bp->displayed_user->domain ) ), $url );

			}

			return apply_filters( 'bonips_bp_logout_url', esc_url( $logouturl ), $this );

		}

		/**
		 * Show Balance in Profile
		 * @since 0.1
		 * @version 1.4.1
		 */
		public function show_balance_profile() {

			// Prep
			$output       = '';
			$user_id      = bp_displayed_user_id();

			// Check for exclusion
			if ( $this->core->exclude_user( $user_id ) ) return;

			// Check visibility settings
			if ( ! $this->buddypress['visibility']['balance'] && ! bp_is_my_profile() && ! bonips_is_admin() ) return;

			// Loop though all post types
			$bonips_types = bonips_get_types();
			if ( ! empty( $bonips_types ) ) {

				$template = $this->buddypress['balance_template'];
				foreach ( $bonips_types as $type => $label ) {

					// Load boniPS with this points type
					$bonips   = bonips( $type );

					// Check if user is excluded from this type
					if ( $bonips->exclude_user( $user_id ) ) continue;

					// Get users balance
					$balance  = $bonips->get_users_balance( $user_id, $type );

					// Output
					$template = str_replace( '%label%', $label, $template );
					$output  .= sprintf( '<div class="bp-widget bonips"><h4>%s</h4><table class="profile-fields"><tr class="field_1 field_current_balance_' . $type . '"><td class="label">%s</td><td class="data">%s</td></tr></table></div>', $bonips->plural(), __( 'Aktuelles Guthaben', 'bonips' ), $bonips->format_creds( $balance ) );

				}

			}

			echo apply_filters( 'bonips_bp_profile_details', $output, $balance, $this );

		}

		/**
		 * Show Balance in Header
		 * @since 0.1
		 * @version 1.4.1
		 */
		public function show_balance( $dump = NULL, $context = 'header' ) {

			// Prep
			$output       = '';
			$user_id      = bp_displayed_user_id();

			// Check for exclusion
			if ( $this->core->exclude_user( $user_id ) ) return;

			// Check visibility settings
			if ( ! $this->buddypress['visibility']['balance'] && ! bp_is_my_profile() && ! bonips_is_admin() ) return;

			// Parse template
			$template     = $this->buddypress['balance_template'];

			// Loop though all post types
			$bonips_types = bonips_get_types();
			if ( ! empty( $bonips_types ) ) {

				$_template = $template;
				foreach ( $bonips_types as $type => $label ) {

					$template = $_template;

					// Load boniPS with this points type
					$bonips   = bonips( $type );

					// Check if user is excluded from this type
					if ( $bonips->exclude_user( $user_id ) ) continue;

					// Get users balance
					$balance  = $bonips->get_users_balance( $user_id, $type );

					// Output
					$template = str_replace( '%label%', $label, $template );
					$template = $bonips->template_tags_general( $template );
					$output  .= '<div class="bonips-balance bonips-' . $type . '">' . $template . ' ' . $bonips->format_creds( $balance ) . '</div>';

				}
			
			}

			echo apply_filters( 'bonips_bp_profile_header', $output, $this->buddypress['balance_template'], $this );

		}

		/**
		 * Setup Navigation
		 * @since 0.1
		 * @version 1.3.2
		 */
		public function setup_nav() {

			global $bp;

			$user_id = bp_displayed_user_id();

			// User is excluded
			if ( $this->core->exclude_user( $user_id ) || $this->buddypress['history_location'] == '' ) return;

			// If visibility is not set for visitors
			if ( ! is_user_logged_in() && ! $this->buddypress['visibility']['history'] ) return;

			// Admins always see the token history
			if ( ! $this->core->user_is_point_editor() && $this->buddypress['history_location'] != 'top' ) return;

			// Show admins
			if ( $this->core->user_is_point_editor() )
				$show = true;

			else
				$show = $this->buddypress['visibility']['history'];

			// Top Level Nav Item
			$me       = str_replace( '%label%', $this->point_types[ $this->selected_type ], $this->buddypress['history_menu_title']['me'] );
			$others   = str_replace( '%label%', $this->point_types[ $this->selected_type ], $this->buddypress['history_menu_title']['others'] );
			$top_name = bp_word_or_name( $me, $others, false, false );

			bp_core_new_nav_item( array(
				'name'                    => $this->core->template_tags_general( $top_name ),
				'slug'                    => $this->buddypress['history_url'],
				'parent_url'              => $bp->displayed_user->domain,
				'default_subnav_slug'     => $this->buddypress['history_url'],
				'screen_function'         => array( $this, 'my_history' ),
				'show_for_displayed_user' => $show,
				'position'                => $this->buddypress['history_menu_pos']
			) );

			// Date Sorting
			$date_sorting = apply_filters( 'bonips_sort_by_time', array(
				''          => __( 'Alle', 'bonips' ),
				'today'     => __( 'Heute', 'bonips' ),
				'yesterday' => __( 'Gestern', 'bonips' ),
				'thisweek'  => __( 'Diese Woche', 'bonips' ),
				'thismonth' => __( 'Diesen Monat', 'bonips' )
			) );

			$ctype    = '/';
			if ( $this->selected_type != BONIPS_DEFAULT_TYPE_KEY )
				$ctype .= esc_url( add_query_arg( array( 'show-ctype' => $this->selected_type ) ) );

			// "All" is default
			bp_core_new_subnav_item( array(
				'name'            => __( 'Alle', 'bonips' ),
				'slug'            => $this->buddypress['history_url'],
				'parent_url'      => $bp->displayed_user->domain . $this->buddypress['history_url'] . $ctype,
				'parent_slug'     => $this->buddypress['history_url'],
				'screen_function' => array( $this, 'my_history' )
			) );

			// Loop though and add each filter option as a sub menu item
			if ( ! empty( $date_sorting ) ) {
				foreach ( $date_sorting as $sorting_id => $sorting_name ) {

					if ( empty( $sorting_id ) ) continue;

					bp_core_new_subnav_item( array(
						'name'            => $sorting_name,
						'slug'            => $sorting_id,
						'parent_url'      => $bp->displayed_user->domain . $this->buddypress['history_url'] . $ctype,
						'parent_slug'     => $this->buddypress['history_url'],
						'screen_function' => array( $this, 'my_history' )
					) );

				}
			}

		}

		/**
		 * Construct My Verlauf Page
		 * @since 0.1
		 * @version 1.0.2
		 */
		public function my_history() {

			add_action( 'bp_template_title',         array( $this, 'my_history_title' ) );
			add_action( 'bp_template_content',       array( $this, 'my_history_screen' ) );
			add_filter( 'bonips_log_paginate_class', array( $this, 'paginate_class' ) );

			bp_core_load_template( apply_filters( 'bp_core_template_plugin', 'members/single/plugins' ) );

		}

		/**
		 * Pagination Styling
		 * @since 1.5.3
		 * @version 1.0
		 */
		public function paginate_class( $classes ) {

			return 'btn btn-default button button-seconday';

		}

		/**
		 * My Verlauf Title
		 * @since 0.1
		 * @version 1.1
		 */
		public function my_history_title() {

			$me     = str_replace( '%label%', $this->point_types[ $this->selected_type ], $this->buddypress['history_menu_title']['me'] );
			$others = str_replace( '%label%', $this->point_types[ $this->selected_type ], $this->buddypress['history_menu_title']['others'] );
			$title  = bp_word_or_name( $me, $others, false, false );
			$title  = $this->core->template_tags_general( $title );

			echo apply_filters( 'bonips_br_history_page_title', $title, $this );

		}

		/**
		 * My Verlauf Content
		 * @since 0.1
		 * @version 1.2.3
		 */
		public function my_history_screen() {

			global $bp;

			$bonips_types = bonips_get_types();
			$type         = BONIPS_DEFAULT_TYPE_KEY;
			if ( isset( $_REQUEST['show-ctype'] ) && array_key_exists( $_REQUEST['show-ctype'], $bonips_types ) )
				$type = $_REQUEST['show-ctype'];

			$args = array(
				'user_id' => bp_displayed_user_id(),
				'number'  => apply_filters( 'bonips_bp_history_num_to_show', $this->buddypress['history_num'] ),
				'ctype'   => $type
			);

			if ( isset( $_GET['paged'] ) && $_GET['paged'] != '' )
				$args['paged'] = $_GET['paged'];

			if ( isset( $bp->canonical_stack['action'] ) && $bp->canonical_stack['action'] != $this->buddypress['history_url'] )
				$args['time'] = $bp->canonical_stack['action'];

			$log = new boniPS_Query_Log( $args );
			$log->table_headers();
			unset( $log->headers['username'] );

			ob_start();

			if ( count( $bonips_types ) > 1 ) :

?>
<form action="" id="bonips-sort-cred-history-form" method="get"><label><?php _e( 'Show:', 'bonips' ); ?></label> <?php bonips_types_select_from_dropdown( 'show-ctype', 'bonips-select-type', $type ); ?> <input type="submit" class="btn btn-large btn-primary button button-large button-primary" value="<?php _e( 'Los', 'bonips' ); ?>" /></form>
<?php

			endif;

?>
<style type="text/css">
.pagination-links { float: right; }
.tablenav { vertical-align: middle; }
</style>
<?php do_action( 'bonips_bp_profile_before_history', $args['user_id'] ); ?>

<form class="form" role="form" method="get" action="">
	<div class="tablenav top">

		<?php if ( $log->have_entries() && $log->max_num_pages > 1 ) $log->navigation( 'top' ); ?>

	</div>

	<?php $log->display(); ?>

	<div class="tablenav bottom">

		<?php if ( $log->have_entries() && $log->max_num_pages > 1 ) $log->navigation( 'bottom' ); ?>

	</div>

</form>
<?php

			do_action( 'bonips_bp_profile_after_history', $args['user_id'] );

			$output = ob_get_contents();
			ob_end_clean();

			echo apply_filters( 'bonips_bp_history_page', $output, $this );

		}

		/**
		 * After General Settings
		 * @since 0.1
		 * @version 1.4
		 */
		public function after_general_settings( $bonips = NULL ) {

			// Settings
			global $bp;

			$settings          = $this->buddypress;

			$balance_locations = array(
				''            => __( 'Nicht anzeigen', 'bonips' ),
				'top'         => __( 'In Profilheader aufnehmen', 'bonips' ),
				'profile_tab' => __( 'Als Profil-Registerkarte', 'bonips' ),
				'both'        => __( 'Als Profil-Registerkarte und im Profilheader', 'bonips' )
			);

			$history_locations = array(
				''    => __( 'Nicht anzeigen', 'bonips' ),
				'top' => __( 'Im Profil anzeigen', 'bonips' )
			);

			$bp_nav_positions  = array();
			if ( isset( $bp->bp_nav ) ) {
				foreach ( $bp->bp_nav as $pos => $data ) {
					if ( ! isset( $data['slug'] ) || $data['slug'] == $settings['history_url'] ) continue; 
					$bp_nav_positions[] = ucwords( $data['slug'] ) . ' = ' . $pos;
				}
			}

?>
<h4><span class="dashicons dashicons-admin-plugins static"></span><label>BuddyPress</label></h4>
<div class="body" style="display:none;">

	<?php do_action( 'bonips_bp_before_settings', $this ); ?>

	<div class="row">
		<div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'balance_location' ); ?>"><?php echo $this->core->template_tags_general( __( '%singular% Guthaben', 'bonips' ) ); ?></label>
				<select name="<?php echo $this->field_name( 'balance_location' ); ?>" id="<?php echo $this->field_id( 'balance_location' ); ?>" class="form-control">
<?php

			foreach ( $balance_locations as $location => $description ) { 
				echo '<option value="' . $location . '"';
				if ( isset( $settings['balance_location'] ) && $settings['balance_location'] == $location ) echo ' selected="selected"';
				echo '>' . $description . '</option>';
			}

?>
				</select>
			</div>
			<div class="form-group">
				<div class="checkbox">
					<label for="<?php echo $this->field_id( array( 'visibility' => 'balance' ) ); ?>"><input type="checkbox" name="<?php echo $this->field_name( array( 'visibility' => 'balance' ) ); ?>" id="<?php echo $this->field_id( array( 'visibility' => 'balance' ) ); ?>" <?php checked( $settings['visibility']['balance'], 1 ); ?> value="1" /> <?php echo $this->core->template_tags_general( __( 'Mitglieder und Besucher können das Guthaben anderer Mitglieder %_singular% anzeigen.', 'bonips' ) ); ?></label>
				</div>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'balance_template' ); ?>"><?php _e( 'Vorlage', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( 'balance_template' ); ?>" id="<?php echo $this->field_id( 'balance_template' ); ?>" value="<?php echo esc_attr( $settings['balance_template'] ); ?>" class="form-control" />
				<p><span class="description"><?php echo $this->core->available_template_tags( array( 'general', 'balance' ) ); ?></span></p>
			</div>
		</div>
	</div>

	<div class="row">
		<div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'history_location' ); ?>"><?php echo $this->core->template_tags_general( __( '%plural% Verläufe', 'bonips' ) ); ?></label>
				<select name="<?php echo $this->field_name( 'history_location' ); ?>" id="<?php echo $this->field_id( 'history_location' ); ?>" class="form-control">
<?php

			foreach ( $history_locations as $location => $description ) { 
				echo '<option value="' . $location . '"';
				if ( isset( $settings['history_location'] ) && $settings['history_location'] == $location ) echo ' selected="selected"';
				echo '>' . $description . '</option>';
			}

?>
				</select>
			</div>
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'visibility' => 'history' ) ); ?>"><input type="checkbox" name="<?php echo $this->field_name( array( 'visibility' => 'history' ) ); ?>" id="<?php echo $this->field_id( array( 'visibility' => 'history' ) ); ?>" <?php checked( $settings['visibility']['history'], 1 ); ?> value="1" /> <?php echo $this->core->template_tags_general( __( 'Mitglieder können sich gegenseitig den Verlauf von %_plural% anzeigen.', 'bonips' ) ); ?></label>
			</div>
		</div>
		<div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'history_menu_title' => 'me' ) ); ?>"><?php _e( 'Menütitel', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'history_menu_title' => 'me' ) ); ?>" id="<?php echo $this->field_id( array( 'history_menu_title' => 'me' ) ); ?>" value="<?php echo esc_attr( $settings['history_menu_title']['me'] ); ?>" class="form-control" />
				<p><span class="description"><?php _e( 'Titel mir gezeigt', 'bonips' ); ?></span></p>
			</div>
		</div>
		<div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
			<div class="form-group">
				<label>&nbsp;</label>
				<input type="text" name="<?php echo $this->field_name( array( 'history_menu_title' => 'others' ) ); ?>" id="<?php echo $this->field_id( array( 'history_menu_title' => 'others' ) ); ?>" value="<?php echo esc_attr( $settings['history_menu_title']['others'] ); ?>" class="form-control" />
				<p><span class="description"><?php _e( 'Titel anderen gezeigt. Verwende %s, um den Vornamen anzuzeigen.', 'bonips' ); ?></span></p>
			</div>
		</div>
	</div>

	<div class="row">
		<div class="col-lg-4 col-md-4 col-sm-4 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'history_menu_pos' ); ?>"><?php _e( 'Menüposition', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( 'history_menu_pos' ); ?>" id="<?php echo $this->field_id( 'history_menu_pos' ); ?>" value="<?php echo esc_attr( $settings['history_menu_pos'] ); ?>" class="form-control" />
				<p><span class="description"><?php printf( '%s %s', __( 'Aktuelle Menüpositionen:', 'bonips' ), implode( ', ', $bp_nav_positions ) ); ?></span></p>
			</div>
		</div>
		<div class="col-lg-4 col-md-4 col-sm-4 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'history_url' ); ?>"><?php _e( 'Verlaufs-URL-Slug', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( 'history_url' ); ?>" id="<?php echo $this->field_id( 'history_url' ); ?>" value="<?php echo esc_attr( $settings['history_url'] ); ?>" class="form-control" />
				<p><span class="description"><?php _e( 'Der Verlaufsseiten-Slug. Muss URL-freundlich sein.', 'bonips' ); ?></span></p>
			</div>
		</div>
		<div class="col-lg-4 col-md-4 col-sm-4 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'history_num' ); ?>"><?php _e( 'Anzahl der anzuzeigenden Verlaufseinträge', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( 'history_num' ); ?>" id="<?php echo $this->field_id( 'history_num' ); ?>" value="<?php echo esc_attr( $settings['history_num'] ); ?>" class="form-control" />
			</div>
		</div>
	</div>

	<?php do_action( 'bonips_bp_after_settings', $this ); ?>

</div>
<?php

		}

		/**
		 * Sanitize Core Settings
		 * @since 0.1
		 * @version 1.2.1
		 */
		public function sanitize_extra_settings( $new_data, $data, $core ) {

			$new_data['buddypress']['balance_location']             = sanitize_text_field( $data['buddypress']['balance_location'] );
			$new_data['buddypress']['visibility']['balance']        = ( isset( $data['buddypress']['visibility']['balance'] ) ) ? true : false;

			$new_data['buddypress']['history_location']             = sanitize_text_field( $data['buddypress']['history_location'] );
			$new_data['buddypress']['balance_template']             = sanitize_text_field( $data['buddypress']['balance_template'] );

			$new_data['buddypress']['history_menu_title']['me']     = sanitize_text_field( $data['buddypress']['history_menu_title']['me'] );
			$new_data['buddypress']['history_menu_title']['others'] = sanitize_text_field( $data['buddypress']['history_menu_title']['others'] );
			$new_data['buddypress']['history_menu_pos']             = absint( $data['buddypress']['history_menu_pos'] );

			$new_data['buddypress']['history_url']                  = sanitize_text_field( $data['buddypress']['history_url'] );
			$new_data['buddypress']['history_num']                  = absint( $data['buddypress']['history_num'] );

			$new_data['buddypress']['visibility']['history']        = ( isset( $data['buddypress']['visibility']['history'] ) ) ? true : false;

			return apply_filters( 'bonips_bp_sanitize_settings', $new_data, $data, $core );

		}

	}
endif;
