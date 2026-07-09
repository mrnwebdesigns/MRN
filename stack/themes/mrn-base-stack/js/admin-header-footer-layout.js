(function () {
	'use strict';

	var MIN_COLUMNS = 1;
	var MAX_COLUMNS = 6;
	var MIN_ROWS = 1;
	var MAX_ROWS = 12;
	var activePointerDrag = null;
	var CONFIG_GROUPS = {
		header: [
			{
				slug: 'navigation',
				label: 'Navigation',
				fields: ['header_show_utility_menu', 'header_show_tertiary_menu', 'header_primary_nav_inherit_header_settings']
			},
			{
				slug: 'business',
				label: 'Business Info',
				fields: ['header_show_business_profile', 'header_show_business_phone']
			},
			{
				slug: 'search',
				label: 'Search',
				fields: ['header_show_search', 'header_searchwp_form_id', 'header_search_style', 'header_search_icon_source', 'header_search_standard_icon', 'header_search_fa_class', 'header_search_media_icon']
			}
		],
		footer: [
			{
				slug: 'navigation',
				label: 'Navigation',
				fields: ['footer_show_footer_menu', 'footer_show_secondary_menu', 'footer_show_tertiary_menu', 'footer_show_privacy_center_links']
			},
			{
				slug: 'business',
				label: 'Business Info',
				fields: ['footer_show_business_profile', 'footer_show_business_phone', 'footer_show_text_phone', 'footer_show_address', 'footer_show_business_hours']
			},
			{
				slug: 'social',
				label: 'Social',
				fields: ['footer_show_social_menu', 'footer_social_icon_color', 'footer_social_icon_hover_color']
			},
			{
				slug: 'text',
				label: 'Footer Text',
				fields: ['footer_copyright_text', 'footer_legal_text']
			}
		]
	};

	function parseJson(value, fallback) {
		if (typeof value !== 'string' || value.trim() === '') {
			return fallback;
		}

		try {
			return JSON.parse(value);
		} catch (error) {
			return fallback;
		}
	}

	function clone(value) {
		return parseJson(JSON.stringify(value || {}), {});
	}

	function clamp(value, min, max) {
		var number = parseInt(value, 10);

		if (isNaN(number)) {
			number = min;
		}

		return Math.min(max, Math.max(min, number));
	}

	function getItemKeys(items) {
		return Object.keys(items || {});
	}

	function getSectionPanelFields(section, subtab) {
		return document.querySelectorAll('.acf-field.mrn-theme-hf-subtab-section--' + section + '.mrn-theme-hf-subtab--' + subtab);
	}

	function getSectionNextTopTab(section) {
		var sectionTab = document.querySelector('.acf-field[data-key="field_mrn_theme_' + section + '_tab"]');
		var node = sectionTab ? sectionTab.nextElementSibling : null;

		while (node) {
			if (node.className && String(node.className).indexOf('acf-field-tab') !== -1) {
				return node;
			}

			node = node.nextElementSibling;
		}

		return null;
	}

	function createConfigHeading(section, group) {
		var heading = document.querySelector('[data-mrn-theme-hf-config-heading="' + section + ':' + group.slug + '"]');
		var activeTab;
		var isActive;
		var title;

		if (heading) {
			return heading;
		}

		activeTab = document.querySelector('[data-mrn-theme-hf-section="' + section + '"]');
		isActive = !activeTab || activeTab.getAttribute('data-mrn-theme-hf-active') === 'configs';

		heading = document.createElement('div');
		heading.className = 'acf-field acf-field-message mrn-theme-hf-subtab-panel mrn-theme-hf-subtab-section--' + section + ' mrn-theme-hf-subtab--configs mrn-theme-hf-config-heading';
		heading.setAttribute('data-mrn-theme-hf-config-heading', section + ':' + group.slug);
		heading.setAttribute('data-name', '');
		heading.setAttribute('data-type', 'message');
		heading.setAttribute('aria-hidden', isActive ? 'false' : 'true');
		heading.hidden = !isActive;

		title = document.createElement('h3');
		title.className = 'mrn-theme-hf-config-heading__title';
		title.textContent = group.label;
		heading.appendChild(title);

		return heading;
	}

	function removeGeneratedConfigHeadings(section) {
		var headings = document.querySelectorAll('[data-mrn-theme-hf-config-heading^="' + section + ':"]');
		var index;

		for (index = 0; index < headings.length; index += 1) {
			headings[index].remove();
		}
	}

	function hasServerConfigHeadings(section) {
		var selector = '.acf-field.mrn-theme-hf-config-heading.mrn-theme-hf-subtab-section--' + section + '.mrn-theme-hf-subtab--configs:not([data-mrn-theme-hf-config-heading])';

		return !!document.querySelector(selector);
	}

	function prepareConfigField(field) {
		var type = field.getAttribute('data-type') || '';

		if (type === 'true_false' || field.className.indexOf('acf-field-true-false') !== -1) {
			field.className = field.className.replace(/\s*mrn-theme-hf-config-detail/g, '');
			if (field.className.indexOf('mrn-theme-hf-config-toggle') === -1) {
				field.className += ' mrn-theme-hf-config-toggle';
			}
			field.style.width = '50%';
			return;
		}

		if (field.className.indexOf('mrn-theme-hf-config-detail') === -1) {
			field.className += ' mrn-theme-hf-config-detail';
		}

		if (type === 'text' || type === 'textarea' || field.className.indexOf('acf-field-text ') !== -1 || field.className.indexOf('acf-field-textarea') !== -1) {
			field.style.width = '100%';
		} else {
			field.style.width = '50%';
		}
	}

	function organizeConfigFields(section) {
		var groups = CONFIG_GROUPS[section] || [];
		var panels = getSectionPanelFields(section, 'configs');
		var fieldsByName = {};
		var nextTopTab = getSectionNextTopTab(section);
		var parent;
		var used = {};
		var groupIndex;
		var fieldIndex;
		var fieldName;
		var field;

		if (!panels.length || !groups.length) {
			return;
		}

		parent = panels[0].parentNode;
		if (!parent) {
			return;
		}

		removeGeneratedConfigHeadings(section);

		if (hasServerConfigHeadings(section)) {
			for (fieldIndex = 0; fieldIndex < panels.length; fieldIndex += 1) {
				field = panels[fieldIndex];
				if (field.className.indexOf('mrn-theme-hf-config-heading') === -1) {
					prepareConfigField(field);
				}
			}

			return;
		}

		for (fieldIndex = 0; fieldIndex < panels.length; fieldIndex += 1) {
			field = panels[fieldIndex];
			fieldName = field.getAttribute('data-name') || '';
			if (fieldName) {
				fieldsByName[fieldName] = field;
			}
		}

		for (groupIndex = 0; groupIndex < groups.length; groupIndex += 1) {
			var group = groups[groupIndex];
			var heading = createConfigHeading(section, group);
			var hasGroupFields = false;

			for (fieldIndex = 0; fieldIndex < group.fields.length; fieldIndex += 1) {
				if (fieldsByName[group.fields[fieldIndex]]) {
					hasGroupFields = true;
					break;
				}
			}

			if (!hasGroupFields) {
				continue;
			}

			parent.insertBefore(heading, nextTopTab);

			for (fieldIndex = 0; fieldIndex < group.fields.length; fieldIndex += 1) {
				fieldName = group.fields[fieldIndex];
				field = fieldsByName[fieldName];
				if (!field) {
					continue;
				}

				prepareConfigField(field);
				parent.insertBefore(field, nextTopTab);
				used[fieldName] = true;
			}
		}

		for (fieldIndex = 0; fieldIndex < panels.length; fieldIndex += 1) {
			field = panels[fieldIndex];
			fieldName = field.getAttribute('data-name') || '';
			if (fieldName && !used[fieldName] && !field.getAttribute('data-mrn-theme-hf-config-heading')) {
				prepareConfigField(field);
				parent.insertBefore(field, nextTopTab);
			}
		}
	}

	function organizeAllConfigFields() {
		organizeConfigFields('header');
		organizeConfigFields('footer');
	}

	function getActiveItems(items, toggleFields, editor) {
		var activeItems = {};
		var itemKeys = getItemKeys(items);
		var form = editor && editor.closest ? editor.closest('form') : null;
		var index;
		var itemKey;
		var fieldName;

		for (index = 0; index < itemKeys.length; index += 1) {
			itemKey = itemKeys[index];
			fieldName = toggleFields && toggleFields[itemKey] ? String(toggleFields[itemKey]) : '';

			if (!fieldName || isToggleFieldEnabled(form, fieldName)) {
				activeItems[itemKey] = items[itemKey];
			}
		}

		return activeItems;
	}

	function normalizeLayout(layout, defaults, items) {
		var normalized = {};
		var defaultItems = defaults && defaults.items ? defaults.items : {};
		var rawItems = layout && layout.items ? layout.items : {};
		var keys = getItemKeys(items);
		var defaultKeys = Object.keys(defaultItems || {});
		var defaultKey;
		var defaultItem;
		var index;

		normalized.columns = clamp(layout && layout.columns ? layout.columns : defaults.columns, MIN_COLUMNS, MAX_COLUMNS);
		normalized.rows = clamp(layout && layout.rows ? layout.rows : defaults.rows, MIN_ROWS, MAX_ROWS);
		normalized.items = {};

		for (index = 0; index < defaultKeys.length; index += 1) {
			defaultKey = defaultKeys[index];
			defaultItem = defaultItems[defaultKey] || {};

			if (!rawItems[defaultKey] && defaultItem.row) {
				normalized.rows = clamp(Math.max(normalized.rows, defaultItem.row), MIN_ROWS, MAX_ROWS);
			}
		}

		for (index = 0; index < keys.length; index += 1) {
			var itemKey = keys[index];
			var itemDefault = defaultItems[itemKey] || {};
			var item = rawItems[itemKey] || itemDefault;
			var columnSpan = clamp(item.columnSpan || itemDefault.columnSpan || 1, MIN_COLUMNS, normalized.columns);
			var columnMax = Math.max(1, normalized.columns - columnSpan + 1);

			normalized.items[itemKey] = {
				row: clamp(item.row || itemDefault.row || 1, MIN_ROWS, normalized.rows),
				column: clamp(item.column || itemDefault.column || 1, MIN_COLUMNS, columnMax),
				columnSpan: columnSpan
			};
		}

		return normalized;
	}

	function getToggleField(form, fieldName) {
		var selector = '.acf-field[data-name="' + fieldName + '"]';

		if (form && form.querySelector) {
			return form.querySelector(selector);
		}

		return document.querySelector(selector);
	}

	function getToggleControl(form, fieldName) {
		var field = getToggleField(form, fieldName);

		if (!field) {
			return null;
		}

		return field.querySelector('input[type="checkbox"]') || field.querySelector('input');
	}

	function isToggleFieldEnabled(form, fieldName) {
		var control = getToggleControl(form, fieldName);

		if (!control) {
			return false;
		}

		if (control.type === 'checkbox' || control.type === 'radio') {
			return control.checked;
		}

		return control.value !== '' && control.value !== '0';
	}

	function findStorageField(editor) {
		var storageName = editor.getAttribute('data-storage-name') || '';
		var scope = editor.closest('.acf-fields') || document;
		var selector = '.acf-field[data-name="' + storageName + '"] textarea, .acf-field[data-name="' + storageName + '"] input';

		return scope.querySelector(selector) || document.querySelector(selector);
	}

	function getLayoutFromStorage(storage, defaults, items) {
		var stored = storage ? parseJson(storage.value, null) : null;

		return normalizeLayout(stored || defaults, defaults, items);
	}

	function getSubmitInputName(state) {
		var storageName = state.storage && state.storage.getAttribute ? state.storage.getAttribute('name') : '';
		var section = state.editor ? state.editor.getAttribute('data-section') || '' : '';

		if (storageName) {
			return storageName;
		}

		if (section) {
			return 'acf[field_mrn_theme_' + section + '_layout_grid]';
		}

		return '';
	}

	function ensureSubmitInput(state) {
		var section = state.editor ? state.editor.getAttribute('data-section') || '' : '';
		var form = state.editor && state.editor.closest ? state.editor.closest('form') : null;
		var name = getSubmitInputName(state);
		var selector;
		var input;

		if (!form && state.storage && state.storage.closest) {
			form = state.storage.closest('form');
		}

		if (!form || !name || !section) {
			return null;
		}

		if (state.submitInput && state.submitInput.parentNode === form) {
			state.submitInput.name = name;
			return state.submitInput;
		}

		selector = 'input[type="hidden"][data-mrn-theme-hf-layout-submit="' + section + '"]';
		input = form.querySelector(selector);
		if (!input) {
			input = document.createElement('input');
			input.type = 'hidden';
			input.setAttribute('data-mrn-theme-hf-layout-submit', section);
			form.appendChild(input);
		}

		input.name = name;
		state.submitInput = input;

		return input;
	}

	function ensureRequestInput(state) {
		var section = state.editor ? state.editor.getAttribute('data-section') || '' : '';
		var form = state.editor && state.editor.closest ? state.editor.closest('form') : null;
		var selector;
		var input;

		if (!form && state.storage && state.storage.closest) {
			form = state.storage.closest('form');
		}

		if (!form || !section) {
			return null;
		}

		if (state.requestInput && state.requestInput.parentNode === form) {
			return state.requestInput;
		}

		selector = 'input[type="hidden"][data-mrn-theme-hf-layout-request="' + section + '"]';
		input = form.querySelector(selector);
		if (!input) {
			input = document.createElement('input');
			input.type = 'hidden';
			input.setAttribute('data-mrn-theme-hf-layout-request', section);
			form.appendChild(input);
		}

		input.name = 'mrn_theme_hf_layout_grid[' + section + ']';
		state.requestInput = input;

		return input;
	}

	function syncStorage(state) {
		var value = JSON.stringify(state.layout);
		var submitInput = ensureSubmitInput(state);
		var requestInput = ensureRequestInput(state);

		if (state.storage) {
			state.storage.value = value;
			state.storage.dispatchEvent(new Event('input', { bubbles: true }));
			state.storage.dispatchEvent(new Event('change', { bubbles: true }));
		}

		if (submitInput) {
			submitInput.value = value;
		}

		if (requestInput) {
			requestInput.value = value;
		}
	}

	function syncEditorStorageForSubmit(state) {
		var value = JSON.stringify(normalizeLayout(state.layout, state.defaults, state.allItems));
		var submitInput = ensureSubmitInput(state);
		var requestInput = ensureRequestInput(state);

		if (state.storage) {
			state.storage.value = value;
		}

		if (submitInput) {
			submitInput.value = value;
		}

		if (requestInput) {
			requestInput.value = value;
		}
	}

	function syncAllEditorStorage(context) {
		var root = context && context.querySelectorAll ? context : document;
		var editors = root.querySelectorAll('[data-mrn-theme-hf-layout-editor]');
		var index;
		var state;

		for (index = 0; index < editors.length; index += 1) {
			state = editors[index].__mrnThemeHfLayoutState;
			if (state) {
				syncEditorStorageForSubmit(state);
			}
		}
	}

	function setSubmitStateForForm(form, isSubmitting) {
		var editors = form ? form.querySelectorAll('[data-mrn-theme-hf-layout-editor]') : [];
		var index;
		var state;

		for (index = 0; index < editors.length; index += 1) {
			state = editors[index].__mrnThemeHfLayoutState;
			if (state) {
				state.isSubmitting = !!isSubmitting;
			}
		}
	}

	function scheduleSubmitStateReset(root) {
		window.setTimeout(function () {
			if (root && root.tagName && root.tagName.toLowerCase() === 'form') {
				setSubmitStateForForm(root, false);
				return;
			}

			setSubmitStateForAllEditors(root || document, false);
		}, 4000);
	}

	function getSubmitControl(target) {
		var node = target;
		var tagName;
		var type;

		while (node) {
			tagName = node.tagName ? node.tagName.toLowerCase() : '';
			type = node.getAttribute ? String(node.getAttribute('type') || '').toLowerCase() : '';

			if (('button' === tagName || 'input' === tagName) && ('submit' === type || '' === type)) {
				return node;
			}

			node = node.parentNode;
		}

		return null;
	}

	function getSubmitControlForm(control) {
		var formId;

		if (!control) {
			return null;
		}

		formId = control.getAttribute ? String(control.getAttribute('form') || '') : '';
		if (formId) {
			return document.getElementById(formId);
		}

		return control.closest ? control.closest('form') : null;
	}

	function isSubmitControl(target) {
		return !!getSubmitControl(target);
	}

	function setSubmitStateForAllEditors(root, isSubmitting) {
		var scope = root && root.querySelectorAll ? root : document;
		var editors = scope.querySelectorAll('[data-mrn-theme-hf-layout-editor]');
		var index;
		var state;

		for (index = 0; index < editors.length; index += 1) {
			state = editors[index].__mrnThemeHfLayoutState;
			if (state) {
				state.isSubmitting = !!isSubmitting;
			}
		}
	}

	function freezeSubmitFromTarget(target) {
		var control = getSubmitControl(target);
		var form = getSubmitControlForm(control);

		if (!control) {
			return;
		}

		if (form) {
			setSubmitStateForForm(form, true);
			scheduleSubmitStateReset(form);
			return;
		}

		setSubmitStateForAllEditors(document, true);
		scheduleSubmitStateReset(document);
	}

	function bindDocumentSubmitFreeze() {
		if (document.__mrnThemeHfLayoutSubmitFreezeBound) {
			return;
		}

		document.__mrnThemeHfLayoutSubmitFreezeBound = true;
		document.addEventListener('pointerdown', function (event) {
			freezeSubmitFromTarget(event.target);
		}, true);
		document.addEventListener('mousedown', function (event) {
			freezeSubmitFromTarget(event.target);
		}, true);
		document.addEventListener('touchstart', function (event) {
			freezeSubmitFromTarget(event.target);
		}, true);
	}

	function bindFormSubmit(state) {
		var form = state.editor && state.editor.closest ? state.editor.closest('form') : null;

		if (!form && state.storage && state.storage.closest) {
			form = state.storage.closest('form');
		}

		if (!form || form.__mrnThemeHfLayoutSubmitBound) {
			return;
		}

		form.__mrnThemeHfLayoutSubmitBound = true;
		form.addEventListener('pointerdown', function (event) {
			if (isSubmitControl(event.target)) {
				setSubmitStateForForm(form, true);
				scheduleSubmitStateReset(form);
			}
		}, true);
		form.addEventListener('mousedown', function (event) {
			if (isSubmitControl(event.target)) {
				setSubmitStateForForm(form, true);
				scheduleSubmitStateReset(form);
			}
		}, true);
		form.addEventListener('touchstart', function (event) {
			if (isSubmitControl(event.target)) {
				setSubmitStateForForm(form, true);
				scheduleSubmitStateReset(form);
			}
		}, true);
		form.addEventListener('submit', function () {
			setSubmitStateForForm(form, true);
			syncAllEditorStorage(form);
			scheduleSubmitStateReset(form);
		}, true);
	}

	function refreshActiveItems(state, shouldRender) {
		if (state.isSubmitting) {
			return;
		}

		state.items = getActiveItems(state.allItems, state.toggleFields, state.editor);
		state.layout = normalizeLayout(state.layout, state.defaults, state.allItems);

		if (state.selectedItem && !state.items[state.selectedItem]) {
			state.selectedItem = '';
		}

		syncStorage(state);

		if (shouldRender) {
			renderEditor(state);
		}
	}

	function bindToggleFields(state) {
		var form = state.editor && state.editor.closest ? state.editor.closest('form') : null;
		var fields = state.toggleFields || {};
		var itemKeys = getItemKeys(fields);
		var boundFields = {};
		var index;
		var fieldName;
		var field;

		for (index = 0; index < itemKeys.length; index += 1) {
			fieldName = String(fields[itemKeys[index]] || '');
			if (!fieldName || boundFields[fieldName]) {
				continue;
			}

			field = getToggleField(form, fieldName);
			if (!field || field.__mrnThemeHfLayoutToggleBound) {
				boundFields[fieldName] = true;
				continue;
			}

			field.__mrnThemeHfLayoutToggleBound = true;
			field.addEventListener('input', function () {
				refreshActiveItems(state, true);
			}, true);
			field.addEventListener('change', function () {
				refreshActiveItems(state, true);
			}, true);

			boundFields[fieldName] = true;
		}
	}

	function getItemAt(layout, row, column, excludeItemKey, activeItems) {
		var itemKeys = Object.keys(layout.items || {});
		var found = '';

		for (var index = 0; index < itemKeys.length; index += 1) {
			var itemKey = itemKeys[index];
			var item = layout.items[itemKey];

			if (itemKey === excludeItemKey || !item || item.row !== row || (activeItems && !activeItems[itemKey])) {
				continue;
			}

			if (column >= item.column && column < item.column + item.columnSpan) {
				found = itemKey;
				break;
			}
		}

		return found;
	}

	function moveItem(state, itemKey, row, column) {
		var item = state.layout.items[itemKey];
		var previous;
		var occupant;
		var occupantItem;

		if (state.isSubmitting) {
			return;
		}

		if (!item) {
			return;
		}

		previous = {
			row: item.row,
			column: item.column
		};

		row = clamp(row, MIN_ROWS, state.layout.rows);
		column = clamp(column, MIN_COLUMNS, Math.max(1, state.layout.columns - item.columnSpan + 1));
		occupant = getItemAt(state.layout, row, column, itemKey, state.items);

		item.row = row;
		item.column = column;

		if (occupant && state.layout.items[occupant]) {
			occupantItem = state.layout.items[occupant];
			occupantItem.row = previous.row;
			occupantItem.column = clamp(previous.column, MIN_COLUMNS, Math.max(1, state.layout.columns - occupantItem.columnSpan + 1));
		}

		state.selectedItem = itemKey;
		state.layout = normalizeLayout(state.layout, state.defaults, state.allItems);
		syncStorage(state);
		renderEditor(state);
	}

	function getDropCellFromEvent(state, event) {
		var x = event.clientX;
		var y = event.clientY;
		var cells = state.canvas.querySelectorAll('[data-mrn-layout-row][data-mrn-layout-column]');
		var index;
		var rect;
		var canvasRect;

		for (index = 0; index < cells.length; index += 1) {
			rect = cells[index].getBoundingClientRect();
			if (x >= rect.left && x <= rect.right && y >= rect.top && y <= rect.bottom) {
				return {
					row: clamp(cells[index].getAttribute('data-mrn-layout-row'), MIN_ROWS, state.layout.rows),
					column: clamp(cells[index].getAttribute('data-mrn-layout-column'), MIN_COLUMNS, state.layout.columns)
				};
			}
		}

		canvasRect = state.canvas.getBoundingClientRect();
		return {
			row: clamp(Math.floor(((y - canvasRect.top) / Math.max(1, canvasRect.height)) * state.layout.rows) + 1, MIN_ROWS, state.layout.rows),
			column: clamp(Math.floor(((x - canvasRect.left) / Math.max(1, canvasRect.width)) * state.layout.columns) + 1, MIN_COLUMNS, state.layout.columns)
		};
	}

	function getDraggedItemKey(state, event) {
		var itemKey = '';

		if (event.dataTransfer) {
			itemKey = event.dataTransfer.getData('text/plain') || '';
		}

		return itemKey || state.draggingItem || '';
	}

	function bindCanvasDrop(state) {
		if (state.canvas.__mrnThemeHfDropBound) {
			return;
		}

		state.canvas.__mrnThemeHfDropBound = true;
		state.canvas.setAttribute('data-mrn-layout-drop-bound', 'true');

		state.canvas.addEventListener('dragover', function (event) {
			if (!state.draggingItem && !(event.dataTransfer && event.dataTransfer.types && Array.prototype.indexOf.call(event.dataTransfer.types, 'text/plain') !== -1)) {
				return;
			}

			event.preventDefault();
			state.canvas.className = state.canvas.className.replace(/\s*is-drag-over/g, '') + ' is-drag-over';
			if (event.dataTransfer) {
				event.dataTransfer.dropEffect = 'move';
			}
		});

		state.canvas.addEventListener('dragleave', function (event) {
			if (!state.canvas.contains(event.relatedTarget)) {
				state.canvas.className = state.canvas.className.replace(/\s*is-drag-over/g, '');
			}
		});

		state.canvas.addEventListener('drop', function (event) {
			var itemKey = getDraggedItemKey(state, event);
			var cell;

			if (!itemKey || !state.layout.items[itemKey]) {
				return;
			}

			event.preventDefault();
			state.canvas.className = state.canvas.className.replace(/\s*is-drag-over/g, '');
			cell = getDropCellFromEvent(state, event);
			moveItem(state, itemKey, cell.row, cell.column);
		});
	}

	function isInteractiveDragTarget(target, chip) {
		var node = target;
		var tagName;

		while (node && node !== chip) {
			tagName = node.tagName ? node.tagName.toLowerCase() : '';
			if ('select' === tagName || 'option' === tagName || 'input' === tagName || 'button' === tagName || 'a' === tagName || 'textarea' === tagName) {
				return true;
			}

			if (node.className && String(node.className).indexOf('mrn-theme-hf-layout-grid-editor__span-control') !== -1) {
				return true;
			}

			node = node.parentNode;
		}

		return false;
	}

	function clearPointerDragClasses(state, chip) {
		if (state && state.canvas) {
			state.canvas.className = state.canvas.className.replace(/\s*is-dragging|\s*is-drag-over/g, '');
		}

		if (chip) {
			chip.className = chip.className.replace(/\s*is-dragging/g, '');
		}
	}

	function handlePointerDragMove(event) {
		var drag = activePointerDrag;
		var deltaX;
		var deltaY;

		if (!drag) {
			return;
		}

		deltaX = Math.abs(event.clientX - drag.startX);
		deltaY = Math.abs(event.clientY - drag.startY);

		if (deltaX > 4 || deltaY > 4) {
			drag.moved = true;
			drag.state.draggingItem = drag.itemKey;
			drag.chip.className = drag.chip.className.replace(/\s*is-dragging/g, '') + ' is-dragging';
			drag.state.canvas.className = drag.state.canvas.className.replace(/\s*is-dragging/g, '') + ' is-dragging';
			event.preventDefault();
		}
	}

	function handlePointerDragEnd(event) {
		var drag = activePointerDrag;
		var cell;

		if (!drag) {
			return;
		}

		document.removeEventListener('mousemove', handlePointerDragMove);
		document.removeEventListener('mouseup', handlePointerDragEnd);
		activePointerDrag = null;
		drag.state.draggingItem = '';

		if (drag.moved) {
			event.preventDefault();
			drag.chip.__mrnSuppressClick = true;
			cell = getDropCellFromEvent(drag.state, event);
			clearPointerDragClasses(drag.state, drag.chip);
			moveItem(drag.state, drag.itemKey, cell.row, cell.column);
			return;
		}

		clearPointerDragClasses(drag.state, drag.chip);
		selectChip(drag.state, drag.itemKey);
	}

	function startPointerDrag(state, itemKey, chip, event) {
		if (event.button !== 0 || isInteractiveDragTarget(event.target, chip)) {
			return;
		}

		activePointerDrag = {
			state: state,
			itemKey: itemKey,
			chip: chip,
			startX: event.clientX,
			startY: event.clientY,
			moved: false
		};

		state.selectedItem = itemKey;
		document.addEventListener('mousemove', handlePointerDragMove);
		document.addEventListener('mouseup', handlePointerDragEnd);
	}

	function updateSelectedMessage(state) {
		if (!state.selectedNotice) {
			return;
		}

		if (!state.selectedItem || !state.items[state.selectedItem]) {
			state.selectedNotice.textContent = '';
			return;
		}

		state.selectedNotice.textContent = state.items[state.selectedItem] + ' selected';
	}

	function createCell(state, row, column) {
		var cell = document.createElement('button');

		cell.type = 'button';
		cell.className = 'mrn-theme-hf-layout-grid-editor__cell';
		cell.style.gridColumn = String(column);
		cell.style.gridRow = String(row);
		cell.textContent = 'R' + row + ' C' + column;
		cell.setAttribute('data-mrn-layout-row', String(row));
		cell.setAttribute('data-mrn-layout-column', String(column));
		cell.setAttribute('aria-label', 'Move selected item to row ' + row + ', column ' + column);

		cell.addEventListener('click', function () {
			if (state.selectedItem) {
				moveItem(state, state.selectedItem, row, column);
			}
		});

		cell.addEventListener('dragover', function (event) {
			event.preventDefault();
		});

		cell.addEventListener('drop', function (event) {
			var itemKey;

			event.preventDefault();
			itemKey = event.dataTransfer ? event.dataTransfer.getData('text/plain') : '';
			itemKey = itemKey || state.draggingItem || '';

			if (itemKey) {
				moveItem(state, itemKey, row, column);
			}
		});

		return cell;
	}

	function createSpanSelect(state, itemKey, item) {
		var label = document.createElement('label');
		var labelText = document.createElement('span');
		var select = document.createElement('select');
		var index;

		label.className = 'mrn-theme-hf-layout-grid-editor__span-control';
		labelText.textContent = 'Span';
		label.appendChild(labelText);

		for (index = 1; index <= state.layout.columns; index += 1) {
			var option = document.createElement('option');
			option.value = String(index);
			option.textContent = String(index);
			option.selected = index === item.columnSpan;
			select.appendChild(option);
		}

		select.addEventListener('click', function (event) {
			event.stopPropagation();
		});

		select.addEventListener('change', function (event) {
			var span = clamp(event.target.value, MIN_COLUMNS, state.layout.columns);

			if (state.isSubmitting) {
				return;
			}

			item.columnSpan = span;
			item.column = clamp(item.column, MIN_COLUMNS, Math.max(1, state.layout.columns - span + 1));
			state.selectedItem = itemKey;
			state.layout = normalizeLayout(state.layout, state.defaults, state.allItems);
			syncStorage(state);
			renderEditor(state);
		});

		label.appendChild(select);

		return label;
	}

	function selectChip(state, itemKey) {
		state.selectedItem = itemKey;
		renderEditor(state);
	}

	function createChip(state, itemKey) {
		var item = state.layout.items[itemKey];
		var chip = document.createElement('div');
		var label = document.createElement('span');
		var meta = document.createElement('span');

		chip.className = 'mrn-theme-hf-layout-grid-editor__chip';
		if (state.selectedItem === itemKey) {
			chip.className += ' is-selected';
		}
		chip.draggable = true;
		chip.tabIndex = 0;
		chip.setAttribute('role', 'button');
		chip.setAttribute('aria-pressed', state.selectedItem === itemKey ? 'true' : 'false');
		chip.setAttribute('aria-label', 'Select ' + state.items[itemKey]);
		chip.style.gridColumn = item.column + ' / span ' + item.columnSpan;
		chip.style.gridRow = String(item.row);

		label.className = 'mrn-theme-hf-layout-grid-editor__chip-label';
		label.textContent = state.items[itemKey];
		chip.appendChild(label);

		meta.className = 'mrn-theme-hf-layout-grid-editor__chip-meta';
		meta.textContent = 'Row ' + item.row + ', Column ' + item.column;
		chip.appendChild(meta);
		chip.appendChild(createSpanSelect(state, itemKey, item));

		chip.addEventListener('mousedown', function (event) {
			startPointerDrag(state, itemKey, chip, event);
		});

		chip.addEventListener('click', function () {
			if (chip.__mrnSuppressClick) {
				chip.__mrnSuppressClick = false;
				return;
			}

			selectChip(state, itemKey);
		});

		chip.addEventListener('keydown', function (event) {
			if (event.key === 'Enter' || event.key === ' ') {
				event.preventDefault();
				selectChip(state, itemKey);
			}
		});

		chip.addEventListener('dragstart', function (event) {
			state.draggingItem = itemKey;
			chip.className = chip.className.replace(/\s*is-dragging/g, '') + ' is-dragging';
			state.canvas.className = state.canvas.className.replace(/\s*is-dragging/g, '') + ' is-dragging';
			if (event.dataTransfer) {
				event.dataTransfer.effectAllowed = 'move';
				event.dataTransfer.setData('text/plain', itemKey);
			}
		});

		chip.addEventListener('dragend', function () {
			state.draggingItem = '';
			state.canvas.className = state.canvas.className.replace(/\s*is-dragging|\s*is-drag-over/g, '');
		});

		return chip;
	}

	function renderEditor(state) {
		var row;
		var column;

		if (state.isSubmitting) {
			return;
		}

		state.layout = normalizeLayout(state.layout, state.defaults, state.allItems);
		state.columnsInput.value = String(state.layout.columns);
		state.rowsInput.value = String(state.layout.rows);
		state.canvas.style.setProperty('--mrn-hf-layout-columns', String(state.layout.columns));
		state.canvas.innerHTML = '';

		for (row = 1; row <= state.layout.rows; row += 1) {
			for (column = 1; column <= state.layout.columns; column += 1) {
				state.canvas.appendChild(createCell(state, row, column));
			}
		}

		var itemKeys = getItemKeys(state.items);
		for (var index = 0; index < itemKeys.length; index += 1) {
			var itemKey = itemKeys[index];
			state.canvas.appendChild(createChip(state, itemKey));
		}

		updateSelectedMessage(state);
		bindCanvasDrop(state);
	}

	function ensureNumberInput(editor, toolbar, selector, labelText) {
		var input = editor.querySelector(selector);
		var labels;
		var index;
		var label;

		if (input) {
			return input;
		}

		labels = toolbar.querySelectorAll('label');
		for (index = 0; index < labels.length; index += 1) {
			if (labels[index].textContent.indexOf(labelText) !== -1) {
				label = labels[index];
				break;
			}
		}

		if (!label) {
			label = document.createElement('label');
			label.appendChild(document.createElement('span'));
			label.firstChild.textContent = labelText;
			toolbar.insertBefore(label, toolbar.querySelector('[data-mrn-layout-reset]') || null);
		}

		input = document.createElement('input');
		input.type = 'number';
		input.min = labelText === 'Rows' ? String(MIN_ROWS) : String(MIN_COLUMNS);
		input.max = labelText === 'Rows' ? String(MAX_ROWS) : String(MAX_COLUMNS);
		input.step = '1';

		if (selector === '[data-mrn-layout-columns]') {
			input.setAttribute('data-mrn-layout-columns', '');
		} else {
			input.setAttribute('data-mrn-layout-rows', '');
		}

		label.appendChild(input);

		return input;
	}

	function updateGridDimension(state, key, value) {
		var nextValue;

		if (state.isSubmitting) {
			return;
		}

		if (value === '') {
			return;
		}

		nextValue = key === 'rows'
			? clamp(value, MIN_ROWS, MAX_ROWS)
			: clamp(value, MIN_COLUMNS, MAX_COLUMNS);

		if (state.layout[key] === nextValue) {
			return;
		}

		state.layout[key] = nextValue;
		state.layout = normalizeLayout(state.layout, state.defaults, state.allItems);
		syncStorage(state);
		renderEditor(state);
	}

	function initializeEditor(editor) {
		var storage;
		var defaults;
		var items;
		var toggleFields;
		var columnsInput;
		var rowsInput;
		var resetButton;
		var canvas;
		var state;
		var toolbar;

		if (editor.__mrnThemeHfLayoutState) {
			return;
		}

		storage = findStorageField(editor);
		defaults = parseJson(editor.getAttribute('data-default-layout') || '', {});
		items = parseJson(editor.getAttribute('data-items') || '', {});
		toggleFields = parseJson(editor.getAttribute('data-toggle-fields') || '', {});
		toolbar = editor.querySelector('.mrn-theme-hf-layout-grid-editor__toolbar');
		if (!toolbar) {
			return;
		}

		columnsInput = ensureNumberInput(editor, toolbar, '[data-mrn-layout-columns]', 'Columns');
		rowsInput = ensureNumberInput(editor, toolbar, '[data-mrn-layout-rows]', 'Rows');
		resetButton = editor.querySelector('[data-mrn-layout-reset]');
		canvas = editor.querySelector('[data-mrn-layout-canvas]');

		if (!columnsInput || !rowsInput || !resetButton || !canvas) {
			return;
		}

		state = {
			editor: editor,
			storage: storage,
			defaults: normalizeLayout(defaults, defaults, items),
			allItems: items,
			toggleFields: toggleFields,
			items: getActiveItems(items, toggleFields, editor),
			columnsInput: columnsInput,
			rowsInput: rowsInput,
			resetButton: resetButton,
			canvas: canvas,
			selectedNotice: editor.querySelector('[data-mrn-layout-selected]'),
			selectedItem: '',
			draggingItem: '',
			isSubmitting: false
		};
		state.layout = getLayoutFromStorage(storage, state.defaults, items);
		editor.__mrnThemeHfLayoutState = state;
		bindFormSubmit(state);
		bindToggleFields(state);
		bindCanvasDrop(state);
		refreshActiveItems(state, false);

		columnsInput.addEventListener('input', function (event) {
			updateGridDimension(state, 'columns', event.target.value);
		});

		columnsInput.addEventListener('change', function (event) {
			updateGridDimension(state, 'columns', event.target.value);
		});

		rowsInput.addEventListener('input', function (event) {
			updateGridDimension(state, 'rows', event.target.value);
		});

		rowsInput.addEventListener('change', function (event) {
			updateGridDimension(state, 'rows', event.target.value);
		});

		resetButton.addEventListener('click', function () {
			state.layout = normalizeLayout(clone(state.defaults), state.defaults, state.allItems);
			state.selectedItem = '';
			syncStorage(state);
			renderEditor(state);
		});

		syncStorage(state);
		renderEditor(state);
	}

	function initializeEditors(context) {
		var root = context && context.querySelectorAll ? context : document;
		var editors = root.querySelectorAll('[data-mrn-theme-hf-layout-editor]');
		var index;

		for (index = 0; index < editors.length; index += 1) {
			initializeEditor(editors[index]);
		}
	}

	function initializeAllEditors() {
		bindDocumentSubmitFreeze();
		organizeAllConfigFields();
		initializeEditors(document);
	}

	window.mrnThemeHeaderFooterLayout = {
		initialize: initializeAllEditors
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			initializeAllEditors();
		});
	} else {
		initializeAllEditors();
	}

	if (window.jQuery) {
		window.jQuery(initializeAllEditors);
	}

	window.setTimeout(initializeAllEditors, 50);
	window.setTimeout(initializeAllEditors, 250);
	window.setTimeout(initializeAllEditors, 1000);

	if (window.MutationObserver) {
		var observer = new window.MutationObserver(function (mutations) {
			var shouldInitialize = false;
			var mutationIndex;
			var nodeIndex;

			for (mutationIndex = 0; mutationIndex < mutations.length; mutationIndex += 1) {
				var addedNodes = mutations[mutationIndex].addedNodes || [];
				for (nodeIndex = 0; nodeIndex < addedNodes.length; nodeIndex += 1) {
					var node = addedNodes[nodeIndex];
					if (!node || node.nodeType !== 1) {
						continue;
					}

					if (node.matches && node.matches('[data-mrn-theme-hf-layout-editor]')) {
						shouldInitialize = true;
						break;
					}

					if (node.querySelector && node.querySelector('[data-mrn-theme-hf-layout-editor]')) {
						shouldInitialize = true;
						break;
					}
				}

				if (shouldInitialize) {
					break;
				}
			}

			if (shouldInitialize) {
				initializeAllEditors();
			}
		});

		observer.observe(document.documentElement, {
			childList: true,
			subtree: true
		});
	}

	if (window.acf && typeof window.acf.addAction === 'function') {
		window.acf.addAction('ready', function ($el) {
			initializeEditors($el && $el[0] ? $el[0] : document);
		});

		window.acf.addAction('append', function ($el) {
			initializeEditors($el && $el[0] ? $el[0] : document);
		});
	}
})();
