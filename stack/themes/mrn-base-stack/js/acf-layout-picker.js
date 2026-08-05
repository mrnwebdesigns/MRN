(function () {
	'use strict';

	if (window.MRNBaseStackAcfLayoutPickerReady || window.MRNConfigHelperAcfLayoutPickerReady) {
		return;
	}

	window.MRNBaseStackAcfLayoutPickerReady = true;
	window.MRNConfigHelperAcfLayoutPickerReady = true;

	var settings = window.mrnBaseStackAcfLayoutPicker || window.mrnConfigHelperAcfLayoutPicker || {};
	var metadata = settings.metadata && typeof settings.metadata === 'object' ? settings.metadata : {};
	var active = null;
	var observer = null;
	var scanTimer = 0;
	var observationTimer = 0;
	var closeTimer = 0;

	function onReady(callback) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', callback, { once: true });
			return;
		}

		callback();
	}

	function boot() {
		if (!document.body) {
			return;
		}

		document.addEventListener('click', function (event) {
			if (event.target && event.target.closest && event.target.closest('[data-name="add-layout"]')) {
				watchForPopup();
				schedulePopupScan();
			}
		}, true);
	}

	function watchForPopup() {
		if (typeof window.MutationObserver === 'undefined' || !document.body) {
			return;
		}

		stopWatchingForPopup();

		observer = new MutationObserver(function (mutations) {
			mutations.forEach(function (mutation) {
				Array.prototype.forEach.call(mutation.addedNodes, inspectNode);
			});
		});

		observer.observe(document.body, { childList: true, subtree: true });

		observationTimer = window.setTimeout(function () {
			stopWatchingForPopup();
		}, 1200);
	}

	function stopWatchingForPopup() {
		if (observer) {
			observer.disconnect();
			observer = null;
		}

		if (observationTimer) {
			window.clearTimeout(observationTimer);
			observationTimer = 0;
		}
	}

	function schedulePopupScan() {
		if (scanTimer) {
			window.clearTimeout(scanTimer);
		}

		scanTimer = window.setTimeout(function () {
			scanTimer = 0;
			scanPopups();
		}, 25);
	}

	function scanPopups() {
		Array.prototype.forEach.call(document.querySelectorAll('.acf-fc-popup'), handlePopup);
	}

	function inspectNode(node) {
		if (!node || node.nodeType !== 1) {
			return;
		}

		if (node.matches && node.matches('.acf-fc-popup')) {
			handlePopup(node);
			return;
		}

		if (!node.querySelectorAll) {
			return;
		}

		Array.prototype.forEach.call(node.querySelectorAll('.acf-fc-popup'), handlePopup);
	}

	function handlePopup(popup) {
		if (!popup || popup.dataset.mrnBaseStackLayoutPicker === '1') {
			return;
		}

		var layouts = collectLayouts(popup);
		if (!layouts.length) {
			return;
		}

		popup.dataset.mrnBaseStackLayoutPicker = '1';
		popup.setAttribute('data-mrn-fc-handled', '1');
		popup.classList.add('mrn-acf-layout-picker-native');

		stopWatchingForPopup();
		openPicker(popup, layouts);
	}

	function collectLayouts(popup) {
		var links = Array.prototype.slice.call(popup.querySelectorAll('a[data-layout]'));
		var layouts = [];

		links.forEach(function (link) {
			var key = clean(link.getAttribute('data-layout'));
			var fullLabel = clean(link.textContent || link.getAttribute('title') || key);

			if (!key || link.getAttribute('data-max') === '-1') {
				return;
			}

			var labelParts = splitLabel(fullLabel);
			var layoutMeta = getLayoutMetadata(key);
			var description = clean(layoutMeta.description || link.getAttribute('data-description') || link.getAttribute('data-instructions'));
			var icon = clean(layoutMeta.icon || link.getAttribute('data-icon'));
			var category = clean(layoutMeta.category || link.getAttribute('data-category'));
			var previewThumbnailUrl = clean(layoutMeta.preview_thumbnail_url || layoutMeta.previewThumbnailUrl || link.getAttribute('data-preview-thumbnail-url'));
			var previewAltText = clean(layoutMeta.preview_alt_text || layoutMeta.previewAltText || link.getAttribute('data-preview-alt-text') || labelParts.label);
			var keywords = Array.isArray(layoutMeta.keywords) ? layoutMeta.keywords.join(' ') : clean(layoutMeta.keywords || link.getAttribute('data-keywords'));

			if (!description && labelParts.description && labelParts.description.indexOf('|') === -1) {
				description = labelParts.description;
			}

			layouts.push({
				key: key,
				label: labelParts.label,
				description: description,
				icon: icon,
				category: category,
				keywords: keywords,
				previewThumbnailUrl: previewThumbnailUrl,
				previewAltText: previewAltText,
				fullLabel: fullLabel,
				search: [
					key,
					fullLabel,
					labelParts.label,
					labelParts.description,
					description,
					category,
					keywords
				].join(' ').toLowerCase(),
				initials: getInitials(labelParts.label || fullLabel || key),
				link: link
			});
		});

		layouts.sort(function (a, b) {
			return a.label.localeCompare(b.label);
		});

		return layouts;
	}

	function getLayoutMetadata(key) {
		var normalizedKey = clean(key);

		if (!normalizedKey) {
			return {};
		}

		return metadata[normalizedKey] && typeof metadata[normalizedKey] === 'object' ? metadata[normalizedKey] : {};
	}

	function splitLabel(label) {
		var parts = clean(label).split(/\s+[-–—]\s+/);

		return {
			label: clean(parts.shift() || label),
			description: clean(parts.join(' - '))
		};
	}

	function getInitials(label) {
		var words = clean(label)
			.replace(/[^A-Za-z0-9 ]/g, ' ')
			.split(/\s+/)
			.filter(Boolean);

		if (!words.length) {
			return 'S';
		}

		return words.slice(0, 2).map(function (word) {
			return word.charAt(0).toUpperCase();
		}).join('');
	}

	function openPicker(popup, layouts) {
		closePicker(false);

		var previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
		var overlay = document.createElement('div');
		var modal = document.createElement('div');
		var titleId = 'mrn-acf-layout-picker-title';
		var subtitleId = 'mrn-acf-layout-picker-subtitle';
		var searchId = 'mrn-acf-layout-picker-search';

		overlay.className = 'mrn-acf-layout-picker-overlay';

		modal.className = 'mrn-acf-layout-picker';
		modal.setAttribute('role', 'dialog');
		modal.setAttribute('aria-modal', 'true');
		modal.setAttribute('aria-labelledby', titleId);
		modal.setAttribute('aria-describedby', subtitleId);

		modal.innerHTML =
			'<div class="mrn-acf-layout-picker__header">' +
				'<div class="mrn-acf-layout-picker__heading">' +
					'<h2 id="' + titleId + '">' + escapeHtml(settings.title || 'Add a Section') + '</h2>' +
					'<p id="' + subtitleId + '">' + escapeHtml(settings.subtitle || 'Pick a layout to insert into this page.') + '</p>' +
				'</div>' +
				'<button type="button" class="mrn-acf-layout-picker__close" aria-label="Close"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>' +
			'</div>' +
			'<div class="mrn-acf-layout-picker__tools">' +
				'<label class="screen-reader-text" for="' + searchId + '">' + escapeHtml(settings.searchLabel || 'Search sections') + '</label>' +
				'<span class="dashicons dashicons-search mrn-acf-layout-picker__search-icon" aria-hidden="true"></span>' +
				'<input id="' + searchId + '" class="mrn-acf-layout-picker__search" type="search" autocomplete="off" placeholder="' + escapeAttr((settings.searchLabel || 'Search sections') + '...') + '" />' +
				'<span class="mrn-acf-layout-picker__count" aria-live="polite"></span>' +
			'</div>' +
			'<div class="mrn-acf-layout-picker__body">' +
				'<div class="mrn-acf-layout-picker__grid"></div>' +
				'<p class="mrn-acf-layout-picker__empty" hidden>' + escapeHtml(settings.emptyText || 'No sections match that search.') + '</p>' +
			'</div>';

		var grid = modal.querySelector('.mrn-acf-layout-picker__grid');
		var count = modal.querySelector('.mrn-acf-layout-picker__count');
		var search = modal.querySelector('.mrn-acf-layout-picker__search');
		var empty = modal.querySelector('.mrn-acf-layout-picker__empty');
		var cards = [];

		layouts.forEach(function (layout, index) {
			var card = document.createElement('button');
			var description = layout.description ? '<span class="mrn-acf-layout-picker__card-description">' + escapeHtml(layout.description.replace(/\|/g, ' | ')) + '</span>' : '';
			var preview = layout.previewThumbnailUrl ? '<span class="mrn-acf-layout-picker__card-preview" data-src="' + escapeAttr(layout.previewThumbnailUrl) + '" data-alt="' + escapeAttr(layout.previewAltText || layout.label) + '"></span>' : '';
			var icon = layout.icon ? '<span class="dashicons ' + escapeAttr(layout.icon) + '" aria-hidden="true"></span>' : escapeHtml(layout.initials);

			card.type = 'button';
			card.className = 'mrn-acf-layout-picker__card';
			card.dataset.index = String(index);
			card.dataset.search = layout.search;
			card.innerHTML =
				'<span class="mrn-acf-layout-picker__card-icon" aria-hidden="true">' + icon + '</span>' +
				'<span class="mrn-acf-layout-picker__card-copy">' +
					'<span class="mrn-acf-layout-picker__card-label">' + escapeHtml(layout.label) + '</span>' +
					description +
				'</span>' +
				preview;

			grid.appendChild(card);
			cards.push(card);
		});

		document.body.appendChild(overlay);
		document.body.appendChild(modal);
		document.body.classList.add('mrn-acf-layout-picker-open');

		active = {
			popup: popup,
			layouts: layouts,
			overlay: overlay,
			modal: modal,
			search: search,
			count: count,
			empty: empty,
			cards: cards,
			previousFocus: previousFocus,
			searchFrame: 0
		};

		updateResults('');

		overlay.addEventListener('click', function () {
			closePicker(true);
		});

		modal.querySelector('.mrn-acf-layout-picker__close').addEventListener('click', function () {
			closePicker(true);
		});

		grid.addEventListener('click', function (event) {
			var card = event.target.closest('.mrn-acf-layout-picker__card');
			if (!card) {
				return;
			}

			event.preventDefault();

			if (active && active.isSelecting) {
				return;
			}

			var layout = layouts[parseInt(card.dataset.index, 10)];
			if (!layout || !layout.link) {
				return;
			}

			active.isSelecting = true;
			active.modal.setAttribute('aria-busy', 'true');
			active.cards.forEach(function (pickerCard) {
				pickerCard.disabled = true;
			});

			triggerNativeLink(layout.link);
			scheduleClosePicker(false);
		});

		grid.addEventListener('mouseover', function (event) {
			loadPreview(event.target.closest('.mrn-acf-layout-picker__card'));
		});

		grid.addEventListener('focusin', function (event) {
			loadPreview(event.target.closest('.mrn-acf-layout-picker__card'));
		});

		search.addEventListener('input', function () {
			if (active.searchFrame) {
				window.cancelAnimationFrame(active.searchFrame);
			}

			active.searchFrame = window.requestAnimationFrame(function () {
				active.searchFrame = 0;
				updateResults(search.value);
			});
		});

		document.addEventListener('keydown', onKeydown);
		window.requestAnimationFrame(function () {
			search.focus();
		});
	}

	function updateResults(query) {
		if (!active) {
			return;
		}

		var q = clean(query).toLowerCase();
		var visible = 0;

		active.cards.forEach(function (card) {
			var matches = !q || card.dataset.search.indexOf(q) !== -1;
			card.hidden = !matches;
			if (matches) {
				visible += 1;
			}
		});

		active.empty.hidden = visible > 0;
		active.count.textContent = visible === active.cards.length ? visible + ' sections' : visible + ' of ' + active.cards.length;
	}

	function onKeydown(event) {
		if (!active) {
			return;
		}

		if (event.key === 'Escape') {
			event.preventDefault();
			closePicker(true);
			return;
		}

		if (event.key !== 'Tab') {
			return;
		}

		trapFocus(event);
	}

	function trapFocus(event) {
		var focusable = Array.prototype.filter.call(
			active.modal.querySelectorAll('button:not([hidden]), input:not([hidden])'),
			function (element) {
				return !element.disabled && element.offsetParent !== null;
			}
		);

		if (!focusable.length) {
			return;
		}

		var first = focusable[0];
		var last = focusable[focusable.length - 1];

		if (event.shiftKey && document.activeElement === first) {
			event.preventDefault();
			last.focus();
		} else if (!event.shiftKey && document.activeElement === last) {
			event.preventDefault();
			first.focus();
		}
	}

	function triggerNativeLink(link) {
		if (window.jQuery && typeof window.jQuery.fn.trigger === 'function') {
			window.jQuery(link).trigger('click');
			return;
		}

		if (typeof link.click === 'function') {
			link.click();
			return;
		}

		var event = new MouseEvent('click', {
			bubbles: true,
			cancelable: true,
			view: window
		});

		link.dispatchEvent(event);
	}

	function scheduleClosePicker(cancelled) {
		if (closeTimer) {
			window.clearTimeout(closeTimer);
		}

		closeTimer = window.setTimeout(function () {
			closeTimer = 0;
			closePicker(cancelled);
		}, 75);
	}

	function loadPreview(card) {
		if (!card) {
			return;
		}

		var preview = card.querySelector('.mrn-acf-layout-picker__card-preview[data-src]');
		if (!preview) {
			return;
		}

		var image = document.createElement('img');
		image.loading = 'lazy';
		image.decoding = 'async';
		image.src = preview.getAttribute('data-src');
		image.alt = preview.getAttribute('data-alt') || '';

		preview.removeAttribute('data-src');
		preview.appendChild(image);
	}

	function closePicker(cancelled) {
		if (!active) {
			return;
		}

		if (closeTimer) {
			window.clearTimeout(closeTimer);
			closeTimer = 0;
		}

		stopWatchingForPopup();

		if (active.searchFrame) {
			window.cancelAnimationFrame(active.searchFrame);
		}

		document.removeEventListener('keydown', onKeydown);
		document.body.classList.remove('mrn-acf-layout-picker-open');

		if (active.modal && active.modal.parentNode) {
			active.modal.parentNode.removeChild(active.modal);
		}

		if (active.overlay && active.overlay.parentNode) {
			active.overlay.parentNode.removeChild(active.overlay);
		}

		if (active.popup && active.popup.parentNode) {
			active.popup.parentNode.removeChild(active.popup);
		}

		if (cancelled && active.previousFocus && document.contains(active.previousFocus)) {
			active.previousFocus.focus();
		}

		active = null;
	}

	function clean(value) {
		return String(value || '').replace(/\s+/g, ' ').trim();
	}

	function escapeHtml(value) {
		return String(value).replace(/[&<>"']/g, function (character) {
			return {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			}[character];
		});
	}

	function escapeAttr(value) {
		return escapeHtml(value);
	}

	onReady(boot);
})();
