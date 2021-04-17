/**
 * Accordion
 * @since 0.1
 * @version 1.0
 */
jQuery(function($) {
	if ( boniPRESS.active != '-1' ) {
		var active_box = parseInt( boniPRESS.active, 10 );
	}
	else {
		var active_box = false;
	}
	$( "#accordion" ).accordion({ collapsible: true, header: "h4", heightStyle: "content", active: active_box });
});