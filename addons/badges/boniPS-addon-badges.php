<?php
/**
 * Addon: Abzeichen
 * Version: 1.3
 */
if ( ! defined( 'boniPS_VERSION' ) ) exit;

define( 'boniPS_BADGE',              __FILE__ );
define( 'boniPS_BADGE_VERSION',      '1.2' );
define( 'BONIPS_BADGE_DIR',          boniPS_ADDONS_DIR . 'badges/' );
define( 'BONIPS_BADGE_INCLUDES_DIR', BONIPS_BADGE_DIR . 'includes/' );

// Badge Key
if ( ! defined( 'BONIPS_BADGE_KEY' ) )
	define( 'BONIPS_BADGE_KEY', 'bonips_badge' );

// Default badge width
if ( ! defined( 'BONIPS_BADGE_WIDTH' ) )
	define( 'BONIPS_BADGE_WIDTH', 100 );

// Default badge height
if ( ! defined( 'BONIPS_BADGE_HEIGHT' ) )
	define( 'BONIPS_BADGE_HEIGHT', 100 );

require_once BONIPS_BADGE_INCLUDES_DIR . 'bonips-badge-functions.php';
require_once BONIPS_BADGE_INCLUDES_DIR . 'bonips-badge-shortcodes.php';
require_once BONIPS_BADGE_INCLUDES_DIR . 'bonips-badge-object.php';
require_once BONIPS_BADGE_INCLUDES_DIR . 'bonips-badge-secondary.php';

/**
 * boniPS_buyCRED_Module class
 * @since 1.5
 * @version 1.2
 */
if ( ! class_exists( 'boniPS_Badge_Module' ) ) :
	class boniPS_Badge_Module extends boniPS_Module {

		/**
		 * Construct
		 */
		function __construct( $type = BONIPS_DEFAULT_TYPE_KEY ) {

			parent::__construct( 'boniPS_Badge_Module', array(
				'module_name' => 'badges',
				'defaults'    => array(
					'buddypress'  => '',
					'psforum'     => '',
					'show_all_bp' => 0,
					'show_all_bb' => 0
				),
				'add_to_core' => true,
				'register'    => false,
				'menu_pos'    => 50
			), $type );

		}

		/**
		 * Module Pre Init
		 * @since 1.0
		 * @version 1.0
		 */
		public function module_pre_init() {

			add_filter( 'bonips_add_finished', array( $this, 'add_finished' ), 30, 3 );

		}

		/**
		 * Module Init
		 * @since 1.0
		 * @version 1.0.3
		 */
		public function module_init() {

			$this->register_badges();

			add_action( 'bonips_set_current_account', array( $this, 'populate_current_account' ) );
			add_action( 'bonips_get_account',         array( $this, 'populate_account' ) );

			add_shortcode( BONIPS_SLUG . '_my_badges', 'bonips_render_my_badges' );
			add_shortcode( BONIPS_SLUG . '_badges',    'bonips_render_badges' );

			// Insert into PSForum
			if ( class_exists( 'PSForum' ) ) {

				if ( $this->badges['psforum'] == 'profile' || $this->badges['psforum'] == 'both' )
					add_action( 'psf_template_after_user_profile', array( $this, 'insert_into_psforum_profile' ) );

				if ( $this->badges['psforum'] == 'reply' || $this->badges['psforum'] == 'both' )
					add_action( 'psf_theme_after_reply_author_details', array( $this, 'insert_into_psforum_reply' ) );

			}

			// Insert into BuddyPress
			if ( class_exists( 'BuddyPress' ) ) {

				// Insert into header
				if ( $this->badges['buddypress'] == 'header' || $this->badges['buddypress'] == 'both' )
					add_action( 'bp_before_member_header_meta', array( $this, 'insert_into_buddypress' ) );

				// Insert into profile
				if ( $this->badges['buddypress'] == 'profile' || $this->badges['buddypress'] == 'both' )
					add_action( 'bp_after_profile_loop_content', array( $this, 'insert_into_buddypress' ) );

			}

			add_action( 'bonips_add_menu',   array( $this, 'add_to_menu' ), $this->menu_pos );

		}

		/**
		 * Module Admin Init
		 * @since 1.0
		 * @version 1.1
		 */
		public function module_admin_init() {

			add_filter( 'parent_file',                       array( $this, 'parent_file' ) );
			add_filter( 'submenu_file',                      array( $this, 'subparent_file' ), 10, 2 );
			add_action( 'bonips_admin_enqueue',              array( $this, 'enqueue_scripts' ), $this->menu_pos );

			add_filter( 'post_row_actions',                  array( $this, 'adjust_row_actions' ), 10, 2 );

			add_filter( 'post_updated_messages',             array( $this, 'post_updated_messages' ) );
			add_filter( 'enter_title_here',                  array( $this, 'enter_title_here' ) );
			add_action( 'post_submitbox_start',              array( $this, 'publishing_actions' ) );

			add_action( 'wp_ajax_bonips-assign-badge',       array( $this, 'action_assign_badge' ) );
			add_action( 'wp_ajax_bonips-remove-connections', array( $this, 'action_remove_connections' ) );

			add_action( 'bonips_user_edit_after_balances',   array( $this, 'badge_user_screen' ), 10 );

			add_action( 'personal_options_update',           array( $this, 'save_manual_badges' ), 10 );
			add_action( 'edit_user_profile_update',          array( $this, 'save_manual_badges' ), 10 );

			add_action( 'bonips_delete_point_type',          array( $this, 'delete_point_type' ) );
			add_action( 'before_delete_post',                array( $this, 'delete_badge' ) );

			add_filter( 'manage_' . BONIPS_BADGE_KEY . '_posts_columns',       array( $this, 'adjust_column_headers' ) );
			add_action( 'manage_' . BONIPS_BADGE_KEY . '_posts_custom_column', array( $this, 'adjust_column_content' ), 10, 2 );
			add_action( 'save_post_' . BONIPS_BADGE_KEY,                       array( $this, 'save_badge' ), 10, 2 );

		}

		/**
		 * Register Badge Post Type
		 * @since 1.0
		 * @version 1.0
		 */
		public function register_badges() {

			$labels = array(
				'name'               => __( 'Abzeichen', 'bonips' ),
				'singular_name'      => __( 'Abzeichen', 'bonips' ),
				'add_new'            => __( 'Neues hinzufügen', 'bonips' ),
				'add_new_item'       => __( 'Neues hinzufügen', 'bonips' ),
				'edit_item'          => __( 'Abzeichen bearbeiten', 'bonips' ),
				'new_item'           => __( 'Neues Abzeichen', 'bonips' ),
				'all_items'          => __( 'Abzeichen', 'bonips' ),
				'view_item'          => __( 'Abzeichen anzeigen', 'bonips' ),
				'search_items'       => __( 'Abzeichen suchen', 'bonips' ),
				'not_found'          => __( 'Keine Abzeichen gefunden', 'bonips' ),
				'not_found_in_trash' => __( 'Keine Abzeichen im Papierkorb gefunden', 'bonips' ), 
				'parent_item_colon'  => '',
				'menu_name'          => __( 'Abzeichen', 'bonips' )
			);

			$args = array(
				'labels'               => $labels,
				'supports'             => array( 'title' ),
				'hierarchical'         => false,
				'public'               => false,
				'show_ui'              => true,
				'show_in_menu'         => false,
				'show_in_nav_menus'    => false,
				'show_in_admin_bar'    => false,
				'can_export'           => true,
				'has_archive'          => false,
				'exclude_from_search'  => true,
				'publicly_queryable'   => false,
				'register_meta_box_cb' => array( $this, 'add_metaboxes' )
			);

			register_post_type( BONIPS_BADGE_KEY, apply_filters( 'bonips_register_badge', $args ) );

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
				&& ( isset( $bonips_current_account->badges ) )
			) return;

			$earned       = array();
			$users_badges = bonips_get_users_badges( $bonips_current_account->user_id, true );

			if ( ! empty( $users_badges ) ) {
				foreach ( $users_badges as $badge_id => $level ) {

					if ( ! is_numeric( $level ) )
						$level = 0;

					$badge_id = absint( $badge_id );
					$level    = absint( $level );
					$badge    = bonips_get_badge( $badge_id, $level );

					$earned[ $badge_id ] = $badge;

				}
			}

			$bonips_current_account->badges    = $earned;
			$bonips_current_account->badge_ids = $users_badges;

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
				&& ( isset( $bonips_account->badges ) )
			) return;

			$earned       = array();
			$users_badges = bonips_get_users_badges( $bonips_account->user_id );

			if ( ! empty( $users_badges ) ) {
				foreach ( $users_badges as $badge_id => $level ) {

					if ( ! is_numeric( $level ) )
						$level = 0;

					$badge_id = absint( $badge_id );
					$level    = absint( $level );
					$badge    = bonips_get_badge( $badge_id, $level );

					$earned[ $badge_id ] = $badge;

				}
			}

			$bonips_account->badges    = $earned;
			$bonips_account->badge_ids = $users_badges;

		}

		/**
		 * Delete Point Type
		 * When a point type is deleted, we want to remove any data saved for this point type.
		 * @since 1.7
		 * @version 1.0
		 */
		public function delete_point_type( $point_type = NULL ) {

			if ( ! bonips_point_type_exists( $point_type ) || $point_type == BONIPS_DEFAULT_TYPE_KEY ) return;

			$bonips = bonips( $point_type );

			if ( ! $bonips->user_is_point_editor() ) return;

			bonips_delete_option( 'bonips-badge-refs-' . $point_type );

		}

		/**
		 * Delete Badge
		 * When a badge is deleted, we want to delete connections as well.
		 * @since 1.7
		 * @version 1.0
		 */
		public function delete_badge( $post_id ) {

			if ( get_post_status( $post_id ) != BONIPS_BADGE_KEY ) return $post_id;

			// Delete reference list to force a new query
			foreach ( $this->point_types as $type_id => $label )
				bonips_delete_option( 'bonips-badge-refs-' . $type_id );

			global $wpdb;

			// Delete connections to keep usermeta table clean
			$wpdb->delete(
				$wpdb->usermeta,
				array( 'meta_key' => BONIPS_BADGE_KEY . $post_id ),
				array( '%s' )
			);

		}

		/**
		 * Adjust Post Updated Messages
		 * @since 1.0
		 * @version 1.0
		 */
		public function post_updated_messages( $messages ) {

			global $post;

			$messages[ BONIPS_BADGE_KEY ] = array(
				0  => '',
				1  => __( 'Abzeichen aktualisiert.', 'bonips' ),
				2  => __( 'Abzeichen aktualisiert.', 'bonips' ),
				3  => __( 'Abzeichen aktualisiert.', 'bonips' ),
				4  => __( 'Abzeichen aktualisiert.', 'bonips' ),
				5  => false,
				6  => __( 'Abzeichen aktiviert.', 'bonips' ),
				7  => __( 'Abzeichen gespeichert.', 'bonips' ),
				8  => __( 'Abzeichen aktualisiert.', 'bonips' ),
				9  => __( 'Abzeichen aktualisiert.', 'bonips' ),
				10 => __( 'Abzeichen aktualisiert.', 'bonips' )
			);

			return $messages;

		}

		/**
		 * Add Admin Menu Item
		 * @since 1.7
		 * @version 1.1
		 */
		public function add_to_menu() {

			// In case we are using the Master Template feautre on multisites, and this is not the main
			// site in the network, bail.
			if ( bonips_override_settings() && ! bonips_is_main_site() ) return;

			add_submenu_page(
				BONIPS_SLUG,
				__( 'Abzeichen', 'bonips' ),
				__( 'Abzeichen', 'bonips' ),
				$this->core->get_point_editor_capability(),
				'edit.php?post_type=' . BONIPS_BADGE_KEY
			);

		}

		/**
		 * Parent File
		 * @since 1.6
		 * @version 1.0.2
		 */
		public function parent_file( $parent = '' ) {

			global $pagenow;

			if ( ( $pagenow == 'edit.php' || $pagenow == 'post-new.php' ) && isset( $_GET['post_type'] ) && $_GET['post_type'] == BONIPS_BADGE_KEY ) {
			
				return BONIPS_SLUG;
			
			}

			elseif ( $pagenow == 'post.php' && isset( $_GET['post'] ) && bonips_get_post_type( $_GET['post'] ) == BONIPS_BADGE_KEY ) {

				return BONIPS_SLUG;

			}

			return $parent;

		}

		/**
		 * Sub Parent File
		 * @since 1.7
		 * @version 1.0
		 */
		public function subparent_file( $subparent = '', $parent = '' ) {

			global $pagenow;

			if ( ( $pagenow == 'edit.php' || $pagenow == 'post-new.php' ) && isset( $_GET['post_type'] ) && $_GET['post_type'] == BONIPS_BADGE_KEY ) {

				return 'edit.php?post_type=' . BONIPS_BADGE_KEY;
			
			}

			elseif ( $pagenow == 'post.php' && isset( $_GET['post'] ) && bonips_get_post_type( $_GET['post'] ) == BONIPS_BADGE_KEY ) {

				return 'edit.php?post_type=' . BONIPS_BADGE_KEY;

			}

			return $subparent;

		}

		/**
		 * Add Finished
		 * @since 1.0
		 * @version 1.4
		 */
		public function add_finished( $result, $request, $bonips ) {

			if ( is_bool( $request ) ) return $result;

			extract( $request );

			if ( $result !== false && $ref != 'badge_reward' ) {

				// Check if this reference has badges
				$badge_ids = bonips_ref_has_badge( $ref, $type );
				if ( $badge_ids !== false ) {

					// Check if user gets any of the badges
					foreach ( $badge_ids as $badge_id ) {

						$badge = bonips_get_badge( $badge_id );
						if ( $badge === false ) continue;

						// Check what level we reached (if we reached any)
						$level_reached = $badge->query_users_level( $user_id );
						if ( $level_reached !== false )
							$badge->assign( $user_id, $level_reached );

					}

				}

			}

			return $result;

		}

		/**
		 * Adjust Badge Column Header
		 * @since 1.0
		 * @version 1.0
		 */
		public function adjust_column_headers( $defaults ) {

			$columns                        = array();
			$columns['cb']                  = $defaults['cb'];

			// Add / Adjust
			$columns['title']               = __( 'Abzeichenname', 'bonips' );
			$columns['badge-default-image'] = __( 'Standardbild', 'bonips' );
			$columns['badge-earned-image']  = __( 'Erster Level', 'bonips' );
			$columns['badge-reqs']          = __( 'Anforderungen', 'bonips' );
			$columns['badge-users']         = __( 'Benutzer', 'bonips' );

			// Return
			return $columns;

		}

		/**
		 * Adjust Badge Column Content
		 * @since 1.0
		 * @version 1.2
		 */
		public function adjust_column_content( $column_name, $badge_id ) {

			// Default Images
			if ( $column_name == 'badge-default-image' ) {

				$badge = bonips_get_badge( $badge_id );
				if ( $badge === false || $badge->main_image === false )
					echo '-';

				elseif ( $badge->main_image !== false )
					echo $badge->main_image;

			}

			// First Level Image
			if ( $column_name == 'badge-earned-image' ) {

				$badge = bonips_get_badge( $badge_id );
				$image = $badge->get_image( 0 );
				if ( $image === false)
					echo '-';
				else
					echo $image;

			}

			// Badge Requirements
			elseif ( $column_name == 'badge-reqs' ) {

				echo bonips_display_badge_requirements( $badge_id );

			}

			// Badge Users
			elseif ( $column_name == 'badge-users' ) {

				$badge = bonips_get_badge( $badge_id );
				if ( $badge === false )
					echo 0;

				else
					echo $badge->earnedby;

			}

		}

		/**
		 * Adjust Row Actions
		 * @since 1.0
		 * @version 1.0
		 */
		public function adjust_row_actions( $actions, $post ) {

			if ( $post->post_type == BONIPS_BADGE_KEY ) {
				unset( $actions['inline hide-if-no-js'] );
				unset( $actions['view'] );
			}

			return $actions;

		}

		/**
		 * Adjust Enter Title Here
		 * @since 1.0
		 * @version 1.0
		 */
		public function enter_title_here( $title ) {

			global $post_type;

			if ( $post_type == BONIPS_BADGE_KEY )
				return __( 'Abzeichenname', 'bonips' );

			return $title;

		}

		/**
		 * Enqueue Scripts
		 * @since 1.0
		 * @version 1.0.1
		 */
		public function enqueue_scripts() {

			$screen = get_current_screen();
			if ( $screen->id == BONIPS_BADGE_KEY ) {

				wp_enqueue_media();

				wp_register_script(
					'bonips-edit-badge',
					plugins_url( 'assets/js/edit-badge.js', boniPS_BADGE ),
					array( 'jquery', 'bonips-mustache' ),
					boniPS_BADGE_VERSION . '.1'
				);

				wp_localize_script(
					'bonips-edit-badge',
					'boniPSBadge',
					array(
						'ajaxurl'      => admin_url( 'admin-ajax.php' ),
						'addlevel'     => esc_js( __( 'Level hinzufügen', 'bonips' ) ),
						'removelevel'  => esc_js( __( 'Level entfernen', 'bonips' ) ),
						'setimage'     => esc_js( __( 'Bild einstellen', 'bonips' ) ),
						'changeimage'  => esc_js( __( 'Bild ändern', 'bonips' ) ),
						'remove'       => esc_js( esc_attr__( 'Möchtest Du diese Ebene wirklich entfernen?', 'bonips' ) ),
						'levellabel'   => esc_js( sprintf( '%s {{level}}', __( 'Level', 'bonips' ) ) ),
						'uploadtitle'  => esc_js( esc_attr__( 'Abzeichen-Bild', 'bonips' ) ),
						'uploadbutton' => esc_js( esc_attr__( 'Als Abzeichen verwenden', 'bonips' ) ),
						'compareAND'   => esc_js( _x( 'AND', 'Vergleich der Badge-Anforderungen. A AND B', 'bonips' ) ),
						'compareOR'    => esc_js( _x( 'OR', 'Vergleich der Badge-Anforderungen. A OR B', 'bonips' ) )
					)
				);

				wp_enqueue_script( 'bonips-edit-badge' );

				wp_enqueue_style( 'bonips-bootstrap-grid' );
				wp_enqueue_style( 'bonips-forms' );

				add_filter( 'postbox_classes_' . BONIPS_BADGE_KEY . '_bonips-badge-setup',   array( $this, 'metabox_classes' ) );
				add_filter( 'postbox_classes_' . BONIPS_BADGE_KEY . '_bonips-badge-default', array( $this, 'metabox_classes' ) );
				add_filter( 'postbox_classes_' . BONIPS_BADGE_KEY . '_bonips-badge-rewards', array( $this, 'metabox_classes' ) );

				echo '<style type="text/css">
#misc-publishing-actions #visibility, #misc-publishing-actions .misc-pub-post-status { display: none; }
#save-action #save-post { margin-bottom: 12px; }
</style>';

			}

			elseif ( $screen->id == 'edit-' . BONIPS_BADGE_KEY ) {

				echo '<style type="text/css">
th#badge-default-image { width: 120px; }
th#badge-earned-image { width: 120px; }
th#badge-reqs { width: 35%; }
th#badge-users { width: 10%; }
.column-badge-default-image img { max-width: 100px; height: auto; }
.bonips-badge-requirement-list { margin: 6px 0 0 0; padding: 6px 0 0 18px; border-top: 1px dashed #aeaeae; }
.bonips-badge-requirement-list li { margin: 0 0 0 0; padding: 0 0 0 0; font-size: 12px; line-height: 16px; list-style-type: circle; }
.bonips-badge-requirement-list li span { float: right; }
.column-badge-reqs strong { display: block; }
.column-badge-reqs span { color: #aeaeae; }
</style>';

			}

		}

		/**
		 * Add Meta Boxes
		 * @since 1.0
		 * @version 1.0
		 */
		public function add_metaboxes() {

			add_meta_box(
				'bonips-badge-setup',
				__( 'Abzeichen Setup', 'bonips' ),
				array( $this, 'metabox_badge_setup' ),
				BONIPS_BADGE_KEY,
				'normal',
				'high'
			);

			add_meta_box(
				'bonips-badge-default',
				__( 'Standardabzeichenbild', 'bonips' ),
				array( $this, 'metabox_badge_default' ),
				BONIPS_BADGE_KEY,
				'side',
				'low'
			);

		}

		/**
		 * Level Template
		 * @since 1.7
		 * @version 1.0
		 */
		public function level_template( $level = 0 ) {

			if ( $level == 0 )
				return '<div class="row badge-level" id="bonips-badge-level{{level}}" data-level="{{level}}"><div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 text-center">{{addlevelbutton}}<div class="level-image"><div class="level-image-wrapper image-wrapper {{emptylevelimage}}">{{levelimage}}</div><div class="level-image-actions"><button type="button" class="button button-secondary change-level-image" data-level="{{level}}">{{levelimagebutton}}</button></div></div><div class="label-field"><input type="text" placeholder="{{levelplaceholder}}" name="bonips_badge[levels][{{level}}][label]" value="{{levellabel}}" /></div></div><div class="col-lg-12 col-md-12 col-sm-12 col-xs-12"><div class="req-title">{{requirementslabel}}<div class="pull-right" id="badge-requirement-compare"><a href="javascript:void(0);" data-do="AND" class="{{adnselected}}">AND</a> / <a href="javascript:void(0);" data-do="OR" class="{{orselected}}">OR</a><input type="hidden" name="bonips_badge[levels][{{level}}][compare]" value="{{badge_compare_andor}}" /></div></div><div class="level-requirements">{{{requirements}}}</div></div><div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">{{rewards}}</div></div>';

			return '<div class="row badge-level" id="bonips-badge-level{{level}}" data-level="{{level}}"><div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 text-center">{{removelevelbutton}}<div class="level-image"><div class="level-image-wrapper image-wrapper {{emptylevelimage}}">{{levelimage}}</div><div class="level-image-actions"><button type="button" class="button button-secondary change-level-image" data-level="{{level}}">{{levelimagebutton}}</button></div></div><div class="label-field"><input type="text" placeholder="{{levelplaceholder}}" name="bonips_badge[levels][{{level}}][label]" value="{{levellabel}}" /></div></div><div class="col-lg-12 col-md-12 col-sm-12 col-xs-12"><div class="req-title">{{requirementslabel}}</div><div class="level-requirements">{{{requirements}}}</div></div><div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">{{rewards}}</div></div>';

		}

		/**
		 * Get Level Image
		 * @since 1.7
		 * @version 1.0
		 */
		public function get_level_image( $setup, $level = 0 ) {

			$image = false;

			if ( $setup['attachment_id'] > 0 ) {

				$_image = wp_get_attachment_url( $setup['attachment_id'] );
				if ( strlen( $_image ) > 5 )
					$image = '<img src="' . $_image . '" alt="Badge level image" /><input type="hidden" name="bonips_badge[levels][' . $level . '][attachment_id]" value="' . $setup['attachment_id'] . '" /><input type="hidden" name="bonips_badge[levels][' . $level . '][image_url]" value="" />';

			}
			else {

				if ( strlen( $setup['image_url'] ) > 5 )
					$image = '<img src="' . $setup['image_url'] . '" alt="Badge level image" /><input type="hidden" name="bonips_badge[levels][' . $level . '][attachment_id]" value="0" /><input type="hidden" name="bonips_badge[levels][' . $level . '][image_url]" value="' . $setup['image_url'] . '" />';

			}

			return $image;

		}

		/**
		 * Requirements Template
		 * @since 1.7
		 * @version 1.0
		 */
		public function requirements_template( $level = 0 ) {

			// only first level dictates requirements
			if ( $level == 0 )
			return '<div class="row row-narrow" id="level{{level}}requirement{{reqlevel}}" data-row="{{reqlevel}}"><div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 form"><div class="form-group"><select name="bonips_badge[levels][{{level}}][requires][{{reqlevel}}][type]" data-row="{{reqlevel}}" class="form-control point-type">{{pointtypes}}</select></div></div><div class="col-lg-5 col-md-5 col-sm-6 col-xs-12 form"><div class="form-group"><select name="bonips_badge[levels][{{level}}][requires][{{reqlevel}}][reference]" data-row="{{reqlevel}}" class="form-control reference">{{references}}</select></div>{{{customrequirement}}}</div><div class="col-lg-3 col-md-3 col-sm-6 col-xs-10 form-inline"><div class="form-group"><input type="text" size="5" name="bonips_badge[levels][{{level}}][requires][{{reqlevel}}][amount]" class="form-control" value="{{reqamount}}" /></div><div class="form-group"><select name="bonips_badge[levels][{{level}}][requires][{{reqlevel}}][by]" data-row="{{reqlevel}}" class="form-control req-type">{{requirementtype}}</select></div></div><div class="col-lg-1 col-md-1 col-sm-6 col-xs-2 form">{{reqbutton}}</div></div>';

			// All other requirements reflect the level 0's setup
			return '<div class="row row-narrow" id="level{{level}}requirement{{reqlevel}}"><div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 form"><div class="form-group level-type"><p class="form-control-static level-requirement{{reqlevel}}-type">{{selectedtype}}</p></div></div><div class="col-lg-5 col-md-5 col-sm-6 col-xs-12 form"><div class="form-group level-ref"><p class="form-control-static level-requirement{{reqlevel}}-ref">{{selectedref}}</p><p>{{refspecific}}</p></div></div><div class="col-lg-3 col-md-3 col-sm-6 col-xs-10 form-inline"><div class="form-group level-val"><input type="text" size="5" name="bonips_badge[levels][{{level}}][requires][{{reqlevel}}][amount]" class="form-control" value="{{reqamount}}" /></div><div class="form-group level-type-by"><p class="form-control-static level-requirement{{reqlevel}}-by">{{selectedby}}</p></div></div><div class="col-lg-1 col-md-1 col-sm-6 col-xs-2 level-compare form"><p class="form-control-static" data-row="{{reqlevel}}">{{comparelabel}}</p></div></div>';

		}

		/**
		 * Rewards Template
		 * @since 1.7
		 * @version 1.0
		 */
		public function rewards_template() {

			return '<div class="req-title">{{rewardlabel}}</div><div class="row form"><div class="col-lg-4 col-md-4 col-sm-12 col-xs-12"><select name="bonips_badge[levels][{{level}}][reward][type]" class="form-control">{{pointtypes}}</select></div><div class="col-lg-6 col-md-6 col-sm-12 col-xs-12"><input type="text" class="form-control" name="bonips_badge[levels][{{level}}][reward][log]" placeholder="{{logplaceholder}}" value="{{logtemplate}}" /></div><div class="col-lg-2 col-md-2 col-sm-12 col-xs-12"><input type="text" class="form-control" name="bonips_badge[levels][{{level}}][reward][amount]" placeholder="0" value="{{rewardamount}}" /></div></div>';

		}

		/**
		 * Badge Publishing Actions
		 * @since 1.7
		 * @version 1.1
		 */
		public function publishing_actions() {

			global $post;

			if ( ! isset( $post->post_type ) || $post->post_type != BONIPS_BADGE_KEY ) return;

			$manual_badge = ( (int) bonips_get_post_meta( $post->ID, 'manual_badge', true ) == 1 ) ? true : false;

?>
<div id="bonips-badge-actions" class="seperate-bottom">

	<?php do_action( 'bonips_edit_badge_before_actions', $post ); ?>

	<input type="hidden" name="bonips-badge-edit" value="<?php echo wp_create_nonce( 'edit-bonips-badge' ); ?>" />
	<input type="button" id="bonips-assign-badge-connections"<?php if ( $manual_badge || $post->post_status != 'publish' ) echo ' disabled="disabled"'; ?> value="<?php _e( 'Assign Badge', 'bonips' ); ?>" class="button button-secondary bonips-badge-action-button" data-action="bonips-assign-badge" data-token="<?php echo wp_create_nonce( 'bonips-assign-badge' ); ?>" /> 
	<input type="button" id="bonips-remove-badge-connections"<?php if ( $post->post_status != 'publish' ) echo ' disabled="disabled"'; ?> value="<?php _e( 'Remove Connections', 'bonips' ); ?>" class="button button-secondary bonips-badge-action-button" data-action="bonips-remove-connections" data-token="<?php echo wp_create_nonce( 'bonips-remove-badge-connection' ); ?>" />

	<?php do_action( 'bonips_edit_badge_after_actions', $post ); ?>

<script type="text/javascript">
jQuery(function($) {

	$( 'input.bonips-badge-action-button' ).click(function(){
		var button = $(this);
		var label = button.val();

		$.ajax({
			type : "POST",
			data : {
				action   : button.attr( 'data-action' ),
				token    : button.attr( 'data-token' ),
				badge_id : <?php echo $post->ID; ?>
			},
			dataType : "JSON",
			url : ajaxurl,
			beforeSend : function() {
				button.attr( 'value', '<?php echo esc_js( esc_attr__( 'Wird bearbeitet...', 'bonips' ) ); ?>' );
				button.attr( 'disabled', 'disabled' );
			},
			success : function( response ) {
				alert( response.data );
				button.removeAttr( 'disabled' );
				button.val( label );
			}
		});
		return false;

	});

});
</script>

</div>
<div id="bonips-manual-badge" class="seperate-bottom">
	<label for="bonips-badge-is-manual"><input type="checkbox" name="bonips_badge[manual]" id="bonips-badge-is-manual"<?php if ( $manual_badge ) echo ' checked="checked"'; ?> value="1" /> <?php _e( 'Dieses Abzeichen wird manuell vergeben.', 'bonips' ); ?></label>
</div>
<?php

		}

		/**
		 * Default Image Metabox
		 * @since 1.7
		 * @version 1.0
		 */
		public function metabox_badge_default( $post ) {

			$default_image = $di = bonips_get_post_meta( $post->ID, 'main_image', true );
			if ( $default_image != '' )
				$default_image = '<img src="' . $default_image . '" alt="" />';

			$attachment = false;
			if ( is_numeric( $di ) && strpos( '://', $di ) === false ) {
				$attachment    = $di;
				$default_image = '<img src="' . wp_get_attachment_url( $di ) . '" alt="" />';
			}

?>
<div class="row">
	<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
		<div class="default-image text-center seperate-bottom">
			<div class="default-image-wrapper image-wrapper<?php if ( $default_image == '' ) echo ' empty dashicons'; ?>">
				<?php echo $default_image; ?>
				<input type="hidden" name="bonips_badge[main_image]" id="badge-main-image-id" value="<?php if ( $attachment ) echo esc_attr( $di ); ?>" />
				<input type="hidden" name="bonips_badge[main_image_url]" id="badge-main-image-url" value="<?php if ( $di != '' && strpos( '://', $di ) !== false ) echo esc_attr( $default_image ); ?>" />
			</div>
			<div class="level-image-actions">
				<button type="button" class="button button-secondary" id="badges-change-default-image" data-do="<?php if ( $default_image == '' ) echo 'set'; else echo 'change'; ?>"><?php if ( $default_image == '' ) _e( 'Bild einstellen', 'bonips' ); else _e( 'Bild ändern', 'bonips' ); ?></button>
				<button type="button" class="button button-secondary <?php echo ( ( ! $attachment ) ? 'hidden' : '' ); ?>" id="badges-remove-default-image"><?php _e( 'Bild entfernen', 'bonips' ); ?></button>
			</div>
		</div>
		<span class="description"><?php _e( 'Optionales Bild, das angezeigt wird, wenn ein Benutzer dieses Abzeichen nicht verdient hat.', 'bonips' ); ?></span>
	</div>
</div>
<?php

		}

		/**
		 * Badge Setup Metabox
		 * @since 1.7
		 * @version 1.2
		 */
		public function metabox_badge_setup( $post ) {

			$badge       = bonips_get_badge( $post->ID );
			$references  = bonips_get_all_references();
			$point_types = bonips_get_types( true );

			$sums = apply_filters( 'bonips_badge_requirement_sums', array(
				'count' => esc_js( __( 'Mal', 'bonips' ) ),
				'sum'   => esc_js( __( 'Insgesamt', 'bonips' ) )
			), $badge );

			// Badge rewards can no be used as a requirement
			if ( array_key_exists( 'badge_reward', $references ) )
				unset( $references['badge_reward'] );

			$js_level             = $this->level_template( 1 );
			$js_requirement       = $this->requirements_template( 0 );
			$js_requirement_clone = $this->requirements_template( 1 );

?>
<div id="badge-levels">
<?php

			// Loop through each badge level
			$level_counter = 0;
			foreach ( $badge->levels as $level => $setup ) {

				$level        = $level_counter;

				$add_level    = '<button type="button" class="button button-seconary button-small top-right-corner" id="badges-add-new-level">' . esc_js( __( 'Level hinzufügen', 'bonips' ) ) . '</button>';
				$remove_level = '<button type="button" class="button button-seconary button-small top-right-corner remove-badge-level" data-level="' . $level . '">' . esc_js( __( 'Level entfernen', 'bonips' ) ) . '</button>';

				$level_image  = $this->get_level_image( $setup, $level );
				$empty_level  = 'empty dashicons';
				if ( $level_image !== false )
					$empty_level = '';

				$template = $this->level_template( $level );

				$template = str_replace( '{{level}}',             $level, $template );
				$template = str_replace( '{{addlevelbutton}}',    $add_level, $template );
				$template = str_replace( '{{removelevelbutton}}', $remove_level, $template );

				$js_level = str_replace( '{{removelevelbutton}}', $remove_level, $js_level );
				$js_level = str_replace( '{{emptylevelimage}}',   $empty_level, $js_level );
				$js_level = str_replace( '{{levelimage}}',        '', $js_level );
				$js_level = str_replace( '{{levelimagebutton}}',  esc_js( __( 'Bild einstellen', 'bonips' ) ), $js_level );
				$js_level = str_replace( '{{levelplaceholder}}',  esc_js( __( 'Level', 'bonips' ) ) . ' {{levelone}}', $js_level );

				$template = str_replace( '{{levelimage}}',        $level_image, $template );
				$template = str_replace( '{{emptylevelimage}}',   $empty_level, $template );
				$template = str_replace( '{{levelimagebutton}}',  ( ( $level_image === false ) ? esc_js( __( 'Bild einstellen', 'bonips' ) ) : esc_js( __( 'Bild ändern', 'bonips' ) ) ), $template );

				$template = str_replace( '{{levelplaceholder}}',  esc_js( sprintf( __( 'Level %d', 'bonips' ), $level+1 ) ), $template );
				$template = str_replace( '{{levellabel}}',        esc_js( $setup['label'] ), $template );

				$template = str_replace( '{{requirementslabel}}', esc_js( __( 'Voraussetzung', 'bonips' ) ), $template );
				$js_level = str_replace( '{{requirementslabel}}', esc_js( __( 'Voraussetzung', 'bonips' ) ), $js_level );

				$template = str_replace( '{{adnselected}}',       ( ( $setup['compare'] === 'AND' ) ? 'selected' : '' ), $template );
				$template = str_replace( '{{orselected}}',        ( ( $setup['compare'] === 'OR' ) ? 'selected' : '' ), $template );

				$template = str_replace( '{{badge_compare_andor}}', ( ( isset($setup['compare']) && !empty($setup['compare']) ) ? $setup['compare'] : 'AND' ), $template );

				//$requirement = $this->requirements_template( 1 );

				$total_requirements = count( $setup['requires'] );
				$level_requirements = '';

				foreach ( $setup['requires'] as $req_level => $reqsetup ) {

					$requirement         = $this->requirements_template( $level );

					$requirement         = str_replace( '{{level}}',    $level, $requirement );
					$requirement         = str_replace( '{{reqlevel}}', $req_level, $requirement );

					$point_type_options  = '';
					$point_type_options .= '<option value=""';
					if ( $reqsetup['type'] == '' ) $point_type_options .= ' selected="selected"';
					$point_type_options .= '>' . esc_js( __( 'Punkttyp auswählen', 'bonips' ) ) . '</option>';
					foreach ( $point_types as $type_id => $type_label ) {
						$point_type_options .= '<option value="' . esc_attr( $type_id ) . '"';
						if ( $reqsetup['type'] == $type_id ) $point_type_options .= ' selected="selected"';
						$point_type_options .= '>' . esc_html( $type_label ) . '</option>';
					}

					$requirement         = str_replace( '{{pointtypes}}', $point_type_options, $requirement );
					$point_type_options  = str_replace( 'selected="selected"', '', $point_type_options );
					$js_requirement      = str_replace( '{{pointtypes}}', $point_type_options, $js_requirement );

					$reference_options   = '';
					$reference_options  .= '<option value=""';
					if ( $reqsetup['reference'] == '' ) $reference_options .= ' selected="selected"';
					$reference_options  .= '>' . esc_js( __( 'Wähle Referenz', 'bonips' ) ) . '</option>';
					foreach ( $references as $ref_id => $ref_label ) {
						$reference_options .= '<option value="' . esc_attr( $ref_id ) . '"';
						if ( $reqsetup['reference'] == $ref_id ) $reference_options .= ' selected="selected"';
						$reference_options .= '>' . esc_html( $ref_label ) . '</option>';
					}

					$requirement         = str_replace( '{{references}}', $reference_options, $requirement );

					$requirement_specific = apply_filters( 'bonips_badge_requirement_specific_template', '', $req_level, $reqsetup, $badge, $level );
					$requirement         = str_replace( '{{{customrequirement}}}', $requirement_specific,  $requirement );

					$requirement         = str_replace( '{{reqamount}}',  $reqsetup['amount'], $requirement );

					$reference_options   = str_replace( 'selected="selected"', '', $reference_options );
					$js_requirement      = str_replace( '{{references}}', $reference_options, $js_requirement );
					$js_requirement      = str_replace( '{{reqamount}}',  $reqsetup['amount'], $js_requirement );

					$by_options          = '';
					$by_options         .= '<option value=""';
					if ( $reqsetup['by'] == '' ) $by_options .= ' selected="selected"';
					$by_options         .= '>' . __( 'Wählen', 'bonips' ) . '</option>';
					foreach ( $sums as $sum_id => $sum_label ) {
						$by_options .= '<option value="' . $sum_id . '"';
						if ( $reqsetup['by'] == $sum_id ) $by_options .= ' selected="selected"';
						$by_options .= '>' . $sum_label . '</option>';
					}

					$requirement         = str_replace( '{{requirementtype}}', $by_options, $requirement );

					$by_options          = str_replace( 'selected="selected"', '', $by_options );
					$js_requirement      = str_replace( '{{requirementtype}}', $by_options, $js_requirement );

					$selectedtype        = '-';
					if ( array_key_exists( $reqsetup['type'], $point_types ) )
						$selectedtype = $point_types[ $reqsetup['type'] ];

					$requirement = str_replace( '{{selectedtype}}', $selectedtype, $requirement );

					$requirement = str_replace( '{{refspecific}}', '', $requirement );

					$selectedreference   = '-';
					if ( array_key_exists( $reqsetup['reference'], $references ) )
						$selectedreference = $references[ $reqsetup['reference'] ];

					$requirement         = str_replace( '{{selectedref}}', $selectedreference, $requirement );

					$selectedby          = '-';
					if ( array_key_exists( $reqsetup['by'], $sums ) )
						$selectedby = $sums[ $reqsetup['by'] ];

					$requirement         = str_replace( '{{selectedby}}', $selectedby, $requirement );

					$requirement_button  = '<button type="button" class="button button-primary form-control remove-requirement" data-req="{{reqlevel}}">-</button>';
					$js_requirement      = str_replace( '{{reqbutton}}', $requirement_button, $js_requirement );

					$requirement_button  = '<button type="button" class="button button-primary form-control remove-requirement" data-req="' . $req_level . '">-</button>';
					if ( $req_level == 0 )
						$requirement_button = '<button type="button" class="button button-secondary form-control" id="badges-add-new-requirement">+</button>';

					$requirement         = str_replace( '{{reqbutton}}', $requirement_button, $requirement );

					$compare_label       = '';
					if ( $level > 0 && $req_level < $total_requirements )
						$compare_label = ( ( $setup['compare'] === 'AND' ) ? _x( 'AND', 'Comparison of badge requirements. A AND B', 'bonips' ) : _x( 'OR', 'Comparison of badge requirements. A OR B', 'bonips' ) );

					if ( $req_level+1 == $total_requirements )
						$compare_label = '';

					$requirement         = str_replace( '{{comparelabel}}', esc_js( $compare_label ), $requirement );

					$level_requirements .= $requirement;

				}

				$template           = str_replace( '{{{requirements}}}', $level_requirements, $template );

				$rewards            = $this->rewards_template();

				$js_level           = str_replace( '{{reqamount}}',     '', $js_level );

				$rewards            = str_replace( '{{level}}',          $level, $rewards );
				$rewards            = str_replace( '{{rewardlabel}}',    esc_js( __( 'Belohnung', 'bonips' ) ), $rewards );

				$point_type_options = '';
				foreach ( $point_types as $type_id => $type_label ) {
					$point_type_options .= '<option value="' . $type_id . '"';
					if ( $setup['reward']['type'] == $type_id ) $point_type_options .= ' selected="selected"';
					$point_type_options .= '>' . $type_label . '</option>';
				}

				$rewards            = str_replace( '{{pointtypes}}',     $point_type_options, $rewards );
				$rewards            = str_replace( '{{logplaceholder}}', esc_js( __( 'Protokollvorlage', 'bonips' ) ), $rewards );
				$rewards            = str_replace( '{{logtemplate}}',    esc_js( $setup['reward']['log'] ), $rewards );
				$rewards            = str_replace( '{{rewardamount}}',   $setup['reward']['amount'], $rewards );

				$template           = str_replace( '{{rewards}}',       $rewards, $template );

				$rewards            = str_replace( $level,         '{{level}}', $rewards );

				$js_level           = str_replace( '{{rewards}}',       $rewards, $js_level );

				echo $template;

				$level_counter++;

			}

?>
</div>
<script type="text/javascript">
var BadgeLevel         = '<?php echo $js_level; ?>';
var BadgeNewRequrement = '<?php echo $js_requirement; ?>';
var BadgeRequirement   = '<?php echo $js_requirement_clone; ?>';
</script>
<?php

		}

		/**
		 * Save Badge Details
		 * @since 1.7
		 * @version 1.1
		 */
		public function save_badge( $post_id, $post = NULL ) {

			if ( $post === NULL || ! $this->core->user_is_point_editor() || ! isset( $_POST['bonips_badge'] ) ) return $post_id;

			$manual = 0;
			if ( isset( $_POST['bonips_badge']['manual'] ) )
				$manual = 1;

			$badge_levels       = array();
			$badge_requirements = array();

			// Run through each level
			if ( ! empty( $_POST['bonips_badge']['levels'] ) ) {

				$level_row = 0;
				foreach ( $_POST['bonips_badge']['levels'] as $level_id => $level_setup ) {

					$level = array();

					if ( array_key_exists( 'attachment_id', $level_setup ) ) {
						$level['attachment_id'] = absint( $level_setup['attachment_id'] );
						$level['image_url']     = ( ( array_key_exists( 'image_url', $level_setup ) ) ? sanitize_text_field( $level_setup['image_url'] ) : '' );
					}
					else {
						$level['attachment_id'] = 0;
						$level['image_url']     = '';
					}

					$level['label']         = sanitize_text_field( $level_setup['label'] );

					if ( array_key_exists( 'compare', $level_setup ) )
						$level['compare'] = ( ( $level_setup['compare'] == 'AND' ) ? 'AND' : 'OR' );
					else
						$level['compare'] = ( ( array_key_exists( 'compare', $badge_levels[0] ) ) ? $badge_levels[0]['compare'] : 'AND' );

					$level['requires']      = array();

					if ( array_key_exists( 'requires', $level_setup ) ) {

						$level_requirements = array();

						$row = 0;
						foreach ( $level_setup['requires'] as $requirement_id => $requirement_setup ) {

							$requirement              = array();
							$requirement['type']      = ( ( array_key_exists( 'type', $requirement_setup ) ) ? sanitize_key( $requirement_setup['type'] ) : '' );
							$requirement['reference'] = ( ( array_key_exists( 'reference', $requirement_setup ) ) ? sanitize_key( $requirement_setup['reference'] ) : '' );
							$requirement['amount']    = ( ( array_key_exists( 'amount', $requirement_setup ) ) ? sanitize_text_field( $requirement_setup['amount'] ) : '' );
							$requirement['by']        = ( ( array_key_exists( 'by', $requirement_setup ) ) ? sanitize_key( $requirement_setup['by'] ) : '' );
							$requirement['specific']  = ( ( array_key_exists( 'specific', $requirement_setup ) ) ? sanitize_text_field( $requirement_setup['specific'] ) : '' );
							
							$level_requirements[ $row ] = $requirement;
							$row ++;

						}

						if ( $level_row == 0 )
							$badge_requirements = $level_requirements;

						$completed_requirements = array();
						foreach ( $level_requirements as $requirement_id => $requirement_setup ) {

							if ( $level_row == 0 ) {
								$completed_requirements[ $requirement_id ] = $requirement_setup;
								continue;
							}

							$completed_requirements[ $requirement_id ]           = $badge_requirements[ $requirement_id ];
							$completed_requirements[ $requirement_id ]['amount'] = $requirement_setup['amount'];

						}

						$level['requires'] = $completed_requirements;

					}

					$reward = array( 'type' => '', 'log' => '', 'amount' => '' );

					if ( array_key_exists( 'reward', $level_setup ) ) {

						$reward['type'] = sanitize_key( $level_setup['reward']['type'] );
						$reward['log']  = sanitize_text_field( $level_setup['reward']['log'] );

						if ( $reward['type'] != BONIPS_DEFAULT_TYPE_KEY )
							$bonips = bonips( $reward['type'] );
						else
							$bonips = $this->core;

						$reward['amount'] = $bonips->number( $level_setup['reward']['amount'] );

					}

					$level['reward']  = $reward;

					$badge_levels[] = $level;
					$level_row ++;

				}

			}

			// Save Badge Setup
			bonips_update_post_meta( $post_id, 'badge_prefs', $badge_levels );

			// If we just set the badge to be manual we need to re-parse all references.
			$old_manual = bonips_get_post_meta( $post_id, 'manual_badge', true );
			if ( absint( $old_manual ) === 0 && $manual === 1 ) {
				foreach ( $this->point_types as $type_id => $label ) {
					bonips_get_badge_references( $type_id, true );
				}
			}

			// Force re-calculation of used references
			foreach ( $this->point_types as $type_id => $type )
				bonips_delete_option( 'bonips-badge-refs-' . $type_id );

			// Save if badge is manuall
			bonips_update_post_meta( $post_id, 'manual_badge', $manual );

			// Main image (used when a user has not earned a badge
			$main_image = $_POST['bonips_badge']['main_image'];

			// If we are using an attachment
			if ( absint( $main_image ) > 0 )
				$image = absint( $main_image );

			// Else we are using a URL (old setup)
			else
				$image = sanitize_text_field( $_POST['bonips_badge']['main_image_url'] );

			bonips_update_post_meta( $post_id, 'main_image', $image );

			// Let others play
			do_action( 'bonips_save_badge', $post_id );

		}

		/**
		 * Add to General Settings
		 * @since 1.0
		 * @version 1.1
		 */
		public function after_general_settings( $bonips = NULL ) {

			$settings   = $this->badges;

			$buddypress = ( ( class_exists( 'BuddyPress' ) ) ? true : false ); 
			$psforum    = ( ( class_exists( 'PSForum' ) ) ? true : false ); 

?>
<h4><span class="dashicons dashicons-admin-plugins static"></span><?php _e( 'Abzeichen', 'bonips' ); ?></h4>
<div class="body" style="display:none;">

	<h3><?php _e( 'Integrationen von Drittanbietern', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'buddypress' ); ?>">BuddyPress</label>
				<?php if ( $buddypress ) : ?>
				<select name="<?php echo $this->field_name( 'buddypress' ); ?>" id="<?php echo $this->field_id( 'buddypress' ); ?>" class="form-control">
<?php

			$buddypress_options = array(
				''        => __( 'Nicht anzeigen', 'bonips' ),
				'header'  => __( 'In Profilkopfzeile anzeigen', 'bonips' ),
				'profile' => __( 'Unter der Registerkarte "Profil" anzeigen', 'bonips' ),
				'both'    => __( 'Auf der Registerkarte „Profil“ und im Profil-Header anzeigen', 'bonips' )
			);

			foreach ( $buddypress_options as $location => $description ) { 
				echo '<option value="' . $location . '"';
				if ( isset( $settings['buddypress'] ) && $settings['buddypress'] == $location ) echo ' selected="selected"';
				echo '>' . $description . '</option>';
			}

?>

				</select>
			</div>
			<div class="form-group">
				<div class="checkbox">
					<label for="<?php echo $this->field_id( 'show_all_bp' ); ?>"><input type="checkbox" name="<?php echo $this->field_name( 'show_all_bp' ); ?>" id="<?php echo $this->field_id( 'show_all_bp' ); ?>" <?php checked( $settings['show_all_bp'], 1 ); ?> value="1" /> <?php _e( 'Alle Abzeichen anzeigen, einschließlich Abzeichen, die Benutzer noch nicht verdient haben.', 'bonips' ); ?></label>
				</div>
				<?php else : ?>
				<input type="hidden" name="<?php echo $this->field_name( 'buddypress' ); ?>" id="<?php echo $this->field_id( 'buddypress' ); ?>" value="" />
				<p><span class="description"><?php _e( 'Nicht installiert', 'bonips' ); ?></span></p>
				<?php endif; ?>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'psforum' ); ?>">PSForum</label>
				<?php if ( $psforum ) : ?>
				<select name="<?php echo $this->field_name( 'psforum' ); ?>" id="<?php echo $this->field_id( 'psforum' ); ?>" class="form-control">
<?php

			$psforum_options = array(
				''        => __( 'Nicht anzeigen', 'bonips' ),
				'profile' => __( 'In Profil anzeigen', 'bonips' ),
				'reply'   => __( 'In Forumsantworten anzeigen', 'bonips' ),
				'both'    => __( 'In Profil- und Forumantworten anzeigen', 'bonips' )
			);

			foreach ( $psforum_options as $location => $description ) { 
				echo '<option value="' . $location . '"';
				if ( isset( $settings['psforum'] ) && $settings['psforum'] == $location ) echo ' selected="selected"';
				echo '>' . $description . '</option>';
			}

?>

				</select>
			</div>
			<div class="form-group">
				<div class="checkbox">
					<label for="<?php echo $this->field_id( 'show_all_bb' ); ?>"><input type="checkbox" name="<?php echo $this->field_name( 'show_all_bb' ); ?>" id="<?php echo $this->field_id( 'show_all_bb' ); ?>" <?php checked( $settings['show_all_bb'], 1 ); ?> value="1" /> <?php _e( 'Alle Abzeichen anzeigen, einschließlich Abzeichen, die Benutzer noch nicht verdient haben.', 'bonips' ); ?></label>
				</div>
				<?php else : ?>
					<input type="hidden" name="<?php echo $this->field_name( 'psforum' ); ?>" id="<?php echo $this->field_id( 'psforum' ); ?>" value="" />
					<p><span class="description"><?php _e( '<a href="https://n3rds.work/piestingtal_source/ps-forum-plugin/" target=“_blank”>PS Forum</a>ist nicht installiert', 'bonips' ); ?></span></p>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<h3 style="margin-bottom: 0;"><?php _e( 'Verfügbare Shortcodes', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
			<p><a href="https://n3rds.work/docs/bonips-shortcodes-bonips_my_badges/" target="_blank">[bonips_my_badges]</a>, <a href="https://n3rds.work/docs/bonips-shortcodes-bonips_badges/" target="_blank">[bonips_badges]</a></p>
		</div>
	</div>

</div>
<?php

		}

		/**
		 * Save Settings
		 * @since 1.0
		 * @version 1.0.2
		 */
		public function sanitize_extra_settings( $new_data, $data, $core ) {

			$new_data['badges']['show_all_bp'] = ( isset( $data['badges']['show_all_bp'] ) ) ? $data['badges']['show_all_bp'] : 0;
			$new_data['badges']['show_all_bb'] = ( isset( $data['badges']['show_all_bb'] ) ) ? $data['badges']['show_all_bb'] : 0;

			$new_data['badges']['buddypress'] = ( isset( $data['badges']['buddypress'] ) ) ? sanitize_text_field( $data['badges']['buddypress'] ) : '';
			$new_data['badges']['psforum']    = ( isset( $data['badges']['psforum'] ) ) ? sanitize_text_field( $data['badges']['psforum'] ) : '';

			return $new_data;

		}

		/**
		 * User Badges Admin Screen
		 * @since 1.0
		 * @version 1.1
		 */
		public function badge_user_screen( $user ) {

			// Only visible to admins
			if ( ! bonips_is_admin() ) return;

			$user_id      = $user->ID;
			$all_badges   = bonips_get_badge_ids();
			$users_badges = bonips_get_users_badges( $user_id );

?>
<style type="text/css">
.badge-wrapper { min-height: 230px; }
.badge-wrapper select { width: 100%; }
.badge-image-wrap { text-align: center; }
.badge-image-wrap .badge-image { display: block; width: 100%; height: 100px; line-height: 100px; }
.badge-image-wrap .badge-image.empty { content: "<?php _e( 'No image set', 'bonips' ); ?>"; }
.badge-image-wrap .badge-image img { width: auto; height: auto; max-height: 100px; }
</style>
<table class="form-table">
	<tr>
		<th scope="row"><?php _e( 'Abzeichen', 'bonips' ); ?></th>
		<td>
			<fieldset id="bonips-badge-list" class="badge-list">
				<legend class="screen-reader-text"><span><?php _e( 'Abzeichen', 'bonips' ); ?></span></legend>
<?php

			if ( ! empty( $all_badges ) ) {
				foreach ( $all_badges as $badge_id ) {

					$badge_id     = absint( $badge_id );
					$badge        = bonips_get_badge( $badge_id );
					$earned       = 0;
					$earned_level = 0;
					$badge_image  = $badge->main_image;

					if ( array_key_exists( $badge_id, $users_badges ) ) {
						$earned       = 1;
						$earned_level = $users_badges[ $badge_id ];
						$badge_image  = $badge->get_image( $earned_level );
					}

					$level_select = '<input type="hidden" name="bonips_badge_manual[badges][' . $badge_id . '][level]" value="0" /><select disabled="disabled"><option>Level 1</option></select>';
					if ( count( $badge->levels ) > 1 ) {

						$level_select  = '<select name="bonips_badge_manual[badges][' . $badge_id . '][level]">';
						$level_select .= '<option value=""';
						if ( ! $earned ) $level_select .= ' selected="selected"';
						$level_select .= '>' . __( 'Wähle einen Level aus', 'bonips' ) . '</option>';

						foreach ( $badge->levels as $level_id => $level ) {
							$level_select .= '<option value="' . $level_id . '"';
							if ( $earned && $earned_level == $level_id ) $level_select .= ' selected="selected"';
							$level_select .= '>' . ( ( $level['label'] != '' ) ? $level['label'] : sprintf( '%s %d', __( 'Level', 'bonips' ), ( $level_id + 1 ) ) ) . '</option>';
						}

						$level_select .= '</select>';

					}

?>
				<div class="badge-wrapper color-option<?php if ( $earned === 1 ) echo ' selected'; ?>" id="bonips-badge<?php echo $badge_id; ?>-wrapper">
					<label for="bonips-badge<?php echo $badge_id; ?>"><input type="checkbox" name="bonips_badge_manual[badges][<?php echo $badge_id; ?>][has]" class="toggle-badge" id="bonips-badge<?php echo $badge_id; ?>" <?php checked( $earned, 1 );?> value="1" /> <?php _e( 'Verdient', 'bonips' ); ?></label>
					<div class="badge-image-wrap">

						<div class="badge-image<?php if ( $badge_image == '' ) echo ' empty'; ?>"><?php echo $badge_image; ?></div>

						<h4><?php echo $badge->title; ?></h4>
					</div>
					<div class="badge-actions" style="min-height: 32px;">

						<?php echo $level_select; ?>

					</div>
				</div>
<?php

				}
			}

?>
			</fieldset>
			<input type="hidden" name="bonips_badge_manual[token]" value="<?php echo wp_create_nonce( 'bonips-manual-badges' . $user_id ); ?>" />
		</td>
	</tr>
</table>
<script type="text/javascript">
jQuery(function($) {

	$( '.badge-wrapper label input.toggle-badge' ).click(function(){

		if ( $(this).is( ':checked' ) )
			$( '#' + $(this).attr( 'id' ) + '-wrapper' ).addClass( 'selected' );

		else
			$( '#' + $(this).attr( 'id' ) + '-wrapper' ).removeClass( 'selected' );

	});

});
</script>
<?php

		}

		/**
		 * Save Manual Badges
		 * @since 1.0
		 * @version 1.1
		 */
		public function save_manual_badges( $user_id ) {

			if ( ! bonips_is_admin() ) return;

			if ( isset( $_POST['bonips_badge_manual']['token'] ) ) {

				if ( wp_verify_nonce( $_POST['bonips_badge_manual']['token'], 'bonips-manual-badges' . $user_id ) ) {

					$added        = $removed = $updated = 0;
					$users_badges = bonips_get_users_badges( $user_id );

					if ( ! empty( $_POST['bonips_badge_manual']['badges'] ) ) {
						foreach ( $_POST['bonips_badge_manual']['badges'] as $badge_id => $data ) {

							$badge = bonips_get_badge( $badge_id );

							// Most likely not a badge post ID
							if ( $badge === false ) continue;

							// Give badge
							if ( ! array_key_exists( $badge_id, $users_badges ) && isset( $data['has'] ) && $data['has'] == 1 ) {

								$level = 0;
								if ( isset( $data['level'] ) && $data['level'] != '' )
									$level = absint( $data['level'] );

								$badge->assign( $user_id, $level );

								$added ++;

							}

							// Remove badge
							elseif ( array_key_exists( $badge_id, $users_badges ) && ! isset( $data['has'] ) ) {

								$badge->divest( $user_id );

								$removed ++;

							}

							// Level change
							elseif ( array_key_exists( $badge_id, $users_badges ) && isset( $data['level'] ) && $data['level'] != $users_badges[ $badge_id ] ) {

								$badge->assign( $user_id, $data['level'] );

								$updated ++;

							}

						}
					}

					if ( $added > 0 || $removed > 0 || $updated > 0 )
						bonips_delete_user_meta( $user_id, 'bonips_badge_ids' );

				}

			}

		}

		/**
		 * AJAX: Assign Badge
		 * @since 1.0
		 * @version 1.3
		 */
		public function action_assign_badge() {

			check_ajax_referer( 'bonips-assign-badge', 'token' );

			$badge_id = absint( $_POST['badge_id'] );
			if ( $badge_id === 0 ) wp_send_json_error();

			// Get the badge object
			$badge    = bonips_get_badge( $badge_id );

			// Most likely not a badge post ID
			if ( $badge === false ) wp_send_json_error();

			$results = $badge->assign_all();

			if ( $results > 0 )
				wp_send_json_success( sprintf( __( 'Insgesamt %d Benutzer haben dieses Abzeichen erhalten.', 'bonips' ), $results ) );

			wp_send_json_error( __( 'Dieses Abzeichen hat sich noch kein Benutzer verdient.', 'bonips' ) );

		}

		/**
		 * AJAX: Remove Badge Connections
		 * @since 1.0
		 * @version 1.1
		 */
		public function action_remove_connections() {

			check_ajax_referer( 'bonips-remove-badge-connection', 'token' );

			$badge_id = absint( $_POST['badge_id'] );
			if ( $badge_id === 0 ) wp_send_json_error();

			// Get the badge object
			$badge    = bonips_get_badge( $badge_id );

			// Most likely not a badge post ID
			if ( $badge === false ) wp_send_json_error();

			$results = $badge->divest_all();

			if ( $results == 0 )
				wp_send_json_success( __( 'Es wurden keine Verbindungen entfernt.', 'bonips' ) );

			wp_send_json_success( sprintf( __( '%s Verbindungen wurden entfernt.', 'bonips' ), $results ) );

		}

		/**
		 * Insert Badges into PSForum profile
		 * @since 1.0
		 * @version 1.1
		 */
		public function insert_into_psforum_profile() {

			$user_id = psf_get_displayed_user_id();
			if ( isset( $this->badges['show_all_bb'] ) && $this->badges['show_all_bb'] == 1 )
				echo bonips_render_my_badges( array(
					'show'    => 'all',
					'width'   => BONIPS_BADGE_WIDTH,
					'height'  => BONIPS_BADGE_HEIGHT,
					'user_id' => $user_id
				) );

			else
				bonips_display_users_badges( $user_id );

		}

		/**
		 * Insert Badges into PSForum
		 * @since 1.0
		 * @version 1.1
		 */
		public function insert_into_psforum_reply() {

			$user_id = psf_get_reply_author_id();
			if ( $user_id > 0 ) {

				if ( isset( $this->badges['show_all_bb'] ) && $this->badges['show_all_bb'] == 1 )
					echo bonips_render_my_badges( array(
						'show'    => 'all',
						'width'   => BONIPS_BADGE_WIDTH,
						'height'  => BONIPS_BADGE_HEIGHT,
						'user_id' => $user_id
					) );

				else
					bonips_display_users_badges( $user_id );

			}

		}

		/**
		 * Insert Badges in BuddyPress
		 * @since 1.0
		 * @version 1.1.1
		 */
		public function insert_into_buddypress() {

			$user_id = bp_displayed_user_id();
			if ( isset( $this->badges['show_all_bp'] ) && $this->badges['show_all_bp'] == 1 )
				echo bonips_render_my_badges( array(
					'show'    => 'all',
					'width'   => BONIPS_BADGE_WIDTH,
					'height'  => BONIPS_BADGE_HEIGHT,
					'user_id' => $user_id
				) );

			else
				bonips_display_users_badges( $user_id );

		}

	}
endif;

/**
 * Load Badges Module
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'bonips_load_badges_addon' ) ) :
	function bonips_load_badges_addon( $modules, $point_types ) {

		$modules['solo']['badges'] = new boniPS_Badge_Module();
		$modules['solo']['badges']->load();

		return $modules;

	}
endif;
add_filter( 'bonips_load_modules', 'bonips_load_badges_addon', 10, 2 );
