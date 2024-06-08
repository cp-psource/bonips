/**
 * Accordion
 * @since 0.1
 * @version 1.1
 */
jQuery(function($) {

	var active_box = false;
	if ( typeof boniPS !== 'undefined' ) {
		if ( boniPS.active != '-1' )
			active_box = parseInt( boniPS.active, 10 );
	}

	$( "#accordion" ).accordion({ collapsible: true, header: "h4", heightStyle: "content", active: active_box });

});