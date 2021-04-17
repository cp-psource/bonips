/**
 * boniPRESS Editor
 * Handles the product type editor in the WordPress admin area on the "Users" page.
 * @since 1.0
 * @version 1.3
 */
jQuery(function($) {

	var boniPRESStype           = boniPRESSedit.defaulttype;
	var boniPRESSuser           = 0;

	var boniPRESSEditorModal    = $( '#edit-bonipress-balance' );
	var boniPRESSEditorLedger   = $( '#bonipress-users-mini-ledger' );
	var boniPRESSEditorResults  = $( '#bonipress-editor-results' );

	var boniPRESSIDtoShow       = $( '#bonipress-userid-to-show' );
	var boniPRESSUsernametoShow = $( '#bonipress-username-to-show' );
	var boniPRESSCBalancetoShow = $( '#bonipress-current-to-show' );
	var boniPRESSTBalancetoShow = $( '#bonipress-total-to-show' );

	var boniPRESSAmount         = $( 'input#bonipress-editor-amount' );
	var boniPRESSReference      = $( 'select#bonipress-editor-reference' );
	var boniPRESSCustomRefWrap  = $( '#bonipress-custom-reference-wrapper' );
	var boniPRESSCustomRef      = $( 'input#bonipress-editor-custom-reference' );
	var boniPRESSLogEntry       = $( 'input#bonipress-editor-entry' );

	var wWidth     = $(window).width();
	var dWidth     = wWidth * 0.75;

	/**
	 * Reset Editor
	 */
	function bonipress_reset_editor() {

		var currentreference = boniPRESSReference.find( ':selected' );
		if ( currentreference !== undefined && currentreference.val() != boniPRESSedit.ref ) {
			currentreference.removeAttr( 'selected' );
		}

		boniPRESSAmount.val( '' );
		boniPRESSCustomRef.val( '' );
		boniPRESSLogEntry.val( '' );

		$( 'select#bonipress-editor-reference option[value="' + boniPRESSedit.ref + '"]' ).attr( 'selected', 'selected' );
		boniPRESSCustomRefWrap.hide();

		boniPRESSuser = 0;

	}

	/**
	 * Animate Balance
	 */
	function bonipress_animate_balance( elementtoanimate, finalAmount, decimals ) {

		var currentbalance = elementtoanimate.text();

		// Float
		if ( decimals > 0 ) {

			currentbalance = parseFloat( currentbalance );
			finalAmount    = parseFloat( finalAmount );

			var decimal_factor = decimals === 0 ? 1 : Math.pow( 10, decimals );

			elementtoanimate.prop( 'number', currentbalance ).numerator({
				toValue    : finalAmount,
				fromValue  : currentbalance,
				rounding   : decimals,
				duration   : 2000
			});

		}
		// Int
		else {

			currentbalance = parseInt( currentbalance );
			finalAmount    = parseInt( finalAmount );

			elementtoanimate.prop( 'number', currentbalance ).numerator({
				toValue    : finalAmount,
				fromValue  : currentbalance,
				duration   : 2000
			});

		}

	}

	$(document).ready( function() {

		if ( dWidth < 250 )
			dWidth = wWidth;

		if ( dWidth > 960 )
			dWidth = 960;

		/**
		 * Setup Editor Window
		 */
		boniPRESSEditorModal.dialog({
			dialogClass : 'bonipress-update-balance',
			draggable   : true,
			autoOpen    : false,
			title       : boniPRESSedit.title,
			closeText   : boniPRESSedit.close,
			modal       : true,
			width       : dWidth,
			height      : 'auto',
			resizable   : false,
			position    : { my: "center", at: "top+25%", of: window },
			show        : {
				effect     : 'fadeIn',
				duration   : 250
			},
			hide        : {
				effect     : 'fadeOut',
				duration   : 250
			}
		});

		/**
		 * Toggle Editor
		 */
		$( '.bonipress-open-points-editor' ).click( function(e) {

			e.preventDefault();

			boniPRESStype = $(this).data( 'type' );
			boniPRESSuser = $(this).data( 'userid' );

			if ( boniPRESSEditorLedger.hasClass( 'shown' ) )
				boniPRESSEditorLedger.slideUp().removeClass( 'shown' );

			boniPRESSEditorResults.empty();

			// Setup the information we show about the user
			boniPRESSIDtoShow.empty().text( boniPRESSuser );
			boniPRESSUsernametoShow.empty().text( $(this).data( 'username' ) );
			boniPRESSCBalancetoShow.empty().text( $(this).data( 'current' ) );
			boniPRESSTBalancetoShow.empty().text( $(this).data( 'total' ) );

			$( 'input#bonipress-edit-balance-of-user' ).val( boniPRESSuser );
			$( 'input#bonipress-edit-balance-of-type' ).val( boniPRESStype );

			// Setup amount placeholder
			boniPRESSAmount.attr( 'placeholder', $(this).data( 'zero' ) );

			console.log( 'Editing ' + $(this).data( 'username' ) + ' s balance' );

			// Show editor
			boniPRESSEditorModal.dialog( 'open' );

		});

		/**
		 * Toggle custom reference field
		 */
		boniPRESSReference.change(function() {

			var selectedreference = $(this).find( ':selected' );
			if ( selectedreference === undefined ) return false;

			if ( selectedreference.val() == 'bonipress_custom' )
				boniPRESSCustomRefWrap.slideDown();

			else {
				boniPRESSCustomRefWrap.slideUp();
				boniPRESSCustomRef.val( '' );
			}

		});

		/**
		 * Toggle mini ledger
		 */
		$( 'button#load-users-bonipress-history' ).click(function() {

			if ( boniPRESSEditorLedger.hasClass( 'shown' ) ) {
				boniPRESSEditorLedger.slideUp(function(){
					$( '#bonipress-users-mini-ledger .border' ).empty().html( boniPRESSedit.loading );
				}).removeClass( 'shown' );
				
			}

			else {

				$( '#bonipress-users-mini-ledger .border' ).empty().html( boniPRESSedit.loading );
				boniPRESSEditorLedger.slideDown().addClass( 'shown' );
				$(this).attr( 'disabled', 'disabled' );

				$.ajax({
					type       : 'POST',
					data       : {
						action    : 'bonipress-admin-recent-activity',
						token     : boniPRESSedit.ledgertoken,
						userid    : boniPRESSuser,
						type      : boniPRESStype
					},
					dataType   : 'HTML',
					url        : boniPRESSedit.ajaxurl,
					success    : function( response ) {

						$( '#bonipress-users-mini-ledger .border #bonipress-processing' ).slideUp(function(){
							$( '#bonipress-users-mini-ledger .border' ).empty().html( response ).slideDown();
							$( 'button#load-users-bonipress-history' ).removeAttr( 'disabled' );
						});

					}
				});

			}

		});

		/**
		 * Editor Submit
		 */
		$( 'form#bonipress-editor-form' ).submit( function(e) {

			e.preventDefault();

			$.ajax({
				type       : 'POST',
				data       : {
					action    : 'bonipress-admin-editor',
					token     : boniPRESSedit.token,
					form      : $(this).serialize()
				},
				dataType   : 'JSON',
				url        : boniPRESSedit.ajaxurl,
				beforeSend : function() {

					// Disable all fields in the form to prevent edits while we submit the form
					$( 'form#bonipress-editor-form input' ).attr( 'readonly', 'readonly' );
					$( 'form#bonipress-editor-form select' ).attr( 'readonly', 'readonly' );

					if ( boniPRESSEditorLedger.hasClass( 'shown' ) )
						$( 'button#load-users-bonipress-history' ).click();

					// Disable submit button and show that we are working
					$( '#bonipress-editor-submit' ).val( boniPRESSedit.working ).attr( 'disabled', 'disabled' );
					boniPRESSEditorResults.empty();
					$( '#bonipress-editor-indicator' ).addClass( 'is-active' );

				},
				success    : function( response ) {

					$( '#bonipress-editor-indicator' ).removeClass( 'is-active' );

					// Security token has expired or something is blocking access to the ajax handler
					if ( response.success === undefined ) {
						boniPRESSEditorModal.dialog( 'destroy' );
						location.reload();
					}

					console.log( response );

					// Remove form restrictions
					$( 'form#bonipress-editor-form input' ).removeAttr( 'readonly' );
					$( 'form#bonipress-editor-form select' ).removeAttr( 'readonly' );

					// All went well, clear the form
					if ( response.success ) {

						bonipress_animate_balance( boniPRESSCBalancetoShow, response.data.current, response.data.decimals );
						bonipress_animate_balance( boniPRESSTBalancetoShow, response.data.total, response.data.decimals );

						$( '#bonipress-user-' + boniPRESSuser + '-balance-' + boniPRESStype + ' span' ).empty().text( response.data.current );
						$( '#bonipress-user-' + boniPRESSuser + '-balance-total-' + boniPRESStype + ' small span' ).empty().text( response.data.current );

						bonipress_reset_editor();

					}

					// Update results
					boniPRESSEditorResults.html( response.data.results );

					// Reset submit button
					$( '#bonipress-editor-submit' ).val( response.data.label ).removeAttr( 'disabled', 'disabled' );

				}

			});

			return false;

		});

	});

});