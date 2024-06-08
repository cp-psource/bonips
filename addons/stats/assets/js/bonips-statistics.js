var boniPSCharts = {};
jQuery(function($){

	$(document).ready(function(){

		$.each( boniPSStats.charts, function(elementid, data){

			if( $( 'canvas#' + elementid ).length > 0 ) {
				boniPSStats[ elementid ] = new Chart( $( 'canvas#' + elementid ).get(0).getContext( '2d' ), data );
			}

		});

	});

});