( function ( wp, config ) {
	'use strict';

	if ( ! wp || ! wp.blocks || ! wp.data || ! config ) {
		return;
	}

	const registerSource = wp.blocks.registerBlockBindingsSource;
	if ( typeof registerSource !== 'function' ) {
		return;
	}

	const fieldsByKey = Object.fromEntries(
		( config.fields || [] ).map( ( field ) => [ field.key, field ] )
	);

	function getAttachmentUrl( select, attachmentId ) {
		const id = Number( attachmentId );
		if ( ! id ) {
			return '';
		}

		const media = select( 'core' ).getEntityRecord( 'postType', 'attachment', id );
		return media && media.source_url ? media.source_url : '';
	}

	registerSource( {
		name: config.source,
		label: wp.i18n.__( 'CRMM Catalog Fields', 'crmm-trim-catalog' ),
		usesContext: [ 'postId', 'postType' ],

		getFieldsList() {
			return ( config.fields || [] ).map( ( field ) => ( {
				label: field.label,
				type: field.type,
				args: {
					field: field.key,
				},
			} ) );
		},

		getValues( { bindings, context, select } ) {
			if ( ! context || context.postType !== config.postType || ! context.postId ) {
				return {};
			}

			const record = select( 'core' ).getEditedEntityRecord(
				'postType',
				context.postType,
				context.postId
			);

			if ( ! record || ! record.meta ) {
				return {};
			}

			const values = {};

			Object.entries( bindings || {} ).forEach( ( [ attributeName, binding ] ) => {
				const key = binding && binding.args ? binding.args.field : '';
				const field = fieldsByKey[ key ];
				if ( ! field ) {
					return;
				}

				const rawValue = record.meta[ field.meta ];
				if ( field.format === 'attachment_url' ) {
					values[ attributeName ] = getAttachmentUrl( select, rawValue );
					return;
				}

				values[ attributeName ] = rawValue === null || rawValue === undefined ? '' : String( rawValue );
			} );

			return values;
		},

		canUserEditValue() {
			return false;
		},
	} );
} )( window.wp, window.CRMMTrimBlockBindings );
