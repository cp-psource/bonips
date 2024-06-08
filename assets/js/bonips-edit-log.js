/**
 * boniPS Edit Log Scripts
 * These scripts are used to edit or delete entries
 * in the boniPS Log.
 * @since 1.4
 * @version 1.2.5
 */
jQuery(function($) {

	var wWidth     = $(window).width();
	var dWidth     = wWidth * 0.75;

	var boniPSRowId     = 0;
	var boniPSRow       = '';
	var boniPSReference = '';

	var boniPSEditorModal     = $( '#edit-bonips-log-entry' );
	var boniPSEditorResults   = $( '#bonips-editor-results' );
	var boniPSAvailableTags   = $( '#available-template-tags' );

	var boniPSUsertoShow      = $( '#bonips-user-to-show' );
	var boniPSDatetoShow      = $( '#bonips-date-to-show' );
	var boniPSAmounttoShow    = $( '#bonips-creds-to-show' );
	var boniPSReferencetoShow = $( '#bonips-referece-to-show' );
	var boniPSOldEntrytoShow  = $( '#bonips-old-entry-to-show' );
	var boniPSNewEntrytoShow  = $( '#bonips-new-entry-to-show' );

	/**
	 * Reset Editor
	 */
	function bonips_reset_editor() {

		boniPSEditorResults.empty();
		boniPSUsertoShow.empty();
		boniPSDatetoShow.empty();
		boniPSAmounttoShow.val( '' );
		boniPSReferencetoShow.empty();
		boniPSOldEntrytoShow.empty();
		boniPSNewEntrytoShow.val( '' );
		boniPSAvailableTags.empty();

		$.each( boniPSLog.references, function( index ){

			var optiontoinsert = '<option value=\"' + index + '\"';
			if ( boniPSReference == index ) optiontoinsert += ' selected=\"selected\"';
			optiontoinsert += '>' + boniPSLog.references[ index ] + '<\/option>';

			boniPSReferencetoShow.append( optiontoinsert );

		});

		$( 'button#bonips-delete-entry-in-editor' ).attr( 'data', boniPSRowId );

	}

	/**
	 * Animate Row Deletion
	 */
	function bonips_animate_row_deletion( rowtoanimate ) {

		var rowtodelete = $( '#entry-' + rowtoanimate );
		if ( rowtodelete === undefined ) return;

		rowtodelete.addClass( 'deleted-row' ).fadeOut( 2000, function(){
			rowtodelete.remove();
		});

	}

	/**
	 * Animate Row Update
	 */
	function bonips_animate_row_update( newrow ) {

		var affectedrow = $( '#entry-' + boniPSRowId );

		affectedrow.addClass( 'updated-row' ).fadeOut(function(){
			affectedrow.empty().append( newrow ).fadeIn( 2000, function(){
				affectedrow.removeClass( 'updated-row' );
			});
		});

	}

	/**
	 * Update Log Entry
	 */
	function bonips_update_entry( submission ) {

		var submitbutton = $( '#bonips-editor-submit' );
		var submitlabel  = submitbutton.val();

		$.ajax({
			type       : "POST",
			data       : {
				action    : 'bonips-update-log-entry',
				token     : boniPSLog.tokens.update,
				screen    : boniPSLog.screen,
				page      : boniPSLog.page,
				rowid     : boniPSRowId,
				ctype     : boniPSLog.ctype,
				form      : submission
			},
			dataType   : "JSON",
			url        : boniPSLog.ajaxurl,
			beforeSend : function() {

				boniPSAmounttoShow.attr( 'readonly', 'readonly' );
				boniPSReferencetoShow.attr( 'readonly', 'readonly' );
				boniPSNewEntrytoShow.attr( 'readonly', 'readonly' );

				// Prep results box
				boniPSEditorResults.empty();
				$( '#bonips-editor-indicator' ).addClass( 'is-active' );

				// Indicate that we are doing something
				submitbutton.empty().text( boniPSLog.working );

			},
			success    : function( response ) {

				// Remove indicator
				$( '#bonips-editor-indicator' ).removeClass( 'is-active' );

				// Most likelly the wpnonce has expired (screen open too long)
				if ( response.success === undefined ) {
					boniPSEditorModal.dialog( 'destroy' );
					location.reload();
				}

				// Ok, something was done
				else {

					boniPSAmounttoShow.removeAttr( 'readonly' );
					boniPSReferencetoShow.removeAttr( 'readonly' );
					boniPSNewEntrytoShow.removeAttr( 'readonly' );

					submitbutton.empty().text( submitlabel );

					boniPSEditorResults.text( response.data.message );

					if ( response.success === true ) {
						bonips_animate_row_update( response.data.results );
					}

				}

			}
		});

	}

	/**
	 * Delete Log Entry
	 */
	function bonips_delete_entry( entryid ) {

		var ismodalopen  = boniPSEditorModal.dialog( "isOpen" );
		var deletebutton = $( '#bonips-delete-entry-in-editor' );
		var deletelabel  = deletebutton.text();

		$.ajax({
			type       : "POST",
			data       : {
				action    : 'bonips-delete-log-entry',
				token     : boniPSLog.tokens.delete,
				ctype     : boniPSLog.ctype,
				row       : entryid
			},
			dataType   : "JSON",
			url        : boniPSLog.ajaxurl,
			beforeSend : function() {

				if ( ismodalopen === true ) {

					// Make sure we can not make adjustments while we wait fo the AJAX handler to get back to us
					boniPSAmounttoShow.attr( 'readonly', 'readonly' );
					boniPSReferencetoShow.attr( 'readonly', 'readonly' );
					boniPSNewEntrytoShow.attr( 'readonly', 'readonly' );

					// Prep results box
					boniPSEditorResults.empty();
					$( '#bonips-editor-indicator' ).addClass( 'is-active' );

					// Indicate that we are doing something
					deletebutton.empty().text( boniPSLog.working );

				}

			},
			success    : function( response ) {

				// Remove indicator
				$( '#bonips-editor-indicator' ).removeClass( 'is-active' );

				// Most likelly the wpnonce has expired (screen open too long)
				if ( response.success === undefined ) {
					boniPSEditorModal.dialog( 'destroy' );
					location.reload();
				}

				// Ok, something was done
				else {

					// Act based on where we clicked to delete - In modal
					if ( ismodalopen === true ) {

						boniPSEditorResults.text( response.data );

						// Request failed for some reason, restore form usability
						if ( response.success !== true ) {

							boniPSAmounttoShow.removeAttr( 'readonly' );
							boniPSReferencetoShow.removeAttr( 'readonly' );
							boniPSNewEntrytoShow.removeAttr( 'readonly' );

							deletebutton.empty().text( deletelabel );

						}

						// All good. Close Dialog
						else {

							// Reset row id
							boniPSRowId = 0;

							// Restore button label for next opening
							deletebutton.empty().text( deletelabel );

							// Close dialog window
							boniPSEditorModal.dialog( 'close' );

						}

					}

					// In table
					else {

						if ( response.success !== true )
							alert( response.data );

					}

					// No matter which button we pressed, animate the row removal if successfull
					if ( response.success === true )
						bonips_animate_row_deletion( entryid );

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
		boniPSEditorModal.dialog({
			dialogClass : 'bonips-edit-logentry',
			draggable   : true,
			autoOpen    : false,
			title       : boniPSLog.title,
			closeText   : boniPSLog.close,
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
		$( 'tbody#the-list' ).on( 'click', '.bonips-open-log-entry-editor', function(e) {

			e.preventDefault();

			boniPSRowId     = $(this).data( 'id' );
			boniPSReference = $(this).data( 'ref' );
			boniPSRow       = '#entry-' + boniPSRowId;

			bonips_reset_editor();

			boniPSUsertoShow.append( $( boniPSRow + ' td.column-username strong' ).text() );
			boniPSDatetoShow.append( $( boniPSRow + ' td.column-time time' ).text() );
			boniPSOldEntrytoShow.append( $( boniPSRow + ' td.column-entry' ).text() );
			boniPSNewEntrytoShow.val( $( boniPSRow + ' td.column-entry' ).data( 'raw' ) );

			var amounttoshow = $( boniPSRow + ' td.column-creds' ).data( 'raw' );
			boniPSAmounttoShow.val( amounttoshow ).attr( 'placeholder', amounttoshow );

			// Show editor
			boniPSEditorModal.dialog( 'open' );

		});

		/**
		 * Trigger Log Deletion
		 */
		$( 'tbody#the-list' ).on( 'click', '.bonips-delete-row', function(){

			// Require user to confirm deletion (if used)
			if ( boniPSLog.messages.delete_row != '' && ! confirm( boniPSLog.messages.delete ) )
				return false;

			var deletebutton = $(this);
			var rowtodelete  = deletebutton.data( 'id' );

			if ( rowtodelete === undefined || rowtodelete == '' )
				rowtodelete = boniPSRowId;
			else
				boniPSRowId = rowtodelete;

			bonips_delete_entry( rowtodelete );

		});
		$( '#bonips-delete-entry-in-editor' ).on( 'click', function(e){

			e.preventDefault();

			// Require user to confirm deletion (if used)
			if ( boniPSLog.messages.delete_row != '' && ! confirm( boniPSLog.messages.delete ) )
				return false;

			var deletebutton = $(this);
			var rowtodelete  = deletebutton.data( 'id' );

			if ( rowtodelete === undefined || rowtodelete == '' )
				rowtodelete = boniPSRowId;
			else
				boniPSRowId = rowtodelete;

			bonips_delete_entry( rowtodelete );

		});

		/**
		 * Submit New Log Entry
		 */
		$( '#edit-bonips-log-entry' ).on( 'submit', 'form#bonips-editor-form', function(e){

			e.preventDefault();

			bonips_update_entry( $(this).serialize() );

		});

	});

	// Checkbox select in table
	// @see http://stackoverflow.com/questions/19164816/jquery-select-all-checkboxes-in-table
	$( '#boniPS-wrap form table thead .check-column input' ).on('click', function(e){
		var table= $(e.target).closest('table');
		$('.check-column input',table).prop( 'checked',this.checked );
	});

	/**
	 * Click To Toggle Script
	 */
	$( '.click-to-toggle' ).on('click', function(){

		var target = $(this).attr( 'data-toggle' );
		$( '#' + target ).toggle();

	});

});