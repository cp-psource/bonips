var boniPRESSCharts = {};
jQuery(function($){

	$(document).ready(function(){

		$.each( boniPRESSStats.charts, function(elementid, data){

			if( $( 'canvas#' + elementid ).length > 0 ) {
				boniPRESSStats[ elementid ] = new Chart( $( 'canvas#' + elementid ).get(0).getContext( '2d' ), data );
			}

		});

	});

});