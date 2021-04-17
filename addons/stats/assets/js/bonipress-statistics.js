var boniPRESSCharts = {};
jQuery(function($){

	$(document).ready(function(){

		$.each( boniPRESSStats.charts, function(elementid, data){

			console.log( 'Generating canvas#' + elementid );
			console.log( data );
			boniPRESSCharts[ elementid ] = new Chart( $( 'canvas#' + elementid ).get(0).getContext( '2d' ), data );

		});

	});

});