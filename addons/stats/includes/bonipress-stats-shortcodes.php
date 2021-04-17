<?php
if ( ! defined( 'boniPRESS_STATS_VERSION' ) ) exit;

/**
 * Shortcode: Circulation
 * @see http://codex.bonipress.me/shortcodes/bonipress_chart_circulation/
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_render_chart_circulation' ) ) :
	function bonipress_render_chart_circulation( $atts, $no_data = '' ) {

		extract( shortcode_atts( array(
			'type'    => 'pie',
			'title'   => '',
			'animate' => 1,
			'bezier'  => 1,
			'labels'  => 1,
			'legend'  => 1,
			'height'  => '',
			'width'   => ''
		), $atts, BONIPRESS_SLUG . '_chart_circulation' ) );

		// Make sure we request a chart type that we support
		$type  = ( ! in_array( $type, array( 'pie', 'doughnut', 'line', 'bar', 'radar', 'polarArea' ) ) ) ? 'pie' : $type;

		// Get data
		$data  = bonipress_get_circulation_data();
		if ( empty( $data ) ) return $no_data;

		// New Chart Object
		$chart = bonipress_create_chart( array(
			'type'     => $type,
			'title'    => $title,
			'animate'  => (bool) $animate,
			'bezier'   => (bool) $bezier,
			'x_labels' => (bool) $labels,
			'legend'   => (bool) $legend,
			'height'   => $height,
			'width'    => $width
		) );

		return $chart->generate_canvas( $type, $data );

	}
endif;

/**
 * Shortcode: Gains vs. Losses
 * @see http://codex.bonipress.me/shortcodes/bonipress_chart_gain_loss/
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_render_chart_gain_vs_loss' ) ) :
	function bonipress_render_chart_gain_vs_loss( $atts, $no_data = '' ) {

		extract( shortcode_atts( array(
			'type'    => 'pie',
			'ctype'   => BONIPRESS_DEFAULT_TYPE_KEY,
			'title'   => '',
			'animate' => 1,
			'bezier'  => 1,
			'labels'  => 1,
			'legend'  => 1,
			'height'  => '',
			'width'   => '',
			'gains'   => '',
			'losses'  => ''
		), $atts, BONIPRESS_SLUG . '_chart_gain_loss' ) );

		// Make sure we request a chart type that we support
		$type  = ( ! in_array( $type, array( 'pie', 'doughnut', 'line', 'bar', 'polarArea' ) ) ) ? 'pie' : $type;

		// Get data
		$data  = bonipress_get_gains_vs_losses_data( $ctype );
		if ( empty( $data ) ) return $no_data;

		// If we want to customize labels
		if ( ! empty( $gains ) )
			$data[0]->label = $gains;

		if ( ! empty( $losses ) )
			$data[1]->label = $losses;

		// New Chart Object
		$chart = bonipress_create_chart( array(
			'type'     => $type,
			'title'    => $title,
			'animate'  => (bool) $animate,
			'bezier'   => (bool) $bezier,
			'x_labels' => (bool) $labels,
			'legend'   => (bool) $legend,
			'height'   => $height,
			'width'    => $width
		) );

		return $chart->generate_canvas( $type, $data );

	}
endif;

/**
 * Shortcode: Point Verlauf
 * @see http://codex.bonipress.me/shortcodes/bonipress_chart_history/
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_render_chart_history' ) ) :
	function bonipress_render_chart_history( $atts, $no_data = '' ) {

		extract( shortcode_atts( array(
			'type'    => 'line',
			'ctype'   => BONIPRESS_DEFAULT_TYPE_KEY,
			'period'  => 'days',
			'length'  => 10,
			'order'   => 'DESC',
			'title'   => '',
			'animate' => 1,
			'bezier'  => 1,
			'labels'  => 1,
			'legend'  => 1,
			'height'  => '',
			'width'   => ''
		), $atts, BONIPRESS_SLUG . '_chart_history' ) );

		// Make sure we request a chart type that we support
		$type  = ( ! in_array( $type, array( 'line', 'bar' ) ) ) ? 'line' : $type;

		// Get data
		$data  = bonipress_get_history_data( $ctype, $period, $length, $order );
		if ( empty( $data ) ) return $no_data;

		// New Chart Object
		$chart = bonipress_create_chart( array(
			'type'     => $type,
			'title'    => $title,
			'animate'  => (bool) $animate,
			'bezier'   => (bool) $bezier,
			'x_labels' => (bool) $labels,
			'legend'   => (bool) $legend,
			'height'   => $height,
			'width'    => $width
		) );

		return $chart->generate_canvas( $type, $data );

	}
endif;

/**
 * Shortcode: Top Balances
 * @see http://codex.bonipress.me/shortcodes/bonipress_chart_top_balances/
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_render_chart_top_balances' ) ) :
	function bonipress_render_chart_top_balances( $atts, $no_data = '' ) {

		extract( shortcode_atts( array(
			'type'    => 'bar',
			'ctype'   => BONIPRESS_DEFAULT_TYPE_KEY,
			'number'  => 10,
			'order'   => 'DESC',
			'title'   => '',
			'animate' => 1,
			'bezier'  => 1,
			'labels'  => 1,
			'legend'  => 1,
			'height'  => '',
			'width'   => ''
		), $atts, BONIPRESS_SLUG . '_chart_top_balances' ) );

		// Make sure we request a chart type that we support
		$type  = ( ! in_array( $type, array( 'pie', 'doughnut', 'line', 'bar', 'radar', 'polarArea' ) ) ) ? 'bar' : $type;

		// Get data
		$data  = bonipress_get_top_balances_data( $ctype, $number, $order );
		if ( empty( $data ) ) return $no_data;

		// New Chart Object
		$chart = bonipress_create_chart( array(
			'type'     => $type,
			'title'    => $title,
			'animate'  => (bool) $animate,
			'bezier'   => (bool) $bezier,
			'x_labels' => (bool) $labels,
			'legend'   => (bool) $legend,
			'height'   => $height,
			'width'    => $width
		) );

		return $chart->generate_canvas( $type, $data );

	}
endif;

/**
 * Shortcode: Top Instances
 * @see http://codex.bonipress.me/shortcodes/bonipress_chart_top_instances/
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_render_chart_top_instances' ) ) :
	function bonipress_render_chart_top_instances( $atts, $no_data = '' ) {

		extract( shortcode_atts( array(
			'type'    => 'bar',
			'ctype'   => BONIPRESS_DEFAULT_TYPE_KEY,
			'number'  => 10,
			'order'   => 'DESC',
			'title'   => '',
			'animate' => 1,
			'bezier'  => 1,
			'labels'  => 1,
			'legend'  => 1,
			'height'  => '',
			'width'   => ''
		), $atts, BONIPRESS_SLUG . '_chart_top_instances' ) );

		// Make sure we request a chart type that we support
		$type  = ( ! in_array( $type, array( 'pie', 'doughnut', 'line', 'bar', 'radar', 'polarArea' ) ) ) ? 'pie' : $type;

		// Get data
		$data  = bonipress_get_top_instances_data( $ctype, $number, $order );
		if ( empty( $data ) ) return $no_data;

		// New Chart Object
		$chart = bonipress_create_chart( array(
			'type'     => $type,
			'title'    => $title,
			'animate'  => (bool) $animate,
			'bezier'   => (bool) $bezier,
			'x_labels' => (bool) $labels,
			'legend'   => (bool) $legend,
			'height'   => $height,
			'width'    => $width
		) );

		return $chart->generate_canvas( $type, $data );

	}
endif;

/**
 * Shortcode: Balance Verlauf
 * @see http://codex.bonipress.me/shortcodes/bonipress_chart_balance_history/
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_render_chart_balance_history' ) ) :
	function bonipress_render_chart_balance_history( $atts, $no_data = '' ) {

		extract( shortcode_atts( array(
			'type'    => 'line',
			'ctype'   => BONIPRESS_DEFAULT_TYPE_KEY,
			'user'    => 'current',
			'period'  => 'days',
			'length'  => 10,
			'order'   => 'DESC',
			'title'   => '',
			'animate' => 1,
			'bezier'  => 1,
			'labels'  => 1,
			'legend'  => 1,
			'height'  => '',
			'width'   => ''
		), $atts, BONIPRESS_SLUG . '_chart_balance_history' ) );

		if ( $user == 'current' && ! is_user_logged_in() ) return $no_data;

		$user_id = bonipress_get_user_id( $user );

		// Make sure we request a chart type that we support
		$type  = ( ! in_array( $type, array( 'line', 'bar' ) ) ) ? 'line' : $type;

		// Get data
		$data  = bonipress_get_users_history_data( $user_id, $ctype, $period, $length, $order );
		if ( empty( $data ) ) return $no_data;

		// New Chart Object
		$chart = bonipress_create_chart( array(
			'type'     => $type,
			'title'    => $title,
			'animate'  => (bool) $animate,
			'bezier'   => (bool) $bezier,
			'x_labels' => (bool) $labels,
			'legend'   => (bool) $legend,
			'height'   => $height,
			'width'    => $width
		) );

		return $chart->generate_canvas( $type, $data );

	}
endif;

/**
 * Shortcode: Reference Verlauf
 * @see http://codex.bonipress.me/shortcodes/bonipress_chart_instance_history/
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_render_chart_instance_history' ) ) :
	function bonipress_render_chart_instance_history( $atts, $no_data = '' ) {

		extract( shortcode_atts( array(
			'type'    => 'line',
			'ctype'   => BONIPRESS_DEFAULT_TYPE_KEY,
			'ref'     => '',
			'period'  => 'days',
			'length'  => 10,
			'order'   => 'DESC',
			'title'   => '',
			'animate' => 1,
			'bezier'  => 1,
			'labels'  => 1,
			'legend'  => 1,
			'height'  => '',
			'width'   => ''
		), $atts, BONIPRESS_SLUG . '_chart_instance_history' ) );

		if ( empty( $ref ) ) return $no_data;

		// Make sure we request a chart type that we support
		$type  = ( ! in_array( $type, array( 'line', 'bar', 'radar' ) ) ) ? 'line' : $type;

		// Get data
		$data  = bonipress_get_ref_history_data( $ref, $ctype, $period, $length, $order );
		if ( empty( $data ) ) return $no_data;

		// New Chart Object
		$chart = bonipress_create_chart( array(
			'type'     => $type,
			'title'    => $title,
			'animate'  => (bool) $animate,
			'bezier'   => (bool) $bezier,
			'x_labels' => (bool) $labels,
			'legend'   => (bool) $legend,
			'height'   => $height,
			'width'    => $width
		) );

		return $chart->generate_canvas( $type, $data );

	}
endif;
