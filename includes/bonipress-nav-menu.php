<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

/**
 * BoniPress Menu
 * Menu Items in Wordpress menu
 * @since 1.9
 * @version 1.0
 */

if ( ! class_exists( 'boniPRESS_Nav_Menu' ) ) :
	class boniPRESS_Nav_Menu {

		// Instnace
		protected static $_instance = NULL;

		/**
		 * Setup Instance
		 * @since 1.9
		 * @version 1.0
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Construct
		 */
		public function __construct() {

			add_action( 'admin_head-nav-menus.php', array( $this, 'setup_bonipress_nav_menu' ) );
			add_filter( 'walker_nav_menu_start_el', array( $this, 'display_bonipress_menu_items' ), 20, 2 );
			add_filter( 'wp_setup_nav_menu_item',   array( $this, 'setup_item' ), 10, 1 );

		}

		public function setup_bonipress_nav_menu() {

			add_meta_box( 
				'bonipress-nav-menu-section', 
				__( 'BoniPress', 'bonipress' ), 
				array( $this, 'bonipress_nav_menu_meta_box' ), 
				'nav-menus', 
				'side', 
				'default' 
			);
		}

		public function bonipress_nav_menu_meta_box() {

			global $_nav_menu_placeholder, $nav_menu_selected_id;

			$nav_menu_placeholder = ( 0 > $_nav_menu_placeholder ? $_nav_menu_placeholder - 1 : -1 );

			$bonipress_types  = bonipress_get_types();?>

			<div id="bonipress-menu" class="posttypediv">

				<div id="tabs-panel-posttype-bonipress" class="tabs-panel tabs-panel-active">
					<ul id="bonipress-menu-list" class="categorychecklist form-no-clear">
						<?php 
							foreach ( $bonipress_types as $key => $value ) {
								$this->bonipress_nav_menu_list( $key, $value, $nav_menu_placeholder, 'balance' );
								$nav_menu_placeholder--;
								$this->bonipress_nav_menu_list( $key, $value, $nav_menu_placeholder, 'rank' );
								$nav_menu_placeholder--;
							}
						?>
					</ul>
				</div>
				<p class="button-controls">
					<span class="add-to-menu">
						<input type="submit"<?php if ( function_exists( 'wp_nav_menu_disabled_check' ) ) : wp_nav_menu_disabled_check( $nav_menu_selected_id ); endif; ?> class="button-secondary submit-add-to-menu right" value="<?php esc_attr_e( 'Add to Menu', 'bonipress' ); ?>" name="add-custom-menu-item" id="submit-bonipress-menu" />
						<span class="spinner"></span>
					</span>
				</p>
			</div><?php 
		}

		public function bonipress_nav_menu_list( $meta_key, $point_type_label, $nav_menu_placeholder, $type ) {?>
			<li>
				<input type="hidden" class="menu-item-title" name="menu-item[<?php echo $nav_menu_placeholder;?>][menu-item-title]" value="<?php echo ( $type == 'rank' ? '%rank_title% %rank_logo%' : '%balance%' );?>">
				<input type="hidden" class="menu-item-db-id" name="menu-item[<?php echo $nav_menu_placeholder;?>][menu-item-db-id]" value="0" />
				<input type="hidden" class="menu-item-object-id" name="menu-item[<?php echo $nav_menu_placeholder;?>][menu-item-object-id]" value="1" />
				<input type="hidden" class="menu-item-object" name="menu-item[<?php echo $nav_menu_placeholder;?>][menu-item-object]" value="bonipress_menu_<?php echo $type;?>" />
				<input type="hidden" class="menu-item-type" name="menu-item[<?php echo $nav_menu_placeholder;?>][menu-item-type]" value="<?php echo $meta_key;?>" />
				<input type="hidden" id="menu-item[<?php echo $nav_menu_placeholder;?>][menu-item-nonce]" value="<?php echo esc_attr( wp_create_nonce( 'bonipress-menu-nonce' ) ); ?>" />
				<label class="menu-item-title">
					<input type="checkbox" 
						class="menu-item-checkbox" 
						name="menu-item[<?php echo $nav_menu_placeholder;?>][menu-item-object-id]" 
						value="<?php echo $meta_key?>"
					/> <?php echo $point_type_label.( $type == 'rank' ? ' Rank' : ' Balance' );?>
				</label>
			</li><?php 
		}

		public function display_bonipress_menu_items( $item_output, $item ) {

			if ( ! is_object( $item ) || ! isset( $item->object ) ) {
				return $item_output;
			}

			$template = $item->title;

			if ( 'bonipress_menu_balance' === $item->object ) {

				$balance     = do_shortcode( '[bonipress_my_balance wrapper=0 title_el="" balance_el="" type="'.$item->type.'"]' );
				$balance     = '<span class="bonipress-nav-balance">'.$balance.'</span>';

				$template    = str_replace( '%balance%', $balance, $template );
				$item_output = $template;

				return $item_output; 

			} else if ( 'bonipress_menu_rank' === $item->object ) {

				$account_object = bonipress_get_account( get_current_user_id() );

				$rank_object = false;
				if( isset( $account_object->balance[ $item->type ]->rank ) && is_object( $account_object->balance[ $item->type ]->rank ) ) {
					$rank_object    = $account_object->balance[ $item->type ]->rank;
				}

				//var_dump( $rank_object );die;

				if ( $rank_object !== false ) {

					$rank_title  = '<span class="bonipress-nav-rank-title">'.$rank_object->title.'</span>';

					$template    = str_replace( '%rank_title%', $rank_title, $template );

					$rank_logo   = '';

					if ( $rank_object->has_logo ) {
						$rank_logo = bonipress_get_rank_logo( $rank_object->post_id, 24 );
					}

					$template    = str_replace( '%rank_logo%', $rank_logo, $template );

					$item_output = $template;

					return '<span class="bonipress-nav-rank">'.$item_output.'</span>';
				}
				else {
					return '';
				}
			}

			return $item_output;
		}

		public function setup_item( $item ) {

			$types = array( 'bonipress_menu_balance', 'bonipress_menu_rank' );

			if ( is_object( $item ) && in_array( $item->object, $types ) && ! empty( $item->type ) ) {

				if ( $types[0] === $item->object ) {
					$label = __( 'BoniPress %s-Guthaben', 'bonipress' );
				}
				elseif ( $types[1] === $item->object ) {
					$label = __( 'BoniPress %s Rang', 'bonipress' );
				}

				$singular_name = '';

				$point_type    = bonipress( $item->type );

				if ( ! empty( $point_type ) && ! empty( $point_type->core['name']['singular'] ) ) {
					$singular_name = $point_type->core['name']['singular'];
				}

				$item->type_label = str_replace( '%s', $singular_name, $label );

			}

			return $item;
		}

	}
endif;

function bonipress_nav_menu() {
	return boniPRESS_Nav_Menu::instance();
}
bonipress_nav_menu();