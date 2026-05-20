/**
 * Graceful Error Pages — Merge tag input component.
 *
 * Enhances text inputs and textareas marked with .gep-merge-input.
 * Renders {tag} patterns as visual pills and provides autocomplete
 * on '{' keystroke. The underlying form value keeps raw {tag} syntax.
 *
 * @package GracefulErrorPages
 */

/* global jQuery, gepMergeTags */

(function ($) {
	'use strict';

	var tags = gepMergeTags || [];

	/**
	 * Escape HTML entities in a string.
	 */
	function esc(str) {
		var el = document.createElement('span');
		el.textContent = str;
		return el.innerHTML;
	}

	/**
	 * Convert a single line of raw text (no newlines) into HTML with pills.
	 */
	function lineToHtml(line) {
		var html = '';
		var remaining = line;
		var idx;

		while (remaining.length) {
			idx = remaining.indexOf('{');

			if (idx === -1) {
				html += esc(remaining);
				break;
			}

			if (idx > 0) {
				html += esc(remaining.substring(0, idx));
			}

			var end = remaining.indexOf('}', idx);
			if (end === -1) {
				html += esc(remaining.substring(idx));
				break;
			}

			var tagText = remaining.substring(idx, end + 1);
			var match = findTag(tagText);

			if (match) {
				html += '<span class="gep-tag-pill" contenteditable="false" data-tag="' + esc(tagText) + '">' + esc(tagText) + '</span>';
			} else {
				html += esc(tagText);
			}

			remaining = remaining.substring(end + 1);
		}

		return html;
	}

	/**
	 * Convert raw text with {tags} into HTML with pill spans.
	 *
	 * Splits on newlines and joins with <br> so multiline content
	 * round-trips correctly through htmlToText() → textToHtml().
	 */
	function textToHtml(raw) {
		var lines = raw.split('\n');

		return lines.map(function (line) {
			return lineToHtml(line);
		}).join('<br>');
	}

	/**
	 * Extract raw text from editor HTML, converting pills back to {tag}.
	 *
	 * Recurses into block elements (<div>, <p>) that browsers insert on
	 * Enter key so pills nested inside them are read via data-tag.
	 */
	function htmlToText($container) {
		var result = '';

		$container.contents().each(function () {
			if (this.nodeType === 3) {
				result += this.textContent;
			} else if (this.nodeType === 1) {
				var $el = $(this);
				if ($el.hasClass('gep-tag-pill')) {
					result += $el.data('tag') || $el.text();
				} else if ($el.is('br')) {
					result += '\n';
				} else if ($el.is('div, p')) {
					if (result.length > 0 && result[result.length - 1] !== '\n') {
						result += '\n';
					}
					result += htmlToText($el);
				} else {
					result += htmlToText($el);
				}
			}
		});

		return result;
	}

	/**
	 * Find a tag definition by its {name} string.
	 */
	function findTag(tagText) {
		for (var i = 0; i < tags.length; i++) {
			if (tags[i].tag === tagText) {
				return tags[i];
			}
		}
		return null;
	}

	/**
	 * Save caret position in a contenteditable element.
	 */
	function saveCaretPos(el) {
		var sel = window.getSelection();
		if (!sel || sel.rangeCount === 0) {
			return null;
		}
		var range = sel.getRangeAt(0);
		var pre = range.cloneRange();
		pre.selectNodeContents(el);
		pre.setEnd(range.startContainer, range.startOffset);
		return pre.toString().length;
	}

	/**
	 * Restore caret position in a contenteditable element.
	 */
	function restoreCaretPos(el, pos) {
		if (pos === null) {
			return;
		}

		var sel = window.getSelection();
		var range = document.createRange();
		var walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT | NodeFilter.SHOW_ELEMENT, null);
		var current = 0;
		var node;

		while ((node = walker.nextNode())) {
			if (node.nodeType === 3) {
				if (node.parentNode.closest && node.parentNode.closest('.gep-tag-pill')) {
					continue;
				}
				if (current + node.textContent.length >= pos) {
					range.setStart(node, pos - current);
					range.collapse(true);
					sel.removeAllRanges();
					sel.addRange(range);
					return;
				}
				current += node.textContent.length;
			} else if (node.nodeType === 1 && node.classList && node.classList.contains('gep-tag-pill')) {
				var tagLen = ($(node).data('tag') || $(node).text()).length;
				if (current + tagLen >= pos) {
					range.setStartAfter(node);
					range.collapse(true);
					sel.removeAllRanges();
					sel.addRange(range);
					return;
				}
				current += tagLen;
			}
		}

		range.selectNodeContents(el);
		range.collapse(false);
		sel.removeAllRanges();
		sel.addRange(range);
	}

	var fieldCounter = 0;

	/**
	 * Initialize a single merge-tag input.
	 */
	function initField($input) {
		var isTextarea = $input.is('textarea');
		var fieldId = 'gep-mt-' + (++fieldCounter);

		var $wrap = $('<div class="gep-merge-wrap"></div>');
		var $editor = $('<div>')
			.addClass('gep-merge-editor')
			.attr('contenteditable', 'true')
			.attr('role', 'combobox')
			.attr('aria-haspopup', 'listbox')
			.attr('aria-autocomplete', 'list')
			.attr('aria-expanded', 'false')
			.attr('aria-controls', fieldId + '-listbox');

		var inputId = $input.attr('id');
		if (inputId) {
			var $label = $('label[for="' + inputId + '"]');
			if ($label.length) {
				$editor.attr('aria-labelledby', $label.attr('id') || (function () {
					var labelId = fieldId + '-label';
					$label.attr('id', labelId);
					return labelId;
				})());
			}
		}

		if (isTextarea) {
			$editor.addClass('gep-merge-editor--multi');
		}

		var $dropdown = $('<ul class="gep-merge-dropdown"></ul>')
			.attr('id', fieldId + '-listbox')
			.attr('role', 'listbox')
			.attr('aria-label', 'Merge tags')
			.hide();

		$wrap.append($editor).append($dropdown);
		$input.after($wrap).hide();

		var raw = $input.val() || '';
		$editor.html(textToHtml(raw));

		function sync() {
			$input.val(htmlToText($editor)).trigger('change');
		}

		function refreshPills() {
			var pos = saveCaretPos($editor[0]);
			var currentText = htmlToText($editor);
			$editor.html(textToHtml(currentText));
			restoreCaretPos($editor[0], pos);
		}

		function showDropdown(filter) {
			$editor.removeAttr('aria-activedescendant');
			$dropdown.empty().removeClass('gep-merge-dropdown--above');
			var lower = (filter || '').toLowerCase();
			var matched = 0;

			for (var i = 0; i < tags.length; i++) {
				var t = tags[i];
				if (!lower || t.tag.toLowerCase().indexOf(lower) !== -1 || t.label.toLowerCase().indexOf(lower) !== -1) {
					var optionId = fieldId + '-opt-' + matched;
					var $li = $('<li class="gep-merge-dropdown-item"></li>')
						.attr('id', optionId)
						.attr('role', 'option')
						.attr('aria-selected', matched === 0 ? 'true' : 'false')
						.data('tag', t.tag)
						.append('<span class="gep-merge-dropdown-tag">' + esc(t.tag) + '</span>')
						.append('<span class="gep-merge-dropdown-desc">' + esc(t.label) + '</span>');

					if (matched === 0) {
						$li.addClass('gep-merge-dropdown-active');
					}

					$dropdown.append($li);
					matched++;
				}
			}

			if (matched > 0) {
				$dropdown.show();
				$editor.attr('aria-expanded', 'true');

				var $firstActive = $dropdown.find('.gep-merge-dropdown-active');
				if ($firstActive.length) {
					$editor.attr('aria-activedescendant', $firstActive.attr('id'));
				}

				var rect = $dropdown[0].getBoundingClientRect();
				if (rect.bottom > window.innerHeight) {
					$dropdown.addClass('gep-merge-dropdown--above');

					var aboveRect = $dropdown[0].getBoundingClientRect();
					if (aboveRect.top < 0) {
						$dropdown.removeClass('gep-merge-dropdown--above');
					}
				}
			} else {
				$dropdown.hide();
				$editor.attr('aria-expanded', 'false').removeAttr('aria-activedescendant');
			}
		}

		function hideDropdown() {
			$dropdown.hide().empty();
			$editor.attr('aria-expanded', 'false').removeAttr('aria-activedescendant');
		}

		function getTypingFragment() {
			var sel = window.getSelection();
			if (!sel || sel.rangeCount === 0) {
				return null;
			}

			var range = sel.getRangeAt(0);
			var node = range.startContainer;

			if (node.nodeType !== 3) {
				return null;
			}

			var text = node.textContent.substring(0, range.startOffset);
			var braceIdx = text.lastIndexOf('{');

			if (braceIdx === -1) {
				return null;
			}

			return {
				text: text.substring(braceIdx),
				node: node,
				braceOffset: braceIdx,
				caretOffset: range.startOffset
			};
		}

		function insertTag(tagText) {
			var frag = getTypingFragment();

			if (frag) {
				var before = frag.node.textContent.substring(0, frag.braceOffset);
				var after = frag.node.textContent.substring(frag.caretOffset);
				frag.node.textContent = before;

				var pill = document.createElement('span');
				pill.className = 'gep-tag-pill';
				pill.contentEditable = 'false';
				pill.setAttribute('data-tag', tagText);
				pill.textContent = tagText;

				var afterNode = document.createTextNode(after || ' ');
				var parent = frag.node.parentNode;
				parent.insertBefore(afterNode, frag.node.nextSibling);
				parent.insertBefore(pill, afterNode);

				var sel = window.getSelection();
				var range = document.createRange();
				range.setStart(afterNode, after ? 0 : 1);
				range.collapse(true);
				sel.removeAllRanges();
				sel.addRange(range);
			} else {
				var pillEl = document.createElement('span');
				pillEl.className = 'gep-tag-pill';
				pillEl.contentEditable = 'false';
				pillEl.setAttribute('data-tag', tagText);
				pillEl.textContent = tagText;
				$editor.append(pillEl).append(' ');
			}

			hideDropdown();
			sync();
		}

		$editor.on('input', function () {
			var frag = getTypingFragment();
			if (frag) {
				showDropdown(frag.text);
			} else {
				hideDropdown();
			}
			sync();
		});

		$editor.on('keydown', function (e) {
			if (!$dropdown.is(':visible')) {
				if (!isTextarea && e.key === 'Enter') {
					e.preventDefault();
				}
				return;
			}

			var $active = $dropdown.find('.gep-merge-dropdown-active');

			if (e.key === 'ArrowDown') {
				e.preventDefault();
				var $next = $active.next('.gep-merge-dropdown-item');
				if ($next.length) {
					$active.removeClass('gep-merge-dropdown-active').attr('aria-selected', 'false');
					$next.addClass('gep-merge-dropdown-active').attr('aria-selected', 'true');
					$editor.attr('aria-activedescendant', $next.attr('id'));
					$next[0].scrollIntoView({ block: 'nearest' });
				}
			} else if (e.key === 'ArrowUp') {
				e.preventDefault();
				var $prev = $active.prev('.gep-merge-dropdown-item');
				if ($prev.length) {
					$active.removeClass('gep-merge-dropdown-active').attr('aria-selected', 'false');
					$prev.addClass('gep-merge-dropdown-active').attr('aria-selected', 'true');
					$editor.attr('aria-activedescendant', $prev.attr('id'));
					$prev[0].scrollIntoView({ block: 'nearest' });
				}
			} else if (e.key === 'Enter' || e.key === 'Tab') {
				e.preventDefault();
				if ($active.length) {
					insertTag($active.data('tag'));
				}
			} else if (e.key === 'Escape') {
				e.preventDefault();
				hideDropdown();
			}
		});

		$dropdown.on('mousedown', '.gep-merge-dropdown-item', function (e) {
			e.preventDefault();
			insertTag($(this).data('tag'));
		});

		$editor.on('blur', function () {
			setTimeout(function () {
				hideDropdown();
				refreshPills();
				sync();
			}, 150);
		});

		$editor.on('paste', function (e) {
			e.preventDefault();

			var clipboard = e.originalEvent.clipboardData || window.clipboardData;
			if (!clipboard) {
				return;
			}

			var text = clipboard.getData('text/plain');
			if (!text) {
				return;
			}

			if (!isTextarea) {
				text = text.replace(/[\r\n]+/g, ' ');
			}

			var sel = window.getSelection();
			if (sel && sel.rangeCount) {
				var range = sel.getRangeAt(0);
				range.deleteContents();
				var textNode = document.createTextNode(text);
				range.insertNode(textNode);
				range.setStartAfter(textNode);
				range.collapse(true);
				sel.removeAllRanges();
				sel.addRange(range);
			}

			setTimeout(function () {
				refreshPills();
				sync();
			}, 0);
		});
	}

	$(document).ready(function () {
		$('.gep-merge-input').each(function () {
			initField($(this));
		});
	});

})(jQuery);
