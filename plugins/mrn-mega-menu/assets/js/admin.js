(function () {
	'use strict';

	const builder = document.querySelector('.mrn-mm-builder');
	if (!builder) return;

	const panelsWrap = builder.querySelector('.mrn-mm-panels');
	const tabsWrap = builder.querySelector('.mrn-mm-panel-tabs');
	const dataInput = builder.querySelector('.mrn-mm-layout-data');
	const linkPickerTarget = builder.querySelector('#mrn-mm-link-picker-target');
	const layoutBuilder = window.MRNAdminLayoutBuilder || null;
	let activeLinkRow = null;
	let pickerReturnFocus = null;
	let copiedColumn = null;

	function iconControlMarkup(field, label, description) {
		return `<div class="mrn-mm-icon-control" data-mrn-icon-control><span class="mrn-mm-icon-control__label">${label}</span><input type="hidden" data-icon-value data-panel-field="${field}" value='{"type":"","value":""}'><span class="mrn-mm-icon-preview" data-icon-preview aria-hidden="true"></span><button type="button" class="button" data-choose-icon>Choose icon</button><button type="button" class="button-link" data-clear-icon hidden>Clear</button><small>${description}</small></div>`;
	}

	function columnMarkup(index) {
		const section = document.createElement('section');
		section.className = 'mrn-mm-column mrn-admin-layout-builder__lane';
		section.dataset.columnIndex = index;
		section.setAttribute('aria-label', `Layout column ${index + 1}`);
		section.innerHTML = `<div class="mrn-mm-column__header"><strong>Layout column ${index + 1}</strong><div class="mrn-mm-column__actions"><span class="mrn-mm-block-count">0 blocks</span><button type="button" class="button-link mrn-mm-copy-column" aria-label="Copy layout column" title="Copy layout column"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button><button type="button" class="button-link mrn-mm-paste-column" aria-label="Paste copied layout column" title="Paste copied layout column" disabled><span class="dashicons dashicons-clipboard" aria-hidden="true"></span></button><button type="button" class="button-link-delete mrn-admin-card-remove mrn-mm-remove-column" aria-label="Remove layout column" title="Remove layout column"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button></div></div><label class="mrn-mm-column__dropdown-toggle"><input type="checkbox" data-column-field="visible_in_dropdown" checked> Show in dropdown</label><div class="mrn-mm-blocks"></div><div class="mrn-mm-add"><button type="button" class="button mrn-mm-add__toggle" aria-expanded="false">+ Add content</button><div class="mrn-mm-add__menu" hidden><button type="button" data-add-block="menu"><span class="dashicons dashicons-menu-alt3"></span><span><strong>WordPress menu</strong><small>Use native menu items and ordering</small></span></button><button type="button" data-add-block="links"><span class="dashicons dashicons-editor-ul"></span><span><strong>Custom link group</strong><small>Build one-off links here</small></span></button><button type="button" data-add-block="categories"><span class="dashicons dashicons-category"></span><span><strong>Product categories</strong><small>Selected WooCommerce categories</small></span></button><button type="button" data-add-block="products"><span class="dashicons dashicons-cart"></span><span><strong>Products</strong><small>Featured, sale, latest, or selected</small></span></button><button type="button" data-add-block="promo"><span class="dashicons dashicons-format-image"></span><span><strong>Promotion</strong><small>Image, message, and call to action</small></span></button><button type="button" data-add-block="reusable"><span class="dashicons dashicons-screenoptions"></span><span><strong>Reusable block</strong><small>Published content from the block library</small></span></button></div></div>`;
		return section;
	}

	function activePanel() {
		return panelsWrap.querySelector('.mrn-mm-panel:not([hidden])') || panelsWrap.querySelector('.mrn-mm-panel');
	}

	function panelMarkup(index, item) {
		const panel = document.createElement('section');
		panel.className = 'mrn-mm-panel';
		panel.dataset.panelIndex = index;
		panel.dataset.menuItemId = String(item?.id || 0);
		panel.dataset.displayMode = 'mega';
		panel.setAttribute('role', 'tabpanel');
		panel.innerHTML = `<div class="mrn-mm-panel__header"><div><h3>Mega menu: <span class="mrn-mm-panel-name"></span></h3><p class="description">Choose the visual columns inside this dropdown, then drag content between them.</p></div><button type="button" class="button-link-delete mrn-admin-card-remove mrn-mm-remove-panel" aria-label="Remove mega menu" title="Remove mega menu"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button></div><div class="mrn-mm-panel-icons"><label class="mrn-mm-panel-display-mode"><span>Menu style</span><select data-panel-field="display_mode"><option value="mega">Full mega menu</option><option value="dropdown">Simple dropdown (single narrow column)</option></select><small>A simple dropdown opens as a narrow list anchored under this item instead of the full-width mega panel. Choose which layout columns it uses below.</small></label><label class="mrn-mm-panel-label-override"><span>Parent navigation label</span><input type="text" data-panel-field="label_override"><small>Overrides the visible top-level label for this layout without renaming the WordPress menu item.</small></label><label class="mrn-mm-panel-parent-click"><span>Parent item click behavior</span><select data-panel-field="parent_click"><option value="inherit">Use global setting</option><option value="toggle">Open mega menu only</option><option value="link">Allow parent link</option></select><small>Applies only to this assigned WordPress menu-item ID. Linked parents navigate on desktop and Enter; touch users tap once to open and again to follow. Space always toggles the panel.</small></label>${iconControlMarkup('item_icon', 'Navigation item icon', 'Overrides the icon configured on the native WordPress menu item for this layout.')}${iconControlMarkup('arrow_icon', 'Navigation arrow icon', 'Overrides the arrow used to indicate this item opens a mega menu.')}${iconControlMarkup('child_arrow_icon', 'Child navigation arrow', 'Replaces the default trailing arrow on child links. A child item’s own navigation arrow takes precedence.')}</div><div class="mrn-mm-columns-toolbar mrn-admin-layout-builder__toolbar"><div><strong>Layout columns</strong><p class="description">These columns appear together inside this one mega menu and stack on smaller screens. Use Copy and Paste in a column header to duplicate its content between top-level menu items.</p></div><label>Columns<input type="number" class="mrn-mm-column-count" min="1" max="6" value="1"></label></div><div class="mrn-mm-columns-scroll mrn-admin-layout-builder__scroll" tabindex="0"><div class="mrn-mm-columns mrn-admin-layout-builder__lanes" data-columns="1"></div></div>`;
		panel.querySelector('.mrn-mm-panel-name').textContent = item?.title || `Unassigned ${index + 1}`;
		panel.querySelector('[data-panel-field="label_override"]').placeholder = item?.title || '';
		panel.querySelector('.mrn-mm-columns').appendChild(columnMarkup(0));
		window.MRNMegaMenuIconControls?.initialize(panel);
		return panel;
	}

	function addPanelFromSelection() {
		const select = builder.querySelector('.mrn-mm-add-panel-item');
		const itemId = Number(select?.value) || 0;
		const assignedMenuId = builder.dataset.assignedMenuId || '';
		const item = (MRNMegaMenuAdmin.menuItems?.[assignedMenuId] || []).find((candidate) => Number(candidate.id) === itemId);
		if (!item) return;
		const existing = Array.from(panelsWrap.querySelectorAll(':scope > .mrn-mm-panel')).findIndex((panel) => Number(panel.dataset.menuItemId) === itemId);
		if (existing >= 0) {
			activatePanel(existing, true);
			window.alert(MRNMegaMenuAdmin.panelExists);
			return;
		}
		const index = panelsWrap.querySelectorAll(':scope > .mrn-mm-panel').length;
		tabsWrap.appendChild(tabMarkup(index, item));
		panelsWrap.appendChild(panelMarkup(index, item));
		reindexPanels();
		activatePanel(index, true);
		refreshSortables();
		serialize();
		select.value = '0';
		notifyLayoutChange(MRNMegaMenuAdmin.panelAdded);
	}

	function tabMarkup(index, item) {
		const tab = document.createElement('button');
		tab.type = 'button';
		tab.setAttribute('role', 'tab');
		tab.dataset.panelTab = index;
		tab.textContent = item?.title || `Unassigned ${index + 1}`;
		return tab;
	}

	function activatePanel(index, moveFocus = false) {
		const tabs = Array.from(tabsWrap.querySelectorAll('[data-panel-tab]'));
		const panels = Array.from(panelsWrap.querySelectorAll(':scope > .mrn-mm-panel'));
		if (layoutBuilder) {
			layoutBuilder.activateTabs(tabs, panels, index, moveFocus);
			return;
		}
		const target = Math.max(0, Math.min(panels.length - 1, Number(index) || 0));
		tabs.forEach((tab, tabIndex) => {
			const selected = tabIndex === target;
			tab.setAttribute('aria-selected', selected ? 'true' : 'false');
			tab.tabIndex = selected ? 0 : -1;
			if (selected && moveFocus) tab.focus();
		});
		panels.forEach((panel, panelIndex) => { panel.hidden = panelIndex !== target; });
	}

	function setColumnCount(value, panel = activePanel()) {
		if (!panel) return;
		const columnsWrap = panel.querySelector('.mrn-mm-columns');
		const target = Math.max(1, Math.min(6, Number(value) || 1));
		while (columnsWrap.children.length < target) columnsWrap.appendChild(columnMarkup(columnsWrap.children.length));
		while (columnsWrap.children.length > target) {
			const last = columnsWrap.lastElementChild;
			const previous = last.previousElementSibling;
			if (previous) last.querySelectorAll('.mrn-mm-block').forEach((block) => previous.querySelector('.mrn-mm-blocks').appendChild(block));
			last.remove();
		}
		reindexPanels();
		refreshSortables();
		serialize();
	}

	function buildAssignedMenuPanels() {
		const currentBlocks = panelsWrap.querySelectorAll('.mrn-mm-block').length;
		if (currentBlocks && !window.confirm(MRNMegaMenuAdmin.confirmBuild)) return;

		const assignedMenuId = builder.dataset.assignedMenuId || '';
		const parentItems = MRNMegaMenuAdmin.menuItems?.[assignedMenuId] || [];
		if (!assignedMenuId || !parentItems.length) return;
		const total = Math.max(1, Math.min(12, parentItems.length));
		panelsWrap.replaceChildren();
		tabsWrap.replaceChildren();

		const template = builder.querySelector('template[data-block-template="menu"]');
		for (let index = 0; index < total; index += 1) {
			const panel = panelMarkup(index, parentItems[index]);
			const column = panel.querySelector('.mrn-mm-column');
			const fragment = template.content.cloneNode(true);
			const block = fragment.querySelector('.mrn-mm-block');
			block.querySelector('[data-field="source"]').value = 'selected';
			block.querySelector('[data-field="branch_mode"]').value = 'children_only';
			block.querySelector('[data-field="distribution_index"]').value = '0';
			block.querySelector('[data-field="distribution_total"]').value = '1';
			block.querySelector('[data-field="menu_id"]').value = assignedMenuId;
			block.querySelector('.mrn-mm-selected-menu').hidden = false;
			block.querySelector('.mrn-mm-menu-root-field').hidden = false;
			populateMenuRoots(block.querySelector('.mrn-mm-block__body'));
			block.querySelector('[data-field="root_item_id"]').value = String(parentItems[index].id);
			block.querySelector('.mrn-mm-branch-mode').hidden = false;
			column.querySelector('.mrn-mm-blocks').appendChild(fragment);
			tabsWrap.appendChild(tabMarkup(index, parentItems[index]));
			panelsWrap.appendChild(panel);
		}

		reindexPanels();
		activatePanel(0);
		refreshSortables();
		serialize();
		notifyLayoutChange(MRNMegaMenuAdmin.columnsBuilt);
	}

	function reindexPanels() {
		const panels = Array.from(panelsWrap.querySelectorAll(':scope > .mrn-mm-panel'));
		const tabs = Array.from(tabsWrap.querySelectorAll('[data-panel-tab]'));
		panels.forEach((panel, panelIndex) => {
			const tab = tabs[panelIndex];
			panel.dataset.panelIndex = panelIndex;
			panel.id = `mrn-mm-panel-${panelIndex}`;
			panel.setAttribute('aria-labelledby', `mrn-mm-panel-tab-${panelIndex}`);
			if (tab) {
				tab.dataset.panelTab = panelIndex;
				tab.id = `mrn-mm-panel-tab-${panelIndex}`;
				tab.setAttribute('aria-controls', panel.id);
			}
			const columnsWrap = panel.querySelector('.mrn-mm-columns');
			const columns = Array.from(columnsWrap.querySelectorAll(':scope > .mrn-mm-column'));
			columns.forEach((column, columnIndex) => {
				column.dataset.columnIndex = columnIndex;
				column.setAttribute('aria-label', `Layout column ${columnIndex + 1}`);
				column.querySelector('.mrn-mm-column__header strong').textContent = `Layout column ${columnIndex + 1}`;
				const blocks = column.querySelector('.mrn-mm-blocks');
				Array.from(blocks.classList).filter((name) => name.startsWith('mrn-mm-sort-panel-')).forEach((name) => blocks.classList.remove(name));
				blocks.classList.add(`mrn-mm-sort-panel-${panelIndex}`);
				updateCount(column);
			});
			columnsWrap.dataset.columns = columns.length;
			const countInput = panel.querySelector('.mrn-mm-column-count');
			if (countInput) countInput.value = String(columns.length);
		});
		updatePasteButtons();
	}

	function updateCount(column) {
		const count = column.querySelectorAll('.mrn-mm-block').length;
		column.querySelector('.mrn-mm-block-count').textContent = `${count} ${count === 1 ? 'block' : 'blocks'}`;
	}

	function announce(message) {
		let status = builder.querySelector('.mrn-mm-sort-status');
		if (!status) {
			status = document.createElement('div');
			status.className = 'screen-reader-text mrn-mm-sort-status';
			status.setAttribute('aria-live', 'polite');
			builder.appendChild(status);
		}
		status.textContent = '';
		window.setTimeout(() => { status.textContent = message; }, 20);
	}

	function notifyLayoutChange(message) {
		dataInput.dispatchEvent(new Event('input', { bubbles: true }));
		dataInput.dispatchEvent(new Event('change', { bubbles: true }));
		if (message) announce(message);
	}

	function refreshSortables() {
		if (!window.jQuery?.fn?.sortable) return;
		const $ = window.jQuery;
		if (layoutBuilder) {
			layoutBuilder.refreshSortableLanes({
				root: panelsWrap,
				listSelector: '.mrn-mm-blocks',
				laneSelector: '.mrn-mm-column',
				items: '> .mrn-mm-block',
				handle: '.mrn-mm-block__toggle',
				cancel: 'input, textarea, select, option, a, .mrn-mm-remove, .mrn-mm-block__body',
				placeholder: 'mrn-mm-block-placeholder mrn-admin-layout-builder__placeholder',
				connectWith: (list) => `.mrn-mm-sort-panel-${list.closest('.mrn-mm-panel')?.dataset.panelIndex || '0'}`,
				onStart: () => builder.classList.add('is-sorting-block'),
				onStop: () => {
					builder.classList.remove('is-sorting-block');
					reindexPanels();
					serialize();
					notifyLayoutChange(MRNMegaMenuAdmin.blockMoved);
				}
			});
			layoutBuilder.refreshSortableLanes({
				root: builder,
				listSelector: '.mrn-mm-link-rows',
				items: '> .mrn-mm-link-row',
				handle: '.mrn-mm-link-drag',
				axis: 'y',
				placeholder: 'mrn-mm-link-placeholder mrn-admin-layout-builder__placeholder',
				onStop: () => {
					serialize();
					notifyLayoutChange(MRNMegaMenuAdmin.linkMoved);
				}
			});
			return;
		}

		$(panelsWrap).find('.mrn-mm-blocks').each(function () {
			const list = $(this);
			if (list.hasClass('ui-sortable')) {
				list.sortable('refresh');
				return;
			}
			const panelIndex = this.closest('.mrn-mm-panel')?.dataset.panelIndex || '0';
			list.sortable({
				connectWith: `.mrn-mm-sort-panel-${panelIndex}`,
				items: '> .mrn-mm-block',
				handle: '.mrn-mm-block__toggle',
				cancel: 'input, textarea, select, option, a, .mrn-mm-remove, .mrn-mm-block__body',
				distance: 4,
				placeholder: 'mrn-mm-block-placeholder',
				forcePlaceholderSize: true,
				tolerance: 'pointer',
				start: function (event, ui) {
					ui.placeholder.height(ui.item.outerHeight());
					builder.classList.add('is-sorting-block');
				},
				over: function () {
					$(this).closest('.mrn-mm-column').addClass('is-drag-over');
				},
				out: function () {
					$(this).closest('.mrn-mm-column').removeClass('is-drag-over');
				},
				stop: function () {
					builder.classList.remove('is-sorting-block');
					builder.querySelectorAll('.mrn-mm-column').forEach((column) => column.classList.remove('is-drag-over'));
					reindexPanels();
					serialize();
					notifyLayoutChange(MRNMegaMenuAdmin.blockMoved);
				}
			});
		});

		builder.querySelectorAll('.mrn-mm-link-rows').forEach((rows) => {
			const list = $(rows);
			if (list.hasClass('ui-sortable')) {
				list.sortable('refresh');
				return;
			}
			list.sortable({
				items: '> .mrn-mm-link-row',
				handle: '.mrn-mm-link-drag',
				axis: 'y',
				placeholder: 'mrn-mm-link-placeholder',
				forcePlaceholderSize: true,
				tolerance: 'pointer',
				start: function (event, ui) {
					ui.placeholder.height(ui.item.outerHeight());
				},
				stop: function () {
					serialize();
					notifyLayoutChange(MRNMegaMenuAdmin.linkMoved);
				}
			});
		});
	}

	function extractBlockData(block, includeClipboardData = false) {
		const data = {};
		block.querySelectorAll('[data-field]').forEach((field) => {
			if (field.dataset.field === 'product_ids') {
				data.product_ids = field.tagName === 'SELECT'
					? Array.from(field.selectedOptions).map((option) => Number(option.value)).filter(Boolean)
					: field.value.split(',').map((id) => parseInt(id.trim(), 10)).filter(Boolean);
			} else {
				data[field.dataset.field] = field.type === 'number' ? Number(field.value) : field.value;
			}
		});
		if (data.type === 'links') {
			data.links = Array.from(block.querySelectorAll('.mrn-mm-link-row')).map((row) => ({
				label: row.querySelector('[data-link-field="label"]').value,
				url: row.querySelector('[data-link-field="url"]').value,
				target: row.querySelector('[data-link-field="target"]').value
			}));
		}
		if (data.type === 'categories') {
			data.category_ids = Array.from(block.querySelectorAll('[data-category-id]:checked'))
				.sort((first, second) => Number(first.dataset.categoryOrder) - Number(second.dataset.categoryOrder))
				.map((input) => Number(input.dataset.categoryId));
		}
		if (includeClipboardData && data.type === 'promo') {
			data.clipboard_image_preview_url = block.querySelector('.mrn-mm-promo-image__preview img')?.src || '';
		}
		return data;
	}

	function extractColumnData(column, includeClipboardData = false) {
		const dropdownToggle = column.querySelector('[data-column-field="visible_in_dropdown"]');
		return {
			visible_in_dropdown: dropdownToggle ? dropdownToggle.checked : true,
			blocks: Array.from(column.querySelectorAll(':scope > .mrn-mm-blocks > .mrn-mm-block'))
				.map((block) => extractBlockData(block, includeClipboardData))
		};
	}

	function populateBlockFromData(block, data) {
		block.querySelectorAll('[data-field]').forEach((field) => {
			const value = data[field.dataset.field];
			if (typeof value === 'undefined') return;
			if (field.dataset.field === 'product_ids' && field.tagName === 'SELECT') {
				const selected = Array.isArray(value) ? value.map(String) : [];
				Array.from(field.options).forEach((option) => { option.selected = selected.includes(option.value); });
				return;
			}
			field.value = Array.isArray(value) ? value.join(',') : String(value);
		});

		const body = block.querySelector('.mrn-mm-block__body');
		if (data.type === 'menu') {
			populateMenuRoots(body);
			const root = body.querySelector('[data-field="root_item_id"]');
			if (root) root.value = String(data.root_item_id || 0);
			const branchMode = body.querySelector('.mrn-mm-branch-mode');
			if (branchMode) branchMode.hidden = !root || root.value === '0';
		}
		if (data.type === 'links') {
			const rows = block.querySelector('.mrn-mm-link-rows');
			const template = builder.querySelector('template[data-link-template]');
			rows?.replaceChildren();
			(data.links || []).forEach((link) => {
				if (!rows || !template) return;
				rows.appendChild(template.content.cloneNode(true));
				const row = rows.lastElementChild;
				row.querySelector('[data-link-field="label"]').value = link.label || '';
				row.querySelector('[data-link-field="url"]').value = link.url || '';
				row.querySelector('[data-link-field="target"]').value = link.target === '_blank' ? '_blank' : '';
			});
		}
		if (data.type === 'categories') {
			const selected = (data.category_ids || []).map(Number);
			block.querySelectorAll('[data-category-id]').forEach((input) => {
				const selectionIndex = selected.indexOf(Number(input.dataset.categoryId));
				input.checked = selectionIndex >= 0;
				if (selectionIndex >= 0) input.dataset.categoryOrder = String(selectionIndex);
			});
		}
		if (data.type === 'products') {
			const manual = body.querySelector('.mrn-mm-manual-products');
			if (manual) manual.hidden = data.source !== 'manual';
		}
		if (data.type === 'promo' && data.clipboard_image_preview_url) {
			const image = block.querySelector('.mrn-mm-promo-image');
			const preview = document.createElement('img');
			preview.alt = '';
			preview.src = data.clipboard_image_preview_url;
			image.querySelector('.mrn-mm-promo-image__preview').replaceChildren(preview);
			image.querySelector('.mrn-mm-remove-image').hidden = false;
			image.querySelector('.mrn-mm-choose-image').textContent = 'Replace image';
		}

		const title = block.querySelector('[data-field="title"]')?.value || '';
		const summary = block.querySelector('.mrn-mm-block__header small');
		if (summary) {
			if (data.type === 'reusable') {
				const reusable = block.querySelector('.mrn-mm-reusable-block-select');
				summary.textContent = reusable?.value !== '0' ? reusable?.selectedOptions[0]?.textContent || 'Untitled' : 'Untitled';
			} else {
				summary.textContent = title || 'Untitled';
			}
		}
	}

	function appendBlockFromData(column, data) {
		const template = builder.querySelector(`template[data-block-template="${data.type}"]`);
		if (!template) return;
		const fragment = template.content.cloneNode(true);
		const block = fragment.querySelector('.mrn-mm-block');
		populateBlockFromData(block, data);
		column.querySelector('.mrn-mm-blocks').appendChild(fragment);
	}

	function updatePasteButtons() {
		builder.querySelectorAll('.mrn-mm-paste-column').forEach((button) => {
			button.disabled = !copiedColumn;
		});
	}

	function serialize() {
		const layout = {
			width: builder.querySelector('[name="mrn_mega_menu_width"]:checked')?.value || 'content',
			panels: []
		};

		panelsWrap.querySelectorAll(':scope > .mrn-mm-panel').forEach((panel) => {
			const parseIcon = (field) => {
				try { return JSON.parse(panel.querySelector(`[data-panel-field="${field}"]`)?.value || '{}'); }
				catch (error) { return { type: '', value: '' }; }
			};
			const panelData = {
				menu_item_id: Number(panel.dataset.menuItemId) || 0,
				label_override: panel.querySelector('[data-panel-field="label_override"]')?.value || '',
				display_mode: panel.querySelector('[data-panel-field="display_mode"]')?.value === 'dropdown' ? 'dropdown' : 'mega',
				parent_click: panel.querySelector('[data-panel-field="parent_click"]')?.value || 'inherit',
				item_icon: parseIcon('item_icon'),
				arrow_icon: parseIcon('arrow_icon'),
				child_arrow_icon: parseIcon('child_arrow_icon'),
				columns: []
			};
			panel.querySelectorAll(':scope > .mrn-mm-columns-scroll > .mrn-mm-columns > .mrn-mm-column').forEach((column) => {
				panelData.columns.push(extractColumnData(column));
			});
			layout.panels.push(panelData);
		});
		dataInput.value = JSON.stringify(layout);
	}

	function closeAddMenus(except) {
		builder.querySelectorAll('.mrn-mm-add__menu').forEach((menu) => {
			if (menu !== except) {
				menu.hidden = true;
				menu.previousElementSibling?.setAttribute('aria-expanded', 'false');
			}
		});
		updateMenuLayer();
	}

	function updateMenuLayer() {
		const hasOpenMenu = Array.from(builder.querySelectorAll('.mrn-mm-add__menu')).some((menu) => !menu.hidden);
		builder.closest('.postbox')?.classList.toggle('mrn-mm-postbox--menu-open', hasOpenMenu);
	}

	function initializeWooSearch() {
		if (window.jQuery) window.jQuery(document.body).trigger('wc-enhanced-select-init');
	}

	function populateMenuRoots(blockBody) {
		const menuSelect = blockBody.querySelector('.mrn-mm-menu-select');
		const rootSelect = blockBody.querySelector('.mrn-mm-menu-root');
		if (!menuSelect || !rootSelect) return;

		const previous = rootSelect.value;
		const items = MRNMegaMenuAdmin.menuItems?.[menuSelect.value] || [];
		rootSelect.innerHTML = '';
		const entire = document.createElement('option');
		entire.value = '0';
		entire.textContent = MRNMegaMenuAdmin.selectParent;
		rootSelect.appendChild(entire);
		items.forEach((item) => {
			const option = document.createElement('option');
			option.value = String(item.id);
			option.textContent = item.title;
			rootSelect.appendChild(option);
		});
		rootSelect.value = Array.from(rootSelect.options).some((option) => option.value === previous) ? previous : '0';
		blockBody.querySelector('.mrn-mm-branch-mode').hidden = rootSelect.value === '0';
	}

	function openLinkPicker(row, returnFocus) {
		if (!window.wpLink || !linkPickerTarget) return false;

		activeLinkRow = row;
		pickerReturnFocus = returnFocus;
		linkPickerTarget.value = '';
		linkPickerTarget.selectionStart = 0;
		linkPickerTarget.selectionEnd = 0;

		const url = row?.querySelector('[data-link-field="url"]')?.value || '';
		const label = row?.querySelector('[data-link-field="label"]')?.value || '';
		window.wpLink.open(linkPickerTarget.id, url, label);

		const newTab = document.querySelector('#wp-link-target');
		if (newTab) newTab.checked = row?.querySelector('[data-link-field="target"]')?.value === '_blank';
		return true;
	}

	function applyPickedLink() {
		if (!linkPickerTarget?.value) return;
		const parsed = document.createElement('div');
		parsed.innerHTML = linkPickerTarget.value;
		const anchor = parsed.querySelector('a[href]');
		if (!anchor) return;

		let row = activeLinkRow;
		if (!row) {
			const template = builder.querySelector('template[data-link-template]');
			const rows = pickerReturnFocus?.closest('.mrn-mm-field')?.querySelector('.mrn-mm-link-rows');
			if (!template || !rows) return;
			rows.appendChild(template.content.cloneNode(true));
			row = rows.lastElementChild;
			refreshSortables();
		}

		row.querySelector('[data-link-field="label"]').value = anchor.textContent.trim() || anchor.getAttribute('href');
		row.querySelector('[data-link-field="url"]').value = anchor.getAttribute('href') || '';
		row.querySelector('[data-link-field="target"]').value = anchor.getAttribute('target') === '_blank' ? '_blank' : '';
		serialize();
	}

	builder.addEventListener('click', (event) => {
		const panelTab = event.target.closest('[data-panel-tab]');
		if (panelTab) {
			activatePanel(panelTab.dataset.panelTab);
			return;
		}

		const removePanel = event.target.closest('.mrn-mm-remove-panel');
		if (removePanel) {
			const panels = Array.from(panelsWrap.querySelectorAll(':scope > .mrn-mm-panel'));
			if (panels.length <= 1) {
				window.alert(MRNMegaMenuAdmin.lastPanel);
				return;
			}
			const panel = removePanel.closest('.mrn-mm-panel');
			if (panel.querySelector('.mrn-mm-block') && !window.confirm(MRNMegaMenuAdmin.confirmRemovePanel)) return;
			const panelIndex = panels.indexOf(panel);
			tabsWrap.querySelectorAll('[data-panel-tab]')[panelIndex]?.remove();
			panel.remove();
			reindexPanels();
			activatePanel(Math.min(panelIndex, panels.length - 2));
			refreshSortables();
			serialize();
			return;
		}

		const removeColumn = event.target.closest('.mrn-mm-remove-column');
		if (removeColumn) {
			const panel = removeColumn.closest('.mrn-mm-panel');
			const columnsWrap = panel.querySelector('.mrn-mm-columns');
			if (columnsWrap.children.length <= 1) {
				window.alert(MRNMegaMenuAdmin.lastColumn);
				return;
			}
			const column = removeColumn.closest('.mrn-mm-column');
			if (column.querySelector('.mrn-mm-block') && !window.confirm(MRNMegaMenuAdmin.confirmRemoveColumn)) return;
			column.remove();
			const countInput = panel.querySelector('.mrn-mm-column-count');
			if (countInput) countInput.value = String(columnsWrap.children.length);
			reindexPanels();
			refreshSortables();
			serialize();
			notifyLayoutChange(MRNMegaMenuAdmin.columnRemoved);
			return;
		}

		const copyColumn = event.target.closest('.mrn-mm-copy-column');
		if (copyColumn) {
			const column = copyColumn.closest('.mrn-mm-column');
			copiedColumn = JSON.parse(JSON.stringify(extractColumnData(column, true)));
			builder.querySelectorAll('.mrn-mm-column').forEach((candidate) => candidate.classList.remove('is-copied'));
			column.classList.add('is-copied');
			updatePasteButtons();
			announce(MRNMegaMenuAdmin.columnCopied);
			return;
		}

		const pasteColumn = event.target.closest('.mrn-mm-paste-column');
		if (pasteColumn && copiedColumn) {
			const column = pasteColumn.closest('.mrn-mm-column');
			const blocks = column.querySelector('.mrn-mm-blocks');
			if (blocks.querySelector('.mrn-mm-block') && !window.confirm(MRNMegaMenuAdmin.confirmPasteColumn)) return;
			blocks.replaceChildren();
			copiedColumn.blocks.forEach((block) => appendBlockFromData(column, block));
			const dropdownToggle = column.querySelector('[data-column-field="visible_in_dropdown"]');
			if (dropdownToggle) dropdownToggle.checked = copiedColumn.visible_in_dropdown !== false;
			updateCount(column);
			initializeWooSearch();
			refreshSortables();
			serialize();
			notifyLayoutChange(MRNMegaMenuAdmin.columnPasted);
			return;
		}

		const buildAssigned = event.target.closest('.mrn-mm-build-assigned');
		if (buildAssigned) {
			buildAssignedMenuPanels();
			return;
		}

		if (event.target.closest('.mrn-mm-add-panel')) {
			addPanelFromSelection();
			return;
		}

		const addToggle = event.target.closest('.mrn-mm-add__toggle');
		if (addToggle) {
			const menu = addToggle.nextElementSibling;
			const willOpen = menu.hidden;
			closeAddMenus(menu);
			menu.hidden = !willOpen;
			addToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
			updateMenuLayer();
			return;
		}

		const addBlock = event.target.closest('[data-add-block]');
		if (addBlock) {
			const template = builder.querySelector(`template[data-block-template="${addBlock.dataset.addBlock}"]`);
			const column = addBlock.closest('.mrn-mm-column');
			if (template && column) {
				column.querySelector('.mrn-mm-blocks').appendChild(template.content.cloneNode(true));
				updateCount(column);
				closeAddMenus();
				initializeWooSearch();
				refreshSortables();
				serialize();
			}
			return;
		}

		const toggle = event.target.closest('.mrn-mm-block__toggle');
		if (toggle) {
			const block = toggle.closest('.mrn-mm-block');
			const body = block.querySelector('.mrn-mm-block__body');
			const expanded = toggle.getAttribute('aria-expanded') === 'true';
			toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
			body.hidden = expanded;
			block.classList.toggle('is-collapsed', expanded);
			return;
		}

		const remove = event.target.closest('.mrn-mm-remove');
		if (remove && window.confirm(MRNMegaMenuAdmin.confirmRemove)) {
			const column = remove.closest('.mrn-mm-column');
			remove.closest('.mrn-mm-block').remove();
			updateCount(column);
			serialize();
			return;
		}

		const moveUp = event.target.closest('.mrn-mm-move-up');
		const moveDown = event.target.closest('.mrn-mm-move-down');
		if (moveUp || moveDown) {
			const block = event.target.closest('.mrn-mm-block');
			const sibling = moveUp ? block.previousElementSibling : block.nextElementSibling;
			if (sibling) block.parentNode.insertBefore(moveUp ? block : sibling, moveUp ? sibling : block);
			serialize();
			return;
		}

		const addLink = event.target.closest('.mrn-mm-add-link');
		if (addLink) {
			if (!openLinkPicker(null, addLink)) {
				const template = builder.querySelector('template[data-link-template]');
				addLink.closest('.mrn-mm-field').querySelector('.mrn-mm-link-rows').appendChild(template.content.cloneNode(true));
				refreshSortables();
			}
			return;
		}

		const pickLink = event.target.closest('.mrn-mm-pick-link');
		if (pickLink) {
			openLinkPicker(pickLink.closest('.mrn-mm-link-row'), pickLink);
			return;
		}

		const removeLink = event.target.closest('.mrn-mm-remove-link');
		if (removeLink) {
			removeLink.closest('.mrn-mm-link-row').remove();
			serialize();
			return;
		}

		const chooseImage = event.target.closest('.mrn-mm-choose-image');
		if (chooseImage && window.wp?.media) {
			const frame = wp.media({ title: MRNMegaMenuAdmin.mediaTitle, button: { text: MRNMegaMenuAdmin.mediaButton }, multiple: false, library: { type: 'image' } });
			frame.on('select', () => {
				const attachment = frame.state().get('selection').first().toJSON();
				const wrap = chooseImage.closest('.mrn-mm-promo-image');
				wrap.querySelector('[data-field="image_id"]').value = attachment.id;
				wrap.querySelector('.mrn-mm-promo-image__preview').innerHTML = `<img src="${attachment.sizes?.medium?.url || attachment.url}" alt="">`;
				wrap.querySelector('.mrn-mm-remove-image').hidden = false;
				chooseImage.textContent = 'Replace image';
				serialize();
			});
			frame.open();
			return;
		}

		const removeImage = event.target.closest('.mrn-mm-remove-image');
		if (removeImage) {
			const wrap = removeImage.closest('.mrn-mm-promo-image');
			wrap.querySelector('[data-field="image_id"]').value = '';
			wrap.querySelector('.mrn-mm-promo-image__preview').innerHTML = '<span class="dashicons dashicons-format-image"></span>';
			wrap.querySelector('.mrn-mm-choose-image').textContent = 'Choose image';
			removeImage.hidden = true;
			serialize();
		}
	});

	builder.addEventListener('input', (event) => {
		if (event.target.matches('.mrn-mm-column-count')) {
			setColumnCount(event.target.value, event.target.closest('.mrn-mm-panel'));
			return;
		}
		if (event.target.matches('[data-field="title"]')) {
			event.target.closest('.mrn-mm-block').querySelector('.mrn-mm-block__header small').textContent = event.target.value || 'Untitled';
		}
		serialize();
	});

	builder.addEventListener('change', (event) => {
		if (event.target.matches('[data-panel-field="display_mode"]')) {
			const panel = event.target.closest('.mrn-mm-panel');
			if (panel) panel.dataset.displayMode = event.target.value === 'dropdown' ? 'dropdown' : 'mega';
		}
		if (event.target.matches('.mrn-mm-reusable-block-select')) {
			const option = event.target.selectedOptions[0];
			event.target.closest('.mrn-mm-block').querySelector('.mrn-mm-block__header small').textContent = event.target.value !== '0' && option ? option.textContent : 'Untitled';
		}
		if (event.target.matches('.mrn-mm-menu-select')) {
			populateMenuRoots(event.target.closest('.mrn-mm-block__body'));
		}
		if (event.target.matches('.mrn-mm-menu-root')) {
			event.target.closest('.mrn-mm-block__body').querySelector('.mrn-mm-branch-mode').hidden = event.target.value === '0';
		}
		if (event.target.matches('[data-field="source"]')) {
			const manualProducts = event.target.closest('.mrn-mm-block__body').querySelector('.mrn-mm-manual-products');
			if (manualProducts) manualProducts.hidden = event.target.value !== 'manual';
		}
		serialize();
	});

	builder.addEventListener('keydown', (event) => {
		const panelTab = event.target.closest('[data-panel-tab]');
		if (panelTab && ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'].includes(event.key)) {
			const tabs = Array.from(tabsWrap.querySelectorAll('[data-panel-tab]'));
			const current = tabs.indexOf(panelTab);
			let target = layoutBuilder ? layoutBuilder.adjacentTabIndex(event, current, tabs.length) : current;
			if (!layoutBuilder && event.key === 'ArrowLeft') target = (current - 1 + tabs.length) % tabs.length;
			if (!layoutBuilder && event.key === 'ArrowRight') target = (current + 1) % tabs.length;
			if (!layoutBuilder && event.key === 'Home') target = 0;
			if (!layoutBuilder && event.key === 'End') target = tabs.length - 1;
			event.preventDefault();
			activatePanel(target, true);
			return;
		}

		const linkHandle = event.target.closest('.mrn-mm-link-drag');
		if (linkHandle && (event.key === 'ArrowUp' || event.key === 'ArrowDown')) {
			const row = linkHandle.closest('.mrn-mm-link-row');
			const sibling = event.key === 'ArrowUp' ? row.previousElementSibling : row.nextElementSibling;
			if (sibling) {
				event.preventDefault();
				row.parentNode.insertBefore(event.key === 'ArrowUp' ? row : sibling, event.key === 'ArrowUp' ? sibling : row);
				serialize();
				notifyLayoutChange(MRNMegaMenuAdmin.linkMoved);
				linkHandle.focus();
			}
			return;
		}

		const blockHandle = event.target.closest('.mrn-mm-block__toggle');
		if (!blockHandle || !event.altKey || !['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(event.key)) return;

		const block = blockHandle.closest('.mrn-mm-block');
		const column = block.closest('.mrn-mm-column');
		const columnsWrap = block.closest('.mrn-mm-columns');
		const columns = Array.from(columnsWrap.querySelectorAll(':scope > .mrn-mm-column'));
		if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
			const sibling = event.key === 'ArrowUp' ? block.previousElementSibling : block.nextElementSibling;
			if (!sibling) return;
			block.parentNode.insertBefore(event.key === 'ArrowUp' ? block : sibling, event.key === 'ArrowUp' ? sibling : block);
		} else {
			const targetIndex = columns.indexOf(column) + (event.key === 'ArrowLeft' ? -1 : 1);
			if (!columns[targetIndex]) return;
			columns[targetIndex].querySelector('.mrn-mm-blocks').appendChild(block);
		}

		event.preventDefault();
		reindexPanels();
		refreshSortables();
		serialize();
		notifyLayoutChange(MRNMegaMenuAdmin.blockMoved);
		blockHandle.focus();
	});

	document.addEventListener('click', (event) => {
		if (!event.target.closest('.mrn-mm-add')) closeAddMenus();
	});

	if (window.jQuery) {
		window.jQuery(document).on('wplink-close.mrnMegaMenu', () => {
			applyPickedLink();
			linkPickerTarget.value = '';
			activeLinkRow = null;
			pickerReturnFocus?.focus();
			pickerReturnFocus = null;
		});
	}

	document.getElementById('post')?.addEventListener('submit', serialize);
	reindexPanels();
	activatePanel(0);
	refreshSortables();
	initializeWooSearch();
	serialize();
})();
