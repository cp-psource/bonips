<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

/**
 * Widget: boniPRESS Transfer
 * @since 0.1
 * @version 1.2.2
 */
if ( ! class_exists( 'boniPRESS_Widget_Transfer' ) ) :
	class boniPRESS_Widget_Transfer extends WP_Widget {

		/**
		 * Construct
		 */
		public function __construct() {

			parent::__construct(
				'bonipress_widget_transfer',
				sprintf( __( '(%s) Transfer', 'bonipress' ), bonipress_label( true ) ),
				array(
					'classname'   => 'widget-my-cred-transfer',
					'description' => __( 'Allow transfers between users.', 'bonipress' )
				)
			);

		}

		/**
		 * Widget Output
		 */
		public function widget( $args, $instance ) {

			extract( $args, EXTR_SKIP );

			$instance = shortcode_atts( array(
				'title'        => '',
				'button'       => 'Transfer',
				'pay_to'       => '',
				'show_balance' => 0,
				'show_limit'   => 0,
				'reference'    => 'transfer',
				'amount'       => '',
				'excluded'     => '',
				'types'        => BONIPRESS_DEFAULT_TYPE_KEY,
				'placeholder'  => ''
			), $instance );

			echo $before_widget;

			// Title
			if ( ! empty( $instance['title'] ) )
				echo $before_title . $instance['title'] . $after_title;

			// Let the shortcode to the job
			echo bonipress_transfer_render( array(
				'button'       => $instance['button'],
				'pay_to'       => $instance['pay_to'],
				'show_balance' => $instance['show_balance'],
				'show_limit'   => $instance['show_limit'],
				'ref'          => $instance['reference'],
				'amount'       => $instance['amount'],
				'excluded'     => $instance['excluded'],
				'types'        => $instance['types'],
				'placeholder'  => $instance['placeholder']
			) );

			echo $after_widget;

		}

		/**
		 * Outputs the options form on admin
		 */
		public function form( $instance ) {

			// Defaults
			$title        = isset( $instance['title'] )        ? $instance['title']        : 'Transfer';
			$show_balance = isset( $instance['show_balance'] ) ? $instance['show_balance'] : 0;
			$show_limit   = isset( $instance['show_limit'] )   ? $instance['show_balance'] : 0;
			$button       = isset( $instance['button'] )       ? $instance['button']       : 'Transfer';
			$amount       = isset( $instance['amount'] )       ? $instance['amount']       : '';
			$reference    = isset( $instance['reference'] )    ? $instance['reference']    : 'transfer';
			$recipient    = isset( $instance['pay_to'] )       ? $instance['pay_to']       : '';
			$point_types  = isset( $instance['types'] )        ? $instance['types']        : BONIPRESS_DEFAULT_TYPE_KEY;
			$excluded     = isset( $instance['excluded'] )     ? $instance['excluded']     : '';
			$placeholder  = isset( $instance['placeholder'] )  ? $instance['placeholder']  : '';

?>
<!-- Widget Options -->
<p class="boniPRESS-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php _e( 'Title', 'bonipress' ); ?>:</label>
	<input id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" placeholder="<?php _e( 'optional', 'bonipress' ); ?>" value="<?php echo esc_attr( $title ); ?>" class="widefat" />
</p>
<p class="boniPRESS-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'show_balance' ) ); ?>"><input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'show_balance' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'show_balance' ) ); ?>" value="1"<?php checked( $show_balance, true ); ?> class="checkbox" /> <?php _e( 'Show users balance', 'bonipress' ); ?></label>
</p>
<p class="boniPRESS-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'show_limit' ) ); ?>"><input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'show_limit' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'show_limit' ) ); ?>" value="1"<?php checked( $show_balance, true ); ?> class="checkbox" /> <?php _e( 'Show users limit', 'bonipress' ); ?></label>
</p>
<p class="boniPRESS-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'button' ) ); ?>"><?php _e( 'Button Label', 'bonipress' ); ?>:</label>
	<input id="<?php echo esc_attr( $this->get_field_id( 'button' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'button' ) ); ?>" type="text" placeholder="<?php _e( 'required', 'bonipress' ); ?>" value="<?php echo esc_attr( $button ); ?>" class="widefat" />
</p>
<p class="boniPRESS-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'amount' ) ); ?>"><?php _e( 'Amount', 'bonipress' ); ?>:</label>
	<input id="<?php echo esc_attr( $this->get_field_id( 'amount' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'amount' ) ); ?>" type="text" placeholder="<?php _e( 'required', 'bonipress' ); ?>" value="<?php echo esc_attr( $amount ); ?>" class="widefat" />
</p>
<p class="boniPRESS-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'reference' ) ); ?>"><?php _e( 'Reference', 'bonipress' ); ?>:</label>
	<input id="<?php echo esc_attr( $this->get_field_id( 'reference' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'reference' ) ); ?>" type="text" placeholder="<?php _e( 'required', 'bonipress' ); ?>" value="<?php echo esc_attr( $reference ); ?>" class="widefat" />
</p>
<p class="boniPRESS-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'pay_to' ) ); ?>"><?php _e( 'Recipient', 'bonipress' ); ?>:</label>
	<input id="<?php echo esc_attr( $this->get_field_id( 'pay_to' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'pay_to' ) ); ?>" type="text" placeholder="<?php _e( 'optional', 'bonipress' ); ?>" value="<?php echo esc_attr( $recipient ); ?>" class="widefat" />
</p>
<p class="boniPRESS-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'placeholder' ) ); ?>"><?php _e( 'Recipient Placeholder', 'bonipress' ); ?>:</label>
	<input id="<?php echo esc_attr( $this->get_field_id( 'placeholder' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'placeholder' ) ); ?>" type="text" placeholder="<?php _e( 'optional', 'bonipress' ); ?>" value="<?php echo esc_attr( $placeholder ); ?>" class="widefat" />
</p>
<p class="boniPRESS-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'types' ) ); ?>"><?php _e( 'Point Types', 'bonipress' ); ?>:</label>
	<input id="<?php echo esc_attr( $this->get_field_id( 'types' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'types' ) ); ?>" type="text" placeholder="<?php _e( 'required', 'bonipress' ); ?>" value="<?php echo esc_attr( $point_types ); ?>" class="widefat" />
</p>
<p class="boniPRESS-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'excluded' ) ); ?>"><?php _e( 'Message for Excluded Users', 'bonipress' ); ?>:</label>
	<input id="<?php echo esc_attr( $this->get_field_id( 'excluded' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'excluded' ) ); ?>" placeholder="<?php _e( 'optional', 'bonipress' ); ?>" type="text" value="<?php echo esc_attr( $excluded ); ?>" class="widefat" />
</p>
<?php

		}

		/**
		 * Processes widget options to be saved
		 */
		public function update( $new_instance, $old_instance ) {

			$instance                 = $old_instance;

			$instance['title']        = wp_kses_post( $new_instance['title'] );
			$instance['show_balance'] = ( isset( $new_instance['show_balance'] ) ) ? 1 : 0;
			$instance['show_limit']   = ( isset( $new_instance['show_limit'] ) ) ? 1 : 0;
			$instance['button']       = sanitize_text_field( $new_instance['button'] );
			$instance['amount']       = sanitize_text_field( $new_instance['amount'] );
			$instance['reference']    = sanitize_key( $new_instance['reference'] );
			$instance['pay_to']       = sanitize_text_field( $new_instance['pay_to'] );
			$instance['placeholder']  = sanitize_text_field( $new_instance['placeholder'] );
			$instance['types']        = sanitize_text_field( $new_instance['types'] );
			$instance['excluded']     = sanitize_text_field( $new_instance['excluded'] );

			bonipress_flush_widget_cache( 'bonipress_widget_transfer' );

			return $instance;

		}

	}
endif;
