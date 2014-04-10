jQuery(document).ready(function($) {

	/*
	 * Settings
	 */

	if ( $('#wpml-tabs').length > 0 ) {

		var links = $('#wpml-tabs .wpml-tabs-nav a');
		var panels = $('#wpml-tabs .wpml-tabs-panels > .form-table');
		var link_active = $('#wpml-tabs .wpml-tabs-nav li.active');
		var active = 0;

		if ( link_active.length )
			active = $('#wpml-tabs .wpml-tabs-nav li').index( link_active );

		var panel = panels[ active ];
		$(panels).hide();
		$(panel).addClass('active');

		links.on('click', function(e) {

			e.preventDefault();
			var index = links.index( this );

			if ( panels.length >= index )
				var panel = panels[ index ];

			var tab = $(this).attr('data-section');
			var url = this.href.replace(this.hash, '');
			    url = url.substring( 0, ( url.length - 1 ) );
			var section = this.href.indexOf('&wpml_section');
			if ( section > 0 )
				url = url.substring( 0, section );

			$('.wpml-tabs-panels .form-table, .wpml-tabs-nav').removeClass('active');
			$(panel).addClass('active');
			$(this).parent('li').addClass('active');

			window.history.replaceState({}, '' + url + '&' + tab, '' + url + '&' + tab);
		});
	}

	if ( undefined != $('#draggable') && undefined != $('#droppable') ) {

		var update_item_style = function( ui ) {

			if ( undefined == ui.sender || ! ui.sender.length )
				return false;

			var _id = ui.sender[0].id;
			var item = ui.item[0];

			if ( 'draggable' == ui.sender[0].id )
				$(item).removeClass('default_movie_meta_selected').addClass('default_movie_meta_droppable');
			else if ( 'droppable' == ui.sender[0].id )
				$(item).removeClass('default_movie_meta_droppable').addClass('default_movie_meta_selected');
		};

		var update_item = function() {

			var value = [];
			var items = $('.default_movie_meta_selected');
			$.each(items, function() {
				var value = $(this).attr('data-movie-meta');
				$('#wpml_settings-wpml-default_movie_meta option[value="'+value+'"]').prop('selected', true);
			});
		};

		$('#draggable, #droppable').sortable({
			connectWith: 'ul',
			placeholder: 'highlight',
			update: function( event, ui ) {
				update_item_style( ui );
			},
			stop: function( event, ui ) {
				update_item();
			}
		});

		$('#draggable, #droppable').disableSelection();
	}

	if ( undefined != $('input#APIKey_check') ) {
		$('input#APIKey_check').click(function(e) {

			e.preventDefault();

			var key = $('input#wpml_settings-tmdb-apikey').val();
			$('#api_status').remove();

			if ( '' == key ) {
				$('input#APIKey_check').after('<span id="api_status" class="invalid">'+ajax_object.empty_key+'</span>');
				return false;
			}
			else if ( 32 != key.length ) {
				$('input#APIKey_check').after('<span id="api_status" class="invalid">'+ajax_object.length_key+'</span>');
				return false;
			}
			
			$.ajax({
				type: 'GET',
				url: ajax_object.ajax_url,
				data: {
					action: 'tmdb_api_key_check',
					wpml_check: ajax_object.wpml_check,
					key: key
				},
				success: function(response) {
					$('input#APIKey_check').after(response);
				},
				beforeSend: function() {
					$('input#APIKey_check').addClass('button-loading');
				},
				complete: function() {
					$('input#APIKey_check').removeClass('button-loading');
				},
			});
		});
	}

	/*
	 * Actor list shortener
	 */

	if ( undefined != $('input[name=screen]')[0] ) {
		var screen = $('input[name=screen]')[0].value;
		if ( 'edit-movie' == screen ) {
			var actors = $('td.column-taxonomy-actor');
			actors.each(function() {
				var visible = []; var hidden = [];
				var links = $(this).find('a');
				var _visible = links.slice( 0, 5 );
				var _hidden = links.slice( 5 );
				_visible.each(function() { visible.push( this.outerHTML ); });
				_hidden.each(function() { hidden.push( this.outerHTML ); });
				$(this).html('<span class="visible-actors"></span><span class="hidden-actors"></span>, <a class="more-actors" href="#">' + ajax_object.see_more + '</a>');
				$(this).find('.visible-actors').html( visible.join(', ') );
				$(this).find('.hidden-actors').html( hidden.join(', ') );
			});

			$('.more-actors').on('click', function(e) {
				e.preventDefault();
				$(this).prev('.hidden-actors').toggle();
				if ( 'none' != $(this).prev('.hidden-actors').css('display') )
					$(this).text( ajax_object.see_less );
				else
					$(this).text( ajax_object.see_more );
			});
		}
	}

	/*
	 * TMDb data -- New movie
	 */

	$('input#tmdb_empty').click(function(e) {
		e.preventDefault();
		$('.tmdb_data_field').val('');
		$('#tmdb_save_images, #progressbar').hide();
		$('.tmdb_select_movie').remove();
		$('.tmdb_movie_images').not('.tmdb_movie_imported_image').remove();
		$('#tmdb_data, .tagchecklist').empty();
		$('.categorydiv input[type=checkbox]').prop('checked', false);
		$('#remove-post-thumbnail').trigger('click');
		wpml.status.clear();
	});

	$('#hide_progressbar').click(function(e) {
		e.preventDefault();
		$('#tmdb_save_images, #progressbar').hide();
	});

	$('#tmdb_search').click(function(e) {
		e.preventDefault();
		wpml.movie.type = $('#tmdb_search_type > :selected').val();
		wpml.movie.data = $('#tmdb_query').val();
		wpml.movie.lang = $('#tmdb_search_lang').val();
		wpml.movie.search_movie();
	});

	/*
	 * Status
	 */

	$('#movie-status-select').siblings('a.edit-movie-status').click(function() {
		if ( $('#movie-status-select').is(":hidden") ) {
			$('#movie-status-select').slideDown('fast');
			$(this).hide();
		}
		return false;
	});

	$('.save-movie-status', '#movie-status-select').click(function() {
		$('#movie-status-select').slideUp('fast');
		$('#movie-status-select').siblings('a.edit-movie-status').show();
		$('#movie-status-display').text($('#movie_status > option:selected').text());
		return false;
	});

	$('.cancel-movie-status', '#movie-status-select').click(function() {
		$('#movie-status-select').slideUp('fast');
		$('#movie_status').val($('#hidden_movie_status').val());
		$('#movie-status-display').text($('#hidden_movie_status').val());
		$('#movie-status-select').siblings('a.edit-movie-status').show();

		return false;
	});

	/*
	 * Media
	 */

	$('#movie-media-select').siblings('a.edit-movie-media').click(function() {
		if ( $('#movie-media-select').is(":hidden") ) {
			$('#movie-media-select').slideDown('fast');
			$(this).hide();
		}
		return false;
	});

	$('.save-movie-media', '#movie-media-select').click(function() {
		$('#movie-media-select').slideUp('fast');
		$('#movie-media-select').siblings('a.edit-movie-media').show();
		$('#movie-media-display').text($('#movie_media > option:selected').text());
		return false;
	});

	$('.cancel-movie-media', '#movie-media-select').click(function() {
		$('#movie-media-select').slideUp('fast');
		$('#movie_media').val($('#hidden_movie_media').val());
		$('#movie-media-display').text($('#hidden_movie_media').val());
		$('#movie-media-select').siblings('a.edit-movie-media').show();

		return false;
	});

	/*
	 * Movie Rating
	 */

	$stars = $('#stars, #bulk_stars');
	$select = $('#movie-rating-select');
	$display = $('#movie-rating-display');

	$stars.mousemove(function(e) {

		var parentOffset = $(this).offset(); 
		var relX = e.pageX - parentOffset.left;

		if ( relX <= 0 ) var _rate = '0';
		if ( relX > 0 && relX < 8 ) var _rate = '0.5';
		if ( relX >= 8 && relX < 16 ) var _rate = '1.0';
		if ( relX >= 16 && relX < 24 ) var _rate = '1.5';
		if ( relX >= 24 && relX < 32 ) var _rate = '2.0';
		if ( relX >= 32 && relX < 40 ) var _rate = '2.5';
		if ( relX >= 40 && relX < 48 ) var _rate = '3.0';
		if ( relX >= 48 && relX < 56 ) var _rate = '3.5';
		if ( relX >= 56 && relX < 64 ) var _rate = '4.0';
		if ( relX >= 64 && relX < 80 ) var _rate = '4.5';
		if ( relX >= 80 ) var _rate = '5.0';

		var _class = 'stars_' + _rate.replace('.','_');
		var _label = _class.replace('stars_','stars_label_');

		$(this).removeClass('stars_0 stars_0_0 stars_0_5 stars_1_0 stars_1_5 stars_2_0 stars_2_5 stars_3_0 stars_3_5 stars_4_0 stars_4_5 stars_5_0').addClass(_class);
		$('.stars_label').removeClass('show');
		$('#'+_label).addClass('show');
		$(this).attr('data-rating', _rate);
	});

	$stars.mouseleave(function() {

		if ( 'true' == $(this).attr('data-rated') )
			return false;

		var _class = '';

		if ( $('#hidden_movie_rating, #bulk_hidden_movie_rating').length ) {
			_class = $('#hidden_movie_rating, #bulk_hidden_movie_rating').val();
			_class = 'stars_' + _class.replace('.','_');
		}

		$(this).removeClass('stars_0 stars_0_0 stars_0_5 stars_1_0 stars_1_5 stars_2_0 stars_2_5 stars_3_0 stars_3_5 stars_4_0 stars_4_5 stars_5_0').addClass(_class);
		$('.stars_label').removeClass('show');
	});

	$stars.click(function() {

		var _rate = $(this).attr('data-rating');

		if ( undefined == _rate )
			return false;

		_rate = _rate.replace('stars_','');
		_rate = _rate.replace('_','.');

		$('#movie_rating, #bulk_movie_rating').val(_rate);
		$(this).attr('data-rating', _rate);
		$(this).attr('data-rated', true);

		$('#save_rating').removeAttr('disabled');
	});

	$select.siblings('a.edit-movie-rating').click(function() {
		if ( $select.is(":hidden") ) {
			$('#movie-rating-display').hide();
			$select.slideDown('fast');
			$(this).hide();
		}
		return false;
	});

	$('.save-movie-rating', '#movie-rating-select').click(function() {
		var n = $('#movie_rating').val();
		$select.slideUp('fast');
		$select.siblings('a.edit-movie-rating').show();
		$display.removeClass().addClass('stars_'+n.replace('.','_')).show();
		$('#movie_rating, #hidden_movie_rating').val(n);
		return false;
	});

	$('.cancel-movie-rating', '#movie-rating-select').click(function() {
		$select.slideUp('fast');
		$select.siblings('a.edit-movie-rating').show();
		$display.show();
		return false;
	});

	$('input#wpml_save').click(function() {
		wpml.movie.save_details();
	});


	// Movie import

	$('.delete_movie').click(function(e) {
		e.preventDefault();
		var id = this.id.replace('delete_','');
		wpml.movie.delete(id);
	});

	$('input#title').on('input', function() {
		if ( '' != $(this).val() )
			$('input#tmdb_query').val( $(this).val() );
	});

	/*
	 * WP List Table AJAX nav
	 */

	if ( 'movie_page_import' == adminpage ) {
		wpml.import.init();
		wpml.import.table.init();
	}

});

$ = jQuery;

wpml = {

	ajax: function( data, param ) {

		//TODO use a single $.ajax call
	},

	movie: {

		lang: '',

		data: {},

		type: '',

		delete: function(id) {

			$.ajax({
				type: 'GET',
				url: ajax_object.ajax_url,
				data: {
					action: 'wpml_delete_movie',
					wpml_check: ajax_object.wpml_check,
					post_id: id
				},
				success: function(response) {
					$('#post_'+id).parents('tr').remove();
				},
				beforeSend: function() {
					$('input.loader').addClass('button-loading');
				},
				complete: function() {
					$('input.loader').removeClass('button-loading loader');
				},
			});

		},

		get_movie: function(id) {

			$.ajax({
				type: 'GET',
				url: ajax_object.ajax_url,
				data: {
					action: 'tmdb_search',
					wpml_check: ajax_object.wpml_check,
					type: 'id',
					data: id,
					lang: wpml.movie.lang
				},
				success: function(response) {
					tmdb_data = document.getElementById('tmdb_data');
					while (tmdb_data.lastChild) tmdb_data.removeChild(tmdb_data.lastChild);
					tmdb_data.style.display = 'none';
					wpml.movie.populate(response);
					wpml.movie.images.set_featured(response.poster_path, null, response.title, response._tmdb_id);
				},
				beforeSend: function() {
					$('#tmdb_search').addClass('button-loading');
				},
				complete: function() {
					$('#tmdb_search').removeClass('button-loading');
				},
			});

		},

		populate: function(data) {

			$('#tmdb_data_tmdb_id').val(data._tmdb_id);

			$('.tmdb_data_field').each(function() {

				var field = this;
				var type = field.type;
				var _id = this.id.replace('tmdb_data_','');
				var value = '';

				field.value = '';

				var sub = wpml.switch_data( _id );

				if ( 'meta' == sub )
					var _data = data.meta;
				else if ( 'crew' == sub )
					var _data = data.crew;
				else
					var _data = data;

				if ( typeof _data[_id] == "object" ) {
					if ( Array.isArray( _data[_id] ) ) {
						if ( _id == 'images' && ! $('.tmdb_movie_imported_image').length ) {
							wpml.movie.images.populate(data.images);
						}
						else {
							_v = [];
							$.each(_data[_id], function() {
								_v.push( field.value + this );
							});
							value = _v.join(', ');
						}
					}
				}
				else {
					_v = ( _data[_id] != null ? _data[_id] : '' );
					value = _v;
				}

				$(field).val(_v);

				$('.list-table, .button-empty').show();
			});

			if ( data.taxonomy.actors.length ) {
				$.each(data.taxonomy.actors, function(i) {
					$('#tagsdiv-actor .tagchecklist').append('<span><a id="actor-check-num-'+i+'" class="ntdelbutton">X</a>&nbsp;'+this+'</span>');
					tagBox.flushTags( $('#actor.tagsdiv'), $('<span>'+this+'</span>') );
				});
			}

			if ( data.taxonomy.genres.length ) {
				$.each(data.taxonomy.genres, function(i) {
					$('#tagsdiv-genre .tagchecklist').append('<span><a id="genre-check-num-'+i+'" class="ntdelbutton">X</a>&nbsp;'+this+'</span>');
					tagBox.flushTags( $('#genre.tagsdiv'), $('<span>'+this+'</span>') );
				});
			}

			if ( data.crew.director.length ) {
				$.each(data.crew.director, function(i) {
					$('#newcollection').prop('value', this);
					$('#collection-add-submit').click();
				});
			}

			$('#tmdb_query').focus();
			wpml.status.set(ajax_object.done, 'success');
		},

		populate_quick_edit: function( movie_details, nonce ) {

			var $wp_inline_edit = inlineEditPost.edit;

			inlineEditPost.edit = function( id ) {

				$wp_inline_edit.apply( this, arguments );

				var $post_id = 0;

				if ( typeof( id ) == 'object' )
					$post_id = parseInt( this.getId( id ) );

				if ( $post_id > 0 ) {

					var $edit_row = $( '#edit-' + $post_id );

					var nonceInput = $('#wpml_movie_details_nonce');
					nonceInput.val( nonce );

					var movie_media = $edit_row.find('select.movie_media');
					var movie_status = $edit_row.find('select.movie_status');
					var movie_rating = $edit_row.find('#movie_rating');
					var hidden_movie_rating = $edit_row.find('#hidden_movie_rating');
					var stars = $edit_row.find('#stars');

					movie_media.children('option').each(function() {
						if ( $(this).val() == movie_details.movie_media )
							$(this).prop("selected", "selected");
						else
							$(this).prop("selected", "");
					});

					movie_status.children('option').each(function() {
						if ( $(this).val() == movie_details.movie_status )
							$(this).prop("selected", true);
						else
							$(this).prop("selected", false);
					});

					if ( '' != movie_details.movie_rating && ' ' != movie_details.movie_rating ) {
						movie_rating.val( movie_details.movie_rating );
						hidden_movie_rating.val( movie_details.movie_rating );
						stars.removeClass('stars_', 'stars_0_0', 'stars_0_5', 'stars_1_0', 'stars_1_5', 'stars_2_0', 'stars_2_5', 'stars_3_0', 'stars_3_5', 'stars_4_0', 'stars_4_5', 'stars_5_0');
						stars.addClass( 'stars_' + movie_details.movie_rating.replace('.','_') );
						stars.attr('data-rated', true);
						stars.attr('data-default-rating', movie_details.movie_rating);
						stars.attr('data-rating', movie_details.movie_rating);
					}

				}

			};
		},

		populate_select_list: function(data) {

			$('#tmdb_data').append(data.p).show();

			var html = '';

			$.each(data.movies, function() {
				html += '<div class="tmdb_select_movie">';
				html += '	<a id="tmdb_'+this.id+'" href="#">';
				html += '		<img src="'+this.poster+'" alt="'+this.title+'" />';
				html += '		<em>'+this.title+'</em>';
				html += '	</a>';
				html += '	<input type=\'hidden\' value=\''+this.json+'\' />';
				html += '</div>';
			});

			$('#tmdb_data').append(html);
		},

		save_details: function() {

			$.ajax({
				type: 'POST',
				url: ajax_object.ajax_url,
				data: {
					action: 'wpml_save_details',
					wpml_check: ajax_object.wpml_check,
					post_id: $('#post_ID').val(),
					wpml_details: {
						media: $('#movie_media').val(),
						status: $('#movie_status').val(),
						rating: $('#movie_rating').val()
					}
				},
				beforeSend: function() {
					$('input#wpml_save').addClass('button-loading');
				},
				complete: function() {
					$('input#wpml_save').removeClass('button-loading');
				},
			});

		},

		search_movie: function() {

			$('#tmdb_data > *, .tmdb_select_movie').remove();
			$('.tmdb_movie_images').not('.tmdb_movie_imported_image').remove();

			if (  wpml.movie.type == 'title' )
				wpml.status.set(ajax_object.search_movie_title+' "'+ wpml.movie.data+'"', 'warning');
			else if (  wpml.movie.type == 'id' )
				wpml.status.set(ajax_object.search_movie+' #'+ wpml.movie.data, 'success');

			$.ajax({
				type: 'GET',
				url: ajax_object.ajax_url,
				data: {
					action: 'tmdb_search',
					wpml_check: ajax_object.wpml_check,
					type:  wpml.movie.type,
					data:  wpml.movie.data,
					lang: wpml.movie.lang
				},
				success: function(response) {
					if ( response._result == 'movie' ) {
						wpml.movie.populate(response);
						wpml.movie.images.set_featured(response.poster_path, null, response.title, response._tmdb_id);
					}
					else if ( response._result == 'movies' ) {
						wpml.movie.populate_select_list(response);

						$('.tmdb_select_movie a').click(function(e) {
							e.preventDefault();
							id = this.id.replace('tmdb_','');
							wpml.movie.get_movie(id);
						});
					}
					else if ( response._result == 'error' || response._result == 'empty' ) {
						$('#tmdb_data').html(response.p).show();
						$('#tmdb_status').empty();
					}
				},
				beforeSend: function() {
					$('#tmdb_search').addClass('button-loading');
				},
				complete: function() {
					$('#tmdb_search').removeClass('button-loading');
				},
			});
		},

		images: {

			load: function(id) {

				var offset = $('.tmdb_movie_images').not('.tmdb_movie_imported_image').length;

				$.ajax({
					type: 'GET',
					url: ajax_object.ajax_url,
					data: {
						action: 'tmdb_load_images',
						wpml_check: ajax_object.wpml_check,
						tmdb_id: id,
						offset: offset
					},
					success: function(response) {
							wpml.movie.images.populate(response);
					},
					beforeSend: function() {
						$('#tmdb_load_images').text(ajax_object.loading_images);
					},
					complete: function() {
						$('#tmdb_load_images').text(ajax_object.load_more);
					},
				});
			},

			populate: function(images) {

				

				/*$('#tmdb_data_images').val('');

				_v = [];
				$.each(images, function() {
					html = '<div class="tmdb_movie_images"><img src=\''+ajax_object.base_url_small+this.file_path+'\' data-tmdb=\''+JSON.stringify(this)+'\' alt=\'\' /></div>';
					$('#tmdb_images_preview').append(html);
				});

				$('.tmdb_movie_images').click(function() {

					if ( ! $(this).hasClass('selected') )
						$(this).addClass('selected');
					else if ( $(this).hasClass('selected') )
						$(this).removeClass('selected');

					var _v   = [];

					$('.tmdb_movie_images.selected').each(function() {
						var _data = $(this).find('img').attr('data-tmdb');
						_v.push(_data);
					});
					$('#tmdb_data_images').val( JSON.stringify( _v ) );
				});

				$('#tmdb_save_images').removeClass('button-secondary').addClass('button-primary').show();
				$('.tmdb_images_preview').focus();*/

			},

			save: function() {

				var img   = $('.tmdb_movie_images.selected').not('.tmdb_movie_imported_image').find('img');
				var title = $('#tmdb_data_title').val();
				var total = img.length;

				$('#progressbar_bg, #hide_progressbar').show();
				$('#progressbar').progressbar({
					value: false,
					complete: function() {
						$('#progressbar_bg').delay(2000).fadeOut('slow');
					}
				}).show();

				$.each(img, function(i) {

					var _data = $(this).attr('data-tmdb');
					    _data = $.parseJSON( _data );
					i = i + 1;
					wpml.status.set(ajax_object.save_image+' #'+i, 'warning');
					$.ajax({
						type: 'GET',
						url: ajax_object.ajax_url,
						data: {
							action: 'tmdb_save_image',
							wpml_check: ajax_object.wpml_check,
							image: _data,
							post_id: $('#post_ID').val(),
							title: ajax_object.image_from + ' ' + title,
							tmdb_id: $('#tmdb_data_tmdb_id').val()
						},
						success: function(_r) {
							$(img).parent('.tmdb_movie_images.selected').addClass('tmdb_movie_imported_image new').removeClass('selected');
						},
						complete: function() {
							$('#progressbar').progressbar({
								value: Math.round( $('#progressbar').progressbar('value') + ( 100 / total ) )
							});
							$('.progress-label').text($('#progressbar').progressbar('value') + '%');
						}
					});
				});

				$('#tmdb_save_images').removeClass('button-primary').addClass('button-secondary');
				$('#progressbar_bg').click(function() {
					if ( $('#progressbar').progressbar('value') == 100 )
						$(this).hide();
				});

			},

			set_featured: function(image, id, title, tmdb_id) {

				if ( ! $('#wpml-tmdb') || wp.media.featuredImage.get() > 0 )
					return false;

				wpml.status.set(ajax_object.set_featured, 'success');
				var title   = title || $('#tmdb_data_title').val();
				var post_id = id || $('#post_ID').val();

				$.ajax({
					type: 'GET',
					url: ajax_object.ajax_url,
					data: {
						action: 'tmdb_set_featured',
						wpml_check: ajax_object.wpml_check,
						image: image,
						post_id: post_id,
						title: title,
						tmdb_id: tmdb_id
					},
					success: function(r) {
						if (r) {
							wp.media.featuredImage.set(r);
							wpml.status.set(ajax_object.done, 'success');
						}
						else {
							wpml.status.set(ajax_object.oops, 'error');
						}
					}
				});

			},
		},
	},

	switch_data: function( f_name ) {

		switch ( f_name ) {
			case "poster":
			case "title":
			case "original_title":
			case "overview":
			case "production_companies":
			case "production_countries":
			case "spoken_languages":
			case "runtime":
			case "genres":
			case "release_date":
				var _data = 'meta';
				break;
			case "director":
			case "producer":
			case "photography":
			case "composer":
			case "author":
			case "writer":
			case "cast":
				var _data = 'crew';
				break;
			default:
				var _data = 'data';
				break;
		}

		return _data;
	},

	status: {

		set: function(message, style) {
			$('#tmdb_status').text(message).removeClass().addClass(style).show();
		},

		clear: function() {
			$('#tmdb_status').empty().removeClass().hide();
		}

	},

	http_query_var: function( query, variable ) {

		var vars = query.split("&");
		for ( var i = 0; i <vars.length; i++ ) {
			var pair = vars[ i ].split("=");
			if ( pair[0] == variable )
				return pair[1];
		}
		return false;
	}

};