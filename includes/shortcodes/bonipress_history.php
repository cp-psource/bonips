<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

/**
 * BoniPress Shortcode: bonipress_history
 * Returns the points history.
 * @see https://n3rds.work/docs/bonipress-shortcodes-bonipress_history/
 * @since 1.0.9
 * @version 1.3.4
 */
if ( ! function_exists( 'bonipress_render_shortcode_history' ) ) :
	function bonipress_render_shortcode_history( $atts, $content = '' ) {

		extract( shortcode_atts( array(
			'user_id'    => '',
			'number'     => 10,
			'time'       => '',
			'ref'        => '',
			'order'      => '',
			'show_user'  => 0,
			'show_nav'   => 1,
			'login'      => '',
			'type'       => BONIPRESS_DEFAULT_TYPE_KEY,
			'pagination' => 10,
			'inlinenav'  => 0
		), $atts, BONIPRESS_SLUG . '_history' ) );

		// If we are not logged in
		if ( ! is_user_logged_in() && $login != '' )
			return $login . $content;

		if ( ! BONIPRESS_ENABLE_LOGGING ) return '';

		$user_id = bonipress_get_user_id( $user_id );

		if ( ! bonipress_point_type_exists( $type ) )
			$type = BONIPRESS_DEFAULT_TYPE_KEY;

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

		$log = new boniPRESS_Query_Log( apply_filters( 'bonipress_front_history_args', $args, $atts ) );

		ob_start();

		if ( $inlinenav ) echo '<style type="text/css">.bonipress-history-wrapper ul li { list-style-type: none; display: inline; padding: 0 6px; }</style>';

		do_action( 'bonipress_front_history', $user_id );

?>
<div class="bonipress-history-wrapper">
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
add_shortcode( BONIPRESS_SLUG . '_history', 'bonipress_render_shortcode_history' );
