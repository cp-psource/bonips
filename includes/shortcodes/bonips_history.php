<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * BoniPress Shortcode: bonips_history
 * Returns the points history.
 * @see https://github.com/cp-psource/docs/bonips-shortcodes-bonips_history/
 * @since 1.0.9
 * @version 1.3.4
 */
if ( ! function_exists( 'bonips_render_shortcode_history' ) ) :
	function bonips_render_shortcode_history( $atts, $content = '' ) {

		extract( shortcode_atts( array(
			'user_id'    => '',
			'number'     => 10,
			'time'       => '',
			'ref'        => '',
			'order'      => '',
			'show_user'  => 0,
			'show_nav'   => 1,
			'login'      => '',
			'type'       => BONIPS_DEFAULT_TYPE_KEY,
			'pagination' => 10,
			'inlinenav'  => 0
		), $atts, BONIPS_SLUG . '_history' ) );

		// If we are not logged in
		if ( ! is_user_logged_in() && $login != '' )
			return $login . $content;

		if ( ! BONIPS_ENABLE_LOGGING ) return '';

		$user_id = bonips_get_user_id( $user_id );

		if ( ! bonips_point_type_exists( $type ) )
			$type = BONIPS_DEFAULT_TYPE_KEY;

		$args    = array( 'ctype' => $type );

		if ( $user_id != 0 && $user_id != '' )
			$args['user_id'] = absint( $user_id );

		if ( absint( $number ) > 0 )
			$args['number'] = absint( $number );

		if ( $time != '' )
			$args['time'] = $time;

		if ( $ref != '' )
			$args['ref'] = $ref;

		if ( $order != '' )
			$args['order'] = $order;

		$log = new boniPS_Query_Log( apply_filters( 'bonips_front_history_args', $args, $atts ) );

		ob_start();

		if ( $inlinenav ) echo '<style type="text/css">.bonips-history-wrapper ul li { list-style-type: none; display: inline; padding: 0 6px; }</style>';

		do_action( 'bonips_front_history', $user_id );

?>
<div class="bonips-history-wrapper">
<form class="form-inline" role="form" method="get" action="">

	<?php if ( $show_nav == 1 ) $log->front_navigation( 'top', $pagination ); ?>

	<?php $log->display(); ?>

	<?php if ( $show_nav == 1 ) $log->front_navigation( 'bottom', $pagination ); ?>

</form>
</div>
<?php

		$content = ob_get_contents();
		ob_end_clean();

		$log->reset_query();

		return $content;

	}
endif;
add_shortcode( BONIPS_SLUG . '_history', 'bonips_render_shortcode_history' );
