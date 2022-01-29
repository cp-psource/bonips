<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

/**
 * Widget: boniPRESS Balance
 * @since 0.1
 * @version 1.4.3
 */
if ( ! class_exists( 'boniPRESS_Widget_Balance' ) ) :
	class boniPRESS_Widget_Balance extends WP_Widget {

		/**
		 * Construct
		 */
		public function __construct() {

			parent::__construct(
				'bonipress_widget_balance',
				sprintf( __( '(%s) Mein Guthaben', 'bonipress' ), bonipress_label( true ) ),
				array(
					'classname'   => 'widget-my-cred',
					'description' => __( 'Zeigt das aktuelle Guthaben und den Verlauf des Benutzers an.', 'bonipress' )
				)
			);

		}

		/**
		 * Widget Output
		 */
		public function widget( $args, $instance ) {

			extract( $args, EXTR_SKIP );

			// Make sure we always have a type set
			if ( ! isset( $instance['type'] ) || $instance['type'] == '' )
				$instance['type'] = BONIPRESS_DEFAULT_TYPE_KEY;

			// If we are logged in
			if ( is_user_logged_in() ) {

				// Get Current Users Account Object
				$account = bonipress_get_account( get_current_user_id() );
				if ( $account === false ) return;

				// Excluded users have no balance(s)
				if ( ! isset( $account->point_types ) || empty( $account->point_types ) || $account->balance[ $instance['type'] ] === false ) return;

				// Get balance object
				$balance = $account->balance[ $instance['type'] ];
				$bonipress  = bonipress( $instance['type'] );

				// Start
				echo $before_widget;

				// Title
				if ( ! empty( $instance['title'] ) )
					echo $before_title . $instance['title'] . $after_title;

				$layout = $bonipress->template_tags_amount( $instance['cred_format'], $balance->current );
				$layout = $bonipress->template_tags_user( $layout, false, wp_get_current_user() );

				echo '<div class="boniPRESS-balance ' . esc_attr( $instance['type'] ) . '">' . do_shortcode( $layout ) . '</div>';

				// If we want to include history
				if ( BONIPRESS_ENABLE_LOGGING && $instance['show_history'] ) {

					echo '<div class="boniPRESS-widget-history">';

					// Query Log
					$log = new boniPRESS_Query_Log( array(
						'user_id' => $account->user_id,
						'number'  => $instance['number'],
						'ctype'   => $instance['type']
					) );

					// Have results
					if ( $log->have_entries() ) {

						// Title
						if ( ! empty( $instance['history_title'] ) )
							echo $before_title . $bonipress->template_tags_general( $instance['history_title'] ) . $after_title;

						// Organized List
						echo '<ol class="boniPRESS-history">';
						$alt         = 0;
						$date_format = get_option( 'date_format' );
						foreach ( $log->results as $entry ) {

							// Row Layout
							$layout = $instance['history_format'];

							$layout = str_replace( '%date%',  '<span class="date">' . date( $date_format, $entry->time ) . '</span>', $layout );
							$layout = str_replace( '%entry%', $bonipress->parse_template_tags( $entry->entry, $entry ), $layout );

							$layout = $bonipress->template_tags_amount( $layout, $entry->creds );

							// Alternating rows
							$alt = $alt+1;
							if ( $alt % 2 == 0 ) $class = 'entry-row alternate';
							else $class = 'entry-row';

							// Output list item
							echo '<li class="' . $class . '">' . $layout . '</li>';

						}
						echo '</ol>';

					}
					$log->reset_query();

					echo '</div>';
				}

				// End
				echo $after_widget;

			}

			// Visitor
			else {

				// If we want to show a message, then do so
				if ( $instance['show_visitors'] ) {

					echo $before_widget;

					$bonipress = bonipress( $instance['type'] );

					// Title
					if ( ! empty( $instance['title'] ) )
						echo $before_title . $instance['title'] . $after_title;

					$message = $instance['message'];
					$message = $bonipress->template_tags_general( $message );
					$message = $bonipress->allowed_tags( $message );

					echo '<div class="boniPRESS-my-balance-message"><p>' . nl2br( $message ) . '</p></div>';

					echo $after_widget;

				}

			}

		}

		/**
		 * Outputs the options form on admin
		 */
		public function form( $instance ) {

			// Defaults
			$title          = isset( $instance['title'] )          ? $instance['title']          : 'Mein Wallet';
			$type           = isset( $instance['type'] )           ? $instance['type']           : BONIPRESS_DEFAULT_TYPE_KEY;
			$cred_format    = isset( $instance['cred_format'] )    ? $instance['cred_format']    : '%cred_f%';
			$show_history   = isset( $instance['show_history'] )   ? $instance['show_history']   : 0;
			$history_title  = isset( $instance['history_title'] )  ? $instance['history_title']  : '%plural% Verläufe';
			$history_entry  = isset( $instance['history_format'] ) ? $instance['history_format'] : '%entry% <span class="creds">%cred_f%</span>';
			$history_length = isset( $instance['number'] )         ? $instance['number']         : 5;
			$show_visitors  = isset( $instance['show_visitors'] )  ? $instance['show_visitors']  : 0;
			$message        = isset( $instance['message'] )        ? $instance['message']        : '<a href="%login_url_here%">Anmelden</a> um Dein Guthaben anzuzeigen.';

			$bonipress         = bonipress( $type );
			$bonipress_types   = bonipress_get_types();

?>
<!-- Widget Admin Styling -->
<style type="text/css">
div.bonipress-hidden { display: none; }
div.bonipress-hidden.ex-field { display: block; }
</style>

<!-- Widget Options -->
<p class="boniPRESS-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php _e( 'Titel', 'bonipress' ); ?>:</label>
	<input id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" class="widefat" />
</p>

<!-- Point Type -->
<?php if ( count( $bonipress_types ) > 1 ) : ?>
<p class="boniPRESS-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'type' ) ); ?>"><?php _e( 'Punkttyp', 'bonipress' ); ?>:</label>
	<?php bonipress_types_select_from_dropdown( $this->get_field_name( 'type' ), $this->get_field_id( 'type' ), $type ); ?>
</p>
<?php else : ?>
	<?php bonipress_types_select_from_dropdown( $this->get_field_name( 'type' ), $this->get_field_id( 'type' ), $type ); ?>
<?php endif; ?>

<!-- Balance layout -->
<p class="boniPRESS-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'cred_format' ) ); ?>"><?php _e( 'Guthaben Layout', 'bonipress' ); ?>:</label>
	<textarea name="<?php echo esc_attr( $this->get_field_name( 'cred_format' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'cred_format' ) ); ?>" rows="3" cols="20" class="widefat"><?php echo esc_attr( $cred_format ); ?></textarea>
	<small><?php echo $bonipress->available_template_tags( array( 'general', 'amount', 'user' ) ); ?></small>
</p>
<?php if ( BONIPRESS_ENABLE_LOGGING ) : ?>
<!-- Verlauf -->
<p class="boniPRESS-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'show_history' ) ); ?>"><input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'show_history' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'show_history' ) ); ?>" value="1"<?php checked( $show_history, 1 ); ?> class="checkbox" /> <?php _e( 'Verlauf einbeziehen', 'bonipress' ); ?></label>
</p>
<div id="<?php echo esc_attr( $this->get_field_id( 'show_history' ) ); ?>-details" class="bonipress-hidden<?php if ( $show_history == 1 ) echo ' ex-field'; ?>">
	<p class="boniPRESS-widget-field">
		<label for="<?php echo esc_attr( $this->get_field_id( 'history_title' ) ); ?>"><?php _e( 'Verlauf Titel', 'bonipress' ); ?>:</label>
		<input id="<?php echo esc_attr( $this->get_field_id( 'history_title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'history_title' ) ); ?>" type="text" value="<?php echo esc_attr( $history_title ); ?>" class="widefat" />
	</p>
	<p class="boniPRESS-widget-field">
		<label for="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>"><?php _e( 'Anzahl der Einträge', 'bonipress' ); ?>:</label>
		<input id="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'number' ) ); ?>" type="text" value="<?php echo absint( $history_length ); ?>" size="3" class="widefat" /><br />
	</p>
	<p class="boniPRESS-widget-field">
		<label for="<?php echo esc_attr( $this->get_field_id( 'history_format' ) ); ?>"><?php _e( 'Reihenlayout', 'bonipress' ); ?>:</label>
		<textarea name="<?php echo esc_attr( $this->get_field_name( 'history_format' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'history_format' ) ); ?>" rows="3" cols="20" class="widefat"><?php echo esc_attr( $history_entry ); ?></textarea>
		<small><?php echo $bonipress->available_template_tags( array( 'general', 'widget' ) ); ?></small>
	</p>
</div>
<?php else : ?>
<input type="hidden" name="<?php echo esc_attr( $this->get_field_name( 'show_history' ) ); ?>" value="<?php echo esc_attr( $show_history ); ?>" />
<input type="hidden" name="<?php echo esc_attr( $this->get_field_name( 'history_title' ) ); ?>" value="<?php echo esc_attr( $history_title ); ?>" />
<input type="hidden" name="<?php echo esc_attr( $this->get_field_name( 'number' ) ); ?>" value="<?php echo esc_attr( $history_length ); ?>" />
<input type="hidden" name="<?php echo esc_attr( $this->get_field_name( 'history_format' ) ); ?>" value="<?php echo esc_attr( $history_entry ); ?>" />
<?php endif; ?>
<!-- Show to Visitors -->
<p class="boniPRESS-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'show_visitors' ) ); ?>"><input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'show_visitors' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'show_visitors' ) ); ?>" value="1"<?php checked( $show_visitors, 1 ); ?> class="checkbox" /> <?php _e( 'Nachricht anzeigen, wenn nicht eingeloggt', 'bonipress' ); ?></label>
</p>
<div id="<?php echo esc_attr( $this->get_field_id( 'show_visitors' ) ); ?>-details" class="bonipress-hidden<?php if ( $show_visitors == 1 ) echo ' ex-field'; ?>">
	<p class="boniPRESS-widget-field">
		<label for="<?php echo esc_attr( $this->get_field_id( 'message' ) ); ?>"><?php _e( 'Nachricht', 'bonipress' ); ?>:</label>
		<textarea name="<?php echo esc_attr( $this->get_field_name( 'message' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'message' ) ); ?>" rows="3" cols="20" class="widefat"><?php echo esc_attr( $message ); ?></textarea>
		<small><?php echo $bonipress->available_template_tags( array( 'general', 'amount' ) ); ?></small>
	</p>
</div>
<!-- Widget Admin Scripting -->
<script type="text/javascript">//<![CDATA[
jQuery(function($) {

	$( '#<?php echo esc_attr( $this->get_field_id( 'show_history' ) ); ?>, #<?php echo esc_attr( $this->get_field_id( 'show_visitors' ) ); ?>' ).change(function(){
		$( '#' + $(this).attr( 'id' ) + '-details' ).toggleClass( 'ex-field' );
	});

});//]]>
</script>
<?php

		}

		/**
		 * Processes widget options to be saved
		 */
		public function update( $new_instance, $old_instance ) {

			$instance                   = $old_instance;

			$instance['title']          = wp_kses_post( $new_instance['title'] );
			$instance['type']           = sanitize_text_field( $new_instance['type'] );
			$instance['cred_format']    = wp_kses_post( $new_instance['cred_format'] );
			$instance['show_history']   = ( isset( $new_instance['show_history'] ) ) ? 1 : 0;
			$instance['history_title']  = wp_kses_post( $new_instance['history_title'] );
			$instance['history_format'] = wp_kses_post( $new_instance['history_format'] );
			$instance['number']         = absint( $new_instance['number'] );
			$instance['show_visitors']  = ( isset( $new_instance['show_visitors'] ) ) ? 1 : 0;
			$instance['message']        = wp_kses_post( $new_instance['message'] );

			bonipress_flush_widget_cache( 'bonipress_widget_balance' );

			return $instance;

		}

	}
endif;
