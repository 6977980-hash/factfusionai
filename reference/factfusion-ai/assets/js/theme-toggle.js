/**
 * Light/dark toggle. The saved choice is applied before paint by an inline
 * script in <head>; this file only wires up the button.
 */
( function () {
	var root = document.documentElement;
	var storageKey = 'factfusion-theme';
	var buttons = document.querySelectorAll( '[data-ff-theme-toggle]' );
	var media = window.matchMedia( '(prefers-color-scheme: dark)' );

	if ( ! buttons.length ) {
		return;
	}

	function currentTheme() {
		var saved = root.getAttribute( 'data-theme' );
		if ( 'dark' === saved || 'light' === saved ) {
			return saved;
		}
		return media.matches ? 'dark' : 'light';
	}

	function sync() {
		var pressed = 'dark' === currentTheme() ? 'true' : 'false';
		buttons.forEach( function ( button ) {
			button.setAttribute( 'aria-pressed', pressed );
		} );
	}

	buttons.forEach( function ( button ) {
		button.hidden = false;
		button.addEventListener( 'click', function () {
			var next = 'dark' === currentTheme() ? 'light' : 'dark';
			root.setAttribute( 'data-theme', next );
			try {
				window.localStorage.setItem( storageKey, next );
			} catch ( e ) {
				// Storage unavailable: the choice lasts for this page only.
			}
			sync();
		} );
	} );

	if ( media.addEventListener ) {
		media.addEventListener( 'change', sync );
	}

	sync();
} )();
