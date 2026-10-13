/**
 * Cookie notice. Google Analytics is requested only after "Accept analytics".
 * The answer is kept in this browser; "Cookie settings" reopens the notice.
 */
( function () {
	var config = window.factfusionConsent;
	var notice = document.querySelector( '[data-ff-consent]' );
	var storageKey = 'factfusion-consent';
	var loaded = false;

	if ( ! config || ! config.id || ! notice ) {
		return;
	}

	function read() {
		try {
			return window.localStorage.getItem( storageKey );
		} catch ( e ) {
			return null;
		}
	}

	function write( value ) {
		try {
			window.localStorage.setItem( storageKey, value );
		} catch ( e ) {
			// Storage unavailable: the choice lasts for this page only.
		}
	}

	function loadAnalytics() {
		if ( loaded ) {
			return;
		}
		loaded = true;
		window[ 'ga-disable-' + config.id ] = false;
		window.dataLayer = window.dataLayer || [];
		window.gtag = function () {
			window.dataLayer.push( arguments );
		};
		window.gtag( 'js', new Date() );
		window.gtag( 'config', config.id, { anonymize_ip: true } );
		var script = document.createElement( 'script' );
		script.async = true;
		script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent( config.id );
		document.head.appendChild( script );
	}

	function stopAnalytics() {
		// Tells an already-loaded Analytics script to send nothing more, and removes its cookies.
		window[ 'ga-disable-' + config.id ] = true;
		document.cookie.split( ';' ).forEach( function ( cookie ) {
			var name = cookie.split( '=' )[ 0 ].trim();
			if ( name.indexOf( '_ga' ) === 0 ) {
				document.cookie = name + '=; Max-Age=0; path=/';
				document.cookie = name + '=; Max-Age=0; path=/; domain=.' + window.location.hostname;
			}
		} );
	}

	function show() {
		notice.hidden = false;
		var first = notice.querySelector( 'button' );
		if ( first ) {
			first.focus();
		}
	}

	notice.querySelector( '[data-ff-consent-accept]' ).addEventListener( 'click', function () {
		write( 'granted' );
		notice.hidden = true;
		loadAnalytics();
	} );

	notice.querySelector( '[data-ff-consent-decline]' ).addEventListener( 'click', function () {
		write( 'denied' );
		notice.hidden = true;
		stopAnalytics();
	} );

	Array.prototype.forEach.call( document.querySelectorAll( '[data-ff-consent-open]' ), function ( button ) {
		button.hidden = false;
		button.addEventListener( 'click', show );
	} );

	var answer = read();
	if ( 'granted' === answer ) {
		loadAnalytics();
	} else if ( 'denied' !== answer ) {
		notice.hidden = false;
	}
} )();
