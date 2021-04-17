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
				sprintf( __( '(%s) Wallet', 'bonipress' ), bonipress_label( true ) ),
				array(
					'classname'   => 'widget-my-cred',
					'description' => __( 'Zeige die aktuelle Benutzerbilanz und den aktuellen Verlauf an.', 'bonipress' )
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
				$account = bonipress_get_account();
				if ( $account === false ) return;

				// Excluded users have no balance(s)
				if ( empty( $account->balance ) || ! array_key_exists( $instance['type'], $account->balance ) ) return;

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
				if ( $instance['show_history'] ) {

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
							if ( $alt % 2 == 0 ) $class = 'row alternate';
							else $class = 'row';

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
	<label for="<?php echo esc_attr( $this->get_field_id( 'cred_format' ) ); ?>"><?php _e( 'Balance-Layout', 'bonipress' ); ?>:</label>
	<textarea name="<?php echo esc_attr( $this->get_field_name( 'cred_format' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'cred_format' ) ); ?>" rows="3" cols="20" class="widefat"><?php echo esc_attr( $cred_format ); ?></textarea>
	<small><?php echo $bonipress->available_template_tags( array( 'general', 'amount', 'user' ) ); ?></small>
</p>

<!-- Verlauf -->
<p class="boniPRESS-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'show_history' ) ); ?>"><input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'show_history' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'show_history' ) ); ?>" value="1"<?php checked( $show_history, 1 ); ?> class="checkbox" /> <?php _e( 'Verlauf einschließen', 'bonipress' ); ?></label>
</p>
<div id="<?php echo esc_attr( $this->get_field_id( 'show_history' ) ); ?>-details" class="bonipress-hidden<?php if ( $show_history == 1 ) echo ' ex-field'; ?>">
	<p class="boniPRESS-widget-field">
		<label for="<?php echo esc_attr( $this->get_field_id( 'history_title' ) ); ?>"><?php _e( 'Verlauf-Titel', 'bonipress' ); ?>:</label>
		<input id="<?php echo esc_attr( $this->get_field_id( 'history_title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'history_title' ) ); ?>" type="text" value="<?php echo esc_attr( $history_title ); ?>" class="widefat" />
	</p>
	<p class="boniPRESS-widget-field">
		<label for="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>"><?php _e( 'Anzahl der Einträge', 'bonipress' ); ?>:</label>
		<input id="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'number' ) ); ?>" type="text" value="<?php echo absint( $history_length ); ?>" size="3" class="widefat" /><br />
	</p>
	<p class="boniPRESS-widget-field">
		<label for="<?php echo esc_attr( $this->get_field_id( 'history_format' ) ); ?>"><?php _e( 'Zeilenlayout', 'bonipress' ); ?>:</label>
		<textarea name="<?php echo esc_attr( $this->get_field_name( 'history_format' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'history_format' ) ); ?>" rows="3" cols="20" class="widefat"><?php echo esc_attr( $history_entry ); ?></textarea>
		<small><?php echo $bonipress->available_template_tags( array( 'general', 'widget' ) ); ?></small>
	</p>
</div>
<!-- Show to Visitors -->
<p class="boniPRESS-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'show_visitors' ) ); ?>"><input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'show_visitors' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'show_visitors' ) ); ?>" value="1"<?php checked( $show_visitors, 1 ); ?> class="checkbox" /> <?php _e( 'Nachricht anzeigen, wenn nicht angemeldet', 'bonipress' ); ?></label>
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

/**
 * Widget: Leaderboard
 * @since 0.1
 * @version 1.3.2
 */
if ( ! class_exists( 'boniPRESS_Widget_Leaderboard' ) ) :
	class boniPRESS_Widget_Leaderboard extends WP_Widget {

		/**
		 * Construct
		 */
		public function __construct() {

			parent::__construct(
				'bonipress_widget_list',
				sprintf( __( '(%s) Bestenliste', 'bonipress' ), bonipress_label( true ) ),
				array(
					'classname'   => 'widget-bonipress-list',
					'description' => __( 'Rangliste basierend auf Instanzen oder Salden.', 'bonipress' )
				)
			);

		}

		/**
		 * Widget Output
		 */
		public function widget( $args, $instance ) {

			extract( $args, EXTR_SKIP );

			// Check if we want to show this to visitors
			if ( ! $instance['show_visitors'] && ! is_user_logged_in() ) return;

			if ( ! isset( $instance['type'] ) || empty( $instance['type'] ) )
				$instance['type'] = BONIPRESS_DEFAULT_TYPE_KEY;

			$bonipress = bonipress( $instance['type'] );

			// Get Rankings
			$args = array(
				'number'   => $instance['number'],
				'template' => $instance['text'],
				'type'     => $instance['type'],
				'based_on' => $instance['based_on']
			);

			if ( isset( $instance['order'] ) )
				$args['order'] = $instance['order'];

			if ( isset( $instance['offset'] ) )
				$args['offset'] = $instance['offset'];

			if ( isset( $instance['current'] ) )
				$args['current'] = 1;

			echo $before_widget;

			// Title
			if ( ! empty( $instance['title'] ) )
				echo $before_title . $bonipress->template_tags_general( $instance['title'] ) . $after_title;

			echo bonipress_render_shortcode_leaderboard( $args );

			// Footer
			echo $after_widget;

		}

		/**
		 * Outputs the options form on admin
		 */
		public function form( $instance ) {

			// Defaults
			$title         = isset( $instance['title'] )         ? $instance['title']         : 'Bestenliste';
			$type          = isset( $instance['type'] )          ? $instance['type']          : BONIPRESS_DEFAULT_TYPE_KEY;
			$based_on      = isset( $instance['based_on'] )      ? $instance['based_on']      : 'balance';

			$number        = isset( $instance['number'] )        ? $instance['number']        : 5;
			$show_visitors = isset( $instance['show_visitors'] ) ? $instance['show_visitors'] : 0;
			$text          = isset( $instance['text'] )          ? $instance['text']          : '#%position% %user_profile_link% %cred_f%';
			$offset        = isset( $instance['offset'] )        ? $instance['offset']        : 0;
			$order         = isset( $instance['order'] )         ? $instance['order']         : 'DESC';
			$current       = isset( $instance['current'] )       ? $instance['current']       : 0;
			$timeframe     = isset( $instance['timeframe'] )     ? $instance['timeframe']     : '';

			$bonipress        = bonipress( $type );
			$bonipress_types  = bonipress_get_types();

?>
<p class="boniPRESS-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php _e( 'Titel', 'bonipress' ); ?>:</label>
	<input id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" class="widefat" />
</p>

<?php if ( count( $bonipress_types ) > 1 ) : ?>
<p class="boniPRESS-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'type' ) ); ?>"><?php _e( 'Punkttyp', 'bonipress' ); ?>:</label>
	<?php bonipress_types_select_from_dropdown( $this->get_field_name( 'type' ), $this->get_field_id( 'type' ), $type ); ?>
</p>
<?php else : ?>
	<?php bonipress_types_select_from_dropdown( $this->get_field_name( 'type' ), $this->get_field_id( 'type' ), $type ); ?>
<?php endif; ?>

<p class="boniPRESS-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'based_on' ) ); ?>"><?php _e( 'Bezogen auf', 'bonipress' ); ?>:</label>
	<input id="<?php echo esc_attr( $this->get_field_id( 'based_on' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'based_on' ) ); ?>" type="text" value="<?php echo esc_attr( $based_on ); ?>" class="widefat" />
	<small><?php _e( 'Verwende "Balance", um die Bestenliste auf die aktuellen Salden Deiner Benutzer zu stützen, oder verwende eine bestimmte Referenz.', 'bonipress' ); ?> <a href="https://n3rds.work/docs/bonipress-referenz-protokoll/" target="_blank"><?php _e( 'Referenzhandbuch', 'bonipress' ); ?></a></small>
</p>

<p class="boniPRESS-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'show_visitors' ) ); ?>"><input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'show_visitors' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'show_visitors' ) ); ?>" value="1"<?php checked( $show_visitors, 1 ); ?> class="checkbox" /> <?php _e( 'Sichtbar für Nichtmitglieder', 'bonipress' ); ?></label>
</p>
<p class="boniPRESS-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>"><?php _e( 'Anzahl der Benutzer', 'bonipress' ); ?>:</label>
	<input id="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'number' ) ); ?>" type="text" value="<?php echo absint( $number ); ?>" size="3" class="widefat" />
</p>
<p class="boniPRESS-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'text' ) ); ?>"><?php _e( 'Zeilenlayout', 'bonipress' ); ?>:</label>
	<textarea name="<?php echo esc_attr( $this->get_field_name( 'text' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'text' ) ); ?>" rows="3" cols="20" class="widefat"><?php echo esc_attr( $text ); ?></textarea>
	<small><?php echo $bonipress->available_template_tags( array( 'general', 'balance' ) ); ?></small>
</p>
<p class="boniPRESS-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'offset' ) ); ?>"><?php _e( 'Offset', 'bonipress' ); ?>:</label>
	<input id="<?php echo esc_attr( $this->get_field_id( 'offset' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'offset' ) ); ?>" type="text" value="<?php echo absint( $offset ); ?>" size="3" class="widefat" />
	<small><?php _e( 'Optional offset of order. Use zero to return the first in the list.', 'bonipress' ); ?></small>
</p>
<p class="boniPRESS-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'order' ) ); ?>"><?php _e( 'Sortierung', 'bonipress' ); ?>:</label> 
	<select name="<?php echo esc_attr( $this->get_field_name( 'order' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'order' ) ); ?>">
<?php

			$options = array(
				'ASC'  => __( 'Aufsteigend', 'bonipress' ),
				'DESC' => __( 'Absteigend', 'bonipress' )
			);

			foreach ( $options as $value => $label ) {
				echo '<option value="' . $value . '"';
				if ( $order == $value ) echo ' selected="selected"';
				echo '>' . $label . '</option>';
			}

?>
	</select>
</p>
<p class="boniPRESS-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'current' ) ); ?>"><input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'current' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'current' ) ); ?>" value="1"<?php checked( $current, 1 ); ?> class="checkbox" />  <?php _e( 'Aktuelle Benutzerposition anhängen', 'bonipress' ); ?></label><br />
	<small><?php _e( 'Wenn sich der aktuelle Benutzer nicht in dieser Bestenliste befindet, kannst Du ihn am Ende an seine aktuelle Position anhängen.', 'bonipress' ); ?></small>
</p>
<p class="boniPRESS-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'timeframe' ) ); ?>"><?php _e( 'Zeitrahmen', 'bonipress' ); ?>:</label>
	<input id="<?php echo esc_attr( $this->get_field_id( 'timeframe' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'timeframe' ) ); ?>" type="text" value="<?php echo esc_attr( $timeframe ); ?>" size="3" class="widefat" />
	<small><?php _e( 'Option zum Begrenzen der Bestenliste basierend auf einem bestimmten Zeitrahmen. Bei Nichtgebrauch leer lassen.', 'bonipress' ); ?></small>
</p>
<?php

		}

		/**
		 * Processes widget options to be saved
		 */
		public function update( $new_instance, $old_instance ) {

			$instance                  = $old_instance;

			$instance['number']        = absint( $new_instance['number'] );
			$instance['title']         = wp_kses_post( $new_instance['title'] );
			$instance['type']          = sanitize_key( $new_instance['type'] );
			$instance['based_on']      = sanitize_key( $new_instance['based_on'] );
			$instance['show_visitors'] = ( isset( $new_instance['show_visitors'] ) ) ? 1 : 0;
			$instance['text']          = wp_kses_post( $new_instance['text'] );
			$instance['offset']        = sanitize_text_field( $new_instance['offset'] );
			$instance['order']         = sanitize_text_field( $new_instance['order'] );
			$instance['current']       = ( isset( $new_instance['current'] ) ) ? 1 : 0;
			$instance['timeframe']     = sanitize_text_field( $new_instance['timeframe'] );

			bonipress_flush_widget_cache( 'bonipress_widget_list' );

			return $instance;

		}

	}
endif;

/**
 * Widget: boniPRESS Wallet
 * @since 1.4
 * @version 1.2
 */
if ( ! class_exists( 'boniPRESS_Widget_Wallet' ) ) :
	class boniPRESS_Widget_Wallet extends WP_Widget {

		/**
		 * Construct
		 */
		public function __construct() {

			parent::__construct(
				'bonipress_widget_wallet',
				sprintf( __( '(%s) Wallet', 'bonipress' ), bonipress_label( true ) ),
				array(
					'classname'   => 'widget-my-wallet',
					'description' => __( 'Zeigt mehrere Salden an.', 'bonipress' )
				)
			);

		}

		/**
		 * Widget Output
		 */
		public function widget( $args, $instance ) {

			extract( $args, EXTR_SKIP );

			$bonipress = bonipress();

			// If we are logged in
			if ( is_user_logged_in() ) {

				if ( ! isset( $instance['types'] ) || empty( $instance['types'] ) )
					$instance['types'] = array( BONIPRESS_DEFAULT_TYPE_KEY );

				// Get Current Users Account Object
				$account = bonipress_get_account();
				if ( $account === false ) return;

				// Excluded users have no balance(s)
				if ( empty( $account->balance ) || empty( $instance['types'] ) ) return;

				// Start
				echo $before_widget;

				// Title
				if ( ! empty( $instance['title'] ) )
					echo $before_title . $instance['title'] . $after_title;

				$current_user = wp_get_current_user();

				// Loop through balances
				foreach ( $account->balance as $point_type_id => $balance ) {

					if ( ! in_array( $point_type_id, (array) $instance['types'] ) ) continue;

					$point_type = bonipress( $point_type_id );

					$layout     = $instance['row'];
					$layout     = $point_type->template_tags_amount( $layout, $balance->current );
					$layout     = $point_type->template_tags_user( $layout, false, $current_user );
					$layout     = str_replace( '%label%', $account->point_types[ $point_type_id ], $layout );

					echo '<div class="boniPRESS-balance bonipress-balance-' . esc_attr( $point_type_id ) . '">' . do_shortcode( $layout ) . '</div>';

				}

				// End
				echo $after_widget;

			}

			// Visitor
			elseif ( ! is_user_logged_in() && $instance['show_visitors'] ) {

				echo $before_widget;

				// Title
				if ( ! empty( $instance['title'] ) )
					echo $before_title . $instance['title'] . $after_title;

				$message = $instance['message'];
				$message = $bonipress->template_tags_general( $message );

				echo '<div class="boniPRESS-wallet-message"><p>' . wptexturize( $message ) . '</p></div>';

				echo $after_widget;

			}

		}

		/**
		 * Outputs the options form on admin
		 */
		public function form( $instance ) {

			$bonipress        = bonipress();

			// Defaults
			$title         = isset( $instance['title'] )         ? $instance['title']         : 'My Wallet';
			$types         = isset( $instance['types'] )         ? $instance['types']         : array();
			$row_template  = isset( $instance['row'] )           ? $instance['row']           : '%label%: %cred_f%';
			$show_visitors = isset( $instance['show_visitors'] ) ? $instance['show_visitors'] : 0;
			$message       = isset( $instance['message'] )       ? $instance['message']       : '<a href="%login_url_here%">Anmelden</a> um Dein Guthaben anzuzeigen.';

?>
<!-- Widget Options -->
<p class="boniPRESS-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php _e( 'Titel', 'bonipress' ); ?>:</label>
	<input id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" class="widefat" />
</p>

<!-- Point Type -->
<p class="boniPRESS-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'types' ) ); ?>"><?php _e( 'Punkttypen', 'bonipress' ); ?>:</label><br />
	<?php bonipress_types_select_from_checkboxes( $this->get_field_name( 'types' ) . '[]', $this->get_field_id( 'types' ), $types ); ?>
</p>

<!-- Row layout -->
<p class="boniPRESS-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'row' ) ); ?>"><?php _e( 'Zeilenlayout', 'bonipress' ); ?>:</label>
	<textarea name="<?php echo esc_attr( $this->get_field_name( 'row' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'row' ) ); ?>" rows="3" cols="20" class="widefat"><?php echo esc_attr( $row_template ); ?></textarea>
	<small><?php echo $bonipress->available_template_tags( array( 'general', 'amount' ) ); ?></small>
</p>

<!-- Show to Visitors -->
<p class="boniPRESS-widget-field">
	<label for="<?php echo esc_attr( $this->get_field_id( 'show_visitors' ) ); ?>"><input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'show_visitors' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'show_visitors' ) ); ?>" value="1"<?php checked( $show_visitors, 1 ); ?> class="checkbox" /> <?php _e( 'Nachricht anzeigen, wenn nicht angemeldet', 'bonipress' ); ?></label>
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

	$( '#<?php echo esc_attr( $this->get_field_id( 'show_visitors' ) ); ?>' ).change(function(){
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

			$instance                  = $old_instance;

			$instance['title']         = wp_kses_post( $new_instance['title'] );
			$instance['types']         = (array) $new_instance['types'];
			$instance['row']           = wp_kses_post( $new_instance['row'] );
			$instance['show_visitors'] = ( isset( $new_instance['show_visitors'] ) ) ? 1 : 0;
			$instance['message']       = wp_kses_post( $new_instance['message'] );

			bonipress_flush_widget_cache( 'bonipress_widget_wallet' );

			return $instance;

		}

	}
endif;