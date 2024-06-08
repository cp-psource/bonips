/**
 * Viewing Videos Core
 * @since 1.2
 * @version 1.1
 */
var timer    = 0;

var actions  = {};
var seconds  = {};
var logic    = {};
var interval = {};
var duration = {};

var done     = {};

/**
 * View Handler
 * @since 1.2
 * @version 1.1
 */
function bonips_view_video( id, state, custom_logic, custom_interval, key, ctype ) {

	var videoid = id;

	var videostate = state;

	if ( actions[ id ] === undefined )
		actions[ id ] = '';

	if ( seconds[ id ] === undefined )
		seconds[ id ] = 0;

	// Logic override
	if ( custom_logic == '0' )
		logic[ id ] = boniPS_Video.default_logic;
	else
		logic[ id ] = custom_logic;

	// Interval override
	if ( custom_interval == '0' )
		interval[ id ] = parseInt( boniPS_Video.default_interval, 10 );
	else
		interval[ id ] = parseInt( custom_interval, 10 );

	// Ready
	if ( videostate != '-1' ) {

		// Points when video starts
		if ( logic[ id ] == 'play' ) {
			// As soon as we start playing we award points
			if ( videostate == 1 && done[ id ] === undefined )
				bonips_video_call( videoid, key, videostate, '', '', ctype );
		}

		// Points first when video has ended
		else if ( logic[ id ] == 'full' ) {
	
			actions[ id ] = actions[ id ]+state.toString();

			// Play
			if ( state == 1 ) {
				// Start timer
				timer = setInterval( function() {
					seconds[ id ] = seconds[ id ] + 1;
				}, 1000 );
			}

			// Finished
			else if ( state == 0 ) {
				// Stop timer
				clearInterval( timer );

				// Notify boniPS
				bonips_video_call( videoid, key, videostate, actions[ videoid ], seconds[ videoid ], ctype );

				// Reset
				seconds[ id ] = 0;
				actions[ id ] = '';
			}

			// All else
			else {
				// Stop Timer
				clearInterval( timer );
			}
		}

		// Points per x number of seconds played
		else if ( logic[ id ] == 'interval' ) {
			// Update actions
			actions[ id ] = actions[ id ]+state.toString();

			// Video is playing
			if ( state == 1 ) {
				// Start timer
				timer = window.setInterval( function() {
					var laps = parseInt( interval[ id ] / 1000, 10 );
					seconds[ id ] = seconds[ id ] + laps;
					// key, state, id, actions, seconds, duration
					bonips_video_call( videoid, key, videostate, actions[ videoid ], seconds[ videoid ], ctype );
				}, interval[ id ] );
			}

			// Video has ended
			else if ( state == 0 ) {
				clearInterval( timer );
				bonips_video_call( videoid, key, videostate, actions[ videoid ], seconds[ videoid ], ctype );

				seconds[ id ] = 0;
				actions[ id ] = '';
			}

			// All else
			else {
				// Stop Timer
				clearInterval( timer );
			}
		}
	}
}

/**
 * AJAX call handler
 * @since 1.2
 * @version 1.1
 */
function bonips_video_call( id, key, state, actions, seconds, pointtype ) {

	if ( done[ id ] === undefined ) {

		if ( duration[ id ] === undefined )
			duration[ id ] = 0;

		jQuery.ajax({
			type       : "POST",
			data       : {
				action   : 'bonips-viewing-videos',
				token    : boniPS_Video.token,
				setup    : key,
				video_a  : actions,
				video_b  : seconds,
				video_c  : duration[ id ],
				video_d  : state,
				type     : pointtype
			},
			dataType   : "JSON",
			url        : boniPS_Video.ajaxurl,
			success    : function( response ) {

				// Add to done list
				if ( response.status === 'max' )
					done[ id ] = response.amount;

			}
		});
	}
}