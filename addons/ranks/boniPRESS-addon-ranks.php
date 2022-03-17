<?php
/**
 * Addon: Ranks
 * Addon URI: https://n3rds.work/docs/bonipress-erweiterungen-raenge/
 * Version: 1.6
 */
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

define( 'boniPRESS_RANKS',         __FILE__ );
define( 'boniPRESS_RANKS_DIR',     boniPRESS_ADDONS_DIR . 'ranks/' );
define( 'boniPRESS_RANKS_VERSION', '1.6' );

// Rank key
if ( ! defined( 'BONIPRESS_RANK_KEY' ) )
	define( 'BONIPRESS_RANK_KEY', 'bonipress_rank' );

// Default badge width
if ( ! defined( 'BONIPRESS_RANK_WIDTH' ) )
	define( 'BONIPRESS_RANK_WIDTH', 250 );

// Default badge height
if ( ! defined( 'BONIPRESS_RANK_HEIGHT' ) )
	define( 'BONIPRESS_RANK_HEIGHT', 250 );

require_once boniPRESS_RANKS_DIR . 'includes/bonipress-rank-functions.php';
require_once boniPRESS_RANKS_DIR . 'includes/bonipress-rank-object.php';
require_once boniPRESS_RANKS_DIR . 'includes/bonipress-rank-shortcodes.php';

/**
 * boniPRESS_Ranks_Module class
 * While boniPRESS rankings just ranks users according to users total amount of
 * points, ranks are titles that can be given to users when their reach a certain
 * amount.
 * @since 1.1
 * @version 1.6
 */
if ( ! class_exists( 'boniPRESS_Ranks_Module' ) ) :
	class boniPRESS_Ranks_Module extends boniPRESS_Module {

		/**
		 * Construct
		 */
		public function __construct() {

			parent::__construct( 'boniPRESS_Ranks_Module', array(
				'module_name' => 'rank',
				'defaults'    => array(
					'manual'      => 0,
					'public'      => 0,
					'base'        => 'current',
					'slug'        => BONIPRESS_RANK_KEY,
					'bb_location' => 'top',
					'bb_template' => 'Rank: %rank_title%',
					'bp_location' => '',
					'bb_template' => 'Rank: %rank_title%',
					'order'       => 'ASC',
					'support'     => array(
						'content'         => 0,
						'excerpt'         => 0,
						'comments'        => 0,
						'page-attributes' => 0,
						'custom-fields'   => 0
					)
				),
				'register'    => false,
				'add_to_core' => false,
				'menu_pos'    => 100
			) );

			if ( ! isset( $this->rank['order'] ) )
				$this->rank['order'] = 'ASC';

			if ( ! isset( $this->rank['support'] ) )
				$this->rank['support'] = array(
					'content'         => 0,
					'excerpt'         => 0,
					'comments'        => 0,
					'page-attributes' => 0,
					'custom-fields'   => 0
				);

		}

		/**
		 * Load
		 * Custom module load for multiple point type support.
		 * @since 1.6
		 * @version 1.0
		 */
		public function load() {

			add_action( 'bonipress_pre_init',             array( $this, 'module_pre_init' ) );
			add_action( 'bonipress_init',                 array( $this, 'module_init' ) );
			add_action( 'bonipress_admin_init',           array( $this, 'module_admin_init' ), $this->menu_pos );

		}

		/**
		 * Hook into Init
		 * @since 1.4.4
		 * @version 1.0.1
		 */
		public function module_pre_init() {

			add_filter( 'bonipress_has_tags',           array( $this, 'user_tags' ) );
			add_filter( 'bonipress_parse_tags_user',    array( $this, 'parse_rank' ), 10, 3 );
			add_filter( 'bonipress_post_type_excludes', array( $this, 'exclude_ranks' ) );
			add_filter( 'bonipress_add_finished',       array( $this, 'balance_adjustment' ), 20, 3 );
			add_action( 'bonipress_zero_balances',      array( $this, 'zero_balance_action' ) );

		}

		/**
		 * Hook into Init
		 * @since 1.1
		 * @version 1.5
		 */
		public function module_init() {

			$this->register_ranks();
			$this->add_default_rank();
			$this->add_multiple_point_types_support();

			add_action( 'bonipress_set_current_account', array( $this, 'populate_current_account' ) );
			add_action( 'bonipress_get_account',         array( $this, 'populate_account' ) );

			add_action( 'pre_get_posts',                            array( $this, 'adjust_wp_query' ), 20 );
			add_action( 'bonipress_admin_enqueue',                     array( $this, 'enqueue_scripts' ), $this->menu_pos );

			// Instances to update ranks
			add_action( 'transition_post_status',                   array( $this, 'post_status_change' ), 99, 3 );

			// BuddyPress
			if ( class_exists( 'BuddyPress' ) ) {
				add_action( 'bp_before_member_header_meta',         array( $this, 'insert_rank_header' ) );
				add_action( 'bp_after_profile_loop_content',        array( $this, 'insert_rank_profile' ) );
			}

			// PSForum
			if ( class_exists( 'PSForum' ) ) {
				add_action( 'psf_theme_after_reply_author_details', array( $this, 'insert_rank_bb_reply' ) );
				add_action( 'psf_template_after_user_profile',      array( $this, 'insert_rank_bb_profile' ) );
			}

			// Shortcodes
			add_shortcode( BONIPRESS_SLUG . '_my_rank',            'bonipress_render_my_rank' );
			add_shortcode( BONIPRESS_SLUG . '_my_ranks',           'bonipress_render_my_ranks' );
			add_shortcode( BONIPRESS_SLUG . '_users_of_rank',      'bonipress_render_users_of_rank' );
			add_shortcode( BONIPRESS_SLUG . '_users_of_all_ranks', 'bonipress_render_users_of_all_ranks' );
			add_shortcode( BONIPRESS_SLUG . '_list_ranks',         'bonipress_render_rank_list' );

			// Admin Management items
			add_action( 'wp_ajax_bonipress-calc-totals',               array( $this, 'calculate_totals' ) );

		}

		/**
		 * Hook into Admin Init
		 * @since 1.1
		 * @version 1.2
		 */
		public function module_admin_init() {

			add_filter( 'parent_file',                        array( $this, 'parent_file' ) );
			add_filter( 'submenu_file',                       array( $this, 'subparent_file' ), 10, 2 );
			add_filter( 'admin_url',                          array( $this, 'replace_add_new_rank_url' ), 10, 3 );

			add_filter( 'post_row_actions',                   array( $this, 'adjust_row_actions' ), 10, 2 );

			add_filter( 'post_updated_messages',              array( $this, 'post_updated_messages' ) );
			add_filter( 'enter_title_here',                   array( $this, 'enter_title_here' ) );

			add_action( 'wp_ajax_bonipress-action-delete-ranks', array( $this, 'action_delete_ranks' ) );
			add_action( 'wp_ajax_bonipress-action-assign-ranks', array( $this, 'action_assign_ranks' ) );

			add_filter( 'bonipress_users_balance_column',        array( $this, 'custom_user_column_content' ), 10, 3 );

			add_action( 'bonipress_user_edit_after_balance',     array( $this, 'show_rank_in_user_editor' ), 40, 3 );
			add_action( 'personal_options_update',            array( $this, 'save_manual_rank' ), 50 );
			add_action( 'edit_user_profile_update',           array( $this, 'save_manual_rank' ), 50 );

			add_filter( 'manage_' . BONIPRESS_RANK_KEY . '_posts_columns',       array( $this, 'adjust_column_headers' ), 50 );
			add_action( 'manage_' . BONIPRESS_RANK_KEY . '_posts_custom_column', array( $this, 'adjust_column_content' ), 10, 2 );
			add_action( 'save_post_' . BONIPRESS_RANK_KEY,                       array( $this, 'save_rank' ), 10, 2 );

		}

		/**
		 * Is Manual Mode
		 * @since 1.8
		 * @version 1.0
		 */
		public function is_manual_mode( $type_id ) {

			$manual_mode = false;
			$point_type = 'bonipress_pref_core';

			if ( $type_id != BONIPRESS_DEFAULT_TYPE_KEY ) {
				$point_type = 'bonipress_pref_core_' . $type_id;
			}

			$setting = bonipress_get_option( $point_type );

			if ( ! empty( $setting['rank']['base'] ) && $setting['rank']['base'] == 'manual' )
				$manual_mode = $setting['rank']['base'];

			return $manual_mode;

		}

		/**
		 * Add Multiple Point Types Support
		 * @since 1.6
		 * @version 1.0
		 */
		public function add_multiple_point_types_support() {

			add_action( 'bonipress_management_prefs', array( $this, 'rank_management' ) );
			add_action( 'bonipress_after_core_prefs', array( $this, 'after_general_settings' ) );
			add_filter( 'bonipress_save_core_prefs',  array( $this, 'sanitize_extra_settings' ), 90, 3 );

			add_action( 'bonipress_add_menu',         array( $this, 'add_menus' ), $this->menu_pos );

			if ( count( $this->point_types ) > 1 ) {

				$priority = 10;
				foreach ( $this->point_types as $type_id => $label ) {

					add_action( 'bonipress_management_prefs' . $type_id, array( $this, 'rank_management' ), $priority );

					add_action( 'bonipress_after_core_prefs' . $type_id, array( $this, 'after_general_settings' ), $priority );
					add_filter( 'bonipress_save_core_prefs' . $type_id,  array( $this, 'sanitize_extra_settings' ), $priority, 3 );

					$priority += 10;

				}
			}

		}

		/**
		 * Register Rank Post Type
		 * @since 1.1
		 * @version 1.3.1
		 */
		public function register_ranks() {

			if ( isset( $_GET['ctype'] ) && array_key_exists( $_GET['ctype'], $this->point_types ) && isset( $_GET['post_type'] ) && $_GET['post_type'] == BONIPRESS_RANK_KEY )
				$name = sprintf( __( 'Ränge für %s', 'bonipress' ), $this->point_types[ $_GET['ctype'] ] );
			else
				$name = __( 'Ranks', 'bonipress' );

			$labels = array(
				'name'                  => $name,
				'singular_name'         => __( 'Rang', 'bonipress' ),
				'add_new'               => __( 'Neuer Rang', 'bonipress' ),
				'add_new_item'          => __( 'Neuer Rang', 'bonipress' ),
				'edit_item'             => __( 'Rang bearbeiten', 'bonipress' ),
				'new_item'              => __( 'Neuer Rang', 'bonipress' ),
				'all_items'             => __( 'Ränge', 'bonipress' ),
				'view_item'             => __( 'Rang ansehen', 'bonipress' ),
				'search_items'          => __( 'Ränge suchen', 'bonipress' ),
				'featured_image'        => __( 'Rang-Logo', 'bonipress' ),
				'set_featured_image'    => __( 'Stelle das Rang-Logo ein', 'bonipress' ),
				'remove_featured_image' => __( 'Rang-Logo entfernen', 'bonipress' ),
				'use_featured_image'    => __( 'Als Logo verwenden', 'bonipress' ),
				'not_found'             => __( 'Keine Ränge gefunden', 'bonipress' ),
				'not_found_in_trash'    => __( 'Keine Ränge im Papierkorb gefunden', 'bonipress' ), 
				'parent_item_colon'     => '',
				'menu_name'             => __( 'Ränge', 'bonipress' )
			);

			// Support
			$supports = array( 'title', 'thumbnail' );
			if ( isset( $this->rank['support']['content'] ) && $this->rank['support']['content'] )
				$supports[] = 'editor';
			if ( isset( $this->rank['support']['excerpt'] ) && $this->rank['support']['excerpt'] )
				$supports[] = 'excerpt';
			if ( isset( $this->rank['support']['comments'] ) && $this->rank['support']['comments'] )
				$supports[] = 'comments';
			if ( isset( $this->rank['support']['page-attributes'] ) && $this->rank['support']['page-attributes'] )
				$supports[] = 'page-attributes';
			if ( isset( $this->rank['support']['custom-fields'] ) && $this->rank['support']['custom-fields'] )
				$supports[] = 'custom-fields';

			// Custom Post Type Attributes
			$args = array(
				'labels'               => $labels,
				'public'               => (bool) $this->rank['public'],
				'publicly_queryable'   => (bool) $this->rank['public'],
				'has_archive'          => (bool) $this->rank['public'],
				'show_ui'              => true, 
				'show_in_menu'         => false,
				'capability_type'      => 'page',
				'supports'             => $supports,
				'register_meta_box_cb' => array( $this, 'add_metaboxes' )
			);

			// Rewrite
			if ( $this->rank['public'] && ! empty( $this->rank['slug'] ) )
				$args['rewrite'] = array( 'slug' => $this->rank['slug'] );

			register_post_type( BONIPRESS_RANK_KEY, apply_filters( 'bonipress_register_ranks', $args, $this ) );

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
				&& ( isset( $bonipress_current_account->ranks ) )
			) return;

			$ranks = 0;
			if ( ! empty( $bonipress_current_account->balance ) ) {
				foreach ( $bonipress_current_account->balance as $type_id => $balance ) {

					if ( $balance === false ) continue;

					$rank = bonipress_get_users_rank( $bonipress_current_account->user_id, $type_id );
					if ( $rank !== false ) $ranks ++; 

					$bonipress_current_account->balance[ $type_id ]->rank = $rank;

				}
			}

			$bonipress_current_account->ranks = $ranks;

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
				&& ( isset( $bonipress_account->ranks ) )
			) return;

			$ranks = 0;
			if ( ! empty( $bonipress_account->balance ) ) {
				foreach ( $bonipress_account->balance as $type_id => $balance ) {

					if ( $balance === false ) continue;

					$rank = bonipress_get_users_rank( $bonipress_account->user_id, $type_id );
					if ( $rank !== false ) $ranks ++; 

					$bonipress_account->balance[ $type_id ]->rank = $rank;

				}
			}

			$bonipress_account->ranks = $ranks;

		}

		/**
		 * Adjust Post Updated Messages
		 * @since 1.1
		 * @version 1.2
		 */
		public function post_updated_messages( $messages ) {

			$messages[ BONIPRESS_RANK_KEY ] = array(
				0 => '',
				1 => __( 'Rang aktualisiert.', 'bonipress' ),
				2 => __( 'Rang aktualisiert.', 'bonipress' ),
				3 => __( 'Rang aktualisiert.', 'bonipress' ),
				4 => __( 'Rang aktualisiert.', 'bonipress' ),
				5 => __( 'Rang aktualisiert.', 'bonipress' ),
				6 => __( 'Rang aktiviert.', 'bonipress' ),
				7 => __( 'Rang gespeichert.', 'bonipress' ),
				8 => __( 'Rang aktualisiert.', 'bonipress' ),
				9 => __( 'Rang aktualisiert.', 'bonipress' ),
				10 => ''
			);

			return $messages;

		}

		/**
		 * Replace Add New Rank URL
		 * @since 1.7
		 * @version 1.0.1
		 */
		public function replace_add_new_rank_url( $url, $path, $blog_id ) {

			global $post;

			if ( $path == 'post-new.php?post_type=' . BONIPRESS_RANK_KEY ) {

				if ( isset( $_GET['ctype'] ) )
					return get_site_url( $blog_id, 'wp-admin/', 'admin' ) . 'post-new.php?post_type=' . BONIPRESS_RANK_KEY . '&ctype=' . ( ( isset( $_GET['ctype'] ) ) ? $_GET['ctype'] : BONIPRESS_DEFAULT_TYPE_KEY );

				elseif ( isset( $post->post_type ) && $post->post_type == BONIPRESS_RANK_KEY && bonipress_get_post_meta( $post->ID, 'ctype', true ) != '' )
					return get_site_url( $blog_id, 'wp-admin/', 'admin' ) . 'post-new.php?post_type=' . BONIPRESS_RANK_KEY . '&ctype=' . bonipress_get_post_meta( $post->ID, 'ctype', true );

			}

			return $url;

		}

		/**
		 * Add Admin Menu Item
		 * @since 1.6
		 * @version 1.1
		 */
		public function add_menus() {

			// In case we are using the Master Template feautre on multisites, and this is not the main
			// site in the network, bail.
			if ( bonipress_override_settings() && ! bonipress_is_main_site() ) return;

			$capability = $this->core->get_point_editor_capability();

			foreach ( $this->point_types as $type_id => $label ) {

				$menu_slug = ( $type_id != BONIPRESS_DEFAULT_TYPE_KEY ) ? BONIPRESS_SLUG . '_' . $type_id : BONIPRESS_SLUG;

				add_submenu_page(
					$menu_slug,
					__( 'Ränge', 'bonipress' ),
					__( 'Ränge', 'bonipress' ),
					$capability,
					'edit.php?post_type=' . BONIPRESS_RANK_KEY . '&ctype=' . $type_id
				);

			}

		}

		/**
		 * Parent File
		 * @since 1.6
		 * @version 1.0.2
		 */
		public function parent_file( $parent = '' ) {

			global $pagenow;

			// When listing ranks, we need to indicate that we are under the appropriate point type menu
			if ( ( $pagenow == 'edit.php' || $pagenow == 'post-new.php' ) && isset( $_GET['post_type'] ) && $_GET['post_type'] == BONIPRESS_RANK_KEY ) {
			
				if ( isset( $_GET['ctype'] ) && sanitize_key( $_GET['ctype'] ) != BONIPRESS_DEFAULT_TYPE_KEY )
					return BONIPRESS_SLUG . '_' . sanitize_key( $_GET['ctype'] );

				return BONIPRESS_SLUG;
			
			}

			// When editing a rank, we need to indicate that we are under the appropriate point type menu
			elseif ( $pagenow == 'post.php' && isset( $_GET['post'] ) && bonipress_get_post_type( $_GET['post'] ) == BONIPRESS_RANK_KEY ) {

				if ( isset( $_GET['ctype'] ) && $_GET['ctype'] != BONIPRESS_DEFAULT_TYPE_KEY )
					return BONIPRESS_SLUG . '_' . sanitize_key( $_GET['ctype'] );

				$point_type = bonipress_get_post_meta( $_GET['post'], 'ctype', true );
				$point_type = sanitize_key( $point_type );

				if ( $point_type != BONIPRESS_DEFAULT_TYPE_KEY )
					return BONIPRESS_SLUG . '_' . $point_type;

				return BONIPRESS_SLUG;

			}

			return $parent;

		}

		/**
		 * Sub Parent File
		 * @since 1.7
		 * @version 1.0.1
		 */
		public function subparent_file( $subparent = '', $parent = '' ) {

			global $pagenow;

			// When listing ranks, we need to highlight the "Ranks" submenu to indicate where we are
			if ( ( $pagenow == 'edit.php' || $pagenow == 'post-new.php' ) && isset( $_GET['post_type'] ) && $_GET['post_type'] == BONIPRESS_RANK_KEY ) {

				if ( isset( $_GET['ctype'] ) )
					return 'edit.php?post_type=' . BONIPRESS_RANK_KEY . '&ctype=' . $_GET['ctype'];

				return 'edit.php?post_type=' . BONIPRESS_RANK_KEY . '&ctype=' . BONIPRESS_DEFAULT_TYPE_KEY;
			
			}

			// When editing a rank, we need to highlight the "Ranks" submenu to indicate where we are
			elseif ( $pagenow == 'post.php' && isset( $_GET['post'] ) && bonipress_get_post_type( $_GET['post'] ) == BONIPRESS_RANK_KEY ) {

				if ( isset( $_GET['ctype'] ) )
					return 'edit.php?post_type=' . BONIPRESS_RANK_KEY . '&ctype=' . $_GET['ctype'];

				$point_type = bonipress_get_post_meta( $_GET['post'], 'ctype', true );
				$point_type = sanitize_key( $point_type );

				if ( $point_type != BONIPRESS_DEFAULT_TYPE_KEY )
					return 'edit.php?post_type=' . BONIPRESS_RANK_KEY . '&ctype=' . $point_type;

				return 'edit.php?post_type=' . BONIPRESS_RANK_KEY . '&ctype=' . BONIPRESS_DEFAULT_TYPE_KEY;

			}

			return $subparent;

		}

		/**
		 * Exclude Ranks from Publish Content Hook
		 * @since 1.3
		 * @version 1.0
		 */
		public function exclude_ranks( $excludes ) {

			$excludes[] = BONIPRESS_RANK_KEY;
			return $excludes;

		}

		/**
		 * AJAX: Calculate Totals
		 * @since 1.2
		 * @version 1.4
		 */
		public function calculate_totals() {

			// Security
			check_ajax_referer( 'bonipress-calc-totals', 'token' );

			$point_type = BONIPRESS_DEFAULT_TYPE_KEY;
			if ( isset( $_POST['ctype'] ) && bonipress_point_type_exists( sanitize_key( $_POST['ctype'] ) ) )
				$point_type = sanitize_key( $_POST['ctype'] );

			global $wpdb;

			// Get all users that have a balance. Excluded users will have no balance
			$users = $wpdb->get_col( $wpdb->prepare( "
				SELECT DISTINCT user_id 
				FROM {$wpdb->usermeta} 
				WHERE meta_key = %s", bonipress_get_meta_key( $point_type ) ) );

			$count = 0;
			if ( ! empty( $users ) ) {

				// Get the total for each user with a balance
				foreach ( $users as $user_id ) {

					$total = bonipress_calculate_users_total( $user_id, $point_type );
					bonipress_update_user_meta( $user_id, $point_type, '_total', $total );
					$count ++;

				}

			}

			wp_send_json( sprintf( __( 'Abgeschlossen - Gesamtzahl von %d betroffenen Benutzern', 'bonipress' ), $count ) );

		}

		/**
		 * Balance Adjustment
		 * Check if users rank should change.
		 * @since 1.1
		 * @version 1.6
		 */
		public function balance_adjustment( $result, $request, $bonipress ) {

			// If the result was declined
			if ( $result === false ) return $result;

			extract( $request );


			// Manual mode
			if ( $this->is_manual_mode() ) return $result;

			// If ranks for this type is based on total and this is not a admin adjustment
			if ( bonipress_rank_based_on_total( $type ) && $amount < 0 && $ref != 'manual' )
				return $result;

			// Find users rank
			$rank = bonipress_find_users_rank( $user_id, $type );

			// If users rank changed, save it now.
			if ( isset( $rank->rank_id ) && $rank->rank_id !== $rank->current_id )
				bonipress_save_users_rank( $user_id, $rank->rank_id, $type );

			return $result;

		}

		/**
		 * Publishing Content
		 * Check if users rank should change.
		 * @since 1.1
		 * @version 1.6
		 */
		public function post_status_change( $new_status, $old_status, $post ) {

			global $bonipress_ranks;

			// Only ranks please
			if ( $post->post_type != BONIPRESS_RANK_KEY ) return;

			$point_type = bonipress_get_post_meta( $post->ID, 'ctype', true );
			if ( $point_type == '' ) {

				$point_type = BONIPRESS_DEFAULT_TYPE_KEY;
				bonipress_update_post_meta( $post->ID, 'ctype', $point_type );

			}

			if ( $this->is_manual_mode( $point_type ) ) return;

			// Publishing or trashing of ranks
			if ( ( $new_status == 'publish' && $old_status != 'publish' ) || ( $new_status == 'trash' && $old_status != 'trash' ) ) {

				wp_cache_delete( 'ranks-published-' . $point_type, BONIPRESS_SLUG );
				wp_cache_delete( 'ranks-published-count-' . $point_type, BONIPRESS_SLUG );

				bonipress_assign_ranks( $point_type );

			}

		}

		/**
		 * User Related Template Tags
		 * Adds support for ranks of custom point types.
		 * @since 1.6
		 * @version 1.0
		 */
		public function user_tags( $tags ) {

			$tags   = explode( '|', $tags );
			$tags[] = 'rank';
			$tags[] = 'rank_logo';

			foreach ( $this->point_types as $type_id => $label ) {

				if ( $type_id == BONIPRESS_DEFAULT_TYPE_KEY ) continue;
				$tags[] = 'rank_' . $type_id;
				$tags[] = 'rank_logo_' . $type_id;

			}

			return implode( '|', $tags );

		}

		/**
		 * Parse Rank
		 * Parses the %rank% and %rank_logo% template tags.
		 * @since 1.1
		 * @version 1.3
		 */
		public function parse_rank( $content, $user = '', $data = '' ) {

			// No rank no need to run
			if ( ! preg_match( '/(%rank[%|_])/', $content ) ) return $content;

			// User ID does not exist ( user no longer exists )
			if ( ! isset( $user->ID ) ) {
				foreach ( $this->point_types as $type_id => $label ) {

					if ( $type_id == BONIPRESS_DEFAULT_TYPE_KEY ) {
						$content = str_replace( '%rank%',      '', $content );
						$content = str_replace( '%rank_logo%', '', $content );
					}
					else {
						$content = str_replace( '%rank_' . $type_id . '%',      '', $content );
						$content = str_replace( '%rank_logo_' . $type_id . '%', '', $content );
					}

				}
			}

			// Got a user ID
			else {

				// Loop the point types and replace template tags
				foreach ( $this->point_types as $type_id => $label ) {

					$rank_id = bonipress_get_users_rank_id( $user->ID, $type_id );
					if ( $rank_id === false ) {

						if ( $type_id == BONIPRESS_DEFAULT_TYPE_KEY ) {
							$content = str_replace( '%rank%',      '', $content );
							$content = str_replace( '%rank_logo%', '', $content );
						}
						else {
							$content = str_replace( '%rank_' . $type_id . '%',      '', $content );
							$content = str_replace( '%rank_logo_' . $type_id . '%', '', $content );
						}

					}
					else {

						if ( $type_id == BONIPRESS_DEFAULT_TYPE_KEY ) {
							$content = str_replace( '%rank%',      bonipress_get_the_title( $rank_id ), $content );
							$content = str_replace( '%rank_logo%', bonipress_get_rank_logo( $rank_id ), $content );
						}
						else {
							$content = str_replace( '%rank_' . $type_id . '%',      bonipress_get_the_title( $rank_id ), $content );
							$content = str_replace( '%rank_logo_' . $type_id . '%', bonipress_get_rank_logo( $rank_id ), $content );
						}

					}

				}
			}

			return $content;

		}

		/**
		 * Insert Rank In Profile Header
		 * @since 1.1
		 * @version 1.3.1
		 */
		public function insert_rank_header() {

			$output       = '';
			$user_id      = bp_displayed_user_id();
			$bonipress_types = bonipress_get_usable_types( $user_id );

			foreach ( $bonipress_types as $type_id ) {

				// Load type
				$bonipress     = bonipress( $type_id );

				//Nothing to do if we are excluded
				if ( $bonipress->exclude_user( $user_id ) ) continue;

				// No settings
				if ( ! isset( $bonipress->rank['bb_location'] ) ) continue;

				// Not shown
				if ( ! in_array( $bonipress->rank['bb_location'], array( 'top', 'both' ) ) || $bonipress->rank['bb_template'] == '' ) continue;

				// Get rank (if user has one)
				$users_rank = bonipress_get_users_rank_id( $user_id, $type_id );
				if ( $users_rank === false ) continue;

				// Parse template
				$template   = $bonipress->rank['bb_template'];
				$template   = str_replace( '%rank_title%', bonipress_get_the_title( $users_rank ), $template );
				$template   = str_replace( '%rank_logo%',  bonipress_get_rank_logo( $users_rank, 'full' ), $template );

				$template   = $bonipress->template_tags_general( $template );
				$template   = '<div class="bonipress-my-rank ' . $type_id . '">' . $template . '</div>';

				// Let others play
				$output    .= apply_filters( 'bonipress_bp_header_ranks_row', $template, $user_id, $users_rank, $bonipress, $this );

			}

			if ( $output == '' ) return;

			echo '<div id="bonipress-my-ranks">' . apply_filters( 'bonipress_bp_rank_in_header', $output, $user_id, $this ) . '</div>';

		}

		/**
		 * Insert Rank In Profile Details
		 * @since 1.1
		 * @version 1.4.1
		 */
		public function insert_rank_profile() {

			$output       = '';
			$user_id      = bp_displayed_user_id();
			$bonipress_types = bonipress_get_usable_types( $user_id );

			$count = 0;
			foreach ( $bonipress_types as $type_id ) {

				// Load type
				$bonipress     = bonipress( $type_id );

				//Nothing to do if we are excluded
				if ( $bonipress->exclude_user( $user_id ) ) continue;

				// No settings
				if ( ! isset( $bonipress->rank['bb_location'] ) ) continue;

				// Not shown
				if ( ! in_array( $bonipress->rank['bb_location'], array( 'profile_tab', 'both' ) ) || $bonipress->rank['bb_template'] == '' ) continue;

				// Get rank (if user has one)
				$users_rank = bonipress_get_users_rank_id( $user_id, $type_id );
				if ( $users_rank === false ) continue;

				// Parse template
				$template   = $bonipress->rank['bb_template'];
				$template   = str_replace( '%rank_title%', bonipress_get_the_title( $users_rank ), $template );
				$template   = str_replace( '%rank_logo%',  bonipress_get_rank_logo( $users_rank ), $template );

				$template   = $bonipress->template_tags_general( $template );
				$template   = '<div class="bonipress-my-rank ' . $type_id . '">' . $template . '</div>';

				// Let others play
				$output    .= apply_filters( 'bonipress_bp_profile_ranks_row', $template, $user_id, $users_rank, $bonipress, $this );
				$count ++;

			}

			if ( $output == '' ) return;

?>
<div class="bp-widget bonipress-field">
	<table class="profile-fields">
		<tr id="bonipress-users-rank">
			<td class="label"><?php if ( $count == 1 ) _e( 'Rang', 'bonipress' ); else _e( 'Ränge', 'bonipress' ); ?></td>
			<td class="data">
				<?php echo apply_filters( 'bonipress_bp_rank_in_profile', $output, $user_id, $this ); ?>

			</td>
		</tr>
	</table>
</div>
<?php

		}

		/**
		 * Insert Rank In PSForum Reply
		 * @since 1.6
		 * @version 1.1.1
		 */
		public function insert_rank_bb_reply() {

			$output  = '';
			$user_id = psf_get_reply_author_id();
			if ( $user_id == 0 ) return;

			$bonipress_types = bonipress_get_usable_types( $user_id );

			foreach ( $bonipress_types as $type_id ) {

				// Load type
				$bonipress     = bonipress( $type_id );

				// No settings
				if ( ! isset( $bonipress->rank['bp_location'] ) ) continue;

				//Nothing to do if we are excluded
				if ( $bonipress->exclude_user( $user_id ) ) continue;

				// Not shown
				if ( ! in_array( $bonipress->rank['bp_location'], array( 'reply', 'both' ) ) || $bonipress->rank['bp_template'] == '' ) continue;

				// Get rank (if user has one
				$users_rank = bonipress_get_users_rank_id( $user_id, $type_id );
				if ( $users_rank === false ) continue;

				// Parse template
				$template   = $bonipress->rank['bp_template'];
				$template   = str_replace( '%rank_title%', bonipress_get_the_title( $users_rank ), $template );
				$template   = str_replace( '%rank_logo%',  bonipress_get_rank_logo( $users_rank ), $template );

				$template   = $bonipress->template_tags_general( $template );
				$template   = '<div class="bonipress-my-rank ' . $type_id . '">' . $template . '</div>';

				// Let others play
				$output    .= apply_filters( 'bonipress_bb_reply_ranks_row', $template, $user_id, $users_rank, $bonipress, $this );

			}

			if ( $output == '' ) return;

			echo '<div id="bonipress-my-ranks">' . apply_filters( 'bonipress_bb_rank_in_reply', $output, $user_id, $this ) . '</div>';

		}

		/**
		 * Insert Rank In PSForum Profile
		 * @since 1.6
		 * @version 1.0.1
		 */
		public function insert_rank_bb_profile() {

			$output       = '';
			$user_id      = psf_get_displayed_user_id();
			$bonipress_types = bonipress_get_usable_types( $user_id );

			foreach ( $bonipress_types as $type_id => $label ) {

				// Load type
				$bonipress     = bonipress( $type_id );

				// No settings
				if ( ! isset( $bonipress->rank['bp_location'] ) ) continue;

				//Nothing to do if we are excluded
				if ( $bonipress->exclude_user( $user_id ) ) continue;

				// Not shown
				if ( ! in_array( $bonipress->rank['bp_location'], array( 'profile', 'both' ) ) || $bonipress->rank['bp_template'] == '' ) continue;

				// Get rank (if user has one
				$users_rank = bonipress_get_users_rank_id( $user_id, $type_id );
				if ( $users_rank === false ) continue;

				// Parse template
				$template   = $bonipress->rank['bp_template'];
				$template   = str_replace( '%rank_title%', bonipress_get_the_title( $users_rank ), $template );
				$template   = str_replace( '%rank_logo%',  bonipress_get_rank_logo( $users_rank ), $template );

				$template   = $bonipress->template_tags_general( $template );
				$template   = '<div class="bonipress-my-rank ' . $type_id . '">' . $template . '</div>';

				// Let others play
				$output    .= apply_filters( 'bonipress_bb_profile_ranks_row', $template, $user_id, $users_rank, $bonipress, $this );

			}

			if ( $output == '' ) return;

			echo '<div id="bonipress-my-ranks">' . apply_filters( 'bonipress_bb_rank_in_profile', $output, $user_id, $this ) . '</div>';

		}

		/**
		 * Add Default Rank
		 * Adds the default "Newbie" rank and adds all non-exluded user to this rank.
		 * Note! This method is only called when there are zero ranks as this will create the new default rank.
		 * @since 1.1
		 * @version 1.2
		 */
		public function add_default_rank() {

			global $bonipress_ranks;

			// If there are no ranks at all
			if ( ! bonipress_have_ranks() ) {

				// Construct a new post
				$rank                = array();
				$rank['post_title']  = 'Newbie';
				$rank['post_type']   = BONIPRESS_RANK_KEY;
				$rank['post_status'] = 'publish';

				// Insert new rank post
				$rank_id = wp_insert_post( $rank );

				// Update min and max values
				bonipress_update_post_meta( $rank_id, 'bonipress_rank_min', 0 );
				bonipress_update_post_meta( $rank_id, 'bonipress_rank_max', 9999999 );
				bonipress_update_post_meta( $rank_id, 'ctype',           BONIPRESS_DEFAULT_TYPE_KEY );

				$bonipress_ranks = 1;
				bonipress_assign_ranks();

			}

		}

		/**
		 * Custom User Balance Content
		 * Inserts a users rank for each point type.
		 * @since 1.6
		 * @version 1.1
		 */
		public function custom_user_column_content( $balance, $user_id, $type ) {

			$rank = bonipress_get_users_rank( $user_id, $type );
			if ( $rank !== false )
				$balance .= '<small style="display:block;">' . sprintf( '<strong>%s:</strong> %s', __( 'Rang', 'bonipress' ), $rank->title ) . '</small>';

			else
				$balance .= '<small style="display:block;">' . sprintf( '<strong>%s:</strong> -', __( 'Rang', 'bonipress' ) ) . '</small>';

			return $balance;

		}

		/**
		 * Show Rank in User Editor
		 * @since 1.7
		 * @version 1.3
		 */
		public function show_rank_in_user_editor( $point_type, $user, $data ) {

			if ( $data['excluded'] ) {
				echo '<div class="balance-desc current-rank">-</div>';
				return;
			}

			if ( ! bonipress_have_ranks( $point_type ) ) {
				echo '<div class="balance-desc current-rank"><em>' . __( 'Es gibt keine Ränge.', 'bonipress' ) . '</em></div>';
				return;
			}

			$users_rank = bonipress_get_users_rank( $user->ID, $point_type );
			$rank_title = '-';
			if ( isset( $users_rank->title ) )
				$rank_title = $users_rank->title;

			// In manual mode we want to show a dropdown menu so an admin can adjust a users rank
			if ( $this->is_manual_mode( $point_type ) && bonipress_is_admin( NULL, $point_type ) ) {

				$ranks = bonipress_get_ranks( 'publish', '-1', 'DESC', $point_type );
				echo '<div class="balance-desc current-rank"><select name="rank-' . $point_type . '" id="bonipress-rank">';

				echo '<option value=""';
				if ( $users_rank === false )
					echo ' selected="selected"';
				echo '>' . __( 'Kein Rang', 'bonipress' ) . '</option>';

				foreach ( $ranks as $rank ) {
					echo '<option value="' . $rank->post_id . '"';
					if ( ! empty( $users_rank ) && $users_rank->post_id == $rank->post_id ) echo ' selected="selected"';
					echo '>' . $rank->title . '</option>';
				}

				echo '</select></div>';

			}
			else {

				echo '<div class="balance-desc current-rank">' . sprintf( '<strong>%s:</strong> %s', __( 'Rang', 'bonipress' ), $rank_title ) . '</div>';

			}

		}

		/**
		 * Save Users Rank
		 * @since 1.8
		 * @version 1.0
		 */
		public function save_manual_rank( $user_id ) {

			$point_types = bonipress_get_types();
			foreach ( $point_types as $type_key => $label ) {

				if ( $this->is_manual_mode( $type_key ) ) {

					if ( isset( $_POST[ 'rank-' . $type_key ] ) && bonipress_is_admin( NULL, $type_key ) ) {

						// Get users current rank for comparison
						$users_rank = bonipress_get_users_rank( $user_id, $type_key );

						$rank       = false;

						if ( $_POST[ 'rank-' . $type_key ] != '' )
							$rank = absint( $_POST[ 'rank-' . $type_key ] );

						// Save users rank if a valid rank id is provided and it differs from the users current one
						if ( $rank !== false && $rank > 0 && $users_rank->rank_id != $rank )
							bonipress_save_users_rank( $user_id, $rank, $type_key );

						// Delete users rank
						elseif ( $rank === false ) {

							$end     = '';
							if ( $type_key != BONIPRESS_DEFAULT_TYPE_KEY )
								$end = $type_key;

							bonipress_delete_user_meta( $user_id, BONIPRESS_RANK_KEY, $end );

						}

					}

				}

			}

		}

		/**
		 * Register Scripts & Styles
		 * @since 1.7
		 * @version 1.0
		 */
		public function scripts_and_styles() { }

		/**
		 * Enqueue Scripts & Styles
		 * @since 1.1
		 * @version 1.3.2
		 */
		public function enqueue_scripts() {

			$adjust_header = false;
			$screen        = get_current_screen();

			wp_register_script(
				'bonipress-rank-tweaks',
				plugins_url( 'assets/js/tweaks.js', boniPRESS_RANKS ),
				array( 'jquery' ),
				boniPRESS_VERSION . '.1'
			);

			wp_register_script(
				'bonipress-rank-management',
				plugins_url( 'assets/js/management.js', boniPRESS_RANKS ),
				array( 'jquery' ),
				boniPRESS_VERSION . '.1'
			);

			// Ranks List Page
			if ( strpos( 'edit-' . BONIPRESS_RANK_KEY, $screen->id ) > -1 ) {

				wp_enqueue_style( 'bonipress-admin' );

				if ( isset( $_GET['ctype'] ) && array_key_exists( $_GET['ctype'], $this->point_types ) ) :

					wp_localize_script(
						'bonipress-rank-tweaks',
						'boniPRESS_Ranks',
						array(
							'rank_ctype' => $_GET['ctype']
						)
					);
					wp_enqueue_script( 'bonipress-rank-tweaks' );

				endif;

			}

			// Edit Rank Page
			if ( strpos( BONIPRESS_RANK_KEY, $screen->id ) > -1 ) {

				wp_dequeue_script( 'autosave' );
				wp_enqueue_style( 'bonipress-bootstrap-grid' );
				wp_enqueue_style( 'bonipress-forms' );

				add_filter( 'postbox_classes_' . BONIPRESS_RANK_KEY . '_bonipress-rank-setup', array( $this, 'metabox_classes' ) );

?>
<style type="text/css">
#misc-publishing-actions .misc-pub-curtime { display: none; }
#misc-publishing-actions #visibility { display: none; }
</style>
<?php

			}

			// Insert management script
			if ( in_array( substr( $screen->id, -9 ), array( '_settings', '-settings' ) ) ) {

				wp_localize_script(
					'bonipress-rank-management',
					'boniPRESS_Ranks',
					array(
						'ajaxurl'        => admin_url( 'admin-ajax.php' ),
						'token'          => wp_create_nonce( 'bonipress-management-actions-roles' ),
						'working'        => esc_attr__( 'Wird bearbeitet...', 'bonipress' ),
						'confirm_del'    => esc_attr__( 'Warnung! Alle Ränge werden gelöscht! Das kann nicht rückgängig gemacht werden!', 'bonipress' ),
						'confirm_assign' => esc_attr__( 'Möchtest Du Benutzerränge wirklich neu zuweisen?', 'bonipress' )
					)
				);
				wp_enqueue_script( 'bonipress-rank-management' );

			}

		}

		/**
		 * Adjust Rank Sort Order
		 * Adjusts the wp query when viewing ranks to order by the min. point requirement.
		 * @since 1.1.1
		 * @version 1.2.1
		 */
		public function adjust_wp_query( $query ) {

			if ( ! function_exists( 'is_admin' ) ) return;

			// Front End Queries
			if ( ! is_admin() ) {

				// Only applicable on the post archive page (if used) and only for the main query
				if ( ! is_post_type_archive( BONIPRESS_RANK_KEY ) || ! $query->is_main_query() ) return;

				// By default we want to only show ranks for the main point type
				if ( ! isset( $_GET['ctype'] ) ) {
					$query->set( 'meta_query', array(
						array(
							'key'     => 'ctype',
							'value'   => BONIPRESS_DEFAULT_TYPE_KEY,
							'compare' => '='
						)
					) );
				}

				// Otherwise if ctype is set and it is a point type filter the results
				elseif ( isset( $_GET['ctype'] ) && array_key_exists( $_GET['ctype'], $this->point_types ) ) {
					$query->set( 'meta_query', array(
						array(
							'key'     => 'ctype',
							'value'   => $_GET['ctype'],
							'compare' => '='
						)
					) );
				}

			}

			// Admin Queries
			else {

				// Only applicable when we are quering ranks
				if ( ! isset( $query->query['post_type'] ) || $query->query['post_type'] != BONIPRESS_RANK_KEY ) return;

				// If ctype is set, filter ranks according to it's value
				if ( isset( $_GET['ctype'] ) && array_key_exists( $_GET['ctype'], $this->point_types ) ) {
					$query->set( 'meta_query', array(
						array(
							'key'     => 'ctype',
							'value'   => $_GET['ctype'],
							'compare' => '='
						)
					) );
				}

			}

			// Sort by meta value
			$query->set( 'meta_key', 'bonipress_rank_min' );
			$query->set( 'orderby',  'meta_value_num' );

			// Sort order
			if ( ! isset( $this->rank['order'] ) ) $this->rank['order'] = 'ASC';
			$query->set( 'order',    $this->rank['order'] );

		}

		/**
		 * Adjust Rank Column Header
		 * @since 1.1
		 * @version 1.2
		 */
		public function adjust_column_headers( $defaults ) {

			$columns                      = array();
			$columns['cb']                = $defaults['cb'];

			// Add / Adjust
			$columns['title']             = __( 'Rangtitel', 'bonipress' );
			$columns['bonipress-rank-logo']  = __( 'Logo', 'bonipress' );
			$columns['bonipress-rank-req']   = __( 'Erforderlich', 'bonipress' );
			$columns['bonipress-rank-users'] = __( 'Benutzer', 'bonipress' );

			if ( count( $this->point_types ) > 1 )
				$columns['bonipress-rank-type']  = __( 'Punkttyp', 'bonipress' );

			// Return
			return $columns;

		}

		/**
		 * Adjust Rank Column Content
		 * @since 1.1
		 * @version 1.1
		 */
		public function adjust_column_content( $column_name, $post_id ) {

			$type = bonipress_get_post_meta( $post_id, 'ctype', true );
			if ( $type == '' )
				$type = BONIPRESS_DEFAULT_TYPE_KEY;

			// Rank Logo (thumbnail)
			if ( $column_name == 'bonipress-rank-logo' ) {
				$logo = bonipress_get_rank_logo( $post_id, 'thumbnail' );
				if ( empty( $logo ) )
					echo __( 'Kein Logo gesetzt', 'bonipress' );
				else
					echo $logo;

			}

			// Rank Requirement (custom metabox)
			elseif ( $column_name == 'bonipress-rank-req' ) {

				$bonipress = $this->core;
				if ( $type != BONIPRESS_DEFAULT_TYPE_KEY )
					$bonipress = bonipress( $type );

				$min = bonipress_get_post_meta( $post_id, 'bonipress_rank_min', true );
				if ( empty( $min ) && (int) $min !== 0 )
					$min = __( 'Any Value', 'bonipress' );

				$min = $bonipress->template_tags_general( __( 'Minimum %plural%', 'bonipress' ) ) . ': ' . $min;
				$max = bonipress_get_post_meta( $post_id, 'bonipress_rank_max', true );
				if ( empty( $max ) )
					$max = __( 'Any Value', 'bonipress' );

				$max = $bonipress->template_tags_general( __( 'Maximum %plural%', 'bonipress' ) ) . ': ' . $max;
				echo $min . '<br />' . $max;

			}

			// Rank Users (user list)
			elseif ( $column_name == 'bonipress-rank-users' ) {

				echo bonipress_count_users_with_rank( $post_id );

			}

			// Rank Point Type
			if ( $column_name == 'bonipress-rank-type' ) {

				if ( isset( $this->point_types[ $type ] ) )
					echo $this->point_types[ $type ];
				else
					echo $this->core->plural();

			}

		}

		/**
		 * Adjust Row Actions
		 * @since 1.1
		 * @version 1.0
		 */
		public function adjust_row_actions( $actions, $post ) {

			if ( $post->post_type == BONIPRESS_RANK_KEY ) {
				unset( $actions['inline hide-if-no-js'] );

				if ( ! $this->rank['public'] )
					unset( $actions['view'] );
			}

			return $actions;

		}

		/**
		 * Adjust Enter Title Here
		 * @since 1.1
		 * @version 1.0
		 */
		public function enter_title_here( $title ) {

			global $post_type;
			if ( $post_type == BONIPRESS_RANK_KEY )
				return __( 'Rangtitel', 'bonipress' );

			return $title;

		}

		/**
		 * Add Meta Boxes
		 * @since 1.1
		 * @version 1.0
		 */
		public function add_metaboxes() {

			add_meta_box(
				'bonipress-rank-setup',
				__( 'Rang einrichten', 'bonipress' ),
				array( $this, 'rank_settings' ),
				BONIPRESS_RANK_KEY,
				'normal',
				'high'
			);

		}

		/**
		 * Rank Settings Metabox
		 * @since 1.1
		 * @version 1.2.1
		 */
		public function rank_settings( $post ) {

			// Get type
			$type = bonipress_get_post_meta( $post->ID, 'ctype', true );
			if ( $type == '' ) {
				$type = BONIPRESS_DEFAULT_TYPE_KEY;
				bonipress_update_post_meta( $post->ID, 'ctype', $type );
			}

			// If a custom type has been requested via the URL
			if ( isset( $_REQUEST['ctype'] ) && ! empty( $_REQUEST['ctype'] ) )
				$type = sanitize_key( $_REQUEST['ctype'] );

			// Load the appropriate type object
			$bonipress = $this->core;
			if ( $type != BONIPRESS_DEFAULT_TYPE_KEY )
				$bonipress = bonipress( $type );

			$rank = bonipress_get_rank( $post->ID );

?>
<div class="form">
	<div class="row">
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="row">
				<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
					<div class="form-group">
						<label for="bonipress-rank-min"><?php _e( 'Minimale Guthabenanforderung', 'bonipress' ); ?></label>
						<input type="text" name="bonipress_rank[bonipress_rank_min]" id="bonipress-rank-min" class="form-control" value="<?php echo esc_attr( $rank->minimum ); ?>" />
					</div>
				</div>
				<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
					<div class="form-group">
						<label for="bonipress-rank-max"><?php _e( 'Maximale Guthabenanforderung', 'bonipress' ); ?></label>
						<input type="text" name="bonipress_rank[bonipress_rank_max]" id="bonipress-rank-max" class="form-control" value="<?php echo esc_attr( $rank->maximum ); ?>" />
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">

					<?php if ( count( $this->point_types ) > 1 ) : ?>

					<div class="form-group">
						<label for="bonipress-rank-point-type"><?php _e( 'Punkttyp', 'bonipress' ); ?></label>
						<?php bonipress_types_select_from_dropdown( 'bonipress_rank[ctype]', 'bonipress-rank-point-type', $type, false, '  class="form-control"' ); ?>
					</div>

					<?php else : ?>

					<div class="form-group">
						<label for="bonipress-rank-point-type"><?php _e( 'Punkttyp', 'bonipress' ); ?></label>
						<p class="form-control-static"><?php echo $bonipress->plural(); ?></p>
						<input type="hidden" name="bonipress_rank[ctype]" value="<?php echo BONIPRESS_DEFAULT_TYPE_KEY; ?>" />
					</div>

					<?php endif; ?>

				</div>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
<?php

			// Get all published ranks for this type
			$all_ranks = bonipress_get_ranks( 'publish', '-1', 'DESC', $type );

			if ( ! empty( $all_ranks ) ) {

				echo '<ul>';
				foreach ( $all_ranks as $rank ) {

					if ( $rank->minimum == '' ) $rank->minimum = __( 'Nicht festgelegt', 'bonipress' );
					if ( $rank->maximum == '' ) $rank->maximum = __( 'Nicht festgelegt', 'bonipress' );

					echo '<li><strong>' . $rank->title . '</strong> ' . $rank->minimum . ' - ' . $rank->maximum . '</li>';

				}
				echo '</ul>';

			}
			else {

				echo '<p>' . __( 'Keine Ränge gefunden', 'bonipress' ) . '.</p>';

			}

?>
		</div>
	</div>
</div>
<?php

		}

		/**
		 * Save Rank Details
		 * @since 1.1
		 * @version 1.5
		 */
		public function save_rank( $post_id, $post ) {

			// Make sure fields exists
			if ( $post === NULL || ! $this->core->user_is_point_editor() || ! isset( $_POST['bonipress_rank'] ) ) return;

			$point_type = sanitize_key( $_POST['bonipress_rank']['ctype'] );
			if ( ! array_key_exists( $point_type, $this->point_types ) )
				$point_type = BONIPRESS_DEFAULT_TYPE_KEY;

			bonipress_update_post_meta( $post_id, 'ctype', $point_type );

			$type_object = new boniPRESS_Point_Type( $point_type );

			foreach ( $_POST['bonipress_rank'] as $meta_key => $meta_value ) {

				if ( $meta_key == 'ctype' ) continue;

				$new_value = sanitize_text_field( $meta_value );
				$new_value = $type_object->number( $new_value );

				bonipress_update_post_meta( $post_id, $meta_key, $new_value );

			}

			// Delete caches
			wp_cache_delete( 'ranks-published-' . $point_type, BONIPRESS_SLUG );
			wp_cache_delete( 'ranks-published-count-' . $point_type, BONIPRESS_SLUG );

			if ( ! $this->is_manual_mode() )
				bonipress_assign_ranks( $point_type );

		}

		/**
		 * Add to General Settings
		 * @since 1.1
		 * @version 1.5
		 */
		public function after_general_settings( $bonipress = NULL ) {

			$prefs             = $this->rank;
			$this->add_to_core = true;
			if ( $bonipress->bonipress_type != BONIPRESS_DEFAULT_TYPE_KEY ) {

				if ( ! isset( $bonipress->rank ) )
					$prefs = $this->default_prefs;
				else
					$prefs = $bonipress->rank;

				$this->option_id = $bonipress->option_id;

			}

			$buddypress        = ( ( class_exists( 'BuddyPress' ) ) ? true : false ); 
			$psforum           = ( ( class_exists( 'PSForum' ) ) ? true : false ); 

			$box               = ( ( $prefs['base'] == 'current' ) ? 'display: none;' : 'display: block;' );

?>
<h4><span class="dashicons dashicons-admin-plugins static"></span><?php _e( 'Ränge', 'bonipress' ); ?></h4>
<div class="body" style="display:none;">

	<?php if ( $bonipress->bonipress_type == BONIPRESS_DEFAULT_TYPE_KEY ) : ?>

	<div class="row">
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<h3><?php _e( 'Rangfunktionen', 'bonipress' ); ?></h3>
			<div class="form-group">
				<div class="checkbox">
					<label><input type="checkbox" value="1" checked="checked" disabled="disabled" /> <?php _e( 'Titel', 'bonipress' ); ?></label>
				</div>
				<div class="checkbox">
					<label><input type="checkbox" value="1" checked="checked" disabled="disabled" /> <?php echo $bonipress->core->template_tags_general( __( '%plural% Anforderung', 'bonipress' ) ); ?></label>
				</div>
				<div class="checkbox">
					<label><input type="checkbox" value="1" checked="checked" disabled="disabled" /> <?php _e( 'Rang-Logo', 'bonipress' ); ?></label>
				</div>
				<div class="checkbox">
					<label for="<?php echo $this->field_id( array( 'support' => 'content' ) ); ?>"><input type="checkbox" name="<?php echo $this->field_name( array( 'support' => 'content' ) ); ?>" id="<?php echo $this->field_id( array( 'support' => 'content' ) ); ?>" <?php checked( $prefs['support']['content'], 1 ); ?> value="1" /> <?php _e( 'Content', 'bonipress' ); ?></label>
				</div>
				<div class="checkbox">
					<label for="<?php echo $this->field_id( array( 'support' => 'excerpt' ) ); ?>"><input type="checkbox" name="<?php echo $this->field_name( array( 'support' => 'excerpt' ) ); ?>" id="<?php echo $this->field_id( array( 'support' => 'excerpt' ) ); ?>" <?php checked( $prefs['support']['excerpt'], 1 ); ?> value="1" /> <?php _e( 'Auszug', 'bonipress' ); ?></label>
				</div>
				<div class="checkbox">
					<label for="<?php echo $this->field_id( array( 'support' => 'comments' ) ); ?>"><input type="checkbox" name="<?php echo $this->field_name( array( 'support' => 'comments' ) ); ?>" id="<?php echo $this->field_id( array( 'support' => 'comments' ) ); ?>" <?php checked( $prefs['support']['comments'], 1 ); ?> value="1" /> <?php _e( 'Kommentare', 'bonipress' ); ?></label>
				</div>
				<div class="checkbox">
					<label for="<?php echo $this->field_id( array( 'support' => 'page-attributes' ) ); ?>"><input type="checkbox" name="<?php echo $this->field_name( array( 'support' => 'page-attributes' ) ); ?>" id="<?php echo $this->field_id( array( 'support' => 'page-attributes' ) ); ?>" <?php checked( $prefs['support']['page-attributes'], 1 ); ?> value="1" /> <?php _e( 'Seitenattribute', 'bonipress' ); ?></label>
				</div>
				<div class="checkbox">
					<label for="<?php echo $this->field_id( array( 'support' => 'custom-fields' ) ); ?>"><input type="checkbox" name="<?php echo $this->field_name( array( 'support' => 'custom-fields' ) ); ?>" id="<?php echo $this->field_id( array( 'support' => 'custom-fields' ) ); ?>" <?php checked( $prefs['support']['custom-fields'], 1 ); ?> value="1" /> <?php _e( 'Benutzerdefinierte Felder', 'bonipress' ); ?></label>
				</div>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<h3><?php _e( 'Rang Beitragstyp', 'bonipress' ); ?></h3>
			<div class="form-group">
				<div class="checkbox">
					<label for="<?php echo $this->field_id( 'public' ); ?>"><input type="checkbox" name="<?php echo $this->field_name( 'public' ); ?>" id="<?php echo $this->field_id( 'public' ); ?>" <?php checked( $prefs['public'], 1 ); ?> value="1" /> <?php _e( 'Ränge öffentlich machen', 'bonipress' ); ?></label>
				</div>
			</div>
			<div class="form-group">
				<label class="subheader" for="<?php echo $this->field_id( 'slug' ); ?>"><?php _e( 'Rang SLUG', 'bonipress' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( 'slug' ); ?>" id="<?php echo $this->field_id( 'slug' ); ?>" value="<?php echo esc_attr( $prefs['slug'] ); ?>" class="form-control" />
				<p><span class="description"><?php _e( 'Wenn Du Dich entschieden hast, Ränge öffentlich zu machen, kannst Du auswählen, welchen Rangarchiv-URL-Slug Du verwenden möchtest. Wird ignoriert, wenn Ränge nicht öffentlich sind.', 'bonipress' ); ?></span></p>
			</div>
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'order' ); ?>"><?php _e( 'Anzeigesortierung', 'bonipress' ); ?></label>
				<select name="<?php echo $this->field_name( 'order' ); ?>" id="<?php echo $this->field_id( 'order' ); ?>" class="form-control">
<?php

			// Order added in 1.1.1
			$options = array(
				'ASC'  => __( 'Aufsteigend - Vom niedrigsten zum höchsten Rang', 'bonipress' ),
				'DESC' => __( 'Absteigend - Vom höchsten Rang zum niedrigsten', 'bonipress' )
			);
			foreach ( $options as $option_value => $option_label ) {
				echo '<option value="' . $option_value . '"';
				if ( $prefs['order'] == $option_value ) echo ' selected="selected"';
				echo '>' . $option_label . '</option>';
			}

?>

				</select>
				<p><span class="description"><?php _e( 'Option um festzulegen, in welcher Reihenfolge Ränge auf der Archivseite angezeigt werden sollen.', 'bonipress' ); ?></span></p>
			</div>
		</div>
	</div>

	<?php endif; ?>

	<h3><?php _e( 'Rangverhalten', 'bonipress' ); ?></h3>
	<div class="row">
		<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
			<div class="form-group">
				<div class="radio">
					<label for="<?php echo $this->field_id( array( 'base' => 'manual' ) ); ?>"><input type="radio" name="<?php echo $this->field_name( 'base' ); ?>" id="<?php echo $this->field_id( array( 'base' => 'manual' ) ); ?>"<?php checked( $prefs['base'], 'manual' ); ?> value="manual" /> <?php _e( 'Manueller Modus', 'bonipress' ); ?></label>
				</div>
				<p><span class="description"><?php _e( 'Ränge werden jedem Benutzer manuell zugewiesen.', 'bonipress' ); ?></span></p>
			</div>
		</div>
		<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
			<div class="form-group">
				<div class="radio">
					<label for="<?php echo $this->field_id( array( 'base' => 'current' ) ); ?>"><input type="radio" name="<?php echo $this->field_name( 'base' ); ?>" id="<?php echo $this->field_id( array( 'base' => 'current' ) ); ?>"<?php checked( $prefs['base'], 'current' ); ?> value="current" /> <?php _e( 'Basierend auf aktuellen Salden', 'bonipress' ); ?></label>
				</div>
				<p><span class="description"><?php _e( 'Benutzer können befördert oder herabgestuft werden, je nachdem, wo ihr Guthaben in Deine Reihen passt.', 'bonipress' ); ?></span></p>
			</div>
		</div>
		<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
			<div class="form-group">
				<div class="radio">
					<label for="<?php echo $this->field_id( array( 'base' => 'total' ) ); ?>"><input type="radio" name="<?php echo $this->field_name( 'base' ); ?>" id="<?php echo $this->field_id( array( 'base' => 'total' ) ); ?>"<?php checked( $prefs['base'], 'total' ); ?> value="total" /> <?php _e( 'Basierend auf Gesamtsalden', 'bonipress' ); ?></label>
				</div>
				<p><span class="description"><?php _e( 'Benutzer können nur befördert werden und höhere Ränge erreichen, selbst wenn ihr Guthaben abnimmt.', 'bonipress' ); ?></span></p>
			</div>
		</div>
	</div>

	<div class="row" id="bonipress-rank-based-on-wrapper" style="<?php echo $box; ?>">
		<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
			<h3><?php _e( 'Werkzeuge', 'bonipress' ); ?></h3>
			<p><span class="description"><?php _e( 'Verwende diese Schaltfläche, um das Gesamtguthaben jedes einzelnen Benutzers zu berechnen oder neu zu berechnen, wenn Du der Meinung bist, dass das Gesamtguthaben Deines Benutzers falsch ist, oder wenn Du von Rängen, die auf dem aktuellen Kontostand des Benutzers basieren, zum Gesamtguthaben wechselst.', 'bonipress' ); ?></span></p>
			<p><input type="button" name="bonipress-update-totals" data-type="<?php echo $bonipress->bonipress_type; ?>" id="bonipress-update-totals" value="<?php _e( 'Berechne Summen', 'bonipress' ); ?>" class="button button-large button-<?php if ( $prefs['base'] == 'current' ) echo 'secondary'; else echo 'primary'; ?>"<?php if ( $prefs['base'] == 'current' ) echo ' disabled="disabled"'; ?> /></p>
		</div>
	</div>

	<h3><?php _e( 'Integrationen von Drittanbietern', 'bonipress' ); ?></h3>
	<div class="row">
		<div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'bb_location' ); ?>">BuddyPress</label>
				<?php if ( $buddypress ) : ?>
				<select name="<?php echo $this->field_name( 'bb_location' ); ?>" id="<?php echo $this->field_id( 'bb_location' ); ?>" class="form-control">
<?php

			if ( ! array_key_exists( 'bb_location', $prefs ) )
				$prefs['bb_location'] = '';

			if ( ! array_key_exists( 'bb_template', $prefs ) )
				$prefs['bb_template'] = 'Rang: %rank_title%';

			$rank_locations = array(
				''            => __( 'Nicht anzeigen.', 'bonipress' ),
				'top'         => __( 'In Profilkopfzeile einschließen.', 'bonipress' ),
				'profile_tab' => __( 'Unter der Registerkarte "Profil" einschließen', 'bonipress' ),
				'both'        => __( 'Auf der Registerkarte „Profil“ und im Profil-Header einschließen.', 'bonipress' )
			);

			foreach ( $rank_locations as $value => $label ) {
				echo '<option value="' . $value . '"';
				if ( $prefs['bb_location'] == $value ) echo ' selected="selected"';
				echo '>' . $label . '</option>';
			}

?>

				</select>
			</div>
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'bb_template' ); ?>"><?php _e( 'Vorlage', 'bonipress' ); ?></label>
				<textarea name="<?php echo $this->field_name( 'bb_template' ); ?>" id="<?php echo $this->field_id( 'bb_template' ); ?>" rows="5" cols="50" class="form-control"><?php echo esc_attr( $prefs['bb_template'] ); ?></textarea>
				<p><span class="description"><?php _e( 'Vorlage zum Anzeigen des Rangs eines Benutzers in BuddyPress. Verwende %rank_title% für den Titel und %rank_logo%, um das Ranglogo anzuzeigen. HTML ist erlaubt.', 'bonipress' ); ?></span></p>
				<?php else : ?>
				<input type="hidden" name="<?php echo $this->field_name( 'bb_location' ); ?>" value="" />
				<input type="hidden" name="<?php echo $this->field_name( 'bb_template' ); ?>" value="" />
				<p><span class="description"><?php _e( 'Nicht installiert', 'bonipress' ); ?></span></p>
				<?php endif; ?>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'bp_location' ); ?>">PSForum</label>
				<?php if ( $psforum ) : ?>
				<select name="<?php echo $this->field_name( 'bp_location' ); ?>" id="<?php echo $this->field_id( 'bp_location' ); ?>" class="form-control">
<?php

			if ( ! array_key_exists( 'bp_location', $prefs ) )
				$prefs['bp_location'] = '';

			if ( ! array_key_exists( 'bp_template', $prefs ) )
				$prefs['bp_template'] = 'Rang: %rank_title%';

			$rank_locations = array(
				''        => __( 'Nicht anzeigen.', 'bonipress' ),
				'reply'   => __( 'In Themenantworten aufnehmen', 'bonipress' ),
				'profile' => __( 'In Profil aufnehmen', 'bonipress' ),
				'both'    => __( 'In Themenantworten und Profil aufnehmen', 'bonipress' )
			);

			foreach ( $rank_locations as $value => $label ) {
				echo '<option value="' . $value . '"';
				if ( $prefs['bp_location'] == $value ) echo ' selected="selected"';
				echo '>' . $label . '</option>';
			}

?>

				</select>
			</div>
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'bp_template' ); ?>"><?php _e( 'Vorlage', 'bonipress' ); ?></label>
				<textarea name="<?php echo $this->field_name( 'bp_template' ); ?>" id="<?php echo $this->field_id( 'bp_template' ); ?>" rows="5" cols="50" class="form-control"><?php echo esc_attr( $prefs['bp_template'] ); ?></textarea>
				<p><span class="description"><?php _e( 'Vorlage zum Anzeigen des Rangs eines Benutzers in BuddyPress. Verwende %rank_title% für den Titel und %rank_logo%, um das Ranglogo anzuzeigen. HTML ist erlaubt.', 'bonipress' ); ?></span></p>
				<?php else : ?>
				<input type="hidden" name="<?php echo $this->field_name( 'bp_location' ); ?>" value="" />
				<input type="hidden" name="<?php echo $this->field_name( 'bp_template' ); ?>" value="" />
				<p><span class="description"><?php _e( 'Nicht installiert', 'bonipress' ); ?></span></p>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<h3 style="margin-bottom: 0;"><?php _e( 'Verfügbare Shortcodes', 'bonipress' ); ?></h3>
	<div class="row">
		<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
			<p><a href="https://n3rds.work/docs/bonipress-shortcodes-bonipress_my_rank/" target="_blank">[bonipress_my_rank]</a>, <a href="https://n3rds.work/docs/bonipress-shortcodes-bonipress_my_ranks/" target="_blank">[bonipress_my_ranks]</a>, <a href="https://n3rds.work/docs/bonipress-shortcodes-bonipress_list_ranks/" target="_blank">[bonipress_list_ranks]</a>, <a href="https://n3rds.work/docs/bonipress-shortcodes-bonipress_users_of_all_ranks/" target="_blank">[bonipress_users_of_all_ranks]</a>, <a href="https://n3rds.work/docs/bonipress-shortcodes-bonipress_users_of_rank/" target="_blank">[bonipress_users_of_rank]</a></p>
		</div>
	</div>

<script type="text/javascript">
jQuery(function($){

	var bonipress_calc = function( button, pointtype ) {

		$.ajax({
			type       : "POST",
			data       : {
				action    : 'bonipress-calc-totals',
				token     : '<?php echo wp_create_nonce( 'bonipress-calc-totals' ); ?>',
				ctype     : pointtype
			},
			dataType   : "JSON",
			url        : '<?php echo admin_url( 'admin-ajax.php' ); ?>',
			beforeSend : function() {
				button.attr( 'disabled', 'disabled' ).removeClass( 'button-primary' ).addClass( 'button-seconday' ).val( '<?php echo esc_js( esc_attr__( 'Wird bearbeitet...', 'bonipress' ) ); ?>' );
			},
			success    : function( response ) {
				button.val( response );
			}
		});

	};

	$( 'input[name="<?php echo $this->field_name( 'base' ); ?>"]' ).change(function(){

		var button    = $( '#bonipress-update-totals' );
		var hiddenrow = $( '#bonipress-rank-based-on-wrapper' );
		// Update
		if ( $(this).val() != 'total' ) {
			hiddenrow.hide();
			button.attr( 'disabled', 'disabled' ).removeClass( 'button-primary' ).addClass( 'button-seconday' );
		}
		else {
			hiddenrow.show();
			button.removeAttr( 'disabled' ).removeClass( 'button-seconday' ).addClass( 'button-primary' );
		}

	});

	$( 'input#bonipress-update-totals' ).click(function(){

		bonipress_calc( $(this), $(this).data( 'type' ) );

	});

});
</script>
</div>
<?php

		}

		/**
		 * Save Settings
		 * @since 1.1
		 * @version 1.4
		 */
		public function sanitize_extra_settings( $new_data, $data, $core ) {

			$new_data['rank']['support']['content']         = ( isset( $data['rank']['support']['content'] ) ) ? true : false;
			$new_data['rank']['support']['excerpt']         = ( isset( $data['rank']['support']['excerpt'] ) ) ? true : false;
			$new_data['rank']['support']['comments']        = ( isset( $data['rank']['support']['comments'] ) ) ? true : false;
			$new_data['rank']['support']['page-attributes'] = ( isset( $data['rank']['support']['page-attributes'] ) ) ? true : false;
			$new_data['rank']['support']['custom-fields']   = ( isset( $data['rank']['support']['custom-fields'] ) ) ? true : false;

			$new_data['rank']['base']                       = sanitize_key( $data['rank']['base'] );
			$new_data['rank']['public']                     = ( isset( $data['rank']['public'] ) ) ? true : false;
			$new_data['rank']['slug']                       = ( isset( $data['rank']['slug'] ) ) ? sanitize_title( $data['rank']['slug'] ) : '';
			$new_data['rank']['order']                      = ( isset( $data['rank']['order'] ) ) ? sanitize_text_field( $data['rank']['order'] ) : '';

			$new_data['rank']['bb_location']                = sanitize_text_field( $data['rank']['bb_location'] );
			$new_data['rank']['bb_template']                = wp_kses_post( $data['rank']['bb_template'] );
			$new_data['rank']['bp_location']                = sanitize_text_field( $data['rank']['bp_location'] );
			$new_data['rank']['bp_template']                = wp_kses_post( $data['rank']['bp_template'] );

			return $new_data;

		}

		/**
		 * Management
		 * @since 1.3.2
		 * @version 1.1
		 */
		public function rank_management( $bonipress ) {

			$count         = bonipress_get_published_ranks_count( $bonipress->bonipress_type );
			$reset_block   = false;
			if ( $count == 0 || $count === false )
				$reset_block = true;

			$rank_meta_key = BONIPRESS_RANK_KEY;
			if ( $this->core->is_multisite && $GLOBALS['blog_id'] > 1 && ! $this->core->use_master_template )
				$rank_meta_key .= '_' . $GLOBALS['blog_id'];

			if ( $bonipress->bonipress_type != BONIPRESS_DEFAULT_TYPE_KEY )
				$rank_meta_key .= $bonipress->bonipress_type;

?>
<label class="subheader"><?php _e( 'Ränge', 'bonipress' ); ?></label>
<ol id="boniPRESS-rank-actions" class="inline">
	<li>
		<label><?php _e( 'Benutzer-Metaschlüssel', 'bonipress' ); ?></label>
		<div class="h2"><input type="text" id="bonipress-rank-post-type" disabled="disabled" value="<?php echo $rank_meta_key; ?>" class="readonly" /></div>
	</li>
	<li>
		<label><?php _e( 'Anzahl der Ränge', 'bonipress' ); ?></label>
		<div class="h2"><input type="text" id="bonipress-ranks-no-of-ranks" disabled="disabled" value="<?php echo $count; ?>" class="readonly short" /></div>
	</li>
	<li>
		<label><?php _e( 'Aktionen', 'bonipress' ); ?></label>
		<div class="h2"><input type="button" id="bonipress-manage-action-reset-ranks" data-type="<?php echo $bonipress->bonipress_type; ?>" value="<?php _e( 'Alle Ränge entfernen', 'bonipress' ); ?>" class="button button-large large <?php if ( $reset_block ) echo '" disabled="disabled'; else echo 'button-primary'; ?>" /><?php if ( ! $this->is_manual_mode( $bonipress->bonipress_type ) ) : ?> <input type="button" id="bonipress-manage-action-assign-ranks" data-type="<?php echo $bonipress->bonipress_type; ?>" value="<?php _e( 'Weise Benutzern Ränge zu', 'bonipress' ); ?>" class="button button-large large <?php if ( $reset_block ) echo '" disabled="disabled'; ?>" /></div><?php endif; ?>
	</li>
</ol>
<?php

		}

		/**
		 * Zero Balance Action
		 * When an admin selects to zero out all balances
		 * we want to remove all ranks as well.
		 * @since 1.6
		 * @version 1.1
		 */
		public function zero_balance_action( $point_type = '' ) {

			global $wpdb;

			if ( ! array_key_exists( $point_type, $this->point_types ) )
				$point_type = BONIPRESS_DEFAULT_TYPE_KEY;

			$wpdb->delete(
				$wpdb->usermeta,
				array( 'meta_key' => bonipress_get_meta_key( BONIPRESS_RANK_KEY, ( ( $point_type != BONIPRESS_DEFAULT_TYPE_KEY ) ? $point_type : '' ) ) ),
				array( '%s' )
			);

		}

		/**
		 * Delete Ranks
		 * @since 1.3.2
		 * @version 1.3
		 */
		public function action_delete_ranks() {

			// Security
			check_ajax_referer( 'bonipress-management-actions-roles', 'token' );

			// Define type
			$point_type     = BONIPRESS_DEFAULT_TYPE_KEY;
			if ( isset( $_POST['ctype'] ) && array_key_exists( sanitize_key( $_POST['ctype'] ), $this->point_types ) )
				$point_type = sanitize_key( $_POST['ctype'] );

			global $wpdb;

			// Get the appropriate tables based on setup
			$posts_table    = bonipress_get_db_column( 'posts' );
			$postmeta_table = bonipress_get_db_column( 'postmeta' );

			// First get the ids of all existing ranks
			$rank_key       = BONIPRESS_RANK_KEY;
			$rank_ids       = $wpdb->get_col( $wpdb->prepare( "
				SELECT DISTINCT ranks.ID 
				FROM {$posts_table} ranks 
				INNER JOIN {$postmeta_table} ctype 
					ON ( ranks.ID = ctype.post_id AND ctype.meta_key = 'ctype' )
				WHERE ranks.post_type = '{$rank_key}' 
				AND ctype.meta_value = %s;", $point_type ) );

			// If ranks were found
			$rows           = 0;
			if ( ! empty( $rank_ids ) ) {

				$id_list = implode( ', ', $rank_ids );

				// Remove posts
				$wpdb->query( "
					DELETE FROM {$posts_table} 
					WHERE post_type = '{$rank_key}' 
					AND ID IN ({$id_list});" );

				// Remove post meta
				$wpdb->query( "
					DELETE FROM {$postmeta_table} 
					WHERE post_id IN ({$id_list});" );

				// Confirm that ranks are gone by counting ranks
				// If all went well this should return zero.
				$rows    = $wpdb->get_var( $wpdb->prepare( "
					SELECT COUNT(*) 
					FROM {$posts_table} ranks 
					INNER JOIN {$postmeta_table} ctype 
						ON ( ranks.ID = ctype.post_id AND ctype.meta_key = 'ctype' )
					WHERE ranks.post_type = '{$rank_key}' 
					AND ctype.meta_value = %s;", $point_type ) );
				if ( $rows === NULL ) $rows = 0;

				// Delete users rank meta
				$this->zero_balance_action( $point_type );

			}

			// Delete caches
			wp_cache_delete( 'ranks-published-' . $point_type, BONIPRESS_SLUG );
			wp_cache_delete( 'ranks-published-count-' . $point_type, BONIPRESS_SLUG );

			wp_send_json( array( 'status' => 'OK', 'rows' => $rows ) );

		}

		/**
		 * Assign Ranks
		 * @since 1.3.2
		 * @version 1.3
		 */
		public function action_assign_ranks() {

			check_ajax_referer( 'bonipress-management-actions-roles', 'token' );

			$point_type     = BONIPRESS_DEFAULT_TYPE_KEY;
			if ( isset( $_POST['ctype'] ) && array_key_exists( sanitize_key( $_POST['ctype'] ), $this->point_types ) )
				$point_type = sanitize_key( $_POST['ctype'] );

			$adjustments = bonipress_assign_ranks( $point_type );
			wp_send_json( array( 'status' => 'OK', 'rows' => $adjustments ) );

		}

	}
endif;

/**
 * Load Ranks Module
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_load_ranks_addon' ) ) :
	function bonipress_load_ranks_addon( $modules, $point_types ) {

		$modules['solo']['ranks'] = new boniPRESS_Ranks_Module();
		$modules['solo']['ranks']->load();

		return $modules;

	}
endif;
add_filter( 'bonipress_load_modules', 'bonipress_load_ranks_addon', 80, 2 );
