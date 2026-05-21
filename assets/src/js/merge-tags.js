/**
 * Graceful Error Pages — Merge tag input component.
 *
 * Enhances text inputs and textareas marked with .gcep-merge-input.
 * Renders {tag} patterns as visual pills and provides autocomplete
 * on '{' keystroke. The underlying form value keeps raw {tag} syntax.
 *
 * @package
 */

import domReady from '@wordpress/dom-ready';

const tags = window.gcepMergeTags || [];

function esc( str ) {
	const el = document.createElement( 'span' );
	el.textContent = str;
	return el.innerHTML;
}

function findTag( tagText ) {
	return tags.find( ( t ) => t.tag === tagText ) || null;
}

function lineToHtml( line ) {
	let html = '';
	let remaining = line;

	while ( remaining.length ) {
		const idx = remaining.indexOf( '{' );

		if ( idx === -1 ) {
			html += esc( remaining );
			break;
		}

		if ( idx > 0 ) {
			html += esc( remaining.substring( 0, idx ) );
		}

		const end = remaining.indexOf( '}', idx );
		if ( end === -1 ) {
			html += esc( remaining.substring( idx ) );
			break;
		}

		const tagText = remaining.substring( idx, end + 1 );
		const match = findTag( tagText );

		if ( match ) {
			html +=
				'<span class="gcep-tag-pill" contenteditable="false" data-tag="' +
				esc( tagText ) +
				'">' +
				esc( tagText ) +
				'</span>';
		} else {
			html += esc( tagText );
		}

		remaining = remaining.substring( end + 1 );
	}

	return html;
}

function textToHtml( raw ) {
	return raw.split( '\n' ).map( lineToHtml ).join( '<br>' );
}

function htmlToText( container ) {
	let result = '';

	Array.from( container.childNodes ).forEach( ( node ) => {
		if ( node.nodeType === 3 ) {
			result += node.textContent;
		} else if ( node.nodeType === 1 ) {
			if ( node.classList.contains( 'gcep-tag-pill' ) ) {
				result += node.dataset.tag || node.textContent;
			} else if ( node.tagName === 'BR' ) {
				result += '\n';
			} else if ( node.tagName === 'DIV' || node.tagName === 'P' ) {
				if (
					result.length > 0 &&
					result[ result.length - 1 ] !== '\n'
				) {
					result += '\n';
				}
				result += htmlToText( node );
			} else {
				result += htmlToText( node );
			}
		}
	} );

	return result;
}

function saveCaretPos( el ) {
	const sel = window.getSelection();
	if ( ! sel || sel.rangeCount === 0 ) {
		return null;
	}
	const range = sel.getRangeAt( 0 );
	const pre = range.cloneRange();
	pre.selectNodeContents( el );
	pre.setEnd( range.startContainer, range.startOffset );
	return pre.toString().length;
}

function restoreCaretPos( el, pos ) {
	if ( pos === null ) {
		return;
	}

	const sel = window.getSelection();
	const range = document.createRange();
	const walker = document.createTreeWalker(
		el,
		NodeFilter.SHOW_TEXT | NodeFilter.SHOW_ELEMENT,
		null
	);
	let current = 0;
	let node;

	while ( ( node = walker.nextNode() ) ) {
		if ( node.nodeType === 3 ) {
			if (
				node.parentNode.closest &&
				node.parentNode.closest( '.gcep-tag-pill' )
			) {
				continue;
			}
			if ( current + node.textContent.length >= pos ) {
				range.setStart( node, pos - current );
				range.collapse( true );
				sel.removeAllRanges();
				sel.addRange( range );
				return;
			}
			current += node.textContent.length;
		} else if (
			node.nodeType === 1 &&
			node.classList &&
			node.classList.contains( 'gcep-tag-pill' )
		) {
			const tagLen = ( node.dataset.tag || node.textContent ).length;
			if ( current + tagLen >= pos ) {
				range.setStartAfter( node );
				range.collapse( true );
				sel.removeAllRanges();
				sel.addRange( range );
				return;
			}
			current += tagLen;
		}
	}

	range.selectNodeContents( el );
	range.collapse( false );
	sel.removeAllRanges();
	sel.addRange( range );
}

let fieldCounter = 0;

function initField( input ) {
	const isTextarea = input.tagName === 'TEXTAREA';
	const fieldId = 'gcep-mt-' + ++fieldCounter;

	const wrap = document.createElement( 'div' );
	wrap.className = 'gcep-merge-wrap';

	const editor = document.createElement( 'div' );
	editor.className = 'gcep-merge-editor';
	editor.contentEditable = 'true';
	editor.setAttribute( 'role', 'combobox' );
	editor.setAttribute( 'aria-haspopup', 'listbox' );
	editor.setAttribute( 'aria-autocomplete', 'list' );
	editor.setAttribute( 'aria-expanded', 'false' );
	editor.setAttribute( 'aria-controls', fieldId + '-listbox' );

	const inputId = input.id;
	if ( inputId ) {
		const label = document.querySelector( 'label[for="' + inputId + '"]' );
		if ( label ) {
			let labelId = label.id;
			if ( ! labelId ) {
				labelId = fieldId + '-label';
				label.id = labelId;
			}
			editor.setAttribute( 'aria-labelledby', labelId );
		}
	}

	if ( isTextarea ) {
		editor.classList.add( 'gcep-merge-editor--multi' );
	}

	const dropdown = document.createElement( 'ul' );
	dropdown.className = 'gcep-merge-dropdown';
	dropdown.id = fieldId + '-listbox';
	dropdown.setAttribute( 'role', 'listbox' );
	dropdown.setAttribute( 'aria-label', 'Merge tags' );
	dropdown.style.display = 'none';

	wrap.appendChild( editor );
	wrap.appendChild( dropdown );
	input.parentNode.insertBefore( wrap, input.nextSibling );
	input.style.display = 'none';

	const raw = input.value || '';
	editor.innerHTML = textToHtml( raw );

	function sync() {
		input.value = htmlToText( editor );
		input.dispatchEvent( new Event( 'change' ) );
	}

	function refreshPills() {
		const pos = saveCaretPos( editor );
		const currentText = htmlToText( editor );
		editor.innerHTML = textToHtml( currentText );
		restoreCaretPos( editor, pos );
	}

	function isDropdownVisible() {
		return dropdown.style.display !== 'none';
	}

	function showDropdown( filter ) {
		editor.removeAttribute( 'aria-activedescendant' );
		dropdown.innerHTML = '';
		dropdown.classList.remove( 'gcep-merge-dropdown--above' );
		const lower = ( filter || '' ).toLowerCase();
		let matched = 0;

		for ( let i = 0; i < tags.length; i++ ) {
			const t = tags[ i ];
			if (
				! lower ||
				t.tag.toLowerCase().indexOf( lower ) !== -1 ||
				t.label.toLowerCase().indexOf( lower ) !== -1
			) {
				const optionId = fieldId + '-opt-' + matched;
				const li = document.createElement( 'li' );
				li.className = 'gcep-merge-dropdown-item';
				li.id = optionId;
				li.setAttribute( 'role', 'option' );
				li.setAttribute(
					'aria-selected',
					matched === 0 ? 'true' : 'false'
				);
				li.dataset.tag = t.tag;
				li.innerHTML =
					'<span class="gcep-merge-dropdown-tag">' +
					esc( t.tag ) +
					'</span>' +
					'<span class="gcep-merge-dropdown-desc">' +
					esc( t.label ) +
					'</span>';

				if ( matched === 0 ) {
					li.classList.add( 'gcep-merge-dropdown-active' );
				}

				dropdown.appendChild( li );
				matched++;
			}
		}

		if ( matched > 0 ) {
			dropdown.style.display = '';
			editor.setAttribute( 'aria-expanded', 'true' );

			const firstActive = dropdown.querySelector(
				'.gcep-merge-dropdown-active'
			);
			if ( firstActive ) {
				editor.setAttribute( 'aria-activedescendant', firstActive.id );
			}

			const rect = dropdown.getBoundingClientRect();
			if ( rect.bottom > window.innerHeight ) {
				dropdown.classList.add( 'gcep-merge-dropdown--above' );

				const aboveRect = dropdown.getBoundingClientRect();
				if ( aboveRect.top < 0 ) {
					dropdown.classList.remove( 'gcep-merge-dropdown--above' );
				}
			}
		} else {
			dropdown.style.display = 'none';
			editor.setAttribute( 'aria-expanded', 'false' );
			editor.removeAttribute( 'aria-activedescendant' );
		}
	}

	function hideDropdown() {
		dropdown.style.display = 'none';
		dropdown.innerHTML = '';
		editor.setAttribute( 'aria-expanded', 'false' );
		editor.removeAttribute( 'aria-activedescendant' );
	}

	function getTypingFragment() {
		const sel = window.getSelection();
		if ( ! sel || sel.rangeCount === 0 ) {
			return null;
		}

		const range = sel.getRangeAt( 0 );
		const node = range.startContainer;

		if ( node.nodeType !== 3 ) {
			return null;
		}

		const text = node.textContent.substring( 0, range.startOffset );
		const braceIdx = text.lastIndexOf( '{' );

		if ( braceIdx === -1 ) {
			return null;
		}

		return {
			text: text.substring( braceIdx ),
			node,
			braceOffset: braceIdx,
			caretOffset: range.startOffset,
		};
	}

	function insertTag( tagText ) {
		const frag = getTypingFragment();

		if ( frag ) {
			const before = frag.node.textContent.substring(
				0,
				frag.braceOffset
			);
			const after = frag.node.textContent.substring( frag.caretOffset );
			frag.node.textContent = before;

			const pill = document.createElement( 'span' );
			pill.className = 'gcep-tag-pill';
			pill.contentEditable = 'false';
			pill.setAttribute( 'data-tag', tagText );
			pill.textContent = tagText;

			const afterNode = document.createTextNode( after || ' ' );
			const parent = frag.node.parentNode;
			parent.insertBefore( afterNode, frag.node.nextSibling );
			parent.insertBefore( pill, afterNode );

			const sel = window.getSelection();
			const range = document.createRange();
			range.setStart( afterNode, after ? 0 : 1 );
			range.collapse( true );
			sel.removeAllRanges();
			sel.addRange( range );
		} else {
			const pillEl = document.createElement( 'span' );
			pillEl.className = 'gcep-tag-pill';
			pillEl.contentEditable = 'false';
			pillEl.setAttribute( 'data-tag', tagText );
			pillEl.textContent = tagText;
			editor.appendChild( pillEl );
			editor.appendChild( document.createTextNode( ' ' ) );
		}

		hideDropdown();
		sync();
	}

	editor.addEventListener( 'input', () => {
		const frag = getTypingFragment();
		if ( frag ) {
			showDropdown( frag.text );
		} else {
			hideDropdown();
		}
		sync();
	} );

	editor.addEventListener( 'keydown', ( e ) => {
		if ( ! isDropdownVisible() ) {
			if ( ! isTextarea && e.key === 'Enter' ) {
				e.preventDefault();
			}
			return;
		}

		const active = dropdown.querySelector( '.gcep-merge-dropdown-active' );

		if ( e.key === 'ArrowDown' ) {
			e.preventDefault();
			const next =
				active && active.nextElementSibling
					? active.nextElementSibling.classList.contains(
							'gcep-merge-dropdown-item'
					  )
						? active.nextElementSibling
						: null
					: null;
			if ( next ) {
				active.classList.remove( 'gcep-merge-dropdown-active' );
				active.setAttribute( 'aria-selected', 'false' );
				next.classList.add( 'gcep-merge-dropdown-active' );
				next.setAttribute( 'aria-selected', 'true' );
				editor.setAttribute( 'aria-activedescendant', next.id );
				next.scrollIntoView( { block: 'nearest' } );
			}
		} else if ( e.key === 'ArrowUp' ) {
			e.preventDefault();
			const prev =
				active && active.previousElementSibling
					? active.previousElementSibling.classList.contains(
							'gcep-merge-dropdown-item'
					  )
						? active.previousElementSibling
						: null
					: null;
			if ( prev ) {
				active.classList.remove( 'gcep-merge-dropdown-active' );
				active.setAttribute( 'aria-selected', 'false' );
				prev.classList.add( 'gcep-merge-dropdown-active' );
				prev.setAttribute( 'aria-selected', 'true' );
				editor.setAttribute( 'aria-activedescendant', prev.id );
				prev.scrollIntoView( { block: 'nearest' } );
			}
		} else if ( e.key === 'Enter' || e.key === 'Tab' ) {
			e.preventDefault();
			if ( active ) {
				insertTag( active.dataset.tag );
			}
		} else if ( e.key === 'Escape' ) {
			e.preventDefault();
			hideDropdown();
		}
	} );

	dropdown.addEventListener( 'mousedown', ( e ) => {
		const item = e.target.closest( '.gcep-merge-dropdown-item' );
		if ( item ) {
			e.preventDefault();
			insertTag( item.dataset.tag );
		}
	} );

	editor.addEventListener( 'blur', () => {
		setTimeout( () => {
			hideDropdown();
			refreshPills();
			sync();
		}, 150 );
	} );

	editor.addEventListener( 'paste', ( e ) => {
		e.preventDefault();

		const clipboard = e.clipboardData || window.clipboardData;
		if ( ! clipboard ) {
			return;
		}

		let text = clipboard.getData( 'text/plain' );
		if ( ! text ) {
			return;
		}

		if ( ! isTextarea ) {
			text = text.replace( /[\r\n]+/g, ' ' );
		}

		const sel = window.getSelection();
		if ( sel && sel.rangeCount ) {
			const range = sel.getRangeAt( 0 );
			range.deleteContents();
			const textNode = document.createTextNode( text );
			range.insertNode( textNode );
			range.setStartAfter( textNode );
			range.collapse( true );
			sel.removeAllRanges();
			sel.addRange( range );
		}

		setTimeout( () => {
			refreshPills();
			sync();
		}, 0 );
	} );
}

domReady( () => {
	document.querySelectorAll( '.gcep-merge-input' ).forEach( ( input ) => {
		initField( input );
	} );
} );
