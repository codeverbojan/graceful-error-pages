/**
 * Graceful Error Pages — Admin settings scripts.
 *
 * Handles color picker init, media uploader, tab persistence,
 * and preview iframe. Enqueued only on the plugin's settings page.
 *
 * @package
 */

import domReady from '@wordpress/dom-ready';
import jQuery from 'jquery';

const mediaFrames = new WeakMap();

function initColorPickers() {
	jQuery( '.gcep-color-picker' ).wpColorPicker();
}

function initMediaUploaders() {
	document.querySelectorAll( '.gcep-media-select' ).forEach( ( button ) => {
		button.addEventListener( 'click', ( e ) => {
			e.preventDefault();

			const targetSelector = button.dataset.target;
			const targetInput = document.querySelector( targetSelector );

			let frame = mediaFrames.get( button );

			if ( ! frame ) {
				frame = window.wp.media( {
					title: gcepAdmin.mediaTitle,
					button: { text: gcepAdmin.mediaButton },
					multiple: false,
					library: { type: 'image' },
				} );

				frame.on( 'select', () => {
					const attachment = frame
						.state()
						.get( 'selection' )
						.first()
						.toJSON();
					targetInput.value = attachment.url;
					targetInput.dispatchEvent( new Event( 'change' ) );
				} );

				mediaFrames.set( button, frame );
			}

			frame.open();
		} );
	} );

	document.querySelectorAll( '.gcep-media-remove' ).forEach( ( button ) => {
		button.addEventListener( 'click', ( e ) => {
			e.preventDefault();
			const targetSelector = button.dataset.target;
			const input = document.querySelector( targetSelector );
			input.value = '';
			input.dispatchEvent( new Event( 'change' ) );
		} );
	} );
}

function collectFormValues() {
	const params = {};
	const form = document.getElementById( 'gcep-settings-form' );

	if ( ! form ) {
		return params;
	}

	form.querySelectorAll( 'input, select, textarea' ).forEach( ( el ) => {
		const name = el.getAttribute( 'name' );

		if ( ! name || name.indexOf( 'gcep_' ) !== 0 ) {
			return;
		}

		if ( el.type === 'radio' && ! el.checked ) {
			return;
		}

		if ( el.type === 'checkbox' ) {
			params[ name ] = el.checked ? '1' : '0';
			return;
		}

		params[ name ] = el.value;
	} );

	return params;
}

function initPreview() {
	let controller = null;

	document
		.querySelectorAll( '.gcep-preview-btn' )
		.forEach( ( triggerBtn ) => {
			triggerBtn.addEventListener( 'click', ( e ) => {
				e.preventDefault();

				document
					.querySelectorAll(
						'.gcep-preview-overlay, .gcep-preview-modal'
					)
					.forEach( ( el ) => el.remove() );

				if ( controller ) {
					controller.abort();
				}
				controller = new AbortController();
				const { signal } = controller;

				const formValues = collectFormValues();
				let previewUrl =
					gcepAdmin.ajaxUrl +
					'?action=' +
					encodeURIComponent( gcepAdmin.previewAction ) +
					'&_wpnonce=' +
					encodeURIComponent( gcepAdmin.previewNonce );

				Object.entries( formValues ).forEach( ( [ key, value ] ) => {
					if ( value !== '' ) {
						previewUrl +=
							'&' +
							encodeURIComponent( key ) +
							'=' +
							encodeURIComponent( value );
					}
				} );

				const overlay = document.createElement( 'div' );
				overlay.className = 'gcep-preview-overlay';

				const closeBtn = document.createElement( 'button' );
				closeBtn.type = 'button';
				closeBtn.className = 'gcep-preview-close';
				closeBtn.setAttribute(
					'aria-label',
					gcepAdmin.closeLabel || 'Close'
				);
				closeBtn.textContent = '×';

				const header = document.createElement( 'div' );
				header.className = 'gcep-preview-header';
				header.appendChild( closeBtn );

				const iframe = document.createElement( 'iframe' );
				iframe.className = 'gcep-preview-iframe';
				iframe.src = previewUrl;
				iframe.title = gcepAdmin.previewTitle || 'Preview';

				const modal = document.createElement( 'div' );
				modal.className = 'gcep-preview-modal';
				modal.setAttribute( 'role', 'dialog' );
				modal.setAttribute( 'aria-modal', 'true' );
				modal.setAttribute( 'aria-label', 'Error page preview' );
				modal.appendChild( header );
				modal.appendChild( iframe );

				document.body.appendChild( overlay );
				document.body.appendChild( modal );
				closeBtn.focus();

				function closePreview() {
					overlay.remove();
					modal.remove();
					if ( controller ) {
						controller.abort();
						controller = null;
					}
					triggerBtn.focus();
				}

				overlay.addEventListener( 'click', closePreview );
				closeBtn.addEventListener( 'click', closePreview );

				const focusable = [ closeBtn, iframe ];

				document.addEventListener(
					'keydown',
					( evt ) => {
						if ( evt.key === 'Escape' ) {
							closePreview();
							return;
						}

						if ( evt.key === 'Tab' ) {
							let idx = focusable.indexOf(
								document.activeElement
							);

							if ( evt.shiftKey ) {
								idx = idx <= 0 ? focusable.length - 1 : idx - 1;
							} else {
								idx = idx >= focusable.length - 1 ? 0 : idx + 1;
							}

							evt.preventDefault();
							focusable[ idx ].focus();
						}
					},
					{ signal }
				);
			} );
		} );
}

domReady( () => {
	initColorPickers();
	initMediaUploaders();
	initPreview();
} );
