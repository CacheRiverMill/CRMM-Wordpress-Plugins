( function ( $, config ) {
	'use strict';

	if ( ! config ) {
		return;
	}
	let previewRevision = 0;
	let previewRequest = null;

	function updatePostTitle( value ) {
		const editor = window.wp && wp.data && wp.data.select ? wp.data.select( 'core/editor' ) : null;
		if ( editor && editor.getEditedPostAttribute( 'title' ) !== value ) {
			wp.data.dispatch( 'core/editor' ).editPost( { title: value } );
		}
		const classicTitle = $( '#title' );
		if ( classicTitle.length && classicTitle.val() !== value ) {
			classicTitle.val( value ).trigger( 'input' ).trigger( 'change' );
		}
	}

	function fields() {
		return {
			category: $( '[data-key="field_crmm_trim_category"] select' ),
			subtype: $( '[data-key="field_crmm_trim_subtype"] select' ),
		};
	}

	function updateSubtypeChoices() {
		if ( showAssignedNumber() ) {
			return;
		}
		const controls = fields();
		const categoryCode = config.categoryCodes[ String( controls.category.val() || '' ) ] || '';

		controls.subtype.find( 'option' ).each( function () {
			const definition = config.subtypes[ String( this.value ) ];
			this.disabled = Boolean( definition && categoryCode && definition.category !== categoryCode );
		} );

		const selected = config.subtypes[ String( controls.subtype.val() || '' ) ];
		if ( selected && categoryCode && selected.category !== categoryCode ) {
			controls.subtype.val( '' ).trigger( 'change' );
		} else {
			controls.subtype.trigger( 'change.select2' );
		}
	}

	function showAssignedNumber() {
		const numberField = $( '[data-key="field_crmm_part_number"] input' );
		const assigned = config.assignedNumber || numberField.val();
		if ( ! assigned ) {
			return false;
		}
		numberField.val( assigned );
		$( '#crmm-expected-number' ).text( config.assignedLabel + ' ' + assigned );
		updatePostTitle( assigned );
		return true;
	}

	function previewNumber() {
		const revision = ++previewRevision;
		if ( previewRequest ) {
			previewRequest.abort();
			previewRequest = null;
		}
		if ( showAssignedNumber() ) {
			return;
		}
		const controls = fields();
		const categoryCode = config.categoryCodes[ String( controls.category.val() || '' ) ] || '';
		const subtype = config.subtypes[ String( controls.subtype.val() || '' ) ];
		const target = $( '#crmm-expected-number' );

		if ( ! categoryCode || ! subtype || subtype.category !== categoryCode ) {
			target.text( 'Select a category and profile type to preview the expected next number.' );
			return;
		}
		target.text( 'Calculating expected number…' );

		previewRequest = $.post( config.ajaxUrl, {
			action: 'crmm_preview_trim_number',
			nonce: config.nonce,
			post_id: config.postId,
			category_code: categoryCode,
			subtype_code: subtype.code,
		} ).done( function ( response ) {
			if ( revision !== previewRevision || showAssignedNumber() ) {
				return;
			}
			if ( response && response.success ) {
				if ( response.data.assigned ) {
					config.assignedNumber = response.data.part_number;
					showAssignedNumber();
				} else {
					// A preview is never an assignment or a post title.
					target.text( config.previewLabel + ' ' + response.data.part_number );
				}
			} else if ( response && response.data && response.data.message ) {
				target.text( response.data.message );
			}
		} ).fail( function ( xhr, status ) {
			if ( revision !== previewRevision || status === 'abort' || showAssignedNumber() ) {
				return;
			}
			target.text( 'The expected number could not be loaded. Save the profile to retry assignment.' );
		} );
	}

	$( document ).on( 'change', '[data-key="field_crmm_trim_category"] select', function () {
		updateSubtypeChoices();
		previewNumber();
	} );
	$( document ).on( 'change', '[data-key="field_crmm_trim_subtype"] select', previewNumber );
	$( document ).on( 'click', '#crmm-assign-number', function () {
		const button = this;
		const form = $( '<form>', { method: 'post', action: button.dataset.actionUrl } );
		form.append( $( '<input>', { type: 'hidden', name: 'action', value: 'crmm_assign_trim_number' } ) );
		form.append( $( '<input>', { type: 'hidden', name: 'post_id', value: button.dataset.postId } ) );
		form.append( $( '<input>', { type: 'hidden', name: '_wpnonce', value: button.dataset.nonce } ) );
		$( document.body ).append( form );
		form.trigger( 'submit' );
	} );
	$( function () {
		updateSubtypeChoices();
		previewNumber();
	} );
} )( jQuery, window.CRMMTrimAdmin );

