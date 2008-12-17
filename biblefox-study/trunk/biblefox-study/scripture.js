
function bible_ref_remove_tag() {
	var id = jQuery( this ).attr( 'id' );
	var num = id.substr( 16 );
	var current_tags = jQuery( '#bible-ref-list' ).val().split(';');
	delete current_tags[num];
	var new_tags = [];
	jQuery.each( current_tags, function( key, val ) {
		if ( val && !val.match(/^\s+$/) && '' != val ) {
			new_tags = new_tags.concat( val );
		}
	});
	var new_text = new_tags.join(';');
	bible_ref_set_text(new_text);
	jQuery('#newtag').focus();
	return false;
}

function bible_ref_update_quickclicks() {
	empty_txt = '<span>No Scripture Tags.<br/>To add a new scripture tag, first view the scripture you want in the Scripture Quick View below.</span>';
	if (jQuery('#bible-ref-list').length == 0)
	{
		jQuery('#bible-ref-checklist').html(empty_txt);
		return;
	}

	var current_tags = jQuery( '#bible-ref-list' ).val().split(';');
	jQuery( '#bible-ref-checklist' ).empty();
	shown = false;

	jQuery.each( current_tags, function( key, val ) {
		val = val.replace( /^\s+/, '' ).replace( /\s+$/, '' ); // trim
		if ( !val.match(/^\s+$/) && '' != val ) {
			txt = '<span><a id="bible-ref-check-' + key + '" class="bible-tag-remove-button">X</a>&nbsp;<a id="bible-ref-link-' + key + '" class="bible-ref-quick-link" bible_ref="' + val + '">' + val + '</a></span>';
			jQuery('#bible-ref-checklist').append(txt);
			jQuery('#bible-ref-check-' + key).click(bible_ref_remove_tag);
			jQuery('#bible-ref-link-' + key).click(bible_ref_link_click);
			shown = true;
		}
	});

	if (jQuery('#bible-ref-checklist').html().length == 0)
		jQuery('#bible-ref-checklist').html(empty_txt);
}

function bible_ref_set_text(newtags)
{
	newtags = newtags.replace( /\s+;+\s*/g, ';' ).replace( /;+/g, ';' ).replace( /;+\s+;+/g, ';' ).replace( /;+\s*$/g, '' ).replace( /^\s*;+/g, '' );
	jQuery('#bible-ref-list').val( newtags );
	bible_ref_update_quickclicks();
	return false;
}

function bible_ref_flush_to_text() {
	var newtags = jQuery('#bible-ref-list').val() + ';' + jQuery('#add-bible-ref').attr('bible_ref');
	
	bible_ref_set_text(newtags);
	return false;
}

function bible_ref_press_key( e ) {
	if ( 13 == e.keyCode ) {
		bible_text_request_new();
		return false;
	}
}

function bible_text_request(ref_str)
{
	var mysack = new sack(jQuery('#bible-request-url').val());
	
	mysack.execute = 1;
	mysack.method = 'POST';
	mysack.setVar("action", "bfox_ajax_send_bible_text");
	mysack.setVar("ref_str", ref_str);
	mysack.encVar("cookie", document.cookie, false);
	mysack.onError = function() { alert('Ajax error in looking up bible reference')};
	mysack.runAJAX();

	// Fade out the the progress text, then update it to say we are loading
	jQuery('#bible-text-progress').fadeOut("fast", function () {
		jQuery('#bible-text-progress').html('Loading "' + ref_str + '"...');
	});
	
	// Fade the bible-text slightly to indicate to the user that it is about to be replaced
	jQuery('#bible-text').fadeTo("fast", 0.6);

	// Fade the load progress loading text back in
	jQuery('#bible-text-progress').fadeIn("fast");
	
	return false;
}

function bfox_quick_view_loaded(ref_str, content)
{
	// Wait until the progress text is finished
	jQuery('#bible-text-progress').queue( function () {

		// Fade out the progress text, then update it to say we are done loading
		jQuery('#bible-text-progress').fadeOut("fast", function() {
			jQuery('#bible-text-progress').html('Viewing ' + ref_str);
		});

		// Fade out the old bible text, then replace it with the new text
		jQuery('#bible-text').fadeOut("fast", function () {
			jQuery('#bible-text').html(content);
			jQuery('.bible-ref-link').click(bible_ref_link_click);
		});

		// Fade everything back in
		jQuery('#bible-text-progress').fadeIn("fast");
		jQuery('#bible-text').fadeIn("fast");
		jQuery('#bible-text').fadeTo("fast", 1);

		// We must dequeue to continue the queue
		jQuery(this).dequeue();
	});
}

function bible_text_request_new()
{
	bible_text_request(jQuery('#new-bible-ref').val());
}

function bible_ref_link_click()
{
	bible_text_request(jQuery(this).attr('bible_ref'));
}

function bfox_toggle_quick_view()
{
	if ('none' == jQuery('#bible_quick_view').css('display'))
	{
		jQuery('#bible_view').animate({width: '50%'}, 'fast');
		jQuery('#bible_view').queue(function() {
			jQuery('#bible_quick_view').fadeIn('fast', function() {
				jQuery('#bible_view').dequeue();
			});
		});
	}
	else
	{
		jQuery('#bible_view').queue(function() {
			jQuery('#bible_quick_view').fadeOut('fast', function() {
				jQuery('#bible_view').dequeue();
			});
		});
		jQuery('#bible_view').animate({width: '100%'}, 'fast');
	}
}

function bfox_text_select()
{
	// Use Javascript Range Objects
	// See http://www.quirksmode.org/dom/range_intro.html
	var userSelection;
	if (window.getSelection) {
		userSelection = window.getSelection();
	}
	else if (document.selection) { // should come last; Opera!
		userSelection = document.selection.createRange();
	}
	
	var selectedText = userSelection;
	if (userSelection.text)
		selectedText = userSelection.text;

/*		
//	selectedText = jQuery(userSelection.anchorNode).html;
	
//	var rangeObject = bfox_get_range_object(userSelection);
	var ref = //jQuery(userSelection.anchorNode).prev().html() + '; ' + 
//	userSelection.focusNode.nodeValue + '; ' + 
	jQuery(userSelection.anchorNode).parent().html();
	*/
	
	if ('' != selectedText)
	{
		var ref = jQuery('#bible_text_main_ref').html(); 
		jQuery('#verse_selected').html(ref);
		jQuery('#verse_select_more_info').fadeIn('fast');
//		jQuery('#edit_quick_note_text').focus();
	}
	else jQuery('#verse_select_more_info').fadeOut('fast');
//	jQuery('#verse-select-menu').dialog();
	
	//if ('' != selectedText) alert(selectedText);
}

function bfox_get_range_object(selectionObject) {
	if (selectionObject.getRangeAt)
		return selectionObject.getRangeAt(0);
	else { // Safari!
		var range = document.createRange();
		range.setStart(selectionObject.anchorNode,selectionObject.anchorOffset);
		range.setEnd(selectionObject.focusNode,selectionObject.focusOffset);
		return range;
	}
}

function bfox_save_quick_note()
{
	jQuery('.edit_quick_note_input').attr("disabled", true);

	var note = jQuery('#edit_quick_note_text').val();
	var id = jQuery('#edit_quick_note_id').val();
	var ref_str = 'gen 1';

	var mysack = new sack(jQuery('#bible-request-url').val());
	
	mysack.execute = 1;
	mysack.method = 'POST';
	mysack.setVar("action", "bfox_ajax_save_quick_note");
	mysack.setVar("note", note);
	mysack.setVar("note_id", id);
	mysack.setVar("ref_str", ref_str);
	mysack.encVar("cookie", document.cookie, false);
	mysack.onError = function() { alert('Ajax error in saving the ')};
	mysack.runAJAX();

	jQuery('#edit_quick_note_progress').html('Saving...');
	jQuery('#edit_quick_note_progress').fadeIn("fast");
	
	return false;
}

function bfox_quick_note_saved(content)
{
	jQuery('#edit_quick_note_progress').fadeOut("fast", function() {
		jQuery('#edit_quick_note_progress').html('Saved!');
		jQuery('.edit_quick_note_input').removeAttr("disabled");
		jQuery('#quick_note_list').html(content);
	}).fadeIn(1000).fadeOut(1000);
}

function bfox_set_quick_note(id, note)
{
	jQuery('#edit_quick_note_id').val(id);
	jQuery('#edit_quick_note_text').val(note);
	jQuery('#edit_quick_note_text').focus();
}

function bfox_edit_quick_note(id)
{
	bfox_set_quick_note(id, jQuery('#quick_note_' + id).html());
}

function bfox_new_quick_note()
{
	bfox_set_quick_note('0', '');
}

function bfox_edit_quick_note_press_key( e ) {
	if ( 13 == e.keyCode ) {
		bfox_save_quick_note();
		return false;
	}
}

jQuery(document).ready( function() {
	bible_ref_update_quickclicks();
	jQuery('#add-bible-ref').click(bible_ref_flush_to_text);
	jQuery('#view-bible-ref').click(bible_text_request_new);
	jQuery('#new-bible-ref').keypress(bible_ref_press_key);
	
	jQuery('#quick_view_button').click(bfox_toggle_quick_view);

	jQuery('#edit_quick_note_text').keypress(bfox_edit_quick_note_press_key);
	jQuery('#edit_quick_note_text').val('');

	jQuery(document).mouseup(bfox_text_select);
});