<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

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
                    'description' => __( 'Bestenliste basierend auf Instanzen oder Salden.', 'bonipress' )
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
                'template' => $instance['row_layout'],
                'type'     => $instance['type'],
                'based_on' => $instance['based_on'],
                'total' => $instance['total'],
                'timeframe' => $instance['timeframe'],
                'wrap' => $instance['wrap'],
                'nothing' => $instance['nothing'],
                'exclude_zero' => $instance['exclude_zero']
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
            $total         = isset( $instance['total'] )         ? $instance['total']         : 0;
 
            $number        = isset( $instance['number'] )        ? $instance['number']        : 5;
            $show_visitors = isset( $instance['show_visitors'] ) ? $instance['show_visitors'] : 0;
            $row_layout    = isset( $instance['row_layout'] )    ? $instance['row_layout']    : '<span>#%position%</span> <span>%user_profile_link%</span> <span>%cred_f%</span>';
            $offset        = isset( $instance['offset'] )        ? $instance['offset']        : 0;
            $order         = isset( $instance['order'] )         ? $instance['order']         : 'DESC';
            $current       = isset( $instance['current'] )       ? $instance['current']       : 0;
            $timeframe     = isset( $instance['timeframe'] )     ? $instance['timeframe']     : '';
            $wrap          = isset( $instance['wrap'] )          ? $instance['wrap']          : 'li';
            $nothing       = isset( $instance['nothing'] )       ? $instance['nothing']       : 'Bestenliste ist leer';
            $exclude_zero  = isset( $instance['exclude_zero'] )  ? $instance['exclude_zero']  : 1;
 
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
    <small><?php _e( 'Verwende "Guthaben", um die Rangliste auf dem aktuellen Guthaben Deiner Benutzer zu basieren, oder verwende eine bestimmte Referenz.', 'bonipress' ); ?> <a href="https://n3rds.work/docs/bonipress-protokollreferenzen/" target="_blank"><?php _e( 'Reference Guide', 'bonipress' ); ?></a></small>
</p>
 
<p class="boniPRESS-widget-field">
    <label for="<?php echo esc_attr( $this->get_field_id( 'total' ) ); ?>"><?php _e( 'Gesamt', 'bonipress' ); ?>:</label>
    <input id="<?php echo esc_attr( $this->get_field_id( 'total' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'total' ) ); ?>" type="text" value="<?php echo esc_attr( $total ); ?>" class="widefat" />
    <small><?php _e( 'Wenn Du eine Bestenliste basierend auf Guthaben anzeigst, kannst Du auswählen, ob Du das Gesamtguthaben des Benutzers (1) anstelle seines aktuellen Guthabens (0) verwenden möchtest.', 'bonipress' ); ?> </small>
</p>
 
<p class="boniPRESS-widget-field">
    <label for="<?php echo esc_attr( $this->get_field_id( 'wrap' ) ); ?>"><?php _e( 'Wrap', 'bonipress' ); ?>:</label>
    <input id="<?php echo esc_attr( $this->get_field_id( 'wrap' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'wrap' ) ); ?>" type="text" value="<?php echo esc_attr( $wrap ); ?>" class="widefat" />
    <small><?php _e( 'Das Umbruchelement, das für die Liste verwendet werden soll. Standardmäßig rendert die Rangliste eine organisierte Liste (ol) und jedes Element verwendet ein Listenelement (li).', 'bonipress' ); ?> </small>
</p>
 
<p class="boniPRESS-widget-field">
    <label for="<?php echo esc_attr( $this->get_field_id( 'nothing' ) ); ?>"><?php _e( 'Nichts', 'bonipress' ); ?>:</label>
    <input id="<?php echo esc_attr( $this->get_field_id( 'nothing' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'nothing' ) ); ?>" type="text" value="<?php echo esc_attr( $nothing ); ?>" class="widefat" />
    <small><?php _e( 'Die Nachricht, die Benutzern angezeigt werden soll, wenn die Bestenliste leer ist.', 'bonipress' ); ?></small>
</p>
 
<p class="boniPRESS-widget-field">
    <label for="<?php echo esc_attr( $this->get_field_id( 'exclude_zero' ) ); ?>"><?php _e( 'Null ausschließen', 'bonipress' ); ?>:</label>
    <input id="<?php echo esc_attr( $this->get_field_id( 'exclude_zero' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'exclude_zero' ) ); ?>" type="text" value="<?php echo esc_attr( $exclude_zero ); ?>" class="widefat" />
    <small><?php _e( 'Option zum Herausfiltern von Benutzern mit null Salden/Ergebnissen. Verwende 1 zum Aktivieren und 0 zum Deaktivieren.', 'bonipress' ); ?> </small>
</p>
 
<p class="boniPRESS-widget-field">
    <label for="<?php echo esc_attr( $this->get_field_id( 'show_visitors' ) ); ?>"><input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'show_visitors' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'show_visitors' ) ); ?>" value="1"<?php checked( $show_visitors, 1 ); ?> class="checkbox" /> <?php _e( 'Für Nichtmitglieder sichtbar', 'bonipress' ); ?></label>
</p>
<p class="boniPRESS-widget-field">
    <label for="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>"><?php _e( 'Anzahl der Nutzer', 'bonipress' ); ?>:</label>
    <input id="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'number' ) ); ?>" type="text" value="<?php echo absint( $number ); ?>" size="3" class="widefat" />
</p>
<p class="boniPRESS-widget-field">
    <label for="<?php echo esc_attr( $this->get_field_id( 'row_layout' ) ); ?>"><?php _e( 'Reihenlayout', 'bonipress' ); ?>:</label>
    <textarea name="<?php echo esc_attr( $this->get_field_name( 'row_layout' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'row_layout' ) ); ?>" rows="3" cols="20" class="widefat"><?php echo esc_attr( $row_layout ); ?></textarea>
    <small><?php echo $bonipress->available_template_tags( array( 'general', 'balance' ) ); ?></small>
</p>
<p class="boniPRESS-widget-field">
    <label for="<?php echo esc_attr( $this->get_field_id( 'offset' ) ); ?>"><?php _e( 'Versatz', 'bonipress' ); ?>:</label>
    <input id="<?php echo esc_attr( $this->get_field_id( 'offset' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'offset' ) ); ?>" type="text" value="<?php echo absint( $offset ); ?>" size="3" class="widefat" />
    <small><?php _e( 'Optionaler Versatz der Bestellung. Verwende Null, um den ersten in der Liste zurückzugeben.', 'bonipress' ); ?></small>
</p>
<p class="boniPRESS-widget-field">
    <label for="<?php echo esc_attr( $this->get_field_id( 'order' ) ); ?>"><?php _e( 'Reihenfolge', 'bonipress' ); ?>:</label> 
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
    <label for="<?php echo esc_attr( $this->get_field_id( 'current' ) ); ?>"><input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'current' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'current' ) ); ?>" value="1"<?php checked( $current, 1 ); ?> class="checkbox" />  <?php _e( 'Position des aktuellen Benutzers anhängen', 'bonipress' ); ?></label><br />
    <small><?php _e( 'Wenn sich der aktuelle Benutzer nicht in dieser Rangliste befindet, kannst Du ihn mit seiner aktuellen Position am Ende anhängen.', 'bonipress' ); ?></small>
</p>
<p class="boniPRESS-widget-field">
    <label for="<?php echo esc_attr( $this->get_field_id( 'timeframe' ) ); ?>"><?php _e( 'Zeitrahmen', 'bonipress' ); ?>:</label>
    <input id="<?php echo esc_attr( $this->get_field_id( 'timeframe' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'timeframe' ) ); ?>" type="text" value="<?php echo esc_attr( $timeframe ); ?>" size="3" class="widefat" />
    <small><?php _e( 'Option zum Begrenzen der Rangliste basierend auf einem bestimmten Zeitrahmen. Leer lassen, wenn nicht verwendet.', 'bonipress' ); ?></small>
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
            $instance['total']         = sanitize_key( $new_instance['total'] );
            $instance['show_visitors'] = ( isset( $new_instance['show_visitors'] ) ) ? 1 : 0;
            $instance['row_layout']    = wp_kses_post( $new_instance['row_layout'] );
            $instance['offset']        = sanitize_text_field( $new_instance['offset'] );
            $instance['order']         = sanitize_text_field( $new_instance['order'] );
            $instance['current']       = ( isset( $new_instance['current'] ) ) ? 1 : 0;
            $instance['timeframe']     = sanitize_text_field( $new_instance['timeframe'] );
            $instance['wrap']     	   = sanitize_text_field( $new_instance['wrap'] );
            $instance['nothing']       = sanitize_text_field( $new_instance['nothing'] );
            $instance['exclude_zero']  = sanitize_text_field( $new_instance['exclude_zero'] );
 
            bonipress_flush_widget_cache( 'bonipress_widget_list' );
 
            return $instance;
 
        }
 
    }
endif;