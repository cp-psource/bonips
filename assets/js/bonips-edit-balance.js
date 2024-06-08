/**
 * boniPS Editor
 * Handles the product type editor in the WordPress admin area on the "Users" page.
 * @since 1.0
 * @version 1.3
 */
jQuery(function($) {

	var boniPStype           = boniPSedit.defaulttype;
	var boniPSuser           = 0;

	var boniPSEditorModal    = $( '#edit-bonips-balance' );
	var boniPSEditorLedger   = $( '#bonips-users-mini-ledger' );
	var boniPSEditorResults  = $( '#bonips-editor-results' );

	var boniPSIDtoShow       = $( '#bonips-userid-to-show' );
	var boniPSUsernametoShow = $( '#bonips-username-to-show' );
	var boniPSCBalancetoShow = $( '#bonips-current-to-show' );
	var boniPSTBalancetoShow = $( '#bonips-total-to-show' );

	var boniPSAmount         = $( 'input#bonips-editor-amount' );
	var boniPSReference      = $( 'select#bonips-editor-reference' );
	var boniPSCustomRefWrap  = $( '#bonips-custom-reference-wrapper' );
	var boniPSCustomRef      = $( 'input#bonips-editor-custom-reference' );
	var boniPSLogEntry       = $( 'input#bonips-editor-entry' );

	var wWidth     = $(window).width();
	var dWidth     = wWidth * 0.75;

	/**
	 * Reset Editor
	 */
	function bonips_reset_editor() {

		var currentreference = boniPSReference.find( ':selected' );
		if ( currentreference !== undefined && currentreference.val() != boniPSedit.ref ) {
			currentreference.removeAttr( 'selected' );
		}

		boniPSAmount.val( '' );
		boniPSCustomRef.val( '' );
		boniPSLogEntry.val( '' );

		$( 'select#bonips-editor-reference option[value="' + boniPSedit.ref + '"]' ).attr( 'selected', 'selected' );
		boniPSCustomRefWrap.hide();

		boniPSuser = 0;

	}

	/**
	 * Animate Balance
	 */
	function bonips_animate_balance( elementtoanimate, finalAmount, decimals ) {

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
		boniPSEditorModal.dialog({
			dialogClass : 'bonips-update-balance',
			draggable   : true,
			autoOpen    : false,
			title       : boniPSedit.title,
			closeText   : boniPSedit.close,
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
		$( '.bonips-open-points-editor' ).on('click', function(e) {

			e.preventDefault();

			boniPStype = $(this).data( 'type' );
			boniPSuser = $(this).data( 'userid' );

			if ( boniPSEditorLedger.hasClass( 'shown' ) )
				boniPSEditorLedger.slideUp().removeClass( 'shown' );

			boniPSEditorResults.empty();

			// Setup the information we show about the user
			boniPSIDtoShow.empty().text( boniPSuser );
			boniPSUsernametoShow.empty().text( $(this).data( 'username' ) );
			boniPSCBalancetoShow.empty().text( $(this).data( 'current' ) );
			boniPSTBalancetoShow.empty().text( $(this).data( 'total' ) );

			$( 'input#bonips-edit-balance-of-user' ).val( boniPSuser );
			$( 'input#bonips-edit-balance-of-type' ).val( boniPStype );

			// Setup amount placeholder
			boniPSAmount.attr( 'placeholder', $(this).data( 'zero' ) );

			console.log( 'Editing ' + $(this).data( 'username' ) + ' s balance' );

			// Show editor
			boniPSEditorModal.dialog( 'open' );

		});

		/**
		 * Toggle custom reference field
		 */
		boniPSReference.on('change', function() {

			var selectedreference = $(this).find( ':selected' );
			if ( selectedreference === undefined ) return false;

			if ( selectedreference.val() == 'bonips_custom' )
				boniPSCustomRefWrap.slideDown();

			else {
				boniPSCustomRefWrap.slideUp();
				boniPSCustomRef.val( '' );
			}

		});

		/**
		 * Toggle mini ledger
		 */
		$( 'button#load-users-bonips-history' ).on('click', function() {

			if ( boniPSEditorLedger.hasClass( 'shown' ) ) {
				boniPSEditorLedger.slideUp(function(){
					$( '#bonips-users-mini-ledger .border' ).empty().html( boniPSedit.loading );
				}).removeClass( 'shown' );
				
			}

			else {

				$( '#bonips-users-mini-ledger .border' ).empty().html( boniPSedit.loading );
				boniPSEditorLedger.slideDown().addClass( 'shown' );
				$(this).attr( 'disabled', 'disabled' );

				$.ajax({
					type       : 'POST',
					data       : {
						action    : 'bonips-admin-recent-activity',
						token     : boniPSedit.ledgertoken,
						userid    : boniPSuser,
						type      : boniPStype
					},
					dataType   : 'HTML',
					url        : boniPSedit.ajaxurl,
					success    : function( response ) {

						$( '#bonips-users-mini-ledger .border #bonips-processing' ).slideUp(function(){
							$( '#bonips-users-mini-ledger .border' ).empty().html( response ).slideDown();
							$( 'button#load-users-bonips-history' ).removeAttr( 'disabled' );
						});

					}
				});

			}

		});

		/**
		 * Editor Submit
		 */
		$( 'form#bonips-editor-form' ).submit( function(e) {

			e.preventDefault();

			$.ajax({
				type       : 'POST',
				data       : {
					action    : 'bonips-admin-editor',
					token     : boniPSedit.token,
					form      : $(this).serialize()
				},
				dataType   : 'JSON',
				url        : boniPSedit.ajaxurl,
				beforeSend : function() {

					// Disable all fields in the form to prevent edits while we submit the form
					$( 'form#bonips-editor-form input' ).attr( 'readonly', 'readonly' );
					$( 'form#bonips-editor-form select' ).attr( 'readonly', 'readonly' );

					if ( boniPSEditorLedger.hasClass( 'shown' ) )
						$( 'button#load-users-bonips-history' ).click();

					// Disable submit button and show that we are working
					$( '#bonips-editor-submit' ).val( boniPSedit.working ).attr( 'disabled', 'disabled' );
					boniPSEditorResults.empty();
					$( '#bonips-editor-indicator' ).addClass( 'is-active' );

				},
				success    : function( response ) {

					$( '#bonips-editor-indicator' ).removeClass( 'is-active' );

					// Security token has expired or something is blocking access to the ajax handler
					if ( response.success === undefined ) {
						boniPSEditorModal.dialog( 'destroy' );
						location.reload();
					}

					console.log( response );

					// Remove form restrictions
					$( 'form#bonips-editor-form input' ).removeAttr( 'readonly' );
					$( 'form#bonips-editor-form select' ).removeAttr( 'readonly' );

					// All went well, clear the form
					if ( response.success ) {

						bonips_animate_balance( boniPSCBalancetoShow, response.data.current, response.data.decimals );
						bonips_animate_balance( boniPSTBalancetoShow, response.data.total, response.data.decimals );

						$( '#bonips-user-' + boniPSuser + '-balance-' + boniPStype + ' span' ).empty().text( response.data.current );
						$( '#bonips-user-' + boniPSuser + '-balance-total-' + boniPStype + ' small span' ).empty().text( response.data.current );

						bonips_reset_editor();

					}

					// Update results
					boniPSEditorResults.html( response.data.results );

					// Reset submit button
					$( '#bonips-editor-submit' ).val( response.data.label ).removeAttr( 'disabled', 'disabled' );

				}

			});

			return false;

		});

	});

});