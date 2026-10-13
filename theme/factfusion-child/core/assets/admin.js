/**
 * Fact edit screen: "Draft with AI" button. The draft only fills the
 * explanation box. Nothing is saved until the editor presses Save.
 */
( function () {
	var config = window.factfusionAdmin;
	var button = document.querySelector( '[data-ff-ai-explain]' );
	if ( ! config || ! button ) {
		return;
	}
	var status = document.querySelector( '[data-ff-ai-status]' );
	var explanation = document.getElementById( 'ff-explanation' );
	var assisted = document.getElementById( 'ff-is-ai-assisted' );

	function value( id ) {
		var field = document.getElementById( id );
		return field ? field.value : '';
	}

	button.addEventListener( 'click', function () {
		if ( explanation.value.trim() !== '' && ! window.confirm( config.text.replace ) ) {
			return;
		}
		var sources = Array.prototype.slice
			.call( document.querySelectorAll( 'input[name="ff[source_ids][]"]:checked' ) )
			.map( function ( box ) {
				return Number( box.value );
			} );

		button.disabled = true;
		status.textContent = config.text.working;

		fetch( config.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
			body: JSON.stringify( {
				title: value( 'ff-title' ),
				claim: value( 'ff-claim' ),
				short_answer: value( 'ff-short-answer' ),
				source_summary: value( 'ff-source-summary' ),
				source_ids: sources,
			} ),
		} )
			.then( function ( response ) {
				return response.json().then( function ( data ) {
					return { ok: response.ok, data: data };
				} );
			} )
			.then( function ( result ) {
				if ( result.ok && result.data && result.data.explanation ) {
					explanation.value = result.data.explanation;
					if ( assisted ) {
						assisted.checked = true;
					}
					status.textContent = config.text.done;
				} else {
					status.textContent = result.data && result.data.message ? result.data.message : config.text.failed;
				}
			} )
			.catch( function () {
				status.textContent = config.text.failed;
			} )
			.then( function () {
				button.disabled = false;
			} );
	} );
} )();
