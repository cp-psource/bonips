<?php
/**
 * Addon: Badges
 * Addon URI: http://codex.bonipress.me/chapter-iii/badges/
 * Version: 1.3
 */
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

define( 'boniPRESS_BADGE',              __FILE__ );
define( 'boniPRESS_BADGE_VERSION',      '1.2' );
define( 'BONIPRESS_BADGE_DIR',          boniPRESS_ADDONS_DIR . 'badges/' );
define( 'BONIPRESS_BADGE_INCLUDES_DIR', BONIPRESS_BADGE_DIR . 'includes/' );

// Badge Key
if ( ! defined( 'BONIPRESS_BADGE_KEY' ) )
	define( 'BONIPRESS_BADGE_KEY', 'bonipress_badge' );

// Default badge width
if ( ! defined( 'BONIPRESS_BADGE_WIDTH' ) )
	define( 'BONIPRESS_BADGE_WIDTH', 100 );

// Default badge height
if ( ! defined( 'BONIPRESS_BADGE_HEIGHT' ) )
	define( 'BONIPRESS_BADGE_HEIGHT', 100 );

require_once BONIPRESS_BADGE_INCLUDES_DIR . 'bonipress-badge-functions.php';
require_once BONIPRESS_BADGE_INCLUDES_DIR . 'bonipress-badge-shortcodes.php';
require_once BONIPRESS_BADGE_INCLUDES_DIR . 'bonipress-badge-object.php';

/**
 * boniPRESS_buyCRED_Module class
 * @since 1.5
 * @version 1.2
 */
if ( ! class_exists( 'boniPRESS_Badge_Module' ) ) :
	class boniPRESS_Badge_Module extends boniPRESS_Module {

		/**
		 * Construct
		 */
		function __construct( $type = BONIPRESS_DEFAULT_TYPE_KEY ) {

			parent::__construct( 'boniPRESS_Badge_Module', array(
				'module_name' => 'badges',
				'defaults'    => array(
					'buddypress'  => '',
					'bbpress'     => '',
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

			add_filter( 'bonipress_add_finished', array( $this, 'add_finished' ), 30, 3 );

		}

		/**
		 * Module Init
		 * @since 1.0
		 * @version 1.0.3
		 */
		public function module_init() {

			$this->register_badges();

			add_action( 'bonipress_set_current_account', array( $this, 'populate_current_account' ) );
			add_action( 'bonipress_get_account',         array( $this, 'populate_account' ) );

			add_shortcode( BONIPRESS_SLUG . '_my_badges', 'bonipress_render_my_badges' );
			add_shortcode( BONIPRESS_SLUG . '_badges',    'bonipress_render_badges' );

			// Insert into bbPress
			if ( class_exists( 'bbPress' ) ) {

				if ( $this->badges['bbpress'] == 'profile' || $this->badges['bbpress'] == 'both' )
					add_action( 'bbp_template_after_user_profile', array( $this, 'insert_into_bbpress_profile' ) );

				if ( $this->badges['bbpress'] == 'reply' || $this->badges['bbpress'] == 'both' )
					add_action( 'bbp_theme_after_reply_author_details', array( $this, 'insert_into_bbpress_reply' ) );

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

			add_action( 'bonipress_add_menu',   array( $this, 'add_to_menu' ), $this->menu_pos );

		}

		/**
		 * Module Admin Init
		 * @since 1.0
		 * @version 1.1
		 */
		public function module_admin_init() {

			add_filter( 'parent_file',                       array( $this, 'parent_file' ) );
			add_filter( 'submenu_file',                      array( $this, 'subparent_file' ), 10, 2 );
			add_action( 'bonipress_admin_enqueue',              array( $this, 'enqueue_scripts' ), $this->menu_pos );

			add_filter( 'post_row_actions',                  array( $this, 'adjust_row_actions' ), 10, 2 );

			add_filter( 'post_updated_messages',             array( $this, 'post_updated_messages' ) );
			add_filter( 'enter_title_here',                  array( $this, 'enter_title_here' ) );
			add_action( 'post_submitbox_start',              array( $this, 'publishing_actions' ) );

			add_action( 'wp_ajax_bonipress-assign-badge',       array( $this, 'action_assign_badge' ) );
			add_action( 'wp_ajax_bonipress-remove-connections', array( $this, 'action_remove_connections' ) );

			add_action( 'bonipress_user_edit_after_balances',   array( $this, 'badge_user_screen' ), 10 );

			add_action( 'personal_options_update',           array( $this, 'save_manual_badges' ), 10 );
			add_action( 'edit_user_profile_update',          array( $this, 'save_manual_badges' ), 10 );

			add_action( 'bonipress_delete_point_type',          array( $this, 'delete_point_type' ) );
			add_action( 'before_delete_post',                array( $this, 'delete_badge' ) );

			add_filter( 'manage_' . BONIPRESS_BADGE_KEY . '_posts_columns',       array( $this, 'adjust_column_headers' ) );
			add_action( 'manage_' . BONIPRESS_BADGE_KEY . '_posts_custom_column', array( $this, 'adjust_column_content' ), 10, 2 );
			add_action( 'save_post_' . BONIPRESS_BADGE_KEY,                       array( $this, 'save_badge' ), 10, 2 );

		}

		/**
		 * Register Badge Post Type
		 * @since 1.0
		 * @version 1.0
		 */
		public function register_badges() {

			$labels = array(
				'name'               => __( 'Badges', 'bonipress' ),
				'singular_name'      => __( 'Badge', 'bonipress' ),
				'add_new'            => __( 'Add New', 'bonipress' ),
				'add_new_item'       => __( 'Add New', 'bonipress' ),
				'edit_item'          => __( 'Edit Badge', 'bonipress' ),
				'new_item'           => __( 'New Badge', 'bonipress' ),
				'all_items'          => __( 'Badges', 'bonipress' ),
				'view_item'          => __( 'View Badge', 'bonipress' ),
				'search_items'       => __( 'Search Badge', 'bonipress' ),
				'not_found'          => __( 'No badges found', 'bonipress' ),
				'not_found_in_trash' => __( 'No badges found in Trash', 'bonipress' ), 
				'parent_item_colon'  => '',
				'menu_name'          => __( 'Badges', 'bonipress' )
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

			register_post_type( BONIPRESS_BADGE_KEY, apply_filters( 'bonipress_register_badge', $args ) );

		}

		/**
		 * Populate Current Account
		 * @since 1.8
		 * @version 1.0
		 */
		public function populate_current_account() {

			global $bonipress_current_account;

			if ( isset( $bonipress_current_account )
				&& ( $bonipress_current_account instanceof boniPRESS_Account )
				&& ( isset( $bonipress_current_account->badges ) )
			) return;

			$earned       = array();
			$users_badges = bonipress_get_users_badges( $bonipress_current_account->user_id, true );

			if ( ! empty( $users_badges ) ) {
				foreach ( $users_badges as $badge_id => $level ) {

					if ( ! is_numeric( $level ) )
						$level = 0;

					$badge_id = absint( $badge_id );
					$level    = absint( $level );
					$badge    = bonipress_get_badge( $badge_id, $level );

					$earned[ $badge_id ] = $badge;

				}
			}

			$bonipress_current_account->badges    = $earned;
			$bonipress_current_account->badge_ids = $users_badges;

		}

		/**
		 * Populate Account
		 * @since 1.8
		 * @version 1.0
		 */
		public function populate_account() {

			global $bonipress_account;

			if ( isset( $bonipress_account )
				&& ( $bonipress_account instanceof boniPRESS_Account )
				&& ( isset( $bonipress_account->badges ) )
			) return;

			$earned       = array();
			$users_badges = bonipress_get_users_badges( $bonipress_account->user_id );

			if ( ! empty( $users_badges ) ) {
				foreach ( $users_badges as $badge_id => $level ) {

					if ( ! is_numeric( $level ) )
						$level = 0;

					$badge_id = absint( $badge_id );
					$level    = absint( $level );
					$badge    = bonipress_get_badge( $badge_id, $level );

					$earned[ $badge_id ] = $badge;

				}
			}

			$bonipress_account->badges    = $earned;
			$bonipress_account->badge_ids = $users_badges;

		}

		/**
		 * Delete Point Type
		 * When a point type is deleted, we want to remove any data saved for this point type.
		 * @since 1.7
		 * @version 1.0
		 */
		public function delete_point_type( $point_type = NULL ) {

			if ( ! bonipress_point_type_exists( $point_type ) || $point_type == BONIPRESS_DEFAULT_TYPE_KEY ) return;

			$bonipress = bonipress( $point_type );

			if ( ! $bonipress->user_is_point_editor() ) return;

			bonipress_delete_option( 'bonipress-badge-refs-' . $point_type );

		}

		/**
		 * Delete Badge
		 * When a badge is deleted, we want to delete connections as well.
		 * @since 1.7
		 * @version 1.0
		 */
		public function delete_badge( $post_id ) {

			if ( get_post_status( $post_id ) != BONIPRESS_BADGE_KEY ) return $post_id;

			// Delete reference list to force a new query
			foreach ( $this->point_types as $type_id => $label )
				bonipress_delete_option( 'bonipress-badge-refs-' . $type_id );

			global $wpdb;

			// Delete connections to keep usermeta table clean
			$wpdb->delete(
				$wpdb->usermeta,
				array( 'meta_key' => BONIPRESS_BADGE_KEY . $post_id ),
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

			$messages[ BONIPRESS_BADGE_KEY ] = array(
				0  => '',
				1  => __( 'Badge Updated.', 'bonipress' ),
				2  => __( 'Badge Updated.', 'bonipress' ),
				3  => __( 'Badge Updated.', 'bonipress' ),
				4  => __( 'Badge Updated.', 'bonipress' ),
				5  => false,
				6  => __( 'Badge Enabled.', 'bonipress' ),
				7  => __( 'Badge Saved.', 'bonipress' ),
				8  => __( 'Badge Updated.', 'bonipress' ),
				9  => __( 'Badge Updated.', 'bonipress' ),
				10 => __( 'Badge Updated.', 'bonipress' )
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
			if ( bonipress_override_settings() && ! bonipress_is_main_site() ) return;

			add_submenu_page(
				BONIPRESS_SLUG,
				__( 'Badges', 'bonipress' ),
				__( 'Badges', 'bonipress' ),
				$this->core->get_point_editor_capability(),
				'edit.php?post_type=' . BONIPRESS_BADGE_KEY
			);

		}

		/**
		 * Parent File
		 * @since 1.6
		 * @version 1.0.2
		 */
		public function parent_file( $parent = '' ) {

			global $pagenow;

			if ( ( $pagenow == 'edit.php' || $pagenow == 'post-new.php' ) && isset( $_GET['post_type'] ) && $_GET['post_type'] == BONIPRESS_BADGE_KEY ) {
			
				return BONIPRESS_SLUG;
			
			}

			elseif ( $pagenow == 'post.php' && isset( $_GET['post'] ) && bonipress_get_post_type( $_GET['post'] ) == BONIPRESS_BADGE_KEY ) {

				return BONIPRESS_SLUG;

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

			if ( ( $pagenow == 'edit.php' || $pagenow == 'post-new.php' ) && isset( $_GET['post_type'] ) && $_GET['post_type'] == BONIPRESS_BADGE_KEY ) {

				return 'edit.php?post_type=' . BONIPRESS_BADGE_KEY;
			
			}

			elseif ( $pagenow == 'post.php' && isset( $_GET['post'] ) && bonipress_get_post_type( $_GET['post'] ) == BONIPRESS_BADGE_KEY ) {

				return 'edit.php?post_type=' . BONIPRESS_BADGE_KEY;

			}

			return $subparent;

		}

		/**
		 * Add Finished
		 * @since 1.0
		 * @version 1.4
		 */
		public function add_finished( $result, $request, $bonipress ) {

			extract( $request );

			if ( $result !== false && $ref != 'badge_reward' ) {

				// Check if this reference has badges
				$badge_ids = bonipress_ref_has_badge( $ref, $type );
				if ( $badge_ids !== false ) {

					// Check if user gets any of the badges
					foreach ( $badge_ids as $badge_id ) {

						$badge = bonipress_get_badge( $badge_id );
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
			$columns['title']               = __( 'Badge Name', 'bonipress' );
			$columns['badge-default-image'] = __( 'Default Image', 'bonipress' );
			$columns['badge-earned-image']  = __( 'First Level', 'bonipress' );
			$columns['badge-reqs']          = __( 'Requirements', 'bonipress' );
			$columns['badge-users']         = __( 'Users', 'bonipress' );

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

				$badge = bonipress_get_badge( $badge_id );
				if ( $badge === false || $badge->main_image === false )
					echo '-';

				elseif ( $badge->main_image !== false )
					echo $badge->main_image;

			}

			// First Level Image
			if ( $column_name == 'badge-earned-image' ) {

				$badge = bonipress_get_badge( $badge_id );
				$image = $badge->get_image( 0 );
				if ( $image === false)
					echo '-';
				else
					echo $image;

			}

			// Badge Requirements
			elseif ( $column_name == 'badge-reqs' ) {

				echo bonipress_display_badge_requirements( $badge_id );

			}

			// Badge Users
			elseif ( $column_name == 'badge-users' ) {

				$badge = bonipress_get_badge( $badge_id );
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

			if ( $post->post_type == BONIPRESS_BADGE_KEY ) {
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

			if ( $post_type == BONIPRESS_BADGE_KEY )
				return __( 'Badge Name', 'bonipress' );

			return $title;

		}

		/**
		 * Enqueue Scripts
		 * @since 1.0
		 * @version 1.0.1
		 */
		public function enqueue_scripts() {

			$screen = get_current_screen();
			if ( $screen->id == BONIPRESS_BADGE_KEY ) {

				wp_enqueue_media();

				wp_register_script(
					'bonipress-edit-badge',
					plugins_url( 'assets/js/edit-badge.js', boniPRESS_BADGE ),
					array( 'jquery', 'bonipress-mustache' ),
					boniPRESS_BADGE_VERSION . '.1'
				);

				wp_localize_script(
					'bonipress-edit-badge',
					'boniPRESSBadge',
					array(
						'ajaxurl'      => admin_url( 'admin-ajax.php' ),
						'addlevel'     => esc_js( __( 'Add Level', 'bonipress' ) ),
						'removelevel'  => esc_js( __( 'Remove Level', 'bonipress' ) ),
						'setimage'     => esc_js( __( 'Set Image', 'bonipress' ) ),
						'changeimage'  => esc_js( __( 'Change Image', 'bonipress' ) ),
						'remove'       => esc_js( esc_attr__( 'Are you sure you want to remove this level?', 'bonipress' ) ),
						'levellabel'   => esc_js( sprintf( '%s {{level}}', __( 'Level', 'bonipress' ) ) ),
						'uploadtitle'  => esc_js( esc_attr__( 'Badge Image', 'bonipress' ) ),
						'uploadbutton' => esc_js( esc_attr__( 'Use as Badge', 'bonipress' ) ),
						'compareAND'   => esc_js( _x( 'AND', 'Comparison of badge requirements. A AND B', 'bonipress' ) ),
						'compareOR'    => esc_js( _x( 'OR', 'Comparison of badge requirements. A OR B', 'bonipress' ) )
					)
				);

				wp_enqueue_script( 'bonipress-edit-badge' );

				wp_enqueue_style( 'bonipress-bootstrap-grid' );
				wp_enqueue_style( 'bonipress-forms' );

				add_filter( 'postbox_classes_' . BONIPRESS_BADGE_KEY . '_bonipress-badge-setup',   array( $this, 'metabox_classes' ) );
				add_filter( 'postbox_classes_' . BONIPRESS_BADGE_KEY . '_bonipress-badge-default', array( $this, 'metabox_classes' ) );
				add_filter( 'postbox_classes_' . BONIPRESS_BADGE_KEY . '_bonipress-badge-rewards', array( $this, 'metabox_classes' ) );

				echo '<style type="text/css">
#misc-publishing-actions #visibility, #misc-publishing-actions .misc-pub-post-status { display: none; }
#save-action #save-post { margin-bottom: 12px; }
</style>';

			}

			elseif ( $screen->id == 'edit-' . BONIPRESS_BADGE_KEY ) {

				echo '<style type="text/css">
th#badge-default-image { width: 120px; }
th#badge-earned-image { width: 120px; }
th#badge-reqs { width: 35%; }
th#badge-users { width: 10%; }
.column-badge-default-image img { max-width: 100px; height: auto; }
.bonipress-badge-requirement-list { margin: 6px 0 0 0; padding: 6px 0 0 18px; border-top: 1px dashed #aeaeae; }
.bonipress-badge-requirement-list li { margin: 0 0 0 0; padding: 0 0 0 0; font-size: 12px; line-height: 16px; list-style-type: circle; }
.bonipress-badge-requirement-list li span { float: right; }
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
				'bonipress-badge-setup',
				__( 'Badge Setup', 'bonipress' ),
				array( $this, 'metabox_badge_setup' ),
				BONIPRESS_BADGE_KEY,
				'normal',
				'high'
			);

			add_meta_box(
				'bonipress-badge-default',
				__( 'Default Badge Image', 'bonipress' ),
				array( $this, 'metabox_badge_default' ),
				BONIPRESS_BADGE_KEY,
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
				return '<div class="row badge-level" id="bonipress-badge-level{{level}}" data-level="{{level}}"><div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 text-center">{{addlevelbutton}}<div class="level-image"><div class="level-image-wrapper image-wrapper {{emptylevelimage}}">{{levelimage}}</div><div class="level-image-actions"><button type="button" class="button button-secondary change-level-image" data-level="{{level}}">{{levelimagebutton}}</button></div></div><div class="label-field"><input type="text" placeholder="{{levelplaceholder}}" name="bonipress_badge[levels][{{level}}][label]" value="{{levellabel}}" /></div></div><div class="col-lg-12 col-md-12 col-sm-12 col-xs-12"><div class="req-title">{{requirementslabel}}<div class="pull-right" id="badge-requirement-compare"><a href="javascript:void(0);" data-do="AND" class="{{adnselected}}">AND</a> / <a href="javascript:void(0);" data-do="OR" class="{{orselected}}">OR</a><input type="hidden" name="bonipress_badge[levels][{{level}}][compare]" value="AND" /></div></div><div class="level-requirements">{{{requirements}}}</div></div><div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">{{rewards}}</div></div>';

			return '<div class="row badge-level" id="bonipress-badge-level{{level}}" data-level="{{level}}"><div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 text-center">{{removelevelbutton}}<div class="level-image"><div class="level-image-wrapper image-wrapper {{emptylevelimage}}">{{levelimage}}</div><div class="level-image-actions"><button type="button" class="button button-secondary change-level-image" data-level="{{level}}">{{levelimagebutton}}</button></div></div><div class="label-field"><input type="text" placeholder="{{levelplaceholder}}" name="bonipress_badge[levels][{{level}}][label]" value="{{levellabel}}" /></div></div><div class="col-lg-12 col-md-12 col-sm-12 col-xs-12"><div class="req-title">{{requirementslabel}}</div><div class="level-requirements">{{{requirements}}}</div></div><div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">{{rewards}}</div></div>';

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
					$image = '<img src="' . $_image . '" alt="Badge level image" /><input type="hidden" name="bonipress_badge[levels][' . $level . '][attachment_id]" value="' . $setup['attachment_id'] . '" /><input type="hidden" name="bonipress_badge[levels][' . $level . '][image_url]" value="" />';

			}
			else {

				if ( strlen( $setup['image_url'] ) > 5 )
					$image = '<img src="' . $setup['image_url'] . '" alt="Badge level image" /><input type="hidden" name="bonipress_badge[levels][' . $level . '][attachment_id]" value="0" /><input type="hidden" name="bonipress_badge[levels][' . $level . '][image_url]" value="' . $setup['image_url'] . '" />';

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
				return '<div class="row row-narrow" id="level{{level}}requirement{{reqlevel}}" data-row="{{reqlevel}}"><div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 form"><div class="form-group"><select name="bonipress_badge[levels][{{level}}][requires][{{reqlevel}}][type]" data-row="{{reqlevel}}" class="form-control point-type">{{pointtypes}}</select></div></div><div class="col-lg-5 col-md-5 col-sm-6 col-xs-12 form"><div class="form-group"><select name="bonipress_badge[levels][{{level}}][requires][{{reqlevel}}][reference]" data-row="{{reqlevel}}" class="form-control reference">{{references}}</select></div></div><div class="col-lg-3 col-md-3 col-sm-6 col-xs-10 form-inline"><div class="form-group"><input type="text" size="5" name="bonipress_badge[levels][{{level}}][requires][{{reqlevel}}][amount]" class="form-control" value="{{reqamount}}" /></div><div class="form-group"><select name="bonipress_badge[levels][{{level}}][requires][{{reqlevel}}][by]" data-row="{{reqlevel}}" class="form-control req-type">{{requirementtype}}</select></div></div><div class="col-lg-1 col-md-1 col-sm-6 col-xs-2 form">{{reqbutton}}</div></div>';

			// All other requirements reflect the level 0's setup
			return '<div class="row row-narrow" id="level{{level}}requirement{{reqlevel}}"><div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 form"><div class="form-group level-type"><p class="form-control-static level-requirement{{reqlevel}}-type">{{selectedtype}}</p></div></div><div class="col-lg-5 col-md-5 col-sm-6 col-xs-12 form"><div class="form-group level-ref"><p class="form-control-static level-requirement{{reqlevel}}-ref">{{selectedref}}</p></div></div><div class="col-lg-3 col-md-3 col-sm-6 col-xs-10 form-inline"><div class="form-group level-val"><input type="text" size="5" name="bonipress_badge[levels][{{level}}][requires][{{reqlevel}}][amount]" class="form-control" value="{{reqamount}}" /></div><div class="form-group level-type-by"><p class="form-control-static level-requirement{{reqlevel}}-by">{{selectedby}}</p></div></div><div class="col-lg-1 col-md-1 col-sm-6 col-xs-2 level-compare form"><p class="form-control-static" data-row="{{reqlevel}}">{{comparelabel}}</p></div></div>';

		}

		/**
		 * Rewards Template
		 * @since 1.7
		 * @version 1.0
		 */
		public function rewards_template() {

			return '<div class="req-title">{{rewardlabel}}</div><div class="row form"><div class="col-lg-4 col-md-4 col-sm-12 col-xs-12"><select name="bonipress_badge[levels][{{level}}][reward][type]" class="form-control">{{pointtypes}}</select></div><div class="col-lg-6 col-md-6 col-sm-12 col-xs-12"><input type="text" class="form-control" name="bonipress_badge[levels][{{level}}][reward][log]" placeholder="{{logplaceholder}}" value="{{logtemplate}}" /></div><div class="col-lg-2 col-md-2 col-sm-12 col-xs-12"><input type="text" class="form-control" name="bonipress_badge[levels][{{level}}][reward][amount]" placeholder="0" value="{{rewardamount}}" /></div></div>';

		}

		/**
		 * Badge Publishing Actions
		 * @since 1.7
		 * @version 1.1
		 */
		public function publishing_actions() {

			global $post;

			if ( ! isset( $post->post_type ) || $post->post_type != BONIPRESS_BADGE_KEY ) return;

			$manual_badge = ( (int) bonipress_get_post_meta( $post->ID, 'manual_badge', true ) == 1 ) ? true : false;

?>
<div id="bonipress-badge-actions" class="seperate-bottom">

	<?php do_action( 'bonipress_edit_badge_before_actions', $post ); ?>

	<input type="hidden" name="bonipress-badge-edit" value="<?php echo wp_create_nonce( 'edit-bonipress-badge' ); ?>" />
	<input type="button" id="bonipress-assign-badge-connections"<?php if ( $manual_badge || $post->post_status != 'publish' ) echo ' disabled="disabled"'; ?> value="<?php _e( 'Assign Badge', 'bonipress' ); ?>" class="button button-secondary bonipress-badge-action-button" data-action="bonipress-assign-badge" data-token="<?php echo wp_create_nonce( 'bonipress-assign-badge' ); ?>" /> 
	<input type="button" id="bonipress-remove-badge-connections"<?php if ( $post->post_status != 'publish' ) echo ' disabled="disabled"'; ?> value="<?php _e( 'Remove Connections', 'bonipress' ); ?>" class="button button-secondary bonipress-badge-action-button" data-action="bonipress-remove-connections" data-token="<?php echo wp_create_nonce( 'bonipress-remove-badge-connection' ); ?>" />

	<?php do_action( 'bonipress_edit_badge_after_actions', $post ); ?>

<script type="text/javascript">
jQuery(function($) {

	$( 'input.bonipress-badge-action-button' ).click(function(){
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
				button.attr( 'value', '<?php echo esc_js( esc_attr__( 'Processing...', 'bonipress' ) ); ?>' );
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
<div id="bonipress-manual-badge" class="seperate-bottom">
	<label for="bonipress-badge-is-manual"><input type="checkbox" name="bonipress_badge[manual]" id="bonipress-badge-is-manual"<?php if ( $manual_badge ) echo ' checked="checked"'; ?> value="1" /> <?php _e( 'This badge is manually awarded.', 'bonipress' ); ?></label>
</div>
<?php

		}

		/**
		 * Default Image Metabox
		 * @since 1.7
		 * @version 1.0
		 */
		public function metabox_badge_default( $post ) {

			$default_image = $di = bonipress_get_post_meta( $post->ID, 'main_image', true );
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
				<input type="hidden" name="bonipress_badge[main_image]" id="badge-main-image-id" value="<?php if ( $attachment ) echo esc_attr( $di ); ?>" />
				<input type="hidden" name="bonipress_badge[main_image_url]" id="badge-main-image-url" value="<?php if ( $di != '' && strpos( '://', $di ) !== false ) echo esc_attr( $default_image ); ?>" />
			</div>
			<div class="level-image-actions">
				<button type="button" class="button button-secondary" id="badges-change-default-image" data-do="<?php if ( $default_image == '' ) echo 'set'; else echo 'change'; ?>"><?php if ( $default_image == '' ) _e( 'Set Image', 'bonipress' ); else _e( 'Change Image', 'bonipress' ); ?></button>
			</div>
		</div>
		<span class="description"><?php _e( 'Optional image to show when a user has not earned this badge.', 'bonipress' ); ?></span>
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

			$badge       = bonipress_get_badge( $post->ID );
			$references  = bonipress_get_all_references();
			$point_types = bonipress_get_types( true );

			$sums = apply_filters( 'bonipress_badge_requirement_sums', array(
				'count' => esc_js( __( 'Time(s)', 'bonipress' ) ),
				'sum'   => esc_js( __( 'In total', 'bonipress' ) )
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

				$add_level    = '<button type="button" class="button button-seconary button-small top-right-corner" id="badges-add-new-level">' . esc_js( __( 'Add Level', 'bonipress' ) ) . '</button>';
				$remove_level = '<button type="button" class="button button-seconary button-small top-right-corner remove-badge-level" data-level="' . $level . '">' . esc_js( __( 'Remove Level', 'bonipress' ) ) . '</button>';

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
				$js_level = str_replace( '{{levelimagebutton}}',  esc_js( __( 'Set Image', 'bonipress' ) ), $js_level );
				$js_level = str_replace( '{{levelplaceholder}}',  esc_js( __( 'Level', 'bonipress' ) ) . ' {{levelone}}', $js_level );

				$template = str_replace( '{{levelimage}}',        $level_image, $template );
				$template = str_replace( '{{emptylevelimage}}',   $empty_level, $template );
				$template = str_replace( '{{levelimagebutton}}',  ( ( $level_image === false ) ? esc_js( __( 'Set Image', 'bonipress' ) ) : esc_js( __( 'Change Image', 'bonipress' ) ) ), $template );

				$template = str_replace( '{{levelplaceholder}}',  esc_js( sprintf( __( 'Level %d', 'bonipress' ), $level+1 ) ), $template );
				$template = str_replace( '{{levellabel}}',        esc_js( $setup['label'] ), $template );

				$template = str_replace( '{{requirementslabel}}', esc_js( __( 'Requirement', 'bonipress' ) ), $template );
				$js_level = str_replace( '{{requirementslabel}}', esc_js( __( 'Requirement', 'bonipress' ) ), $js_level );

				$template = str_replace( '{{adnselected}}',       ( ( $setup['compare'] === 'AND' ) ? 'selected' : '' ), $template );
				$template = str_replace( '{{orselected}}',        ( ( $setup['compare'] === 'OR' ) ? 'selected' : '' ), $template );

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
					$point_type_options .= '>' . esc_js( __( 'Select Point Type', 'bonipress' ) ) . '</option>';
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
					$reference_options  .= '>' . esc_js( __( 'Select Reference', 'bonipress' ) ) . '</option>';
					foreach ( $references as $ref_id => $ref_label ) {
						$reference_options .= '<option value="' . esc_attr( $ref_id ) . '"';
						if ( $reqsetup['reference'] == $ref_id ) $reference_options .= ' selected="selected"';
						$reference_options .= '>' . esc_html( $ref_label ) . '</option>';
					}

					$requirement         = str_replace( '{{references}}', $reference_options, $requirement );
					$requirement         = str_replace( '{{reqamount}}',  $reqsetup['amount'], $requirement );

					$reference_options   = str_replace( 'selected="selected"', '', $reference_options );
					$js_requirement      = str_replace( '{{references}}', $reference_options, $js_requirement );
					$js_requirement      = str_replace( '{{reqamount}}',  $reqsetup['amount'], $js_requirement );

					$by_options          = '';
					$by_options         .= '<option value=""';
					if ( $reqsetup['by'] == '' ) $by_options .= ' selected="selected"';
					$by_options         .= '>' . __( 'Select', 'bonipress' ) . '</option>';
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
						$compare_label = ( ( $setup['compare'] === 'AND' ) ? _x( 'AND', 'Comparison of badge requirements. A AND B', 'bonipress' ) : _x( 'OR', 'Comparison of badge requirements. A OR B', 'bonipress' ) );

					if ( $req_level+1 == $total_requirements )
						$compare_label = '';

					$requirement         = str_replace( '{{comparelabel}}', esc_js( $compare_label ), $requirement );

					$level_requirements .= $requirement;

				}

				$template           = str_replace( '{{{requirements}}}', $level_requirements, $template );

				$rewards            = $this->rewards_template();

				$js_level           = str_replace( '{{reqamount}}',     '', $js_level );

				$rewards            = str_replace( '{{level}}',          $level, $rewards );
				$rewards            = str_replace( '{{rewardlabel}}',    esc_js( __( 'Reward', 'bonipress' ) ), $rewards );

				$point_type_options = '';
				foreach ( $point_types as $type_id => $type_label ) {
					$point_type_options .= '<option value="' . $type_id . '"';
					if ( $setup['reward']['type'] == $type_id ) $point_type_options .= ' selected="selected"';
					$point_type_options .= '>' . $type_label . '</option>';
				}

				$rewards            = str_replace( '{{pointtypes}}',     $point_type_options, $rewards );
				$rewards            = str_replace( '{{logplaceholder}}', esc_js( __( 'Log template', 'bonipress' ) ), $rewards );
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

			if ( $post === NULL || ! $this->core->user_is_point_editor() || ! isset( $_POST['bonipress_badge'] ) ) return $post_id;

			$manual = 0;
			if ( isset( $_POST['bonipress_badge']['manual'] ) )
				$manual = 1;

			$badge_levels       = array();
			$badge_requirements = array();

			// Run through each level
			if ( ! empty( $_POST['bonipress_badge']['levels'] ) ) {

				$level_row = 0;
				foreach ( $_POST['bonipress_badge']['levels'] as $level_id => $level_setup ) {

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

						if ( $reward['type'] != BONIPRESS_DEFAULT_TYPE_KEY )
							$bonipress = bonipress( $reward['type'] );
						else
							$bonipress = $this->core;

						$reward['amount'] = $bonipress->number( $level_setup['reward']['amount'] );

					}

					$level['reward']  = $reward;

					$badge_levels[] = $level;
					$level_row ++;

				}

			}

			// Save Badge Setup
			bonipress_update_post_meta( $post_id, 'badge_prefs', $badge_levels );

			// If we just set the badge to be manual we need to re-parse all references.
			$old_manual = bonipress_get_post_meta( $post_id, 'manual_badge', true );
			if ( absint( $old_manual ) === 0 && $manual === 1 ) {
				foreach ( $this->point_types as $type_id => $label ) {
					bonipress_get_badge_references( $type_id, true );
				}
			}

			// Force re-calculation of used references
			foreach ( $this->point_types as $type_id => $type )
				bonipress_delete_option( 'bonipress-badge-refs-' . $type_id );

			// Save if badge is manuall
			bonipress_update_post_meta( $post_id, 'manual_badge', $manual );

			// Main image (used when a user has not earned a badge
			$main_image = $_POST['bonipress_badge']['main_image'];

			// If we are using an attachment
			if ( absint( $main_image ) > 0 )
				$image = absint( $main_image );

			// Else we are using a URL (old setup)
			else
				$image = sanitize_text_field( $_POST['bonipress_badge']['main_image_url'] );

			bonipress_update_post_meta( $post_id, 'main_image', $image );

			// Let others play
			do_action( 'bonipress_save_badge', $post_id );

		}

		/**
		 * Add to General Settings
		 * @since 1.0
		 * @version 1.1
		 */
		public function after_general_settings( $bonipress = NULL ) {

			$settings   = $this->badges;

			$buddypress = ( ( class_exists( 'BuddyPress' ) ) ? true : false ); 
			$bbpress    = ( ( class_exists( 'bbPress' ) ) ? true : false ); 

?>
<h4><span class="dashicons dashicons-admin-plugins static"></span><?php _e( 'Badges', 'bonipress' ); ?></h4>
<div class="body" style="display:none;">

	<h3><?php _e( 'Third-party Integrations', 'bonipress' ); ?></h3>
	<div class="row">
		<div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'buddypress' ); ?>">BuddyPress</label>
				<?php if ( $buddypress ) : ?>
				<select name="<?php echo $this->field_name( 'buddypress' ); ?>" id="<?php echo $this->field_id( 'buddypress' ); ?>" class="form-control">
<?php

			$buddypress_options = array(
				''        => __( 'Do not show', 'bonipress' ),
				'header'  => __( 'Include in Profile Header', 'bonipress' ),
				'profile' => __( 'Include under the "Profile" tab', 'bonipress' ),
				'both'    => __( 'Include under the "Profile" tab and Profile Header', 'bonipress' )
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
					<label for="<?php echo $this->field_id( 'show_all_bp' ); ?>"><input type="checkbox" name="<?php echo $this->field_name( 'show_all_bp' ); ?>" id="<?php echo $this->field_id( 'show_all_bp' ); ?>" <?php checked( $settings['show_all_bp'], 1 ); ?> value="1" /> <?php _e( 'Show all badges, including badges users have not yet earned.', 'bonipress' ); ?></label>
				</div>
				<?php else : ?>
				<input type="hidden" name="<?php echo $this->field_name( 'buddypress' ); ?>" id="<?php echo $this->field_id( 'buddypress' ); ?>" value="" />
				<p><span class="description"><?php _e( 'Not installed', 'bonipress' ); ?></span></p>
				<?php endif; ?>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'bbpress' ); ?>">bbPress</label>
				<?php if ( $bbpress ) : ?>
				<select name="<?php echo $this->field_name( 'bbpress' ); ?>" id="<?php echo $this->field_id( 'bbpress' ); ?>" class="form-control">
<?php

			$bbpress_options = array(
				''        => __( 'Do not show', 'bonipress' ),
				'profile' => __( 'Include in Profile', 'bonipress' ),
				'reply'   => __( 'Include in Forum Replies', 'bonipress' ),
				'both'    => __( 'Include in Profile and Forum Replies', 'bonipress' )
			);

			foreach ( $bbpress_options as $location => $description ) { 
				echo '<option value="' . $location . '"';
				if ( isset( $settings['bbpress'] ) && $settings['bbpress'] == $location ) echo ' selected="selected"';
				echo '>' . $description . '</option>';
			}

?>

				</select>
			</div>
			<div class="form-group">
				<div class="checkbox">
					<label for="<?php echo $this->field_id( 'show_all_bb' ); ?>"><input type="checkbox" name="<?php echo $this->field_name( 'show_all_bb' ); ?>" id="<?php echo $this->field_id( 'show_all_bb' ); ?>" <?php checked( $settings['show_all_bb'], 1 ); ?> value="1" /> <?php _e( 'Show all badges, including badges users have not yet earned.', 'bonipress' ); ?></label>
				</div>
				<?php else : ?>
					<input type="hidden" name="<?php echo $this->field_name( 'bbpress' ); ?>" id="<?php echo $this->field_id( 'bbpress' ); ?>" value="" />
					<p><span class="description"><?php _e( 'Not installed', 'bonipress' ); ?></span></p>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<h3 style="margin-bottom: 0;"><?php _e( 'Available Shortcodes', 'bonipress' ); ?></h3>
	<div class="row">
		<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
			<p><a href="http://codex.bonipress.me/shortcodes/bonipress_my_badges/" target="_blank">[bonipress_my_badges]</a>, <a href="http://codex.bonipress.me/shortcodes/bonipress_badges/" target="_blank">[bonipress_badges]</a></p>
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
			$new_data['badges']['bbpress']    = ( isset( $data['badges']['bbpress'] ) ) ? sanitize_text_field( $data['badges']['bbpress'] ) : '';

			return $new_data;

		}

		/**
		 * User Badges Admin Screen
		 * @since 1.0
		 * @version 1.1
		 */
		public function badge_user_screen( $user ) {

			// Only visible to admins
			if ( ! bonipress_is_admin() ) return;

			$user_id      = $user->ID;
			$all_badges   = bonipress_get_badge_ids();
			$users_badges = bonipress_get_users_badges( $user_id );

?>
<style type="text/css">
.badge-wrapper { min-height: 230px; }
.badge-wrapper select { width: 100%; }
.badge-image-wrap { text-align: center; }
.badge-image-wrap .badge-image { display: block; width: 100%; height: 100px; line-height: 100px; }
.badge-image-wrap .badge-image.empty { content: "<?php _e( 'No image set', 'bonipress' ); ?>"; }
.badge-image-wrap .badge-image img { width: auto; height: auto; max-height: 100px; }
</style>
<table class="form-table">
	<tr>
		<th scope="row"><?php _e( 'Badges', 'bonipress' ); ?></th>
		<td>
			<fieldset id="bonipress-badge-list" class="badge-list">
				<legend class="screen-reader-text"><span><?php _e( 'Badges', 'bonipress' ); ?></span></legend>
<?php

			if ( ! empty( $all_badges ) ) {
				foreach ( $all_badges as $badge_id ) {

					$badge_id     = absint( $badge_id );
					$badge        = bonipress_get_badge( $badge_id );
					$earned       = 0;
					$earned_level = 0;
					$badge_image  = $badge->main_image;

					if ( array_key_exists( $badge_id, $users_badges ) ) {
						$earned       = 1;
						$earned_level = $users_badges[ $badge_id ];
						$badge_image  = $badge->get_image( $earned_level );
					}

					$level_select = '<input type="hidden" name="bonipress_badge_manual[badges][' . $badge_id . '][level]" value="0" /><select disabled="disabled"><option>Level 1</option></select>';
					if ( count( $badge->levels ) > 1 ) {

						$level_select  = '<select name="bonipress_badge_manual[badges][' . $badge_id . '][level]">';
						$level_select .= '<option value=""';
						if ( ! $earned ) $level_select .= ' selected="selected"';
						$level_select .= '>' . __( 'Select a level', 'bonipress' ) . '</option>';

						foreach ( $badge->levels as $level_id => $level ) {
							$level_select .= '<option value="' . $level_id . '"';
							if ( $earned && $earned_level == $level_id ) $level_select .= ' selected="selected"';
							$level_select .= '>' . ( ( $level['label'] != '' ) ? $level['label'] : sprintf( '%s %d', __( 'Level', 'bonipress' ), ( $level_id + 1 ) ) ) . '</option>';
						}

						$level_select .= '</select>';

					}

?>
				<div class="badge-wrapper color-option<?php if ( $earned === 1 ) echo ' selected'; ?>" id="bonipress-badge<?php echo $badge_id; ?>-wrapper">
					<label for="bonipress-badge<?php echo $badge_id; ?>"><input type="checkbox" name="bonipress_badge_manual[badges][<?php echo $badge_id; ?>][has]" class="toggle-badge" id="bonipress-badge<?php echo $badge_id; ?>" <?php checked( $earned, 1 );?> value="1" /> <?php _e( 'Earned', 'bonipress' ); ?></label>
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
			<input type="hidden" name="bonipress_badge_manual[token]" value="<?php echo wp_create_nonce( 'bonipress-manual-badges' . $user_id ); ?>" />
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

			if ( ! bonipress_is_admin() ) return;

			if ( isset( $_POST['bonipress_badge_manual']['token'] ) ) {

				if ( wp_verify_nonce( $_POST['bonipress_badge_manual']['token'], 'bonipress-manual-badges' . $user_id ) ) {

					$added        = $removed = $updated = 0;
					$users_badges = bonipress_get_users_badges( $user_id );

					if ( ! empty( $_POST['bonipress_badge_manual']['badges'] ) ) {
						foreach ( $_POST['bonipress_badge_manual']['badges'] as $badge_id => $data ) {

							$badge = bonipress_get_badge( $badge_id );

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
						bonipress_delete_user_meta( $user_id, 'bonipress_badge_ids' );

				}

			}

		}

		/**
		 * AJAX: Assign Badge
		 * @since 1.0
		 * @version 1.3
		 */
		public function action_assign_badge() {

			check_ajax_referer( 'bonipress-assign-badge', 'token' );

			$badge_id = absint( $_POST['badge_id'] );
			if ( $badge_id === 0 ) wp_send_json_error();

			// Get the badge object
			$badge    = bonipress_get_badge( $badge_id );

			// Most likely not a badge post ID
			if ( $badge === false ) wp_send_json_error();

			$results = $badge->assign_all();

			if ( $results > 0 )
				wp_send_json_success( sprintf( __( 'A total of %d users have received this badge.', 'bonipress' ), $results ) );

			wp_send_json_error( __( 'No users has yet earned this badge.', 'bonipress' ) );

		}

		/**
		 * AJAX: Remove Badge Connections
		 * @since 1.0
		 * @version 1.1
		 */
		public function action_remove_connections() {

			check_ajax_referer( 'bonipress-remove-badge-connection', 'token' );

			$badge_id = absint( $_POST['badge_id'] );
			if ( $badge_id === 0 ) wp_send_json_error();

			// Get the badge object
			$badge    = bonipress_get_badge( $badge_id );

			// Most likely not a badge post ID
			if ( $badge === false ) wp_send_json_error();

			$results = $badge->divest_all();

			if ( $results == 0 )
				wp_send_json_success( __( 'No connections where removed.', 'bonipress' ) );

			wp_send_json_success( sprintf( __( '%s connections where removed.', 'bonipress' ), $results ) );

		}

		/**
		 * Insert Badges into bbPress profile
		 * @since 1.0
		 * @version 1.1
		 */
		public function insert_into_bbpress_profile() {

			$user_id = bbp_get_displayed_user_id();
			if ( isset( $this->badges['show_all_bb'] ) && $this->badges['show_all_bb'] == 1 )
				bonipress_render_my_badges( array(
					'show'    => 'all',
					'width'   => BONIPRESS_BADGE_WIDTH,
					'height'  => BONIPRESS_BADGE_HEIGHT,
					'user_id' => $user_id
				) );

			else
				bonipress_display_users_badges( $user_id );

		}

		/**
		 * Insert Badges into bbPress
		 * @since 1.0
		 * @version 1.1
		 */
		public function insert_into_bbpress_reply() {

			$user_id = bbp_get_reply_author_id();
			if ( $user_id > 0 ) {

				if ( isset( $this->badges['show_all_bb'] ) && $this->badges['show_all_bb'] == 1 )
					bonipress_render_my_badges( array(
						'show'    => 'all',
						'width'   => BONIPRESS_BADGE_WIDTH,
						'height'  => BONIPRESS_BADGE_HEIGHT,
						'user_id' => $user_id
					) );

				else
					bonipress_display_users_badges( $user_id );

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
				echo bonipress_render_my_badges( array(
					'show'    => 'all',
					'width'   => BONIPRESS_BADGE_WIDTH,
					'height'  => BONIPRESS_BADGE_HEIGHT,
					'user_id' => $user_id
				) );

			else
				bonipress_display_users_badges( $user_id );

		}

	}
endif;

/**
 * Load Badges Module
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_load_badges_addon' ) ) :
	function bonipress_load_badges_addon( $modules, $point_types ) {

		$modules['solo']['badges'] = new boniPRESS_Badge_Module();
		$modules['solo']['badges']->load();

		return $modules;

	}
endif;
add_filter( 'bonipress_load_modules', 'bonipress_load_badges_addon', 10, 2 );
