(function(window, document) {
	'use strict';

	function uniqueValues(values) {
		var seen = {};
		var result = [];

		values.forEach(function(value) {
			var normalized = String(value || '').trim();
			if (!normalized || seen[normalized]) {
				return;
			}
			seen[normalized] = true;
			result.push(normalized);
		});

		return result;
	}

	function parseJsonPayload(rawText) {
		var text = String(rawText || '').trim();
		if (!text) {
			return null;
		}

		try {
			return JSON.parse(text);
		} catch (error) {
			var start = text.indexOf('{');
			var end = text.lastIndexOf('}');
			if (start === -1 || end === -1 || end <= start) {
				return null;
			}

			try {
				return JSON.parse(text.slice(start, end + 1));
			} catch (nestedError) {
				return null;
			}
		}
	}

	function bindChooser(chooser) {
		if (!chooser || chooser.getAttribute('data-mrn-google-fonts-chooser-ready') === '1') {
			return;
		}
		chooser.setAttribute('data-mrn-google-fonts-chooser-ready', '1');

		var datalistId = chooser.getAttribute('data-mrn-google-fonts-datalist-id') || '';
		var datalist = datalistId ? chooser.querySelector('#' + datalistId) : null;
		var searchUrl = chooser.getAttribute('data-mrn-google-fonts-search-url') || '';
		var searchNonce = chooser.getAttribute('data-mrn-google-fonts-search-nonce') || '';
		var inputs = Array.prototype.slice.call(chooser.querySelectorAll('[data-mrn-google-fonts-family-input]'));

		if (!datalist || !searchUrl || !searchNonce || !inputs.length) {
			return;
		}

		var defaultOptions = Array.prototype.slice.call(datalist.querySelectorAll('option')).map(function(option) {
			return String(option.value || '').trim();
		}).filter(Boolean);

		function renderOptions(values) {
			var selectedValues = inputs.map(function(input) {
				return input.value || '';
			});
			var merged = uniqueValues(['system-ui'].concat(selectedValues, values)).slice(0, 30);

			datalist.innerHTML = '';
			merged.forEach(function(value) {
				var option = document.createElement('option');
				option.value = value;
				datalist.appendChild(option);
			});

			var activeInput = document.activeElement;
			if (!activeInput || inputs.indexOf(activeInput) === -1) {
				return;
			}

			var listAttr = activeInput.getAttribute('list');
			var currentValue = activeInput.value;
			var selectionStart = activeInput.selectionStart;
			var selectionEnd = activeInput.selectionEnd;

			if (listAttr) {
				activeInput.setAttribute('list', '');
				activeInput.setAttribute('list', listAttr);
			}

			activeInput.focus();
			activeInput.value = currentValue;
			if (typeof selectionStart === 'number' && typeof selectionEnd === 'number') {
				activeInput.setSelectionRange(selectionStart, selectionEnd);
			}
		}

		var debounceTimer = 0;
		var requestCounter = 0;

		function fetchSuggestions(query) {
			requestCounter += 1;
			var currentRequestId = requestCounter;
			var body = new URLSearchParams();
			body.set('action', 'mrn_google_fonts_search_families');
			body.set('q', query || '');
			body.set('_ajax_nonce', searchNonce);

			fetch(searchUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
					'Cache-Control': 'no-cache'
				},
				body: body.toString()
			})
				.then(function(response) {
					return response.text();
				})
				.then(function(rawText) {
					if (currentRequestId !== requestCounter) {
						return;
					}

					var payload = parseJsonPayload(rawText);
					if (!payload || !payload.success || !payload.data || !Array.isArray(payload.data.families)) {
						if (!query) {
							renderOptions(defaultOptions);
						}
						return;
					}

					renderOptions(payload.data.families.map(function(family) {
						return String(family);
					}).filter(Boolean));
				})
				.catch(function() {
					if (currentRequestId !== requestCounter) {
						return;
					}

					if (!query) {
						renderOptions(defaultOptions);
					}
				});
		}

		function scheduleSearch(query) {
			window.clearTimeout(debounceTimer);
			debounceTimer = window.setTimeout(function() {
				fetchSuggestions(query);
			}, 120);
		}

		inputs.forEach(function(input) {
			input.addEventListener('input', function() {
				scheduleSearch(input.value || '');
			});
			input.addEventListener('focus', function() {
				scheduleSearch(input.value || '');
			});
		});
	}

	function init() {
		var choosers = Array.prototype.slice.call(document.querySelectorAll('[data-mrn-google-fonts-search-url]'));
		choosers.forEach(bindChooser);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})(window, document);
