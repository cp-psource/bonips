/**
 * boniPRESS Edit Log Scripts
 * These scripts are used to edit or delete entries
 * in the boniPRESS Log.
 * @since 1.4
 * @version 1.2.5
 */
jQuery(function($) {

	var wWidth     = $(window).width();
	var dWidth     = wWidth * 0.75;

	var boniPRESSRowId     = 0;
	var boniPRESSRow       = '';
	var boniPRESSReference = '';

	var boniPRESSEditorModal     = $( '#edit-bonipress-log-entry' );
	var boniPRESSEditorResults   = $( '#bonipress-editor-results' );
	var boniPRESSAvailableTags   = $( '#available-template-tags' );

	var boniPRESSUsertoShow      = $( '#bonipress-user-to-show' );
	var boniPRESSDatetoShow      = $( '#bonipress-date-to-show' );
	var boniPRESSAmounttoShow    = $( '#bonipress-creds-to-show' );
	var boniPRESSReferencetoShow = $( '#bonipress-referece-to-show' );
	var boniPRESSOldEntrytoShow  = $( '#bonipress-old-entry-to-show' );
	var boniPRESSNewEntrytoShow  = $( '#bonipress-new-entry-to-show' );

	/**
	 * Reset Editor
	 */
	function bonipress_reset_editor() {

		boniPRESSEditorResults.empty();
		boniPRESSUsertoShow.empty();
		boniPRESSDatetoShow.empty();
		boniPRESSAmounttoShow.val( '' );
		boniPRESSReferencetoShow.empty();
		boniPRESSOldEntrytoShow.empty();
		boniPRESSNewEntrytoShow.val( '' );
		boniPRESSAvailableTags.empty();

		$.each( boniPRESSLog.references, function( index ){

			var optiontoinsert = '<option value=\"' + index + '\"';
			if ( boniPRESSReference == index ) optiontoinsert += ' selected=\"selected\"';
			optiontoinsert += '>' + boniPRESSLog.references[ index ] + '<\/option>';

			boniPRESSReferencetoShow.append( optiontoinsert );

		});

		$( 'button#bonipress-delete-entry-in-editor' ).attr( 'data', boniPRESSRowId );

	}

	/**
	 * Animate Row Deletion
	 */
	function bonipress_animate_row_deletion( rowtoanimate ) {

		var rowtodelete = $( '#entry-' + rowtoanimate );
		if ( rowtodelete === undefined ) return;

		rowtodelete.addClass( 'deleted-row' ).fadeOut( 2000, function(){
			rowtodelete.remove();
		});

	}

	/**
	 * Animate Row Update
	 */
	function bonipress_animate_row_update( newrow ) {

		var affectedrow = $( '#entry-' + boniPRESSRowId );

		affectedrow.addClass( 'updated-row' ).fadeOut(function(){
			affectedrow.empty().append( newrow ).fadeIn( 2000, function(){
				affectedrow.removeClass( 'updated-row' );
			});
		});

	}

	/**
	 * Update Log Entry
	 */
	function bonipress_update_entry( submission ) {

		var submitbutton = $( '#bonipress-editor-submit' );
		var submitlabel  = submitbutton.val();

		$.ajax({
			type       : "POST",
			data       : {
				action    : 'bonipress-update-log-entry',
				token     : boniPRESSLog.tokens.update,
				screen    : boniPRESSLog.screen,
				page      : boniPRESSLog.page,
				rowid     : boniPRESSRowId,
				ctype     : boniPRESSLog.ctype,
				form      : submission
			},
			dataType   : "JSON",
			url        : boniPRESSLog.ajaxurl,
			beforeSend : function() {

				boniPRESSAmounttoShow.attr( 'readonly', 'readonly' );
				boniPRESSReferencetoShow.attr( 'readonly', 'readonly' );
				boniPRESSNewEntrytoShow.attr( 'readonly', 'readonly' );

				// Prep results box
				boniPRESSEditorResults.empty();
				$( '#bonipress-editor-indicator' ).addClass( 'is-active' );

				// Indicate that we are doing something
				submitbutton.empty().text( boniPRESSLog.working );

			},
			success    : function( response ) {

				// Remove indicator
				$( '#bonipress-editor-indicator' ).removeClass( 'is-active' );

				// Most likelly the wpnonce has expired (screen open too long)
				if ( response.success === undefined ) {
					boniPRESSEditorModal.dialog( 'destroy' );
					location.reload();
				}

				// Ok, something was done
				else {

					boniPRESSAmounttoShow.removeAttr( 'readonly' );
					boniPRESSReferencetoShow.removeAttr( 'readonly' );
					boniPRESSNewEntrytoShow.removeAttr( 'readonly' );

					submitbutton.empty().text( submitlabel );

					boniPRESSEditorResults.text( response.data.message );

					if ( response.success === true ) {
						bonipress_animate_row_update( response.data.results );
					}

				}

			}
		});

	}

	/**
	 * Delete Log Entry
	 */
	function bonipress_delete_entry( entryid ) {

		var ismodalopen  = boniPRESSEditorModal.dialog( "isOpen" );
		var deletebutton = $( '#bonipress-delete-entry-in-editor' );
		var deletelabel  = deletebutton.text();

		$.ajax({
			type       : "POST",
			data       : {
				action    : 'bonipress-delete-log-entry',
				token     : boniPRESSLog.tokens.delete,
				ctype     : boniPRESSLog.ctype,
				row       : entryid
			},
			dataType   : "JSON",
			url        : boniPRESSLog.ajaxurl,
			beforeSend : function() {

				if ( ismodalopen === true ) {

					// Make sure we can not make adjustments while we wait fo the AJAX handler to get back to us
					boniPRESSAmounttoShow.attr( 'readonly', 'readonly' );
					boniPRESSReferencetoShow.attr( 'readonly', 'readonly' );
					boniPRESSNewEntrytoShow.attr( 'readonly', 'readonly' );

					// Prep results box
					boniPRESSEditorResults.empty();
					$( '#bonipress-editor-indicator' ).addClass( 'is-active' );

					// Indicate that we are doing something
					deletebutton.empty().text( boniPRESSLog.working );

				}

			},
			success    : function( response ) {

				// Remove indicator
				$( '#bonipress-editor-indicator' ).removeClass( 'is-active' );

				// Most likelly the wpnonce has expired (screen open too long)
				if ( response.success === undefined ) {
					boniPRESSEditorModal.dialog( 'destroy' );
					location.reload();
				}

				// Ok, something was done
				else {

					// Act based on where we clicked to delete - In modal
					if ( ismodalopen === true ) {

						boniPRESSEditorResults.text( response.data );

						// Request failed for some reason, restore form usability
						if ( response.success !== true ) {

							boniPRESSAmounttoShow.removeAttr( 'readonly' );
							boniPRESSReferencetoShow.removeAttr( 'readonly' );
							boniPRESSNewEntrytoShow.removeAttr( 'readonly' );

							deletebutton.empty().text( deletelabel );

						}

						// All good. Close Dialog
						else {

							// Reset row id
							boniPRESSRowId = 0;

							// Restore button label for next opening
							deletebutton.empty().text( deletelabel );

							// Close dialog window
							boniPRESSEditorModal.dialog( 'close' );

						}

					}

					// In table
					else {

						if ( response.success !== true )
							alert( response.data );

					}

					// No matter which button we pressed, animate the row removal if successfull
					if ( response.success === true )
						bonipress_animate_row_deletion( entryid );

				}

			}
		});

	}

	/**
	 * Once Ready
	 */
	$(document).ready( function() {

		// Adjust modal width based on device width
		if ( dWidth < 250 )
			dWidth = wWidth;

		if ( dWidth > 960 )
			dWidth = 960;

		/**
		 * Setup Editor Window
		 */
		boniPRESSEditorModal.dialog({
			dialogClass : 'bonipress-edit-logentry',
			draggable   : true,
			autoOpen    : false,
			title       : boniPRESSLog.title,
			closeText   : boniPRESSLog.close,
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
		$( 'tbody#the-list' ).on( 'click', '.bonipress-open-log-entry-editor', function(e) {

			e.preventDefault();

			boniPRESSRowId     = $(this).data( 'id' );
			boniPRESSReference = $(this).data( 'ref' );
			boniPRESSRow       = '#entry-' + boniPRESSRowId;

			bonipress_reset_editor();

			boniPRESSUsertoShow.append( $( boniPRESSRow + ' td.column-username strong' ).text() );
			boniPRESSDatetoShow.append( $( boniPRESSRow + ' td.column-time time' ).text() );
			boniPRESSOldEntrytoShow.append( $( boniPRESSRow + ' td.column-entry' ).text() );
			boniPRESSNewEntrytoShow.val( $( boniPRESSRow + ' td.column-entry' ).data( 'raw' ) );

			var amounttoshow = $( boniPRESSRow + ' td.column-creds' ).data( 'raw' );
			boniPRESSAmounttoShow.val( amounttoshow ).attr( 'placeholder', amounttoshow );

			// Show editor
			boniPRESSEditorModal.dialog( 'open' );

		});

		/**
		 * Trigger Log Deletion
		 */
		$( 'tbody#the-list' ).on( 'click', '.bonipress-delete-row', function(){

			// Require user to confirm deletion (if used)
			if ( boniPRESSLog.messages.delete_row != '' && ! confirm( boniPRESSLog.messages.delete ) )
				return false;

			var deletebutton = $(this);
			var rowtodelete  = deletebutton.data( 'id' );

			if ( rowtodelete === undefined || rowtodelete == '' )
				rowtodelete = boniPRESSRowId;
			else
				boniPRESSRowId = rowtodelete;

			bonipress_delete_entry( rowtodelete );

		});
		$( '#bonipress-delete-entry-in-editor' ).on( 'click', function(e){

			e.preventDefault();

			// Require user to confirm deletion (if used)
			if ( boniPRESSLog.messages.delete_row != '' && ! confirm( boniPRESSLog.messages.delete ) )
				return false;

			var deletebutton = $(this);
			var rowtodelete  = deletebutton.data( 'id' );

			if ( rowtodelete === undefined || rowtodelete == '' )
				rowtodelete = boniPRESSRowId;
			else
				boniPRESSRowId = rowtodelete;

			bonipress_delete_entry( rowtodelete );

		});

		/**
		 * Submit New Log Entry
		 */
		$( '#edit-bonipress-log-entry' ).on( 'submit', 'form#bonipress-editor-form', function(e){

			e.preventDefault();

			bonipress_update_entry( $(this).serialize() );

		});

	});

	// Checkbox select in table
	// @see http://stackoverflow.com/questions/19164816/jquery-select-all-checkboxes-in-table
	$( '#boniPRESS-wrap form table thead .check-column input' ).click(function(e){
		var table= $(e.target).closest('table');
		$('.check-column input',table).prop( 'checked',this.checked );
	});

	/**
	 * Click To Toggle Script
	 */
	$( '.click-to-toggle' ).click(function(){

		var target = $(this).attr( 'data-toggle' );
		$( '#' + target ).toggle();

	});

});