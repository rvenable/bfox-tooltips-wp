// AJAX Functions

function bfox_theme_ajax_load_bible_notes(bp_page) {
	var j = jQuery;

	j('.ajax-loader').show();

	j.post( ajaxurl, {
		action: 'get_bible_notes_list',
		'cookie': encodeURIComponent(document.cookie),
		'bp_page': bp_page,
		'nt-filter': j("input#bible-note-list-filter").val(),
		'nt-privacy': ('on' == j("input#bible-note-list-filter-privacy:checked").val()) ? 1 : 0
	},
	function(response) {	
		j('.ajax-loader').hide();
	
		response = response.substr(0, response.length-1);

		j("#bible-note-list-content").fadeTo(200, 0.1, 
			function() {
				j("#bible-note-list-content").html(response);
				j('form.bible-note-edit-form').not('form#bible-note-new-edit-form').hide();
				j("#bible-note-list-content").fadeTo(200, 1);
			}
		);

		return false;
	});

	return false;
}

function bfox_theme_ajax_load_bible_search(href) {
	var j = jQuery;
	
	var search = new String(href.match(/s=[^&]*/i));
	search = search.substr(2, search.length - 2);
	var ref = new String(href.match(/ref=[^&]*/i));
	ref = ref.substr(4, ref.length - 4);
	var group = new String(href.match(/group=[^&]*/i));
	group = group.substr(6, group.length - 6);
	var page = new String(href.match(/page=[^&]*/i));
	page = page.substr(5, page.length - 5);
	var trans = new String(href.match(/trans=[^&]*/i));
	trans = trans.substr(6, trans.length - 6);
	
	j('.ajax-loader').show();

	j.post( ajaxurl, {
		action: 'get_bible_search_list',
		's': search,
		'ref': ref,
		'group': group,
		'page': page,
		'trans': trans,
		'cookie': encodeURIComponent(document.cookie)
	},
	function(response) {	
		j('.ajax-loader').hide();
	
		response = response.substr(0, response.length-1);

		j("#bible-search-list-content").fadeTo(200, 0.1, 
			function() {
				j("#bible-search-list-content").html(response);
				
				bfox_blog_add_tooltips(jQuery('#bible-search-list-content span.bible-tooltip a'));
				j("#bible-search-list-content select.bible-search-trans-select").change(function() {
					return bfox_theme_ajax_load_bible_search(j(this).parent('form').attr('action')+'&trans='+j(this).val());
				});
				
				j("#bible-search-list-content").fadeTo(200, 1);
			}
		);

		return false;
	});

	return false;
}

jQuery(document).ready( function() {
	var j = jQuery;

	// Bible Note list filters
	j("form#bible-note-list-filter-form").livequery('submit', function() {
		return bfox_theme_ajax_load_bible_notes(1);
	});
	
	// Bible Note list pages
	j("div#bible-note-pagination a").livequery('click', function() {
		var fpage = j(this).attr('href');
		fpage = fpage.split('bp_page=');
		fpage = fpage[1].split('&');
		
		return bfox_theme_ajax_load_bible_notes(fpage[0]);
	});

	// Bible Note list pages
	j("form.bible-note-edit-form").livequery('submit', function() {
		j('.ajax-loader').show();

		var children = j(this).children();
		var note_id = children.filter('.bible-note-id').val();
		if (undefined == note_id) note_id = '0';

		j.post( ajaxurl, {
			action: 'save_bible_note',
			'cookie': encodeURIComponent(document.cookie),
			'bible-note-id': note_id,
			'bible-note-textarea': children.filter('.bible-note-textarea').val(),
			'bible-note-ref-tags': children.filter('.bible-note-ref-tags').val(),
			'bible-note-privacy': children.filter('.bible-note-privacy-setting').children('input:radio:checked').val()
		},
		function(response) {	
			j('.ajax-loader').hide();
		
			response = response.substr(0, response.length-1);

			var result = children.filter('.bible-note-edit-form-result');
			result.fadeOut(200, function() {
				result.html(response);
				result.fadeIn(200);
				
				// If the save was successful, we should reload all the bible notes
				if (result.children('#message').hasClass('updated')) {
					// For a new note, blank out the new note content to get ready for the user to type another note
					if ('0' == note_id) children.filter('.bible-note-textarea').val('').blur();
					
					// Reload the bible notes
					bfox_theme_ajax_load_bible_notes(1);
				}
			});

			return false;
		});

		return false;
	});
	
	// Hide all the note edit forms (except the new note form)
	j('form.bible-note-edit-form').not('form#bible-note-new-edit-form').hide();
	
	// Whenever the user begins to edit a note, fade out the old note result messages
	j('.bible-note-textarea, .bible-note-ref-tags').livequery('focus', function() {
		j('.bible-note-edit-form-result').fadeOut(200);
	});
	
	// Edit note toggle button
	j('.bible-note-open-edit-form').livequery('click', function() {
		j(this).toggleClass('reject').next('form.bible-note-edit-form').toggle();
		return false;
	});
	
	// Search page AJAX
	j("div.bible-search-results-pagination a, div#verse_map_list a").livequery('click', function() {
		return bfox_theme_ajax_load_bible_search(j(this).attr('href'));
	});
	j("select.bible-search-trans-select").change(function() {
		return bfox_theme_ajax_load_bible_search(j(this).parent('form').attr('action')+'&trans='+j(this).val());
	});
});
