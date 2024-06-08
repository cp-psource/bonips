<?php
/**
 * Addon: Ranks
 * Addon URI: https://github.com/cp-psource/docs/bonips-erweiterungen-raenge/
 * Version: 1.6
 */
if ( ! defined( 'boniPS_VERSION' ) ) exit;

define( 'boniPS_RANKS',         __FILE__ );
define( 'boniPS_RANKS_DIR',     boniPS_ADDONS_DIR . 'ranks/' );
define( 'boniPS_RANKS_VERSION', '1.6' );

// Rank key
if ( ! defined( 'BONIPS_RANK_KEY' ) )
	define( 'BONIPS_RANK_KEY', 'bonips_rank' );

// Default badge width
if ( ! defined( 'BONIPS_RANK_WIDTH' ) )
	define( 'BONIPS_RANK_WIDTH', 250 );

// Default badge height
if ( ! defined( 'BONIPS_RANK_HEIGHT' ) )
	define( 'BONIPS_RANK_HEIGHT', 250 );

require_once boniPS_RANKS_DIR . 'includes/bonips-rank-functions.php';
require_once boniPS_RANKS_DIR . 'includes/bonips-rank-object.php';
require_once boniPS_RANKS_DIR . 'includes/bonips-rank-shortcodes.php';

/**
 * boniPS_Ranks_Module class
 * While boniPS rankings just ranks users according to users total amount of
 * points, ranks are titles that can be given to users when their reach a certain
 * amount.
 * @since 1.1
 * @version 1.6
 */
if ( ! class_exists( 'boniPS_Ranks_Module' ) ) :
	class boniPS_Ranks_Module extends boniPS_Module {

		/**
		 * Construct
		 */
		public function __construct() {

			parent::__construct( 'boniPS_Ranks_Module', array(
				'module_name' => 'rank',
				'defaults'    => array(
					'manual'      => 0,
					'public'      => 0,
					'base'        => 'current',
					'slug'        => BONIPS_RANK_KEY,
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

			add_action( 'bonips_pre_init',             array( $this, 'module_pre_init' ) );
			add_action( 'bonips_init',                 array( $this, 'module_init' ) );
			add_action( 'bonips_admin_init',           array( $this, 'module_admin_init' ), $this->menu_pos );

		}

		/**
		 * Hook into Init
		 * @since 1.4.4
		 * @version 1.0.1
		 */
		public function module_pre_init() {

			add_filter( 'bonips_has_tags',           array( $this, 'user_tags' ) );
			add_filter( 'bonips_parse_tags_user',    array( $this, 'parse_rank' ), 10, 3 );
			add_filter( 'bonips_post_type_excludes', array( $this, 'exclude_ranks' ) );
			add_filter( 'bonips_add_finished',       array( $this, 'balance_adjustment' ), 20, 3 );
			add_action( 'bonips_zero_balances',      array( $this, 'zero_balance_action' ) );

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

			add_action( 'bonips_set_current_account', array( $this, 'populate_current_account' ) );
			add_action( 'bonips_get_account',         array( $this, 'populate_account' ) );

			add_action( 'pre_get_posts',                            array( $this, 'adjust_wp_query' ), 20 );
			add_action( 'bonips_admin_enqueue',                     array( $this, 'enqueue_scripts' ), $this->menu_pos );

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
			add_shortcode( BONIPS_SLUG . '_my_rank',            'bonips_render_my_rank' );
			add_shortcode( BONIPS_SLUG . '_my_ranks',           'bonips_render_my_ranks' );
			add_shortcode( BONIPS_SLUG . '_users_of_rank',      'bonips_render_users_of_rank' );
			add_shortcode( BONIPS_SLUG . '_users_of_all_ranks', 'bonips_render_users_of_all_ranks' );
			add_shortcode( BONIPS_SLUG . '_list_ranks',         'bonips_render_rank_list' );

			// Admin Management items
			add_action( 'wp_ajax_bonips-calc-totals',               array( $this, 'calculate_totals' ) );

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

			add_action( 'wp_ajax_bonips-action-delete-ranks', array( $this, 'action_delete_ranks' ) );
			add_action( 'wp_ajax_bonips-action-assign-ranks', array( $this, 'action_assign_ranks' ) );

			add_filter( 'bonips_users_balance_column',        array( $this, 'custom_user_column_content' ), 10, 3 );

			add_action( 'bonips_user_edit_after_balance',     array( $this, 'show_rank_in_user_editor' ), 40, 3 );
			add_action( 'personal_options_update',            array( $this, 'save_manual_rank' ), 50 );
			add_action( 'edit_user_profile_update',           array( $this, 'save_manual_rank' ), 50 );

			add_filter( 'manage_' . BONIPS_RANK_KEY . '_posts_columns',       array( $this, 'adjust_column_headers' ), 50 );
			add_action( 'manage_' . BONIPS_RANK_KEY . '_posts_custom_column', array( $this, 'adjust_column_content' ), 10, 2 );
			add_action( 'save_post_' . BONIPS_RANK_KEY,                       array( $this, 'save_rank' ), 10, 2 );

		}

		/**
		 * Is Manual Mode
		 * @since 1.8
		 * @version 1.0
		 */
		public function is_manual_mode( $type_id ) {

			$manual_mode = false;
			$point_type = 'bonips_pref_core';

			if ( $type_id != BONIPS_DEFAULT_TYPE_KEY ) {
				$point_type = 'bonips_pref_core_' . $type_id;
			}

			$setting = bonips_get_option( $point_type );

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

			add_action( 'bonips_management_prefs', array( $this, 'rank_management' ) );
			add_action( 'bonips_after_core_prefs', array( $this, 'after_general_settings' ) );
			add_filter( 'bonips_save_core_prefs',  array( $this, 'sanitize_extra_settings' ), 90, 3 );

			add_action( 'bonips_add_menu',         array( $this, 'add_menus' ), $this->menu_pos );

			if ( count( $this->point_types ) > 1 ) {

				$priority = 10;
				foreach ( $this->point_types as $type_id => $label ) {

					add_action( 'bonips_management_prefs' . $type_id, array( $this, 'rank_management' ), $priority );

					add_action( 'bonips_after_core_prefs' . $type_id, array( $this, 'after_general_settings' ), $priority );
					add_filter( 'bonips_save_core_prefs' . $type_id,  array( $this, 'sanitize_extra_settings' ), $priority, 3 );

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

			if ( isset( $_GET['ctype'] ) && array_key_exists( $_GET['ctype'], $this->point_types ) && isset( $_GET['post_type'] ) && $_GET['post_type'] == BONIPS_RANK_KEY )
				$name = sprintf( __( 'Ränge für %s', 'bonips' ), $this->point_types[ $_GET['ctype'] ] );
			else
				$name = __( 'Ranks', 'bonips' );

			$labels = array(
				'name'                  => $name,
				'singular_name'         => __( 'Rang', 'bonips' ),
				'add_new'               => __( 'Neuer Rang', 'bonips' ),
				'add_new_item'          => __( 'Neuer Rang', 'bonips' ),
				'edit_item'             => __( 'Rang bearbeiten', 'bonips' ),
				'new_item'              => __( 'Neuer Rang', 'bonips' ),
				'all_items'             => __( 'Ränge', 'bonips' ),
				'view_item'             => __( 'Rang ansehen', 'bonips' ),
				'search_items'          => __( 'Ränge suchen', 'bonips' ),
				'featured_image'        => __( 'Rang-Logo', 'bonips' ),
				'set_featured_image'    => __( 'Stelle das Rang-Logo ein', 'bonips' ),
				'remove_featured_image' => __( 'Rang-Logo entfernen', 'bonips' ),
				'use_featured_image'    => __( 'Als Logo verwenden', 'bonips' ),
				'not_found'             => __( 'Keine Ränge gefunden', 'bonips' ),
				'not_found_in_trash'    => __( 'Keine Ränge im Papierkorb gefunden', 'bonips' ), 
				'parent_item_colon'     => '',
				'menu_name'             => __( 'Ränge', 'bonips' )
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

			register_post_type( BONIPS_RANK_KEY, apply_filters( 'bonips_register_ranks', $args, $this ) );

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
				&& ( isset( $bonips_current_account->ranks ) )
			) return;

			$ranks = 0;
			if ( ! empty( $bonips_current_account->balance ) ) {
				foreach ( $bonips_current_account->balance as $type_id => $balance ) {

					if ( $balance === false ) continue;

					$rank = bonips_get_users_rank( $bonips_current_account->user_id, $type_id );
					if ( $rank !== false ) $ranks ++; 

					$bonips_current_account->balance[ $type_id ]->rank = $rank;

				}
			}

			$bonips_current_account->ranks = $ranks;

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
				&& ( isset( $bonips_account->ranks ) )
			) return;

			$ranks = 0;
			if ( ! empty( $bonips_account->balance ) ) {
				foreach ( $bonips_account->balance as $type_id => $balance ) {

					if ( $balance === false ) continue;

					$rank = bonips_get_users_rank( $bonips_account->user_id, $type_id );
					if ( $rank !== false ) $ranks ++; 

					$bonips_account->balance[ $type_id ]->rank = $rank;

				}
			}

			$bonips_account->ranks = $ranks;

		}

		/**
		 * Adjust Post Updated Messages
		 * @since 1.1
		 * @version 1.2
		 */
		public function post_updated_messages( $messages ) {

			$messages[ BONIPS_RANK_KEY ] = array(
				0 => '',
				1 => __( 'Rang aktualisiert.', 'bonips' ),
				2 => __( 'Rang aktualisiert.', 'bonips' ),
				3 => __( 'Rang aktualisiert.', 'bonips' ),
				4 => __( 'Rang aktualisiert.', 'bonips' ),
				5 => __( 'Rang aktualisiert.', 'bonips' ),
				6 => __( 'Rang aktiviert.', 'bonips' ),
				7 => __( 'Rang gespeichert.', 'bonips' ),
				8 => __( 'Rang aktualisiert.', 'bonips' ),
				9 => __( 'Rang aktualisiert.', 'bonips' ),
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

			if ( $path == 'post-new.php?post_type=' . BONIPS_RANK_KEY ) {

				if ( isset( $_GET['ctype'] ) )
					return get_site_url( $blog_id, 'wp-admin/', 'admin' ) . 'post-new.php?post_type=' . BONIPS_RANK_KEY . '&ctype=' . ( ( isset( $_GET['ctype'] ) ) ? $_GET['ctype'] : BONIPS_DEFAULT_TYPE_KEY );

				elseif ( isset( $post->post_type ) && $post->post_type == BONIPS_RANK_KEY && bonips_get_post_meta( $post->ID, 'ctype', true ) != '' )
					return get_site_url( $blog_id, 'wp-admin/', 'admin' ) . 'post-new.php?post_type=' . BONIPS_RANK_KEY . '&ctype=' . bonips_get_post_meta( $post->ID, 'ctype', true );

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
			if ( bonips_override_settings() && ! bonips_is_main_site() ) return;

			$capability = $this->core->get_point_editor_capability();

			foreach ( $this->point_types as $type_id => $label ) {

				$menu_slug = ( $type_id != BONIPS_DEFAULT_TYPE_KEY ) ? BONIPS_SLUG . '_' . $type_id : BONIPS_SLUG;

				add_submenu_page(
					$menu_slug,
					__( 'Ränge', 'bonips' ),
					__( 'Ränge', 'bonips' ),
					$capability,
					'edit.php?post_type=' . BONIPS_RANK_KEY . '&ctype=' . $type_id
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
			if ( ( $pagenow == 'edit.php' || $pagenow == 'post-new.php' ) && isset( $_GET['post_type'] ) && $_GET['post_type'] == BONIPS_RANK_KEY ) {
			
				if ( isset( $_GET['ctype'] ) && sanitize_key( $_GET['ctype'] ) != BONIPS_DEFAULT_TYPE_KEY )
					return BONIPS_SLUG . '_' . sanitize_key( $_GET['ctype'] );

				return BONIPS_SLUG;
			
			}

			// When editing a rank, we need to indicate that we are under the appropriate point type menu
			elseif ( $pagenow == 'post.php' && isset( $_GET['post'] ) && bonips_get_post_type( $_GET['post'] ) == BONIPS_RANK_KEY ) {

				if ( isset( $_GET['ctype'] ) && $_GET['ctype'] != BONIPS_DEFAULT_TYPE_KEY )
					return BONIPS_SLUG . '_' . sanitize_key( $_GET['ctype'] );

				$point_type = bonips_get_post_meta( $_GET['post'], 'ctype', true );
				$point_type = sanitize_key( $point_type );

				if ( $point_type != BONIPS_DEFAULT_TYPE_KEY )
					return BONIPS_SLUG . '_' . $point_type;

				return BONIPS_SLUG;

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
			if ( ( $pagenow == 'edit.php' || $pagenow == 'post-new.php' ) && isset( $_GET['post_type'] ) && $_GET['post_type'] == BONIPS_RANK_KEY ) {

				if ( isset( $_GET['ctype'] ) )
					return 'edit.php?post_type=' . BONIPS_RANK_KEY . '&ctype=' . $_GET['ctype'];

				return 'edit.php?post_type=' . BONIPS_RANK_KEY . '&ctype=' . BONIPS_DEFAULT_TYPE_KEY;
			
			}

			// When editing a rank, we need to highlight the "Ranks" submenu to indicate where we are
			elseif ( $pagenow == 'post.php' && isset( $_GET['post'] ) && bonips_get_post_type( $_GET['post'] ) == BONIPS_RANK_KEY ) {

				if ( isset( $_GET['ctype'] ) )
					return 'edit.php?post_type=' . BONIPS_RANK_KEY . '&ctype=' . $_GET['ctype'];

				$point_type = bonips_get_post_meta( $_GET['post'], 'ctype', true );
				$point_type = sanitize_key( $point_type );

				if ( $point_type != BONIPS_DEFAULT_TYPE_KEY )
					return 'edit.php?post_type=' . BONIPS_RANK_KEY . '&ctype=' . $point_type;

				return 'edit.php?post_type=' . BONIPS_RANK_KEY . '&ctype=' . BONIPS_DEFAULT_TYPE_KEY;

			}

			return $subparent;

		}

		/**
		 * Exclude Ranks from Publish Content Hook
		 * @since 1.3
		 * @version 1.0
		 */
		public function exclude_ranks( $excludes ) {

			$excludes[] = BONIPS_RANK_KEY;
			return $excludes;

		}

		/**
		 * AJAX: Calculate Totals
		 * @since 1.2
		 * @version 1.4
		 */
		public function calculate_totals() {

			// Security
			check_ajax_referer( 'bonips-calc-totals', 'token' );

			$point_type = BONIPS_DEFAULT_TYPE_KEY;
			if ( isset( $_POST['ctype'] ) && bonips_point_type_exists( sanitize_key( $_POST['ctype'] ) ) )
				$point_type = sanitize_key( $_POST['ctype'] );

			global $wpdb;

			// Get all users that have a balance. Excluded users will have no balance
			$users = $wpdb->get_col( $wpdb->prepare( "
				SELECT DISTINCT user_id 
				FROM {$wpdb->usermeta} 
				WHERE meta_key = %s", bonips_get_meta_key( $point_type ) ) );

			$count = 0;
			if ( ! empty( $users ) ) {

				// Get the total for each user with a balance
				foreach ( $users as $user_id ) {

					$total = bonips_calculate_users_total( $user_id, $point_type );
					bonips_update_user_meta( $user_id, $point_type, '_total', $total );
					$count ++;

				}

			}

			wp_send_json( sprintf( __( 'Abgeschlossen - Gesamtzahl von %d betroffenen Benutzern', 'bonips' ), $count ) );

		}

		/**
		 * Balance Adjustment
		 * Check if users rank should change.
		 * @since 1.1
		 * @version 1.6
		 */
		public function balance_adjustment( $result, $request, $bonips ) {

			// If the result was declined
			if ( $result === false ) return $result;

			extract( $request );


			// Manual mode
			if ( $this->is_manual_mode() ) return $result;

			// If ranks for this type is based on total and this is not a admin adjustment
			if ( bonips_rank_based_on_total( $type ) && $amount < 0 && $ref != 'manual' )
				return $result;

			// Find users rank
			$rank = bonips_find_users_rank( $user_id, $type );

			// If users rank changed, save it now.
			if ( isset( $rank->rank_id ) && $rank->rank_id !== $rank->current_id )
				bonips_save_users_rank( $user_id, $rank->rank_id, $type );

			return $result;

		}

		/**
		 * Publishing Content
		 * Check if users rank should change.
		 * @since 1.1
		 * @version 1.6
		 */
		public function post_status_change( $new_status, $old_status, $post ) {

			global $bonips_ranks;

			// Only ranks please
			if ( $post->post_type != BONIPS_RANK_KEY ) return;

			$point_type = bonips_get_post_meta( $post->ID, 'ctype', true );
			if ( $point_type == '' ) {

				$point_type = BONIPS_DEFAULT_TYPE_KEY;
				bonips_update_post_meta( $post->ID, 'ctype', $point_type );

			}

			if ( $this->is_manual_mode( $point_type ) ) return;

			// Publishing or trashing of ranks
			if ( ( $new_status == 'publish' && $old_status != 'publish' ) || ( $new_status == 'trash' && $old_status != 'trash' ) ) {

				wp_cache_delete( 'ranks-published-' . $point_type, BONIPS_SLUG );
				wp_cache_delete( 'ranks-published-count-' . $point_type, BONIPS_SLUG );

				bonips_assign_ranks( $point_type );

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

				if ( $type_id == BONIPS_DEFAULT_TYPE_KEY ) continue;
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

					if ( $type_id == BONIPS_DEFAULT_TYPE_KEY ) {
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

					$rank_id = bonips_get_users_rank_id( $user->ID, $type_id );
					if ( $rank_id === false ) {

						if ( $type_id == BONIPS_DEFAULT_TYPE_KEY ) {
							$content = str_replace( '%rank%',      '', $content );
							$content = str_replace( '%rank_logo%', '', $content );
						}
						else {
							$content = str_replace( '%rank_' . $type_id . '%',      '', $content );
							$content = str_replace( '%rank_logo_' . $type_id . '%', '', $content );
						}

					}
					else {

						if ( $type_id == BONIPS_DEFAULT_TYPE_KEY ) {
							$content = str_replace( '%rank%',      bonips_get_the_title( $rank_id ), $content );
							$content = str_replace( '%rank_logo%', bonips_get_rank_logo( $rank_id ), $content );
						}
						else {
							$content = str_replace( '%rank_' . $type_id . '%',      bonips_get_the_title( $rank_id ), $content );
							$content = str_replace( '%rank_logo_' . $type_id . '%', bonips_get_rank_logo( $rank_id ), $content );
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
			$bonips_types = bonips_get_usable_types( $user_id );

			foreach ( $bonips_types as $type_id ) {

				// Load type
				$bonips     = bonips( $type_id );

				//Nothing to do if we are excluded
				if ( $bonips->exclude_user( $user_id ) ) continue;

				// No settings
				if ( ! isset( $bonips->rank['bb_location'] ) ) continue;

				// Not shown
				if ( ! in_array( $bonips->rank['bb_location'], array( 'top', 'both' ) ) || $bonips->rank['bb_template'] == '' ) continue;

				// Get rank (if user has one)
				$users_rank = bonips_get_users_rank_id( $user_id, $type_id );
				if ( $users_rank === false ) continue;

				// Parse template
				$template   = $bonips->rank['bb_template'];
				$template   = str_replace( '%rank_title%', bonips_get_the_title( $users_rank ), $template );
				$template   = str_replace( '%rank_logo%',  bonips_get_rank_logo( $users_rank, 'full' ), $template );

				$template   = $bonips->template_tags_general( $template );
				$template   = '<div class="bonips-my-rank ' . $type_id . '">' . $template . '</div>';

				// Let others play
				$output    .= apply_filters( 'bonips_bp_header_ranks_row', $template, $user_id, $users_rank, $bonips, $this );

			}

			if ( $output == '' ) return;

			echo '<div id="bonips-my-ranks">' . apply_filters( 'bonips_bp_rank_in_header', $output, $user_id, $this ) . '</div>';

		}

		/**
		 * Insert Rank In Profile Details
		 * @since 1.1
		 * @version 1.4.1
		 */
		public function insert_rank_profile() {

			$output       = '';
			$user_id      = bp_displayed_user_id();
			$bonips_types = bonips_get_usable_types( $user_id );

			$count = 0;
			foreach ( $bonips_types as $type_id ) {

				// Load type
				$bonips     = bonips( $type_id );

				//Nothing to do if we are excluded
				if ( $bonips->exclude_user( $user_id ) ) continue;

				// No settings
				if ( ! isset( $bonips->rank['bb_location'] ) ) continue;

				// Not shown
				if ( ! in_array( $bonips->rank['bb_location'], array( 'profile_tab', 'both' ) ) || $bonips->rank['bb_template'] == '' ) continue;

				// Get rank (if user has one)
				$users_rank = bonips_get_users_rank_id( $user_id, $type_id );
				if ( $users_rank === false ) continue;

				// Parse template
				$template   = $bonips->rank['bb_template'];
				$template   = str_replace( '%rank_title%', bonips_get_the_title( $users_rank ), $template );
				$template   = str_replace( '%rank_logo%',  bonips_get_rank_logo( $users_rank ), $template );

				$template   = $bonips->template_tags_general( $template );
				$template   = '<div class="bonips-my-rank ' . $type_id . '">' . $template . '</div>';

				// Let others play
				$output    .= apply_filters( 'bonips_bp_profile_ranks_row', $template, $user_id, $users_rank, $bonips, $this );
				$count ++;

			}

			if ( $output == '' ) return;

?>
<div class="bp-widget bonips-field">
	<table class="profile-fields">
		<tr id="bonips-users-rank">
			<td class="label"><?php if ( $count == 1 ) _e( 'Rang', 'bonips' ); else _e( 'Ränge', 'bonips' ); ?></td>
			<td class="data">
				<?php echo apply_filters( 'bonips_bp_rank_in_profile', $output, $user_id, $this ); ?>

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

			$bonips_types = bonips_get_usable_types( $user_id );

			foreach ( $bonips_types as $type_id ) {

				// Load type
				$bonips     = bonips( $type_id );

				// No settings
				if ( ! isset( $bonips->rank['bp_location'] ) ) continue;

				//Nothing to do if we are excluded
				if ( $bonips->exclude_user( $user_id ) ) continue;

				// Not shown
				if ( ! in_array( $bonips->rank['bp_location'], array( 'reply', 'both' ) ) || $bonips->rank['bp_template'] == '' ) continue;

				// Get rank (if user has one
				$users_rank = bonips_get_users_rank_id( $user_id, $type_id );
				if ( $users_rank === false ) continue;

				// Parse template
				$template   = $bonips->rank['bp_template'];
				$template   = str_replace( '%rank_title%', bonips_get_the_title( $users_rank ), $template );
				$template   = str_replace( '%rank_logo%',  bonips_get_rank_logo( $users_rank ), $template );

				$template   = $bonips->template_tags_general( $template );
				$template   = '<div class="bonips-my-rank ' . $type_id . '">' . $template . '</div>';

				// Let others play
				$output    .= apply_filters( 'bonips_bb_reply_ranks_row', $template, $user_id, $users_rank, $bonips, $this );

			}

			if ( $output == '' ) return;

			echo '<div id="bonips-my-ranks">' . apply_filters( 'bonips_bb_rank_in_reply', $output, $user_id, $this ) . '</div>';

		}

		/**
		 * Insert Rank In PSForum Profile
		 * @since 1.6
		 * @version 1.0.1
		 */
		public function insert_rank_bb_profile() {

			$output       = '';
			$user_id      = psf_get_displayed_user_id();
			$bonips_types = bonips_get_usable_types( $user_id );

			foreach ( $bonips_types as $type_id => $label ) {

				// Load type
				$bonips     = bonips( $type_id );

				// No settings
				if ( ! isset( $bonips->rank['bp_location'] ) ) continue;

				//Nothing to do if we are excluded
				if ( $bonips->exclude_user( $user_id ) ) continue;

				// Not shown
				if ( ! in_array( $bonips->rank['bp_location'], array( 'profile', 'both' ) ) || $bonips->rank['bp_template'] == '' ) continue;

				// Get rank (if user has one
				$users_rank = bonips_get_users_rank_id( $user_id, $type_id );
				if ( $users_rank === false ) continue;

				// Parse template
				$template   = $bonips->rank['bp_template'];
				$template   = str_replace( '%rank_title%', bonips_get_the_title( $users_rank ), $template );
				$template   = str_replace( '%rank_logo%',  bonips_get_rank_logo( $users_rank ), $template );

				$template   = $bonips->template_tags_general( $template );
				$template   = '<div class="bonips-my-rank ' . $type_id . '">' . $template . '</div>';

				// Let others play
				$output    .= apply_filters( 'bonips_bb_profile_ranks_row', $template, $user_id, $users_rank, $bonips, $this );

			}

			if ( $output == '' ) return;

			echo '<div id="bonips-my-ranks">' . apply_filters( 'bonips_bb_rank_in_profile', $output, $user_id, $this ) . '</div>';

		}

		/**
		 * Add Default Rank
		 * Adds the default "Newbie" rank and adds all non-exluded user to this rank.
		 * Note! This method is only called when there are zero ranks as this will create the new default rank.
		 * @since 1.1
		 * @version 1.2
		 */
		public function add_default_rank() {

			global $bonips_ranks;

			// If there are no ranks at all
			if ( ! bonips_have_ranks() ) {

				// Construct a new post
				$rank                = array();
				$rank['post_title']  = 'Newbie';
				$rank['post_type']   = BONIPS_RANK_KEY;
				$rank['post_status'] = 'publish';

				// Insert new rank post
				$rank_id = wp_insert_post( $rank );

				// Update min and max values
				bonips_update_post_meta( $rank_id, 'bonips_rank_min', 0 );
				bonips_update_post_meta( $rank_id, 'bonips_rank_max', 9999999 );
				bonips_update_post_meta( $rank_id, 'ctype',           BONIPS_DEFAULT_TYPE_KEY );

				$bonips_ranks = 1;
				bonips_assign_ranks();

			}

		}

		/**
		 * Custom User Balance Content
		 * Inserts a users rank for each point type.
		 * @since 1.6
		 * @version 1.1
		 */
		public function custom_user_column_content( $balance, $user_id, $type ) {

			$rank = bonips_get_users_rank( $user_id, $type );
			if ( $rank !== false )
				$balance .= '<small style="display:block;">' . sprintf( '<strong>%s:</strong> %s', __( 'Rang', 'bonips' ), $rank->title ) . '</small>';

			else
				$balance .= '<small style="display:block;">' . sprintf( '<strong>%s:</strong> -', __( 'Rang', 'bonips' ) ) . '</small>';

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

			if ( ! bonips_have_ranks( $point_type ) ) {
				echo '<div class="balance-desc current-rank"><em>' . __( 'Es gibt keine Ränge.', 'bonips' ) . '</em></div>';
				return;
			}

			$users_rank = bonips_get_users_rank( $user->ID, $point_type );
			$rank_title = '-';
			if ( isset( $users_rank->title ) )
				$rank_title = $users_rank->title;

			// In manual mode we want to show a dropdown menu so an admin can adjust a users rank
			if ( $this->is_manual_mode( $point_type ) && bonips_is_admin( NULL, $point_type ) ) {

				$ranks = bonips_get_ranks( 'publish', '-1', 'DESC', $point_type );
				echo '<div class="balance-desc current-rank"><select name="rank-' . $point_type . '" id="bonips-rank">';

				echo '<option value=""';
				if ( $users_rank === false )
					echo ' selected="selected"';
				echo '>' . __( 'Kein Rang', 'bonips' ) . '</option>';

				foreach ( $ranks as $rank ) {
					echo '<option value="' . $rank->post_id . '"';
					if ( ! empty( $users_rank ) && $users_rank->post_id == $rank->post_id ) echo ' selected="selected"';
					echo '>' . $rank->title . '</option>';
				}

				echo '</select></div>';

			}
			else {

				echo '<div class="balance-desc current-rank">' . sprintf( '<strong>%s:</strong> %s', __( 'Rang', 'bonips' ), $rank_title ) . '</div>';

			}

		}

		/**
		 * Save Users Rank
		 * @since 1.8
		 * @version 1.0
		 */
		public function save_manual_rank( $user_id ) {

			$point_types = bonips_get_types();
			foreach ( $point_types as $type_key => $label ) {

				if ( $this->is_manual_mode( $type_key ) ) {

					if ( isset( $_POST[ 'rank-' . $type_key ] ) && bonips_is_admin( NULL, $type_key ) ) {

						// Get users current rank for comparison
						$users_rank = bonips_get_users_rank( $user_id, $type_key );

						$rank       = false;

						if ( $_POST[ 'rank-' . $type_key ] != '' )
							$rank = absint( $_POST[ 'rank-' . $type_key ] );

						// Save users rank if a valid rank id is provided and it differs from the users current one
						if ( $rank !== false && $rank > 0 && $users_rank->rank_id != $rank )
							bonips_save_users_rank( $user_id, $rank, $type_key );

						// Delete users rank
						elseif ( $rank === false ) {

							$end     = '';
							if ( $type_key != BONIPS_DEFAULT_TYPE_KEY )
								$end = $type_key;

							bonips_delete_user_meta( $user_id, BONIPS_RANK_KEY, $end );

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
				'bonips-rank-tweaks',
				plugins_url( 'assets/js/tweaks.js', boniPS_RANKS ),
				array( 'jquery' ),
				boniPS_VERSION . '.1'
			);

			wp_register_script(
				'bonips-rank-management',
				plugins_url( 'assets/js/management.js', boniPS_RANKS ),
				array( 'jquery' ),
				boniPS_VERSION . '.1'
			);

			// Ranks List Page
			if ( strpos( 'edit-' . BONIPS_RANK_KEY, $screen->id ) > -1 ) {

				wp_enqueue_style( 'bonips-admin' );

				if ( isset( $_GET['ctype'] ) && array_key_exists( $_GET['ctype'], $this->point_types ) ) :

					wp_localize_script(
						'bonips-rank-tweaks',
						'boniPS_Ranks',
						array(
							'rank_ctype' => $_GET['ctype']
						)
					);
					wp_enqueue_script( 'bonips-rank-tweaks' );

				endif;

			}

			// Edit Rank Page
			if ( strpos( BONIPS_RANK_KEY, $screen->id ) > -1 ) {

				wp_dequeue_script( 'autosave' );
				wp_enqueue_style( 'bonips-bootstrap-grid' );
				wp_enqueue_style( 'bonips-forms' );

				add_filter( 'postbox_classes_' . BONIPS_RANK_KEY . '_bonips-rank-setup', array( $this, 'metabox_classes' ) );

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
					'bonips-rank-management',
					'boniPS_Ranks',
					array(
						'ajaxurl'        => admin_url( 'admin-ajax.php' ),
						'token'          => wp_create_nonce( 'bonips-management-actions-roles' ),
						'working'        => esc_attr__( 'Wird bearbeitet...', 'bonips' ),
						'confirm_del'    => esc_attr__( 'Warnung! Alle Ränge werden gelöscht! Das kann nicht rückgängig gemacht werden!', 'bonips' ),
						'confirm_assign' => esc_attr__( 'Möchtest Du Benutzerränge wirklich neu zuweisen?', 'bonips' )
					)
				);
				wp_enqueue_script( 'bonips-rank-management' );

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
				if ( ! is_post_type_archive( BONIPS_RANK_KEY ) || ! $query->is_main_query() ) return;

				// By default we want to only show ranks for the main point type
				if ( ! isset( $_GET['ctype'] ) ) {
					$query->set( 'meta_query', array(
						array(
							'key'     => 'ctype',
							'value'   => BONIPS_DEFAULT_TYPE_KEY,
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
				if ( ! isset( $query->query['post_type'] ) || $query->query['post_type'] != BONIPS_RANK_KEY ) return;

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
			$query->set( 'meta_key', 'bonips_rank_min' );
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
			$columns['title']             = __( 'Rangtitel', 'bonips' );
			$columns['bonips-rank-logo']  = __( 'Logo', 'bonips' );
			$columns['bonips-rank-req']   = __( 'Erforderlich', 'bonips' );
			$columns['bonips-rank-users'] = __( 'Benutzer', 'bonips' );

			if ( count( $this->point_types ) > 1 )
				$columns['bonips-rank-type']  = __( 'Punkttyp', 'bonips' );

			// Return
			return $columns;

		}

		/**
		 * Adjust Rank Column Content
		 * @since 1.1
		 * @version 1.1
		 */
		public function adjust_column_content( $column_name, $post_id ) {

			$type = bonips_get_post_meta( $post_id, 'ctype', true );
			if ( $type == '' )
				$type = BONIPS_DEFAULT_TYPE_KEY;

			// Rank Logo (thumbnail)
			if ( $column_name == 'bonips-rank-logo' ) {
				$logo = bonips_get_rank_logo( $post_id, 'thumbnail' );
				if ( empty( $logo ) )
					echo __( 'Kein Logo gesetzt', 'bonips' );
				else
					echo $logo;

			}

			// Rank Requirement (custom metabox)
			elseif ( $column_name == 'bonips-rank-req' ) {

				$bonips = $this->core;
				if ( $type != BONIPS_DEFAULT_TYPE_KEY )
					$bonips = bonips( $type );

				$min = bonips_get_post_meta( $post_id, 'bonips_rank_min', true );
				if ( empty( $min ) && (int) $min !== 0 )
					$min = __( 'Any Value', 'bonips' );

				$min = $bonips->template_tags_general( __( 'Minimum %plural%', 'bonips' ) ) . ': ' . $min;
				$max = bonips_get_post_meta( $post_id, 'bonips_rank_max', true );
				if ( empty( $max ) )
					$max = __( 'Any Value', 'bonips' );

				$max = $bonips->template_tags_general( __( 'Maximum %plural%', 'bonips' ) ) . ': ' . $max;
				echo $min . '<br />' . $max;

			}

			// Rank Users (user list)
			elseif ( $column_name == 'bonips-rank-users' ) {

				echo bonips_count_users_with_rank( $post_id );

			}

			// Rank Point Type
			if ( $column_name == 'bonips-rank-type' ) {

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

			if ( $post->post_type == BONIPS_RANK_KEY ) {
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
			if ( $post_type == BONIPS_RANK_KEY )
				return __( 'Rangtitel', 'bonips' );

			return $title;

		}

		/**
		 * Add Meta Boxes
		 * @since 1.1
		 * @version 1.0
		 */
		public function add_metaboxes() {

			add_meta_box(
				'bonips-rank-setup',
				__( 'Rang einrichten', 'bonips' ),
				array( $this, 'rank_settings' ),
				BONIPS_RANK_KEY,
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
			$type = bonips_get_post_meta( $post->ID, 'ctype', true );
			if ( $type == '' ) {
				$type = BONIPS_DEFAULT_TYPE_KEY;
				bonips_update_post_meta( $post->ID, 'ctype', $type );
			}

			// If a custom type has been requested via the URL
			if ( isset( $_REQUEST['ctype'] ) && ! empty( $_REQUEST['ctype'] ) )
				$type = sanitize_key( $_REQUEST['ctype'] );

			// Load the appropriate type object
			$bonips = $this->core;
			if ( $type != BONIPS_DEFAULT_TYPE_KEY )
				$bonips = bonips( $type );

			$rank = bonips_get_rank( $post->ID );

?>
<div class="form">
	<div class="row">
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="row">
				<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
					<div class="form-group">
						<label for="bonips-rank-min"><?php _e( 'Minimale Guthabenanforderung', 'bonips' ); ?></label>
						<input type="text" name="bonips_rank[bonips_rank_min]" id="bonips-rank-min" class="form-control" value="<?php echo esc_attr( $rank->minimum ); ?>" />
					</div>
				</div>
				<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
					<div class="form-group">
						<label for="bonips-rank-max"><?php _e( 'Maximale Guthabenanforderung', 'bonips' ); ?></label>
						<input type="text" name="bonips_rank[bonips_rank_max]" id="bonips-rank-max" class="form-control" value="<?php echo esc_attr( $rank->maximum ); ?>" />
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">

					<?php if ( count( $this->point_types ) > 1 ) : ?>

					<div class="form-group">
						<label for="bonips-rank-point-type"><?php _e( 'Punkttyp', 'bonips' ); ?></label>
						<?php bonips_types_select_from_dropdown( 'bonips_rank[ctype]', 'bonips-rank-point-type', $type, false, '  class="form-control"' ); ?>
					</div>

					<?php else : ?>

					<div class="form-group">
						<label for="bonips-rank-point-type"><?php _e( 'Punkttyp', 'bonips' ); ?></label>
						<p class="form-control-static"><?php echo $bonips->plural(); ?></p>
						<input type="hidden" name="bonips_rank[ctype]" value="<?php echo BONIPS_DEFAULT_TYPE_KEY; ?>" />
					</div>

					<?php endif; ?>

				</div>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
<?php

			// Get all published ranks for this type
			$all_ranks = bonips_get_ranks( 'publish', '-1', 'DESC', $type );

			if ( ! empty( $all_ranks ) ) {

				echo '<ul>';
				foreach ( $all_ranks as $rank ) {

					if ( $rank->minimum == '' ) $rank->minimum = __( 'Nicht festgelegt', 'bonips' );
					if ( $rank->maximum == '' ) $rank->maximum = __( 'Nicht festgelegt', 'bonips' );

					echo '<li><strong>' . $rank->title . '</strong> ' . $rank->minimum . ' - ' . $rank->maximum . '</li>';

				}
				echo '</ul>';

			}
			else {

				echo '<p>' . __( 'Keine Ränge gefunden', 'bonips' ) . '.</p>';

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
			if ( $post === NULL || ! $this->core->user_is_point_editor() || ! isset( $_POST['bonips_rank'] ) ) return;

			$point_type = sanitize_key( $_POST['bonips_rank']['ctype'] );
			if ( ! array_key_exists( $point_type, $this->point_types ) )
				$point_type = BONIPS_DEFAULT_TYPE_KEY;

			bonips_update_post_meta( $post_id, 'ctype', $point_type );

			$type_object = new boniPS_Point_Type( $point_type );

			foreach ( $_POST['bonips_rank'] as $meta_key => $meta_value ) {

				if ( $meta_key == 'ctype' ) continue;

				$new_value = sanitize_text_field( $meta_value );
				$new_value = $type_object->number( $new_value );

				bonips_update_post_meta( $post_id, $meta_key, $new_value );

			}

			// Delete caches
			wp_cache_delete( 'ranks-published-' . $point_type, BONIPS_SLUG );
			wp_cache_delete( 'ranks-published-count-' . $point_type, BONIPS_SLUG );

			if ( ! $this->is_manual_mode() )
				bonips_assign_ranks( $point_type );

		}

		/**
		 * Add to General Settings
		 * @since 1.1
		 * @version 1.5
		 */
		public function after_general_settings( $bonips = NULL ) {

			$prefs             = $this->rank;
			$this->add_to_core = true;
			if ( $bonips->bonips_type != BONIPS_DEFAULT_TYPE_KEY ) {

				if ( ! isset( $bonips->rank ) )
					$prefs = $this->default_prefs;
				else
					$prefs = $bonips->rank;

				$this->option_id = $bonips->option_id;

			}

			$buddypress        = ( ( class_exists( 'BuddyPress' ) ) ? true : false ); 
			$psforum           = ( ( class_exists( 'PSForum' ) ) ? true : false ); 

			$box               = ( ( $prefs['base'] == 'current' ) ? 'display: none;' : 'display: block;' );

?>
<h4><span class="dashicons dashicons-admin-plugins static"></span><?php _e( 'Ränge', 'bonips' ); ?></h4>
<div class="body" style="display:none;">

	<?php if ( $bonips->bonips_type == BONIPS_DEFAULT_TYPE_KEY ) : ?>

	<div class="row">
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<h3><?php _e( 'Rangfunktionen', 'bonips' ); ?></h3>
			<div class="form-group">
				<div class="checkbox">
					<label><input type="checkbox" value="1" checked="checked" disabled="disabled" /> <?php _e( 'Titel', 'bonips' ); ?></label>
				</div>
				<div class="checkbox">
					<label><input type="checkbox" value="1" checked="checked" disabled="disabled" /> <?php echo $bonips->core->template_tags_general( __( '%plural% Anforderung', 'bonips' ) ); ?></label>
				</div>
				<div class="checkbox">
					<label><input type="checkbox" value="1" checked="checked" disabled="disabled" /> <?php _e( 'Rang-Logo', 'bonips' ); ?></label>
				</div>
				<div class="checkbox">
					<label for="<?php echo $this->field_id( array( 'support' => 'content' ) ); ?>"><input type="checkbox" name="<?php echo $this->field_name( array( 'support' => 'content' ) ); ?>" id="<?php echo $this->field_id( array( 'support' => 'content' ) ); ?>" <?php checked( $prefs['support']['content'], 1 ); ?> value="1" /> <?php _e( 'Content', 'bonips' ); ?></label>
				</div>
				<div class="checkbox">
					<label for="<?php echo $this->field_id( array( 'support' => 'excerpt' ) ); ?>"><input type="checkbox" name="<?php echo $this->field_name( array( 'support' => 'excerpt' ) ); ?>" id="<?php echo $this->field_id( array( 'support' => 'excerpt' ) ); ?>" <?php checked( $prefs['support']['excerpt'], 1 ); ?> value="1" /> <?php _e( 'Auszug', 'bonips' ); ?></label>
				</div>
				<div class="checkbox">
					<label for="<?php echo $this->field_id( array( 'support' => 'comments' ) ); ?>"><input type="checkbox" name="<?php echo $this->field_name( array( 'support' => 'comments' ) ); ?>" id="<?php echo $this->field_id( array( 'support' => 'comments' ) ); ?>" <?php checked( $prefs['support']['comments'], 1 ); ?> value="1" /> <?php _e( 'Kommentare', 'bonips' ); ?></label>
				</div>
				<div class="checkbox">
					<label for="<?php echo $this->field_id( array( 'support' => 'page-attributes' ) ); ?>"><input type="checkbox" name="<?php echo $this->field_name( array( 'support' => 'page-attributes' ) ); ?>" id="<?php echo $this->field_id( array( 'support' => 'page-attributes' ) ); ?>" <?php checked( $prefs['support']['page-attributes'], 1 ); ?> value="1" /> <?php _e( 'Seitenattribute', 'bonips' ); ?></label>
				</div>
				<div class="checkbox">
					<label for="<?php echo $this->field_id( array( 'support' => 'custom-fields' ) ); ?>"><input type="checkbox" name="<?php echo $this->field_name( array( 'support' => 'custom-fields' ) ); ?>" id="<?php echo $this->field_id( array( 'support' => 'custom-fields' ) ); ?>" <?php checked( $prefs['support']['custom-fields'], 1 ); ?> value="1" /> <?php _e( 'Benutzerdefinierte Felder', 'bonips' ); ?></label>
				</div>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<h3><?php _e( 'Rang Beitragstyp', 'bonips' ); ?></h3>
			<div class="form-group">
				<div class="checkbox">
					<label for="<?php echo $this->field_id( 'public' ); ?>"><input type="checkbox" name="<?php echo $this->field_name( 'public' ); ?>" id="<?php echo $this->field_id( 'public' ); ?>" <?php checked( $prefs['public'], 1 ); ?> value="1" /> <?php _e( 'Ränge öffentlich machen', 'bonips' ); ?></label>
				</div>
			</div>
			<div class="form-group">
				<label class="subheader" for="<?php echo $this->field_id( 'slug' ); ?>"><?php _e( 'Rang SLUG', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( 'slug' ); ?>" id="<?php echo $this->field_id( 'slug' ); ?>" value="<?php echo esc_attr( $prefs['slug'] ); ?>" class="form-control" />
				<p><span class="description"><?php _e( 'Wenn Du Dich entschieden hast, Ränge öffentlich zu machen, kannst Du auswählen, welchen Rangarchiv-URL-Slug Du verwenden möchtest. Wird ignoriert, wenn Ränge nicht öffentlich sind.', 'bonips' ); ?></span></p>
			</div>
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'order' ); ?>"><?php _e( 'Anzeigesortierung', 'bonips' ); ?></label>
				<select name="<?php echo $this->field_name( 'order' ); ?>" id="<?php echo $this->field_id( 'order' ); ?>" class="form-control">
<?php

			// Order added in 1.1.1
			$options = array(
				'ASC'  => __( 'Aufsteigend - Vom niedrigsten zum höchsten Rang', 'bonips' ),
				'DESC' => __( 'Absteigend - Vom höchsten Rang zum niedrigsten', 'bonips' )
			);
			foreach ( $options as $option_value => $option_label ) {
				echo '<option value="' . $option_value . '"';
				if ( $prefs['order'] == $option_value ) echo ' selected="selected"';
				echo '>' . $option_label . '</option>';
			}

?>

				</select>
				<p><span class="description"><?php _e( 'Option um festzulegen, in welcher Reihenfolge Ränge auf der Archivseite angezeigt werden sollen.', 'bonips' ); ?></span></p>
			</div>
		</div>
	</div>

	<?php endif; ?>

	<h3><?php _e( 'Rangverhalten', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
			<div class="form-group">
				<div class="radio">
					<label for="<?php echo $this->field_id( array( 'base' => 'manual' ) ); ?>"><input type="radio" name="<?php echo $this->field_name( 'base' ); ?>" id="<?php echo $this->field_id( array( 'base' => 'manual' ) ); ?>"<?php checked( $prefs['base'], 'manual' ); ?> value="manual" /> <?php _e( 'Manueller Modus', 'bonips' ); ?></label>
				</div>
				<p><span class="description"><?php _e( 'Ränge werden jedem Benutzer manuell zugewiesen.', 'bonips' ); ?></span></p>
			</div>
		</div>
		<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
			<div class="form-group">
				<div class="radio">
					<label for="<?php echo $this->field_id( array( 'base' => 'current' ) ); ?>"><input type="radio" name="<?php echo $this->field_name( 'base' ); ?>" id="<?php echo $this->field_id( array( 'base' => 'current' ) ); ?>"<?php checked( $prefs['base'], 'current' ); ?> value="current" /> <?php _e( 'Basierend auf aktuellen Salden', 'bonips' ); ?></label>
				</div>
				<p><span class="description"><?php _e( 'Benutzer können befördert oder herabgestuft werden, je nachdem, wo ihr Guthaben in Deine Reihen passt.', 'bonips' ); ?></span></p>
			</div>
		</div>
		<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
			<div class="form-group">
				<div class="radio">
					<label for="<?php echo $this->field_id( array( 'base' => 'total' ) ); ?>"><input type="radio" name="<?php echo $this->field_name( 'base' ); ?>" id="<?php echo $this->field_id( array( 'base' => 'total' ) ); ?>"<?php checked( $prefs['base'], 'total' ); ?> value="total" /> <?php _e( 'Basierend auf Gesamtsalden', 'bonips' ); ?></label>
				</div>
				<p><span class="description"><?php _e( 'Benutzer können nur befördert werden und höhere Ränge erreichen, selbst wenn ihr Guthaben abnimmt.', 'bonips' ); ?></span></p>
			</div>
		</div>
	</div>

	<div class="row" id="bonips-rank-based-on-wrapper" style="<?php echo $box; ?>">
		<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
			<h3><?php _e( 'Werkzeuge', 'bonips' ); ?></h3>
			<p><span class="description"><?php _e( 'Verwende diese Schaltfläche, um das Gesamtguthaben jedes einzelnen Benutzers zu berechnen oder neu zu berechnen, wenn Du der Meinung bist, dass das Gesamtguthaben Deines Benutzers falsch ist, oder wenn Du von Rängen, die auf dem aktuellen Kontostand des Benutzers basieren, zum Gesamtguthaben wechselst.', 'bonips' ); ?></span></p>
			<p><input type="button" name="bonips-update-totals" data-type="<?php echo $bonips->bonips_type; ?>" id="bonips-update-totals" value="<?php _e( 'Berechne Summen', 'bonips' ); ?>" class="button button-large button-<?php if ( $prefs['base'] == 'current' ) echo 'secondary'; else echo 'primary'; ?>"<?php if ( $prefs['base'] == 'current' ) echo ' disabled="disabled"'; ?> /></p>
		</div>
	</div>

	<h3><?php _e( 'Integrationen von Drittanbietern', 'bonips' ); ?></h3>
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
				''            => __( 'Nicht anzeigen.', 'bonips' ),
				'top'         => __( 'In Profilkopfzeile einschließen.', 'bonips' ),
				'profile_tab' => __( 'Unter der Registerkarte "Profil" einschließen', 'bonips' ),
				'both'        => __( 'Auf der Registerkarte „Profil“ und im Profil-Header einschließen.', 'bonips' )
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
				<label for="<?php echo $this->field_id( 'bb_template' ); ?>"><?php _e( 'Vorlage', 'bonips' ); ?></label>
				<textarea name="<?php echo $this->field_name( 'bb_template' ); ?>" id="<?php echo $this->field_id( 'bb_template' ); ?>" rows="5" cols="50" class="form-control"><?php echo esc_attr( $prefs['bb_template'] ); ?></textarea>
				<p><span class="description"><?php _e( 'Vorlage zum Anzeigen des Rangs eines Benutzers in BuddyPress. Verwende %rank_title% für den Titel und %rank_logo%, um das Ranglogo anzuzeigen. HTML ist erlaubt.', 'bonips' ); ?></span></p>
				<?php else : ?>
				<input type="hidden" name="<?php echo $this->field_name( 'bb_location' ); ?>" value="" />
				<input type="hidden" name="<?php echo $this->field_name( 'bb_template' ); ?>" value="" />
				<p><span class="description"><?php _e( 'Nicht installiert', 'bonips' ); ?></span></p>
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
				''        => __( 'Nicht anzeigen.', 'bonips' ),
				'reply'   => __( 'In Themenantworten aufnehmen', 'bonips' ),
				'profile' => __( 'In Profil aufnehmen', 'bonips' ),
				'both'    => __( 'In Themenantworten und Profil aufnehmen', 'bonips' )
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
				<label for="<?php echo $this->field_id( 'bp_template' ); ?>"><?php _e( 'Vorlage', 'bonips' ); ?></label>
				<textarea name="<?php echo $this->field_name( 'bp_template' ); ?>" id="<?php echo $this->field_id( 'bp_template' ); ?>" rows="5" cols="50" class="form-control"><?php echo esc_attr( $prefs['bp_template'] ); ?></textarea>
				<p><span class="description"><?php _e( 'Vorlage zum Anzeigen des Rangs eines Benutzers in BuddyPress. Verwende %rank_title% für den Titel und %rank_logo%, um das Ranglogo anzuzeigen. HTML ist erlaubt.', 'bonips' ); ?></span></p>
				<?php else : ?>
				<input type="hidden" name="<?php echo $this->field_name( 'bp_location' ); ?>" value="" />
				<input type="hidden" name="<?php echo $this->field_name( 'bp_template' ); ?>" value="" />
				<p><span class="description"><?php _e( 'Nicht installiert', 'bonips' ); ?></span></p>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<h3 style="margin-bottom: 0;"><?php _e( 'Verfügbare Shortcodes', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
			<p><a href="https://github.com/cp-psource/docs/bonips-shortcodes-bonips_my_rank/" target="_blank">[bonips_my_rank]</a>, <a href="https://github.com/cp-psource/docs/bonips-shortcodes-bonips_my_ranks/" target="_blank">[bonips_my_ranks]</a>, <a href="https://github.com/cp-psource/docs/bonips-shortcodes-bonips_list_ranks/" target="_blank">[bonips_list_ranks]</a>, <a href="https://github.com/cp-psource/docs/bonips-shortcodes-bonips_users_of_all_ranks/" target="_blank">[bonips_users_of_all_ranks]</a>, <a href="https://github.com/cp-psource/docs/bonips-shortcodes-bonips_users_of_rank/" target="_blank">[bonips_users_of_rank]</a></p>
		</div>
	</div>

<script type="text/javascript">
jQuery(function($){

	var bonips_calc = function( button, pointtype ) {

		$.ajax({
			type       : "POST",
			data       : {
				action    : 'bonips-calc-totals',
				token     : '<?php echo wp_create_nonce( 'bonips-calc-totals' ); ?>',
				ctype     : pointtype
			},
			dataType   : "JSON",
			url        : '<?php echo admin_url( 'admin-ajax.php' ); ?>',
			beforeSend : function() {
				button.attr( 'disabled', 'disabled' ).removeClass( 'button-primary' ).addClass( 'button-seconday' ).val( '<?php echo esc_js( esc_attr__( 'Wird bearbeitet...', 'bonips' ) ); ?>' );
			},
			success    : function( response ) {
				button.val( response );
			}
		});

	};

	$( 'input[name="<?php echo $this->field_name( 'base' ); ?>"]' ).change(function(){

		var button    = $( '#bonips-update-totals' );
		var hiddenrow = $( '#bonips-rank-based-on-wrapper' );
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

	$( 'input#bonips-update-totals' ).on('click', function(){

		bonips_calc( $(this), $(this).data( 'type' ) );

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
		public function rank_management( $bonips ) {

			$count         = bonips_get_published_ranks_count( $bonips->bonips_type );
			$reset_block   = false;
			if ( $count == 0 || $count === false )
				$reset_block = true;

			$rank_meta_key = BONIPS_RANK_KEY;
			if ( $this->core->is_multisite && $GLOBALS['blog_id'] > 1 && ! $this->core->use_master_template )
				$rank_meta_key .= '_' . $GLOBALS['blog_id'];

			if ( $bonips->bonips_type != BONIPS_DEFAULT_TYPE_KEY )
				$rank_meta_key .= $bonips->bonips_type;

?>
<label class="subheader"><?php _e( 'Ränge', 'bonips' ); ?></label>
<ol id="boniPS-rank-actions" class="inline">
	<li>
		<label><?php _e( 'Benutzer-Metaschlüssel', 'bonips' ); ?></label>
		<div class="h2"><input type="text" id="bonips-rank-post-type" disabled="disabled" value="<?php echo $rank_meta_key; ?>" class="readonly" /></div>
	</li>
	<li>
		<label><?php _e( 'Anzahl der Ränge', 'bonips' ); ?></label>
		<div class="h2"><input type="text" id="bonips-ranks-no-of-ranks" disabled="disabled" value="<?php echo $count; ?>" class="readonly short" /></div>
	</li>
	<li>
		<label><?php _e( 'Aktionen', 'bonips' ); ?></label>
		<div class="h2"><input type="button" id="bonips-manage-action-reset-ranks" data-type="<?php echo $bonips->bonips_type; ?>" value="<?php _e( 'Alle Ränge entfernen', 'bonips' ); ?>" class="button button-large large <?php if ( $reset_block ) echo '" disabled="disabled'; else echo 'button-primary'; ?>" /><?php if ( ! $this->is_manual_mode( $bonips->bonips_type ) ) : ?> <input type="button" id="bonips-manage-action-assign-ranks" data-type="<?php echo $bonips->bonips_type; ?>" value="<?php _e( 'Weise Benutzern Ränge zu', 'bonips' ); ?>" class="button button-large large <?php if ( $reset_block ) echo '" disabled="disabled'; ?>" /></div><?php endif; ?>
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
				$point_type = BONIPS_DEFAULT_TYPE_KEY;

			$wpdb->delete(
				$wpdb->usermeta,
				array( 'meta_key' => bonips_get_meta_key( BONIPS_RANK_KEY, ( ( $point_type != BONIPS_DEFAULT_TYPE_KEY ) ? $point_type : '' ) ) ),
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
			check_ajax_referer( 'bonips-management-actions-roles', 'token' );

			// Define type
			$point_type     = BONIPS_DEFAULT_TYPE_KEY;
			if ( isset( $_POST['ctype'] ) && array_key_exists( sanitize_key( $_POST['ctype'] ), $this->point_types ) )
				$point_type = sanitize_key( $_POST['ctype'] );

			global $wpdb;

			// Get the appropriate tables based on setup
			$posts_table    = bonips_get_db_column( 'posts' );
			$postmeta_table = bonips_get_db_column( 'postmeta' );

			// First get the ids of all existing ranks
			$rank_key       = BONIPS_RANK_KEY;
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
			wp_cache_delete( 'ranks-published-' . $point_type, BONIPS_SLUG );
			wp_cache_delete( 'ranks-published-count-' . $point_type, BONIPS_SLUG );

			wp_send_json( array( 'status' => 'OK', 'rows' => $rows ) );

		}

		/**
		 * Assign Ranks
		 * @since 1.3.2
		 * @version 1.3
		 */
		public function action_assign_ranks() {

			check_ajax_referer( 'bonips-management-actions-roles', 'token' );

			$point_type     = BONIPS_DEFAULT_TYPE_KEY;
			if ( isset( $_POST['ctype'] ) && array_key_exists( sanitize_key( $_POST['ctype'] ), $this->point_types ) )
				$point_type = sanitize_key( $_POST['ctype'] );

			$adjustments = bonips_assign_ranks( $point_type );
			wp_send_json( array( 'status' => 'OK', 'rows' => $adjustments ) );

		}

	}
endif;

/**
 * Load Ranks Module
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'bonips_load_ranks_addon' ) ) :
	function bonips_load_ranks_addon( $modules, $point_types ) {

		$modules['solo']['ranks'] = new boniPS_Ranks_Module();
		$modules['solo']['ranks']->load();

		return $modules;

	}
endif;
add_filter( 'bonips_load_modules', 'bonips_load_ranks_addon', 80, 2 );
