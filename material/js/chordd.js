/*!
 *
 * -- Chordd
 * -- chordd.js
 *
 * -- App: https://kearlsaint.github.io/Chordd
 * -- Source: https://github.com/kearlsaint/Chordd
 *
**/

var Chordd = function() {

	this.ajaxStream = null;
	this.server = window.cserver;
	this.saves = [];
	this.results = { chords: [], tabs: [] };
	this.title = "Chordd";
	this.scrollID = 0;
	this.lastQuery = '';
	if(!$.jStorage.get('chorddConfig')) {
		$.jStorage.set('chorddConfig', {
			fontSize: 12,
			tabsFirst: false
		});
	}
	this.config = $.jStorage.get('chorddConfig');
	this.applyConfig();
	
	// event listeners
	var self = this;
	$(window).on('scroll', function(e) {
		var top = $(window).scrollTop();
		var tit = $('[toolbar] header[title]');
		if(top >= 56 && tit.attr('hasTransitioned')===null) {
			tit.attr('hasTransitioned', '').velocity({
				opacity: 0 
			}, {
				duration: 200,
				easing: 'easeOutQuint',
				complete: function() {
					tit.html(self.title).velocity({ opacity: 1 }, { duration: 200, easing: 'easeOutQuint' });
				}
			});
		} else if(top < 56 && tit.attr('hasTransitioned')!==null) {
			tit.removeAttr('hasTransitioned').velocity({
				opacity: 0 
			}, {
				duration: 200,
				easing: 'easeOutQuint',
				complete: function() {
					tit.html('Chordd').velocity({ opacity: 1 }, { duration: 200, easing: 'easeOutQuint' });
				}
			});
		}
	});
	
	$('[searchinput]').on('keydown', function(e) {
		if(e.keyCode === 13) {
			$('[searchbutton]').click().focus();
			//var query = $('[searchinput]').val();
			//self.getResults(query);
		}
	});
	
	$('[searchbutton]').on('click', function(e) {
		var query = $('[searchinput]').val();
		self.getResults(query);
	});

	$('[searchinput]').on('change keyup blur focus', function() {
		if($(this).val() == '') {
			$('[drawer2]').attr('hidden', '');
			$('[drawer1]').removeAttr('hidden');
		} else {
			$('[drawer1]').attr('hidden', '');
			$('[drawer2]').removeAttr('hidden');
			self.getSuggestions.call(self);
		}
	});
	
	/*$('[searchinput]').on('focus', function() {
		// display the second page/list page
		$('[drawer1]').attr('hidden', '');
		$('[drawer2]').removeAttr('hidden');
	});*/
	
	/*$('[searchinput]').on('click', function() {
		this.select();
	});*/
	
	
}

Chordd.prototype.refreshInfo = function() {

	var j = $.jStorage;
	$('[storageAvailable]').html(j.storageAvailable()?'YES':'NO');
	$('[storageBackend]').html(j.currentBackend().toString());
	$('[storageIndex]').html(j.index().length.toString());
	$('[storageSize]').html((j.storageSize().toString()/1000) + ' kb');
	$('[storageFree]').html(((5e6 - j.storageSize().toString())/1000) + ' kb');
	
};

// @param: artists/titles/tabs/chords
Chordd.prototype.refresh = function(sort) {
	// refresh the storage
	$.jStorage.reInit();
	
	var index = $.jStorage.index();
	var key, data, html, count = 0;
	var container = $('[list-local]');
	
	sort = typeof(sort)=='undefined'?'artists':sort;
	
	$('[title-online], [list-online], [list-local]').html('');
	//$('[title-local]').html(sort.toUpperCase() + ' VIEW');
	$('[sorttitle]').html(sort);

	if(sort !== "back") {
		$('[drawer1]').attr('hidden', '');
		$('[drawer2]').removeAttr('hidden');
		$('[searchcontainer]').attr('hidden', '');
		$('[titlecontainer]').removeAttr('hidden');
	} else {
		// go back
		// show the search
		$('[drawer2]').attr('hidden', '');
		$('[drawer1]').removeAttr('hidden');
		$('[titlecontainer]').attr('hidden', '');
		$('[searchcontainer]').removeAttr('hidden');
		return;
	}
	if(index.length < 1) {
		// no data has been saved yet
		$('[title-local]').html('NO SAVED DATA');
		//container.append('<div padded item fg-indigo>No saved data!</div>');

	} else {
		// document fragment for reflow optimizations
		// http://www.smashingmagazine.com/2012/11/05/writing-fast-memory-efficient-javascript/
		var frag = document.createDocumentFragment();
		if(sort === "titles" || sort === "tabs" || sort === "chords") {
			var star_full = '<span icon="star"></span>';
			var star_half = '<span icon="star-outline"></span>';
			var targetType;
			if(sort === "tabs") targetType = "tab";
			if(sort === "chords") targetType = "chord";
			for(var i=0; i<index.length; i++) {
				var key = index[i];
				console.log(key);
				// check type
				if(targetType!==undefined) {
					var type = key.split("/");
					type = type[type.length-1];
					// if the type is tab and the targetType is chord
					// then this will make the loop
					// continue to the next loop
					// while ignoring the preceding statements
					if(type!==targetType) continue;
				}
				data = $.jStorage.get(key);
				if(data.data) {
					var title   = capitalizeFirstLetter(data.title) || 'Unknown Title';
					var artist  = capitalizeFirstLetter(data.artist) || 'Unknown Artist';
					var version = data.version || 1;
					var rating  = data.rating || 0;
					var rating_count = data.rating_count || 0;
					html = title;
					html += '<div>';
						html += '<artist fg-blueA400>';
							html += artist;
						html += '</artist>';
						html += '<info>';
							html += 'version ' + version + '<br>';
							
							switch(Math.floor(rating)) {
								case 1: html += star_full; html += star_half; html += star_half; html += star_half; html += star_half; break;
								case 2: html += star_full; html += star_full; html += star_half; html += star_half; html += star_half; break;
								case 3: html += star_full; html += star_full; html += star_full; html += star_half; html += star_half; break;
								case 4: html += star_full; html += star_full; html += star_full; html += star_full; html += star_half; break;
								case 5: html += star_full; html += star_full; html += star_full; html += star_full; html += star_full; break;
							}
							html += ' &mdash; <span fg-indigo>' + rating_count + ' votes</span>';
						html += '</info>';
						html += '<div itemaction onclick="app.removeContent(this)" itemkey="' + key + '">';
							html += '<i icon="close"></i>';
						html += '</div>';
					html += '</div>';
					var elem = $('<div padded item onclick="app.getContent(this)" itemkey="' + key + '">');
					elem.append(html);
					frag.appendChild(elem[0]);
					count++;
				}
			}
		} else if(sort === "artists") {
		
			var fullData = [];
			for(var i=0; i<index.length; i++) {
				var key = index[i];
				data = $.jStorage.get(key);
				if(data.data) {
					fullData.push(data);
				}
			}
			// sort
			var sortByKey = function(array, key) {
				return array.sort(function(a, b) {
					var x = a[key];
					var y = b[key];
					if (typeof x == "string") {
						x = x.toLowerCase(); 
						y = y.toLowerCase();
					}
					return ((x < y) ? -1 : ((x > y) ? 1 : 0));
				});
			}
			// sort
			fullData = sortByKey(fullData, "artist");
			// remove duplicates
			var artists = [];
			for(var i=0; i<fullData.length; i++) {
				var artist = fullData[i].artist;
				if(artists.indexOf(artist)<0) {
					artists.push(artist);
					var elem = $('<a padded item fg-indigo onclick="app.searchThis(this)">' + fullData[i].artist + '</a>');
					elem.append(html);
					frag.appendChild(elem[0]);
					count++;
				}
			}
		} else {
			// wtf?
			console.log("wtf?");
		}
		$('[title-local]').html(count + " SAVED " + sort.toUpperCase());
		// flush
		container.append(frag);
	}
}

// little helper function for verifying json data
Chordd.prototype.verify = function(data) {
	if(typeof data === 'object') {
		if(data.state !== undefined) {
			if(data.state === 'success') {
				return true;
			} else {
				// something isnt right
				return false;
			}
		} else {
			// invalid json data
			return false;
		}
	} else {
		// server error?
		return false;
	}
}

Chordd.prototype.getSuggestions = function() {
	var input = $('[searchinput]');
	var query = input.val();
	if(query.length > 0 && this.lastQuery != query) {
		this.lastQuery = query;
		this.request('suggestions', { __safe: true, data_request: 'suggestions', data_query: query });
	} else if(query.length === 0) {
		this.lastQuery = '';
		//this.refresh();
	}
}

Chordd.prototype.parseSuggestions = function(json) {
	if(this.verify(json) === true) {
		var container = $('[list-online]');
		container.html('');
		if(json.suggestions != null && json.suggestions.length > 0) {
			var frag = document.createDocumentFragment();
			for(var i=0; i<json.suggestions.length; i++) {
				var elem = $('<a padded item fg-indigo onclick="app.searchThis(this)">' + json.suggestions[i] + '</a>');
				frag.appendChild(elem[0]);
			}
			container.append(frag);
		} else {
			// no suggestions
			//container.append('<div padded item fg-indigo>No suggestions</div>');
		}
	}
}

Chordd.prototype.searchThis = function(element) {
	var query = $(element).html();
	this.getResults(query);
}

Chordd.prototype.getResults = function(query) {
	if(query.length > 0) {
		$('[searchinput]').val(query);
		this.lastQuery = query;
		this.request('results', { __safe: true, data_request: 'results', data_query: query });
	} else {
		//alert('Nothing to search!');
	}
}

Chordd.prototype.getResultContent = function(element) {
	element = $(element);
	var type = element.attr('itemtype');
	var key  = parseInt(element.attr('itemkey'));
	var data;
	if(type === 'chord') {
		data = this.results.chords[key];
	} else if(type === 'tab') {
		data = this.results.tabs[key];
	}
	// check if it has been saved locally or not
	var key = data.artist+'/'+data.title+'/'+data.version.toString()+'/'+type;
	if(!$.jStorage.get(key)) {
		// get content
		this.request('data', {
			__safe: true,
			data_request: 'content',
			data_type: type,
			data_title: data.song_name,
			data_artist: data.artist_name,
			data_version: data.version,
			data_rating: data.rating,
			data_ratingcount: data.votes,
			data_songid: data.id
		});
	} else {
		data = $.jStorage.get(key);
		this.display(data);
	}
}

Chordd.prototype.parseResults = function(json) {
	if(this.verify(json) === true) {
		var container = $('[list-online]');
		container.html('');
		var frag = document.createDocumentFragment();
		var star_full = '<span icon="star"></span>';
		var star_half = '<span icon="star-outline"></span>';
		if(json.results != null && typeof json.results === 'object') {
			frag.appendChild($('<div smaller bold padded fg-white bg-grey700>&middot; CHORDS</div>')[0]);
			var chords = JSON.parse(json.results.chords);
			chords = chords.store.page.data.results;
			console.log(chords);
			if(chords.length > 1) {
				for(var i=1, html, data; i<chords.length; i++) {
					data = chords[i];
					var title   = data.song_name || 'Unknown Title';
					var artist  = data.artist_name || 'Unknown Artist';
					var version = data.version || 1;
					var rating  = data.rating || 0;
					rating = Math.round(rating);
					var rating_count = data.votes || 0;
					html = title;
					html += '<div>';
						html += '<artist fg-blueA400>';
							html += artist;
						html += '</artist>';
						html += '<info>';
							html += 'version ' + version + '<br>';
							
							switch(Math.floor(rating)) {
								case 1: html += star_full; html += star_half; html += star_half; html += star_half; html += star_half; break;
								case 2: html += star_full; html += star_full; html += star_half; html += star_half; html += star_half; break;
								case 3: html += star_full; html += star_full; html += star_full; html += star_half; html += star_half; break;
								case 4: html += star_full; html += star_full; html += star_full; html += star_full; html += star_half; break;
								case 5: html += star_full; html += star_full; html += star_full; html += star_full; html += star_full; break;
							}
							html += ' &mdash; <span fg-indigo>' + rating_count + ' votes</span>';
						html += '</info>';
						// check if it already exists
						if(!$.jStorage.get(artist+'/'+title+'/'+version.toString()+'/chord')) {
							html += '<div class="persistent" title="Save" itemaction onclick="app.saveContent(this)" itemtype="chord" itemkey="' + i + '">';
								html += '<i icon="favorite-outline"></i>';
							html += '</div>';
						} else {
							//html += '<div title="Remove" itemaction onclick="app.removeContent(this)" itemkey="' + i + '">';
							html += '<div class="persistent" title="Remove" itemaction onclick="app.removeContent(this)" itemkey="' + artist+'/'+title+'/'+version.toString()+'/chord' + '">';
								html += '<i icon="close"></i>';
							html += '</div>';
						}
					html += '</div>';
					var elem = $('<div padded item itemtype="chord" itemkey="' + i + '" onclick="app.getResultContent(this)">');
					elem.append(html);
					frag.appendChild(elem[0]);
				}
			} else {
				// no chords
			}
			frag.appendChild($('<div smaller bold padded fg-white bg-grey700>&middot; TABS</div>')[0]);
			var tabs = JSON.parse(json.results.tabs);
			tabs = tabs.store.page.data.results;
			if(tabs.length > 1) {
				for(var i=1, html, data; i<tabs.length; i++) {
					data = tabs[i];
					var title   = data.song_name || 'Unknown Title';
					var artist  = data.artist_name || 'Unknown Artist';
					var version = data.version || 1;
					var rating  = data.rating || 0;
					rating = Math.round(rating);
					var rating_count = data.votes || 0;
					html = title;
					html += '<div>';
						html += '<artist fg-blueA400>';
							html += artist;
						html += '</artist>';
						html += '<info>';
							html += 'version ' + version + '<br>';
							
							switch(Math.floor(rating)) {
								case 1: html += star_full; html += star_half; html += star_half; html += star_half; html += star_half; break;
								case 2: html += star_full; html += star_full; html += star_half; html += star_half; html += star_half; break;
								case 3: html += star_full; html += star_full; html += star_full; html += star_half; html += star_half; break;
								case 4: html += star_full; html += star_full; html += star_full; html += star_full; html += star_half; break;
								case 5: html += star_full; html += star_full; html += star_full; html += star_full; html += star_full; break;
							}
							html += ' &mdash; <span fg-indigo>' + rating_count + ' votes</span>';
						html += '</info>';
						if(!$.jStorage.get(artist+'/'+title+'/'+version.toString()+'/tab')) {
							html += '<div class="persistent" title="Save" itemaction onclick="app.saveContent(this)" itemtype="tab" itemkey="' + i + '">';
								html += '<i icon="favorite-outline"></i>';
							html += '</div>';
						} else {
							//html += '<div title="Remove" itemaction onclick="app.removeContent(this)" itemkey="' + i + '">';
							html += '<div class="persistent" title="Remove" itemaction onclick="app.removeContent(this)" itemkey="' + artist+'/'+title+'/'+version.toString()+'/tab' + '">';
								html += '<i icon="close"></i>';
							html += '</div>';
						}
					html += '</div>';
					var elem = $('<div padded item itemtype="tab" itemkey="' + i + '" onclick="app.getResultContent(this)">');
					elem.append(html);
					frag.appendChild(elem[0]);
				}
			} else {
				// no tabs
			}
			// save results
			this.results = {
				chords: chords,
				tabs: tabs
			};
		} else {
			// no results
			frag.appendChild($('<a padded item fg-white>No results have been found.</a>')[0]);
		}
		container.append(frag);
	}
}

Chordd.prototype.parseData = function(json) {
	if(this.verify(json) === true) {
		if(json.data != null) {
			this.display(json);
		}
	}
}

Chordd.prototype.request = function(type, data) {
	try{ this.ajaxStream.abort() }catch(e){};
	var parser = function() {};
	if(type === 'suggestions' || type === 'results') {
		$('[title-local]').html('OFFLINE RESULTS');
		$('[title-online]').html('ONLINE ' + type.toUpperCase());
		$('[list-online], [list-local]').html('');
		$('[list-online]').append('<div padded fg-white>Loading...</div>');
		// check local items
		(function(data) {
			$.jStorage.reInit();
			var container  = $('[list-local]');
			var index_full = $.jStorage.index();
			var index = [];
			var query = data.data_query.toLowerCase();
			for(var i=0, res, title, artist; i<index_full.length; i++) {
				res = $.jStorage.get(index_full[i]);
				try { title = res.title.toLowerCase() } catch(e){};
				try { artist = res.artist.toLowerCase() } catch(e){};
				if((title != undefined  && (title.search(query) > -1 || query.search(title) > -1))
				|| (artist != undefined && (artist.search(query) > -1 || query.search(artist) > -1))) {
					index.push(index_full[i]);
				}
			}
			var key, data, html;
			if(index.length < 1) {
				//container.append('<div padded item fg-indigo>No saved data matched!</div>');
			} else {
				// document fragment for reflow optimizations
				// http://www.smashingmagazine.com/2012/11/05/writing-fast-memory-efficient-javascript/
				var frag = document.createDocumentFragment();
				var star_full = '<span icon="star"></span>';
				var star_half = '<span icon="star-outline"></span>';
				for(var i=0; i<index.length; i++) {
					var key = index[i];
					data = $.jStorage.get(key);
					if(data.data) {
						var title   = data.title || 'Unknown Title';
						var artist  = data.artist || 'Unknown Artist';
						var version = data.version || 1;
						var rating  = data.rating || 0;
						var rating_count = data.rating_count || 0;
						html = title;
						html += '<div>';
							html += '<artist fg-blueA400>';
								html += artist;
							html += '</artist>';
							html += '<info>';
								html += 'version ' + version + '<br>';
								
								switch(Math.floor(rating)) {
									case 1: html += star_full; html += star_half; html += star_half; html += star_half; html += star_half; break;
									case 2: html += star_full; html += star_full; html += star_half; html += star_half; html += star_half; break;
									case 3: html += star_full; html += star_full; html += star_full; html += star_half; html += star_half; break;
									case 4: html += star_full; html += star_full; html += star_full; html += star_full; html += star_half; break;
									case 5: html += star_full; html += star_full; html += star_full; html += star_full; html += star_full; break;
								}
								html += ' &mdash; <span fg-indigo>' + rating_count + ' votes</span>';
							html += '</info>';
							html += '<div title="Remove" itemaction onclick="app.removeContent(this)" itemkey="' + key + '">';
								html += '<i icon="close"></i>';
							html += '</div>';
						html += '</div>';
						var elem = $('<div padded item onclick="app.getContent(this)" itemkey="' + key + '">');
						elem.append(html);
						frag.appendChild(elem[0]);
					}
				}
				container.append(frag);
			}
		})(data);
		if(type === 'suggestions')
			parser = this.parseSuggestions;
		else
			parser = this.parseResults;
	} else if(type === 'data') {
		parser = this.parseData;
	} else if(type === 'save') {
		parser = this.saveData;
	}
	// search online
	this.ajaxStream = $.ajax({
		type: 'POST',
		url: this.server + '/handle.php?ajaxified=true',
		async: !(type === 'save'),
		context: this,
		success: parser,
		error: function(error) {
			//console.log(error);
		},
		data: data,
		dataType: 'json',
		accepts: 'application/json',
		headers: {'X-Requested-From': 'Application'}
	});
}

Chordd.prototype.randomize = function() {
	var index = $.jStorage.index();
	this.display($.jStorage.get(index[Math.floor(Math.random()*index.length)]));
}

Chordd.prototype.getContent = function(element) {
	var key  = $(element).attr('itemkey');
	var data = $.jStorage.get(key);
	this.display(data);
}

Chordd.prototype.saveContent = function(element) {
	var key  = $(element).attr('itemkey');
	var type = $(element).attr('itemtype');
	var data = this.results[type + 's'][parseInt(key)];
	element = $(element);
	// stop bubbling
	window.event.stopPropagation();
	window.event.preventDefault();
	// get content if it hasnt been saved yet
	key = data.artist+'/'+data.title+'/'+data.version.toString()+'/'+type;
	if(!$.jStorage.get(key)) {
		this.request('save', {
			__safe: true,
			data_request: 'content',
			data_type: type,
			data_title: data.title,
			data_artist: data.artist,
			data_version: data.version,
			data_rating: data.rating,
			data_ratingcount: data.rating_count
		});
		element
			.attr('onclick', 'app.removeContent(this)')
			.attr('itemkey', key)
			.find('[icon="favorite-outline"]')
				.removeAttr('icon')
				.attr('icon', 'close')
		;
		var newEl = element.parent().parent().clone();
		newEl.find('.persistent').removeClass('persistent');
		$('[list-local]').append(newEl);
		//element.remove();
	}
}

Chordd.prototype.saveData = function(json) {
	if(this.verify(json) === true) {
		// save to storage if it hasn't been done yet
		key = json.artist+'/'+json.title+'/'+json.version.toString()+'/'+json.type;
		if(!$.jStorage.get(key)){
			// save
			$.jStorage.set(key, {
				data: json.data,
				type: json.type,
				title: json.title,
				artist: json.artist,
				version: json.version,
				rating: json.rating,
				rating_count: json.rating_count
			});
			//console.log('content saved!');
			this.refreshInfo();
		}
	}
}

Chordd.prototype.removeContent = function(element) {
	var key  = $(element).attr('itemkey');
	if(confirm('Remove the saved item?') === true) {
		$.jStorage.deleteKey(key);
		this.refreshInfo();
		var elem = $(element);
		var key  = elem.attr('itemkey');
		// find the local shit
		$('[itemkey="' + key + '"]', '[list-local]').remove();
		// search again
		$('[searchbutton]').click();
	}
	// stop bubbling
	window.event.stopPropagation();
	window.event.preventDefault();
}

Chordd.prototype.display = function(data) {
	var title   = data.title   || 'Unknown Title';
	var artist  = data.artist  || 'Unknown Artist';
	var version = data.version || 1;
	var rating  = data.rating  || 0;
	var rating_count = data.rating_count || 0;
	var content = JSON.parse(data.data) || 'Unknown Content';
	content = content.store.page.data.tab_view.wiki_tab.content || 'Unknown Content';
	$('[tab-title]').html(capitalizeFirstLetter(title));
	$('[tab-artist]').html(capitalizeFirstLetter(artist));
	var html = '<span>';
	var star_full = '<span icon="star"></span>';
	var star_half = '<span icon="star-outline"></span>';
		switch(Math.floor(rating)) {
			case 1: html += star_full; html += star_half; html += star_half; html += star_half; html += star_half; break;
			case 2: html += star_full; html += star_full; html += star_half; html += star_half; html += star_half; break;
			case 3: html += star_full; html += star_full; html += star_full; html += star_half; html += star_half; break;
			case 4: html += star_full; html += star_full; html += star_full; html += star_full; html += star_half; break;
			case 5: html += star_full; html += star_full; html += star_full; html += star_full; html += star_full; break;
		}
	html += '</span> ';
	html += rating_count + ' votes &middot; version ' + version;
	$('[tab-details]').html(html);
	$('[tablature]').html(content);
	// show content
	$('[page-id]').removeAttr('active');
	$('[page-id="content"]').attr('active', true);
	this.title = '<span fg-white>' + title + '</span>';
	// scroll to top
	$(window).scrollTop(0);
	// hide panel
	$('[panel]').removeAttr("opened");
	$('[panel] > [drawer]').velocity({ left: '-305px' }, { duration: 200, easing: 'easeOutQuint' });
	$('body').css({overflow: 'auto'});
}

Chordd.prototype.autoScroll = function(i) {
	var speed;
	switch(i) {
		case -1:
			$('[fab-options]').show().velocity({ height: '392px', opacity: 1 }, { duration: 400, easing: 'easeOutQuint' });
		break;
		case 0:
			// pause
		break;
		default: speed = i;
	}
	if(i > -1) {
		$('[fab-options]').velocity({ height: '56px', opacity: 0 }, { duration: 400, easing: 'easeOutQuint', complete: function() {$(this).hide()} });
		try{ clearInterval(this.scrollID); }catch(e){};
		if(i > 0) {
			this.scrollID = setInterval(function() {
				$(window).scrollTop($(window).scrollTop()+1);
			}, Math.round(1000/(2.5*speed)));
		}
	}
}

Chordd.prototype.transpose = {
	up: function() {
		$('[tablature] span').each(function(i){
			var alp = "ABCDEFG".split("");
			var crd = this.innerHTML.split("");
			var ncd = "";
			if(crd[1]!="#"&&crd[1]!="b"){
				// no flat/sharps, just chords
				// chords will be transformed from
				// G -> G# || Am -> A#m
				// ** B -> C, E -> F
				if(crd[0]=="B"){
					crd[0] = "C";
				}else if(crd[0]=="E"){
					crd[0] = "F";
				}else{
					crd[0] = crd[0] + "#";
				}
				// merge
				for(var i=0;i<crd.length;i++) ncd += crd[i];
				this.innerHTML = ncd;
			}else{
				// has flat or sharp
				if(crd[1]=="#"){
					// has sharp
					// chords will be transformed from
					// G# -> A, F#m -> Gm
					// remove the #
					if(crd[0]=="G"){
						crd[0] = "A";
					}else{
						for(var o=0;o<alp.length;o++){
							if(alp[o]==crd[0]){
								crd[0] = alp[o+1];
								break;
							}
						}
					}
					crd[1]="";
				}else if(crd[1]=="b"){
					// has flat
					// chords will be transformed from
					// Ab -> A, Bb -> B
					// remove the b and that's it
					crd[1] = "";
				}
				// merge
				for(var i=0;i<crd.length;i++) ncd += crd[i];
				this.innerHTML = ncd;
			}
		});
	},
	down: function() {
		$('[tablature] span').each(function(i){
			var alp = "ABCDEFG".split("");
			var crd = this.innerHTML.split("");
			var ncd = "";
			if(crd[1]!="#"&&crd[1]!="b"){
				// no flat/sharps, just chords
				// chords will be transformed from
				// G -> F# || Am -> G#m
				// ** C -> B, F -> E
				if(crd[0]=="C"){
					crd[0] = "B";
				}else if(crd[0]=="F"){
					crd[0] = "E";
				}else if(crd[0]=="A"){
					crd[0] = "G#";
				}else{
					for(var o=0;o<alp.length;o++){
						if(alp[o]==crd[0]){
							crd[0] = alp[o-1] + "#";
							break;
						}
					}
				}
				// merge
				for(var i=0;i<crd.length;i++) ncd += crd[i];
				this.innerHTML = ncd;
			}else{
				// has flat or sharp
				if(crd[1]=="#"){
					// has sharp
					// chords will be transformed from
					// G# -> G, A#m -> Gm
					// just remove the #
					crd[1] = "";
				}else if(crd[1]=="b"){
					// has flat
					// chords will be transformed from
					// Ab -> G, Bb -> B
					// remove the b
					if(crd[0]=="A"){
						crd[0] = "G";
					}else{
						for(var o=0;o<alp.length;o++){
							if(alp[o]==crd[0]){
								crd[0] = alp[o-1];
								break;
							}
						}
					}
					crd[1] = "";
				}
				// merge
				for(var i=0;i<crd.length;i++) ncd += crd[i];
				this.innerHTML = ncd;
			}
		});
	},
	fontSize: function() {
		if(app.config.fontSize >= 18) {
			app.config.fontSize = 8;
		} else {
			app.config.fontSize += 2;
		}
		app.saveConfig();
	}
}

Chordd.prototype.saveConfig = function() {
	$.jStorage.set('chorddConfig', this.config);
	this.applyConfig();
}

Chordd.prototype.applyConfig = function() {
	$('[tablature]').css({ fontSize: this.config.fontSize });
	if("undefined" === typeof(this.config.tabsFirst)) {
		this.config.tabsFirst = false;
		this.saveConfig();
	}
}

function capitalizeFirstLetter(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
}