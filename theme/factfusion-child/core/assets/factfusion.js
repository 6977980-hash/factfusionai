/**
 * FactFusion AI front end: generator, copy/share/save, search suggestions,
 * saved facts, report form and embed-code copy. No dependencies.
 */
( function () {
	var config = window.factfusion;
	if ( ! config ) {
		return;
	}
	var text = config.text;
	var storageKey = 'factfusion-favorites';

	function $( selector, root ) {
		return ( root || document ).querySelector( selector );
	}

	function $all( selector, root ) {
		return Array.prototype.slice.call( ( root || document ).querySelectorAll( selector ) );
	}

	function say( root, message ) {
		var status = $( '[data-ff-status]', root );
		if ( status ) {
			status.textContent = message;
		}
	}

	function request( path, options ) {
		options = options || {};
		options.headers = Object.assign( { Accept: 'application/json' }, options.headers || {} );
		return fetch( config.rest + path, options ).then( function ( response ) {
			return response.json().then(
				function ( data ) {
					return { ok: response.ok, status: response.status, data: data };
				},
				function () {
					return { ok: response.ok, status: response.status, data: null };
				}
			);
		} );
	}

	/* ---------- Saved facts (this browser only) ---------- */

	function savedIds() {
		try {
			var ids = JSON.parse( window.localStorage.getItem( storageKey ) || '[]' );
			return Array.isArray( ids ) ? ids.filter( Number.isInteger ) : [];
		} catch ( e ) {
			return [];
		}
	}

	function setSaved( ids ) {
		try {
			window.localStorage.setItem( storageKey, JSON.stringify( ids.slice( -200 ) ) );
			return true;
		} catch ( e ) {
			return false;
		}
	}

	/* ---------- Copy and share ---------- */

	function copy( value ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( value );
		}
		return new Promise( function ( resolve, reject ) {
			var area = document.createElement( 'textarea' );
			area.value = value;
			area.setAttribute( 'readonly', '' );
			area.style.position = 'fixed';
			area.style.opacity = '0';
			document.body.appendChild( area );
			area.select();
			var done = false;
			try {
				done = document.execCommand( 'copy' );
			} catch ( e ) {
				done = false;
			}
			document.body.removeChild( area );
			if ( done ) {
				resolve();
			} else {
				reject();
			}
		} );
	}

	function factText( fact ) {
		var line = fact.claim;
		if ( fact.source ) {
			line += ' (' + text.source + ': ' + fact.source.name + ')';
		}
		return line + ' ' + fact.url;
	}

	/* ---------- Action buttons on any fact card ---------- */

	function readFact( root ) {
		var holder = root.hasAttribute( 'data-ff-fact' ) ? root : $( '[data-ff-fact]', root );
		try {
			return holder ? JSON.parse( holder.getAttribute( 'data-ff-fact' ) ) : null;
		} catch ( e ) {
			return null;
		}
	}

	function syncSave( root ) {
		var button = $( '[data-ff-save]', root );
		var fact = readFact( root );
		if ( ! button || ! fact ) {
			return;
		}
		var saved = savedIds().indexOf( fact.id ) !== -1;
		button.setAttribute( 'aria-pressed', saved ? 'true' : 'false' );
		button.textContent = saved ? text.savedLabel : text.save;
	}

	function wireActions( root ) {
		var copyButton = $( '[data-ff-copy]', root );
		var shareButton = $( '[data-ff-share]', root );
		var saveButton = $( '[data-ff-save]', root );

		if ( copyButton ) {
			copyButton.hidden = false;
			copyButton.addEventListener( 'click', function () {
				var fact = readFact( root );
				if ( ! fact ) {
					return;
				}
				copy( factText( fact ) ).then(
					function () {
						say( root, text.copied );
					},
					function () {
						say( root, text.copyFailed );
					}
				);
			} );
		}

		if ( shareButton ) {
			shareButton.hidden = false;
			shareButton.addEventListener( 'click', function () {
				var fact = readFact( root );
				if ( ! fact ) {
					return;
				}
				if ( navigator.share ) {
					navigator.share( { title: fact.title, text: fact.claim, url: fact.url } ).catch( function () {} );
					return;
				}
				copy( fact.url ).then(
					function () {
						say( root, text.linkCopied );
					},
					function () {
						say( root, text.copyFailed );
					}
				);
			} );
		}

		if ( saveButton ) {
			saveButton.hidden = false;
			syncSave( root );
			saveButton.addEventListener( 'click', function () {
				var fact = readFact( root );
				if ( ! fact ) {
					return;
				}
				var ids = savedIds();
				var at = ids.indexOf( fact.id );
				if ( at === -1 ) {
					ids.push( fact.id );
				} else {
					ids.splice( at, 1 );
				}
				if ( ! setSaved( ids ) ) {
					say( root, text.failed );
					return;
				}
				syncSave( root );
				say( root, at === -1 ? text.saved : text.removed );
			} );
		}
	}

	/* ---------- Homepage generator ---------- */

	function wireGenerator( root ) {
		var card = $( '[data-ff-card]', root );
		var next = $( '[data-ff-next]', root );
		var chips = $( '[data-ff-chips]', root );
		var hint = $( '[data-ff-hint]', root );
		// A tool page can lock the generator to one category.
		var category = root.getAttribute( 'data-ff-locked' ) || '';
		var busy = false;

		var counter = $( '[data-ff-count]', root );
		var countKey = 'factfusion-read';

		// How many facts this visitor has seen in this browser tab.
		function count( add ) {
			if ( ! counter ) {
				return;
			}
			var seen = 1;
			try {
				seen = parseInt( window.sessionStorage.getItem( countKey ) || '0', 10 ) + add;
				window.sessionStorage.setItem( countKey, String( seen ) );
			} catch ( e ) {
				seen = Math.max( 1, add );
			}
			counter.textContent = counter.getAttribute( 'data-ff-count-text' ).replace( '%s', seen );
		}

		// "2026-10-03" as "Oct 3, 2026", the way the server writes it.
		function shortDate( ymd ) {
			var parts = String( ymd ).split( '-' );
			var date = new Date( Date.UTC( +parts[ 0 ], +parts[ 1 ] - 1, +parts[ 2 ], 12 ) );
			return date.toLocaleDateString( 'en-US', { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC' } );
		}

		function show( fact ) {
			var compact = {
				id: fact.id,
				title: fact.title,
				claim: fact.claim,
				url: fact.url,
				category: fact.category,
				source: fact.sources && fact.sources.length ? fact.sources[ 0 ] : null,
				verified_date: fact.verified_date || null,
				confidence: fact.confidence,
			};
			card.setAttribute( 'data-ff-fact', JSON.stringify( compact ) );
			$( '[data-ff-field="category"]', card ).textContent = compact.category ? compact.category.name : '';
			$( '[data-ff-field="claim"]', card ).textContent = compact.claim;
			$( '[data-ff-field="link"]', card ).setAttribute( 'href', compact.url );
			var source = $( '[data-ff-field="source"]', card );
			var tier = $( '[data-ff-field="tier"]', card );
			if ( source && compact.source ) {
				source.textContent = compact.source.name;
				source.setAttribute( 'href', compact.source.url );
				tier.textContent = text.tier.replace( '%s', compact.source.tier );
			}
			var badge = $( '[data-ff-field="badge"]', card );
			var verified = $( '[data-ff-field="verified"]', card );
			if ( badge ) {
				badge.hidden = ! compact.verified_date;
			}
			if ( verified ) {
				verified.hidden = ! compact.verified_date;
				var time = $( 'time', verified );
				if ( time && compact.verified_date ) {
					time.setAttribute( 'datetime', compact.verified_date );
					time.textContent = shortDate( compact.verified_date );
				}
			}
			var confidence = $( '[data-ff-field="confidence"]', card );
			if ( confidence && typeof compact.confidence === 'number' ) {
				confidence.textContent = confidence.textContent.replace( /\d+\/100/, compact.confidence + '/100' );
			}
			syncSave( root );
			count( 1 );
		}

		count( 1 );

		function load() {
			if ( busy ) {
				return;
			}
			busy = true;
			card.setAttribute( 'aria-busy', 'true' );
			say( root, '' );
			var current = readFact( root );
			var query = '?exclude=' + ( current ? current.id : 0 ) + ( category ? '&category=' + encodeURIComponent( category ) : '' );
			request( 'random' + query )
				.then( function ( result ) {
					if ( result.ok && result.data && result.data.id ) {
						show( result.data );
					} else if ( result.status === 429 ) {
						say( root, text.limited );
					} else if ( result.status === 404 ) {
						say( root, text.none );
					} else {
						say( root, text.failed );
					}
				} )
				.catch( function () {
					say( root, text.failed );
				} )
				.then( function () {
					busy = false;
					card.removeAttribute( 'aria-busy' );
				} );
		}

		next.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			load();
		} );
		next.setAttribute( 'role', 'button' );
		next.addEventListener( 'keydown', function ( event ) {
			if ( event.key === ' ' ) {
				event.preventDefault();
				event.stopPropagation();
				load();
			}
		} );

		if ( chips ) {
			chips.hidden = false;
			chips.addEventListener( 'click', function ( event ) {
				var chip = event.target.closest( '[data-ff-category]' );
				if ( ! chip ) {
					return;
				}
				category = chip.getAttribute( 'data-ff-category' );
				$all( '[data-ff-category]', chips ).forEach( function ( other ) {
					other.setAttribute( 'aria-pressed', other === chip ? 'true' : 'false' );
				} );
				load();
			} );
		}

		// Space bar gives a new fact, unless the reader is typing or using a control.
		if ( hint ) {
			hint.hidden = false;
		}
		document.addEventListener( 'keydown', function ( event ) {
			if ( event.key !== ' ' || event.defaultPrevented || event.ctrlKey || event.metaKey || event.altKey ) {
				return;
			}
			var target = event.target;
			if ( target && target.closest && target.closest( 'input, textarea, select, button, a, summary, [contenteditable], [role="button"]' ) ) {
				return;
			}
			event.preventDefault();
			load();
		} );

		wireActions( root );
	}

	/* ---------- Search suggestions (combobox) ---------- */

	function wireSearch( input ) {
		var list = document.getElementById( 'ff-suggest' );
		var status = $( '[data-ff-suggest-status]' );
		var timer = null;
		var active = -1;
		var latest = 0;

		if ( ! list ) {
			return;
		}
		input.setAttribute( 'role', 'combobox' );
		input.setAttribute( 'aria-autocomplete', 'list' );
		input.setAttribute( 'aria-controls', 'ff-suggest' );
		input.setAttribute( 'aria-expanded', 'false' );

		function close() {
			list.hidden = true;
			list.innerHTML = '';
			active = -1;
			input.setAttribute( 'aria-expanded', 'false' );
			input.removeAttribute( 'aria-activedescendant' );
		}

		function highlight( index ) {
			var options = $all( '[role="option"]', list );
			options.forEach( function ( option, i ) {
				option.setAttribute( 'aria-selected', i === index ? 'true' : 'false' );
			} );
			active = index;
			if ( index >= 0 && options[ index ] ) {
				input.setAttribute( 'aria-activedescendant', options[ index ].id );
			} else {
				input.removeAttribute( 'aria-activedescendant' );
			}
		}

		function render( items ) {
			list.innerHTML = '';
			items.forEach( function ( item, i ) {
				var option = document.createElement( 'li' );
				option.id = 'ff-suggest-' + i;
				option.setAttribute( 'role', 'option' );
				option.setAttribute( 'aria-selected', 'false' );
				option.setAttribute( 'data-url', item.url );
				option.textContent = item.title;
				list.appendChild( option );
			} );
			list.hidden = items.length === 0;
			input.setAttribute( 'aria-expanded', items.length ? 'true' : 'false' );
			active = -1;
			if ( status ) {
				status.textContent = items.length ? text.suggestions.replace( '%d', items.length ) : text.noResults;
			}
		}

		input.addEventListener( 'input', function () {
			var value = input.value.trim();
			window.clearTimeout( timer );
			if ( value.length < 2 ) {
				close();
				return;
			}
			timer = window.setTimeout( function () {
				var ticket = ++latest;
				request( 'search?limit=6&q=' + encodeURIComponent( value ) )
					.then( function ( result ) {
						if ( ticket === latest && result.ok && Array.isArray( result.data ) ) {
							render( result.data );
						}
					} )
					.catch( function () {} );
			}, 250 );
		} );

		input.addEventListener( 'keydown', function ( event ) {
			var options = $all( '[role="option"]', list );
			if ( event.key === 'ArrowDown' && options.length ) {
				event.preventDefault();
				highlight( ( active + 1 ) % options.length );
			} else if ( event.key === 'ArrowUp' && options.length ) {
				event.preventDefault();
				highlight( active <= 0 ? options.length - 1 : active - 1 );
			} else if ( event.key === 'Enter' && active >= 0 && options[ active ] ) {
				event.preventDefault();
				window.location.href = options[ active ].getAttribute( 'data-url' );
			} else if ( event.key === 'Escape' ) {
				close();
			}
		} );

		// mousedown fires before the input loses focus, so the choice is not lost.
		list.addEventListener( 'mousedown', function ( event ) {
			var option = event.target.closest( '[role="option"]' );
			if ( option ) {
				event.preventDefault();
				window.location.href = option.getAttribute( 'data-url' );
			}
		} );

		input.addEventListener( 'blur', function () {
			window.setTimeout( close, 150 );
		} );
	}

	/* ---------- Saved facts list on /facts/ ---------- */

	function wireSaved( section ) {
		var ids = savedIds();
		var list = $( '[data-ff-saved-list]', section );
		if ( ! ids.length || ! list ) {
			return;
		}
		request( 'facts?include=' + ids.slice( -50 ).join( ',' ) )
			.then( function ( result ) {
				if ( ! result.ok || ! Array.isArray( result.data ) || ! result.data.length ) {
					return;
				}
				result.data.forEach( function ( fact ) {
					var item = document.createElement( 'li' );
					var link = document.createElement( 'a' );
					var claim = document.createElement( 'p' );
					item.className = 'ff-card';
					link.className = 'ff-fact-list__title';
					link.href = fact.url;
					link.textContent = fact.title;
					claim.textContent = fact.claim;
					item.appendChild( link );
					item.appendChild( claim );
					list.appendChild( item );
				} );
				section.hidden = false;
			} )
			.catch( function () {} );
	}

	/* ---------- Report form ---------- */

	function wireReport( form ) {
		var holder = $( '[data-ff-turnstile]', form );
		var details = form.closest( 'details' );

		// The human check loads only when someone opens the form.
		if ( holder && config.turnstile && details ) {
			details.addEventListener( 'toggle', function load() {
				if ( ! details.open || holder.getAttribute( 'data-loaded' ) ) {
					return;
				}
				holder.setAttribute( 'data-loaded', '1' );
				holder.className = 'cf-turnstile';
				holder.setAttribute( 'data-sitekey', config.turnstile );
				var script = document.createElement( 'script' );
				script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
				script.async = true;
				document.head.appendChild( script );
			} );
		}

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			var button = $( 'button[type="submit"]', form );
			var body = new URLSearchParams( new FormData( form ) );
			body.delete( 'redirect' );
			button.disabled = true;
			say( form, text.sending );
			request( 'report', { method: 'POST', body: body } )
				.then( function ( result ) {
					if ( result.ok ) {
						form.reset();
						say( form, text.reported );
					} else {
						say( form, result.data && result.data.message ? result.data.message : text.failed );
					}
				} )
				.catch( function () {
					say( form, text.failed );
				} )
				.then( function () {
					button.disabled = false;
				} );
		} );
	}

	/* ---------- Embed code ---------- */

	function wireEmbed( button ) {
		var box = button.closest( 'details, [data-ff-embed-box]' );
		var code = $( '[data-ff-embed-code]', box );
		button.hidden = false;
		button.addEventListener( 'click', function () {
			copy( code.value ).then(
				function () {
					say( box, text.copied );
				},
				function () {
					code.select();
					say( box, text.copyFailed );
				}
			);
		} );
		code.addEventListener( 'focus', function () {
			code.select();
		} );
	}

	/* ---------- Myth or fact quiz on the homepage ---------- */

	// Without the script every question shows with an "answer" toggle. With it,
	// one question at a time: the reader picks Myth or Fact, then sees the
	// verified fact and its source.
	function wireQuiz( quiz ) {
		var items = $all( '[data-ff-quiz-item]', quiz );
		var next = $( '[data-ff-quiz-next]', quiz );
		var score = $( '[data-ff-quiz-score]', quiz );
		var at = 0;
		var right = 0;
		if ( ! items.length || ! next || ! score ) {
			return;
		}
		quiz.classList.add( 'is-playing' );

		function progress() {
			score.textContent = quiz.getAttribute( 'data-ff-progress' ).replace( '%1$s', at + 1 ).replace( '%2$s', items.length ).replace( '%3$s', right );
		}

		function open( index ) {
			at = index;
			items.forEach( function ( item, i ) {
				var answer = $( '[data-ff-quiz-answer]', item );
				item.hidden = i !== index;
				answer.open = false;
				answer.hidden = true;
				$( '[data-ff-quiz-verdict]', item ).textContent = '';
				$all( '[data-ff-guess]', item ).forEach( function ( button ) {
					button.disabled = false;
				} );
			} );
			next.hidden = true;
			progress();
		}

		items.forEach( function ( item ) {
			$all( '[data-ff-guess]', item ).forEach( function ( button ) {
				button.addEventListener( 'click', function () {
					var correct = button.getAttribute( 'data-ff-guess' ) === item.getAttribute( 'data-ff-answer' );
					var answer = $( '[data-ff-quiz-answer]', item );
					if ( correct ) {
						right++;
					}
					$all( '[data-ff-guess]', item ).forEach( function ( other ) {
						other.disabled = true;
					} );
					$( '[data-ff-quiz-verdict]', item ).textContent = quiz.getAttribute( correct ? 'data-ff-right' : 'data-ff-wrong' );
					answer.hidden = false;
					answer.open = true;
					next.textContent = quiz.getAttribute( at + 1 >= items.length ? 'data-ff-again' : 'data-ff-next' );
					next.hidden = false;
					progress();
					next.focus();
				} );
			} );
		} );

		next.addEventListener( 'click', function () {
			if ( at + 1 >= items.length ) {
				right = 0;
				open( 0 );
			} else {
				open( at + 1 );
			}
			var first = $( '[data-ff-guess]', items[ at ] );
			if ( first ) {
				first.focus();
			}
		} );

		open( 0 );
	}

	$all( '[data-ff-generator]' ).forEach( wireGenerator );
	$all( '[data-ff-quiz]' ).forEach( wireQuiz );
	$all( '[data-ff-actions]' ).forEach( wireActions );
	$all( '[data-ff-search]' ).forEach( wireSearch );
	$all( '[data-ff-saved]' ).forEach( wireSaved );
	$all( '[data-ff-report]' ).forEach( wireReport );
	$all( '[data-ff-embed-copy]' ).forEach( wireEmbed );
} )();
