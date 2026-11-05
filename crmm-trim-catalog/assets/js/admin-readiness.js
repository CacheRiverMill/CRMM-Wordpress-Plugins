( function ( $, config ) {
	'use strict';
	if ( ! config ) {
		return;
	}
	$( function () {
		let edits = 0;
		let savedEdits = 0;
		let revision = 0;
		let timer;
		let wasSaving = false;
		let savingEdits = 0;
		const status = $( '#crmm-readiness-save-state' );
		function showState() {
			status.text( edits > savedEdits ? config.changedMessage : config.savedMessage );
		}
		function refresh( completedEdits ) {
			const request = ++revision;
			$.post( config.ajaxUrl, { action: 'crmm_trim_readiness', nonce: config.nonce, post_id: config.postId } ).done( function ( response ) {
				if ( request !== revision ) {
					return;
				}
				if ( ! response || ! response.success ) {
					status.text( config.errorMessage );
					return;
				}
				$( '#crmm-readiness-results' ).html( response.data.html );
				if ( typeof completedEdits === 'number' ) {
					savedEdits = completedEdits;
				}
				showState();
			} ).fail( function () {
				if ( request === revision ) {
					status.text( config.errorMessage );
				}
			} );
		}
		$( document ).on( 'input change', '.acf-field input, .acf-field select, .acf-field textarea', function () {
			edits++;
			showState();
		} );
		$( document ).on( 'click', '#crmm-refresh-readiness', function () {
			refresh();
		} );
		if ( window.wp && wp.data && wp.data.subscribe ) {
			wp.data.subscribe( function () {
				const editor = wp.data.select( 'core/editor' );
				if ( ! editor || ! editor.isSavingPost ) {
					return;
				}
				const editPost = wp.data.select( 'core/edit-post' );
				const savingMetaBoxes = editPost && editPost.isSavingMetaBoxes && editPost.isSavingMetaBoxes();
				const saving = savingMetaBoxes || ( editor.isSavingPost() && ! editor.isAutosavingPost() );
				if ( saving && ! wasSaving ) {
					clearTimeout( timer );
					savingEdits = edits;
				}
				if ( wasSaving && ! saving && editor.didPostSaveRequestSucceed() ) {
					clearTimeout( timer );
					const completedEdits = savingEdits;
					timer = setTimeout( function () { refresh( completedEdits ); }, 500 );
				}
				wasSaving = saving;
			} );
		}
	} );
} )( jQuery, window.CRMMTrimReadiness );
