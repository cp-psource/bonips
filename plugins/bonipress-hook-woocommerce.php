<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

/**
 * WooCommerce Setup
 * @since 1.5
 * @version 1.1
 */
if ( ! function_exists( 'bonipress_load_woocommerce_reward' ) ) :
	function bonipress_load_woocommerce_reward() {

		if ( ! class_exists( 'WooCommerce' ) ) return;

		add_filter( 'bonipress_comment_gets_cred',                      'bonipress_woo_remove_review_from_comments', 10, 2 );
		add_action( 'add_meta_boxes_product',                        'bonipress_woo_add_product_metabox' );
		add_action( 'save_post_product',                             'bonipress_woo_save_reward_settings' );
		add_action( 'woocommerce_payment_complete',                  'bonipress_woo_payout_rewards' );
		add_action( 'woocommerce_order_status_completed',            'bonipress_woo_payout_rewards' );
		add_action( 'woocommerce_product_after_variable_attributes', 'bonipress_woo_add_product_variation_detail', 10, 3 );
		add_action( 'woocommerce_save_product_variation',            'bonipress_woo_save_product_variation_detail' );
		add_filter( 'bonipress_run_this', 								 'bonipress_woo_refund_points' );

	}
endif;
add_action( 'bonipress_load_hooks', 'bonipress_load_woocommerce_reward', 90 );

/**
 * Remove Reviews from Comment Hook
 * Prevents the comment hook from granting points twice for a review.
 * @since 1.6.3
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_woo_remove_review_from_comments' ) ) :
	function bonipress_woo_remove_review_from_comments( $reply, $comment ) {

		if ( get_post_type( $comment->comment_post_ID ) == 'product' ) return false;

		return $reply;

	}
endif;

/**
 * Add Reward Metabox
 * @since 1.5
 * @version 1.0.1
 */
if ( ! function_exists( 'bonipress_woo_add_product_metabox' ) ) :
	function bonipress_woo_add_product_metabox() {

		add_meta_box(
			'bonipress_woo_sales_setup',
			bonipress_label(),
			'bonipress_woo_product_metabox',
			'product',
			'side',
			'high'
		);

	}
endif;

/**
 * Product Metabox
 * @since 1.5
 * @version 1.1
 */
if ( ! function_exists( 'bonipress_woo_product_metabox' ) ) :
	function bonipress_woo_product_metabox( $post ) {

		if ( ! current_user_can( apply_filters( 'bonipress_woo_reward_cap', 'edit_others_posts' ) ) ) return;

		$types = bonipress_get_types();
		$prefs = (array) get_post_meta( $post->ID, 'bonipress_reward', true );

		foreach ( $types as $point_type => $point_type_label ) {
			if ( ! array_key_exists( $point_type, $prefs ) )
				$prefs[ $point_type ] = '';
		}

		$count = 0;
		$cui   = get_current_user_id();
		foreach ( $types as $point_type => $point_type_label ) {

			$count ++;
			$bonipress = bonipress( $point_type );

			if ( ! $bonipress->can_edit_creds( $cui ) ) continue;

			$setup = $prefs[ $point_type ];

?>
<p class="<?php if ( $count == 1 ) echo 'first'; ?>"><label for="bonipress-reward-purchase-with-<?php echo $point_type; ?>"><input class="toggle-bonipress-reward" data-id="<?php echo $point_type; ?>" <?php if ( $setup != '' ) echo 'checked="checked"'; ?> type="checkbox" name="bonipress_reward[<?php echo $point_type; ?>][use]" id="bonipress-reward-purchase-with-<?php echo $point_type; ?>" value="1" /> <?php echo $bonipress->template_tags_general( __( 'Reward with %plural%', 'bonipress' ) ); ?></label></p>
<div class="bonipress-woo-wrap" id="reward-<?php echo $point_type; ?>" style="display:<?php if ( $setup == '' ) echo 'none'; else echo 'block'; ?>">
	<label><?php echo $bonipress->plural(); ?></label> <input type="text" size="8" name="bonipress_reward[<?php echo $point_type; ?>][amount]" value="<?php echo esc_attr( $setup ); ?>" placeholder="<?php echo $bonipress->zero(); ?>" />
</div>
<?php

		}

?>
<script type="text/javascript">
jQuery(function($) {

	$( '.toggle-bonipress-reward' ).click(function(){
		var target = $(this).attr( 'data-id' );
		$( '#reward-' + target ).toggle();
	});

});
</script>
<style type="text/css">
#bonipress_woo_sales_setup .inside { margin: 0; padding: 0; }
#bonipress_woo_sales_setup .inside > p { padding: 12px; margin: 0; border-top: 1px solid #ddd; }
#bonipress_woo_sales_setup .inside > p.first { border-top: none; }
#bonipress_woo_sales_setup .inside .bonipress-woo-wrap { padding: 6px 12px; line-height: 27px; text-align: right; border-top: 1px solid #ddd; background-color: #F5F5F5; }
#bonipress_woo_sales_setup .inside .bonipress-woo-wrap label { display: block; font-weight: bold; float: left; }
#bonipress_woo_sales_setup .inside .bonipress-woo-wrap input { width: 50%; }
#bonipress_woo_sales_setup .inside .bonipress-woo-wrap p { margin: 0; padding: 0 12px; font-style: italic; text-align: center; }
#bonipress_woo_vaiation .box { display: block; float: left; width: 49%; margin-right: 1%; margin-bottom: 12px; }
#bonipress_woo_vaiation .box input { display: block; width: 100%; }
</style>
<?php

	}
endif;

/**
 * Add Reward details for Variations
 * @since 1.7.6
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_woo_add_product_variation_detail' ) ) :
	function bonipress_woo_add_product_variation_detail( $loop, $variation_data, $variation ) {

		$types   = bonipress_get_types();
		$user_id = get_current_user_id();
		$prefs   = (array) get_post_meta( $variation->ID, '_bonipress_reward', true );

		foreach ( $types as $point_type => $point_type_label ) {
			if ( ! array_key_exists( $point_type, $prefs ) )
				$prefs[ $point_type ] = '';
		}

?>
<div class="" id="bonipress_woo_vaiation">
<?php

		foreach ( $types as $point_type => $point_type_label ) {

			$bonipress = bonipress( $point_type );

			if ( ! $bonipress->can_edit_creds( $user_id ) ) continue;

			$id = 'bonipress-rewards-variation-' . $variation->ID . str_replace( '_', '-', $point_type );

?>
		<div class="box">
			<label for="<?php echo $id; ?>"><?php echo $bonipress->template_tags_general( __( 'Reward with %plural%', 'bonipress' ) ); ?></label>
			<input type="text" name="_bonipress_reward[<?php echo $variation->ID; ?>][<?php echo $point_type; ?>]" id="<?php echo $id; ?>" class="input-text" placeholder="<?php _e( 'Leave empty for no rewards', 'bonipress' ); ?>" value="<?php echo esc_attr( $prefs[ $point_type ] ); ?>" />
		</div>
<?php

		}

?>
</div>
<?php

	}
endif;


/**
 * WooCommerce Points Refund
 * @since 1.7.9.8
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_woo_refund_points' ) ) :
	function bonipress_woo_refund_points( $request ) {

		if( $request['ref'] == 'woocommerce_refund' )
			$request['amount'] = abs($request['amount']);
		
		return $request;
	}
endif;

/**
 * Save Reward Setup
 * @since 1.5
 * @version 1.0.1
 */
if ( ! function_exists( 'bonipress_woo_save_reward_settings' ) ) :
	function bonipress_woo_save_reward_settings( $post_id ) {

		if ( ! isset( $_POST['bonipress_reward'] ) || empty( $_POST['bonipress_reward'] ) || get_post_type( $post_id ) != 'product' ) return;

		$new_setup = array();
		foreach ( $_POST['bonipress_reward'] as $point_type => $setup ) {

			if ( empty( $setup ) ) continue;

			$bonipress = bonipress( $point_type );
			if ( array_key_exists( 'use', $setup ) && $setup['use'] == 1 )
				$new_setup[ $point_type ] = $bonipress->number( $setup['amount'] );

		}

		if ( empty( $new_setup ) )
			delete_post_meta( $post_id, 'bonipress_reward' );
		else
			update_post_meta( $post_id, 'bonipress_reward', $new_setup );

	}
endif;

/**
 * Save Reward for Variations
 * @since 1.7.6
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_woo_save_product_variation_detail' ) ) :
	function bonipress_woo_save_product_variation_detail( $post_id ) {

		if ( ! isset( $_POST['_bonipress_reward'] ) || empty( $_POST['_bonipress_reward'] ) || ! array_key_exists( $post_id, $_POST['_bonipress_reward'] ) ) return;

		$new_setup = array();
		foreach ( $_POST['_bonipress_reward'][ $post_id ] as $point_type => $value ) {

			$value  = sanitize_text_field( $value );
			if ( empty( $value ) ) continue;

			$bonipress = bonipress( $point_type );
			$value  = $bonipress->number( $value );
			if ( $value === $bonipress->zero() ) continue;

			$new_setup[ $point_type ] = $value;

		}

		if ( empty( $new_setup ) )
			delete_post_meta( $post_id, '_bonipress_reward' );
		else
			update_post_meta( $post_id, '_bonipress_reward', $new_setup );

	}
endif;

/**
 * Payout Rewards
 * @since 1.5
 * @version 1.2
 */
if ( ! function_exists( 'bonipress_woo_payout_rewards' ) ) :
	function bonipress_woo_payout_rewards( $order_id ) {

		// Get Order
		$order    = wc_get_order( $order_id );

		global $woocommerce;

		$paid_with = ( version_compare( $woocommerce->version, '3.0', '>=' ) ) ? $order->get_payment_method() : $order->payment_method;
		$buyer_id  = ( version_compare( $woocommerce->version, '3.0', '>=' ) ) ? $order->get_user_id() : $order->user_id;

		// If we paid with boniPRESS we do not award points by default
		if ( $paid_with == 'bonipress' && apply_filters( 'bonipress_woo_reward_bonipress_payment', false, $order ) === false )
			return;

		// Get items
		$items    = $order->get_items();
		$types    = bonipress_get_types();

		// Loop through each point type
		foreach ( $types as $point_type => $point_type_label ) {

			// Load type
			$bonipress = bonipress( $point_type );

			// Check for exclusions
			if ( $bonipress->exclude_user( $buyer_id ) ) continue;

			// Calculate reward
			$payout = $bonipress->zero();
			foreach ( $items as $item ) {

				// Get the product ID or the variation ID
				$product_id    = absint( $item['product_id'] );
				$variation_id  = absint( $item['variation_id'] );
				$reward_amount = bonipress_get_woo_product_reward( $product_id, $variation_id, $point_type );

				// Reward can not be empty or zero
				if ( $reward_amount != '' && $reward_amount != 0 )
					$payout = ( $payout + ( $bonipress->number( $reward_amount ) * $item['qty'] ) );

			}

			// We can not payout zero points
			if ( $payout === $bonipress->zero() ) continue;

			// Let others play with the reference and log entry
			$reference = apply_filters( 'bonipress_woo_reward_reference', 'reward', $order_id, $point_type );
			$log       = apply_filters( 'bonipress_woo_reward_log',       '%plural% reward for store purchase', $order_id, $point_type );

			// Make sure we only get points once per order
			if ( ! $bonipress->has_entry( $reference, $order_id, $buyer_id ) ) {

				// Execute
				$bonipress->add_creds(
					$reference,
					$buyer_id,
					$payout,
					$log,
					$order_id,
					array( 'ref_type' => 'post' ),
					$point_type
				);

			}

		}

	}
endif;

/**
 * Get Product Reward
 * Returns either an array of point types and the reward value set for each or
 * the value set for a given point type. Will check for variable product rewards as well.
 * @since 1.7.6
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_get_woo_product_reward' ) ) :
	function bonipress_get_woo_product_reward( $product_id = NULL, $variation_id = NULL, $requested_type = false ) {

		$product_id   = absint( $product_id );
		$types        = bonipress_get_types();
		$meta_key     = 'bonipress_reward';
		$is_variable  = false;
		if ( $product_id === 0 ) return false;

		if ( function_exists( 'wc_get_product' ) ) {

			$product  = wc_get_product( $product_id );

			// For variations, we need a variation ID
			if ( $product->is_type( 'variable' ) && $variation_id !== NULL && $variation_id > 0 ) {
				$parent_product_id   = $product->get_parent();
				$reward_setup        = (array) get_post_meta( $variation_id, '_bonipress_reward', true );
				$parent_reward_setup = (array) get_post_meta( $parent_product_id, 'bonipress_reward', true );
			}
			else {
				$reward_setup        = (array) get_post_meta( $product_id, 'bonipress_reward', true );
				$parent_reward_setup = array();
			}

		}

		// Make sure all point types are populated in a reward setup
		foreach ( $types as $point_type => $point_type_label ) {

			if ( empty( $reward_setup ) || ! array_key_exists( $point_type, $reward_setup ) )
				$reward_setup[ $point_type ]        = '';

			if ( empty( $parent_reward_setup ) || ! array_key_exists( $point_type, $parent_reward_setup ) )
				$parent_reward_setup[ $point_type ] = '';

		}

		// We might want to enforce the parent value for variations
		foreach ( $reward_setup as $point_type => $value ) {

			// If the variation has no value set, but the parent box has a value set, enforce the parent value
			// If the variation is set to zero however, it indicates we do not want to reward that variation
			if ( $value == '' && $parent_reward_setup[ $point_type ] != '' && $parent_reward_setup[ $point_type ] != 0 )
				$reward_setup[ $point_type ] = $parent_reward_setup[ $point_type ];

		}

		// If we are requesting one particular types reward
		if ( $requested_type !== false ) {

			$value = '';
			if ( array_key_exists( $requested_type, $reward_setup ) )
				$value = $reward_setup[ $requested_type ];

			return $value;

		}

		return $reward_setup;

	}
endif;

/**
 * Register Hook
 * @since 1.5
 * @version 1.1
 */
function bonipress_register_woocommerce_hook( $installed ) {

	if ( ! class_exists( 'WooCommerce' ) ) return $installed;

	$installed['wooreview'] = array(
		'title'         => __( 'WooCommerce Product Reviews', 'bonipress' ),
		'description'   => __( 'Awards %_plural% for users leaving reviews on your WooCommerce products.', 'bonipress' ),
		'documentation' => 'http://codex.bonipress.me/hooks/product-reviews/',
		'callback'      => array( 'boniPRESS_Hook_WooCommerce_Reviews' )
	);

	return $installed;

}
add_filter( 'bonipress_setup_hooks', 'bonipress_register_woocommerce_hook', 95 );

/**
 * WooCommerce Product Review Hook
 * @since 1.5
 * @version 1.1.1
 */
add_action( 'bonipress_load_hooks', 'bonipress_load_woocommerce_hook', 95 );
function bonipress_load_woocommerce_hook() {

	// If the hook has been replaced or if plugin is not installed, exit now
	if ( class_exists( 'boniPRESS_Hook_WooCommerce_Reviews' ) || ! class_exists( 'WooCommerce' ) ) return;

	class boniPRESS_Hook_WooCommerce_Reviews extends boniPRESS_Hook {

		/**
		 * Construct
		 */
		public function __construct( $hook_prefs, $type = BONIPRESS_DEFAULT_TYPE_KEY ) {

			parent::__construct( array(
				'id'       => 'wooreview',
				'defaults' => array(
					'creds' => 1,
					'log'   => '%plural% for product review',
					'limit' => '0/x'
				)
			), $hook_prefs, $type );

		}

		/**
		 * Run
		 * @since 1.5
		 * @version 1.0
		 */
		public function run() {

			add_action( 'comment_post',              array( $this, 'new_review' ), 99, 2 );
			add_action( 'transition_comment_status', array( $this, 'review_transitions' ), 99, 3 );

		}

		/**
		 * New Review
		 * @since 1.5
		 * @version 1.0
		 */
		public function new_review( $comment_id, $comment_status ) {

			// Approved comment
			if ( $comment_status == '1' )
				$this->review_transitions( 'approved', 'unapproved', $comment_id );

		}

		/**
		 * Review Transitions
		 * @since 1.5
		 * @version 1.2
		 */
		public function review_transitions( $new_status, $old_status, $comment ) {

			// Only approved reviews give points
			if ( $new_status != 'approved' ) return;

			// Passing an integer instead of an object means we need to grab the comment object ourselves
			if ( ! is_object( $comment ) )
				$comment = get_comment( $comment );

			// No comment object so lets bail
			if ( $comment === NULL ) return;

			// Only applicable for reviews
			if ( get_post_type( $comment->comment_post_ID ) != 'product' ) return;

			// Check for exclusions
			if ( $this->core->exclude_user( $comment->user_id ) ) return;

			// Limit
			if ( $this->over_hook_limit( '', 'product_review', $comment->user_id ) ) return;

			// Execute
			$data = array( 'ref_type' => 'post' );
			if ( ! $this->core->has_entry( 'product_review', $comment->comment_post_ID, $comment->user_id, $data, $this->bonipress_type ) )
				$this->core->add_creds(
					'product_review',
					$comment->user_id,
					$this->prefs['creds'],
					$this->prefs['log'],
					$comment->comment_post_ID,
					$data,
					$this->bonipress_type
				);

		}

		/**
		 * Preferences for WooCommerce Product Reviews
		 * @since 1.5
		 * @version 1.1
		 */
		public function preferences() {

			$prefs = $this->prefs;

?>
<div class="hook-instance">
	<div class="row">
		<div class="col-lg-2 col-md-6 col-sm-6 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'creds' ); ?>"><?php echo $this->core->plural(); ?></label>
				<input type="text" name="<?php echo $this->field_name( 'creds' ); ?>" id="<?php echo $this->field_id( 'creds' ); ?>" value="<?php echo $this->core->number( $prefs['creds'] ); ?>" class="form-control" />
			</div>
		</div>
		<div class="col-lg-4 col-md-6 col-sm-6 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'limit' ); ?>"><?php _e( 'Limit', 'bonipress' ); ?></label>
				<?php echo $this->hook_limit_setting( $this->field_name( 'limit' ), $this->field_id( 'limit' ), $prefs['limit'] ); ?>
			</div>
		</div>
		<div class="col-lg-6 col-md-12 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'log' ); ?>"><?php _e( 'Protokollvorlage', 'bonipress' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( 'log' ); ?>" id="<?php echo $this->field_id( 'log' ); ?>" placeholder="<?php _e( 'required', 'bonipress' ); ?>" value="<?php echo esc_attr( $prefs['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general', 'post' ) ); ?></span>
			</div>
		</div>
	</div>
</div>
<?php

		}

		/**
		 * Sanitise Preferences
		 * @since 1.6
		 * @version 1.0
		 */
		public function sanitise_preferences( $data ) {

			if ( isset( $data['limit'] ) && isset( $data['limit_by'] ) ) {
				$limit = sanitize_text_field( $data['limit'] );
				if ( $limit == '' ) $limit = 0;
				$data['limit'] = $limit . '/' . $data['limit_by'];
				unset( $data['limit_by'] );
			}

			return $data;

		}

	}

}
