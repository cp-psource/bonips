/**
 * Accordion
 * @since 0.1
 * @version 1.0
 */
jQuery(function($) {
	if ( boniPS.active != '-1' ) {
		var active_box = parseInt( boniPS.active, 10 );
	}
	else {
		var active_box = false;
	}
	$( "#accordion" ).accordion({ collapsible: true, header: "h4", heightStyle: "content", active: active_box });
});