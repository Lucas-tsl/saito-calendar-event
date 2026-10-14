( function ( blocks, element, blockEditor, components, i18n, serverSideRender ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var ServerSideRender = serverSideRender;

	var calendars = ( window.saitoCeBlockData && window.saitoCeBlockData.calendars ) || [];
	var options = [ { label: __( '— Choisir un calendrier —', 'saito-calendar-event' ), value: 0 } ].concat(
		calendars.map( function ( calendar ) {
			return { label: calendar.label, value: calendar.value };
		} )
	);

	blocks.registerBlockType( 'saito-calendar-event/calendar', {
		title: __( 'Calendrier à cases', 'saito-calendar-event' ),
		description: __( 'Affiche un calendrier de cases déblocables (calendrier de l’Avent, etc.) configuré dans Calendriers.', 'saito-calendar-event' ),
		icon: 'calendar-alt',
		category: 'widgets',
		attributes: {
			calendarId: { type: 'number', default: 0 },
		},

		edit: function ( props ) {
			var calendarId = props.attributes.calendarId;

			return el(
				element.Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Calendrier', 'saito-calendar-event' ) },
						el( SelectControl, {
							label: __( 'Calendrier à afficher', 'saito-calendar-event' ),
							value: calendarId,
							options: options,
							onChange: function ( value ) {
								props.setAttributes( { calendarId: parseInt( value, 10 ) } );
							},
						} )
					)
				),
				calendarId
					? el( ServerSideRender, { block: 'saito-calendar-event/calendar', attributes: props.attributes } )
					: el(
							'div',
							{ className: 'saito-ce-block-placeholder', style: { padding: '2em', border: '1px dashed #ccc', textAlign: 'center' } },
							__( 'Choisis un calendrier dans les réglages du bloc.', 'saito-calendar-event' )
					  )
			);
		},

		save: function () {
			return null; // Bloc dynamique : rendu entièrement côté serveur (render_callback).
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n, window.wp.serverSideRender );
