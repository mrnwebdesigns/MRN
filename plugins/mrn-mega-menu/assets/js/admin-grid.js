(function () {
	'use strict';

	const builder = document.querySelector('.mrn-mm-builder[data-layout-mode="grid"]');
	if (!builder) return;

	const canvas = builder.querySelector('.mrn-mm-grid-canvas');
	const cellsLayer = builder.querySelector('.mrn-mm-grid-cells');
	const blocksLayer = builder.querySelector('.mrn-mm-grid-blocks');
	const columnsInput = builder.querySelector('.mrn-mm-grid-columns');
	const rowsInput = builder.querySelector('.mrn-mm-grid-rows');
	const dataInput = builder.querySelector('.mrn-mm-layout-data');
	const linkPickerTarget = builder.querySelector('#mrn-mm-link-picker-target');
	let selectedBlock = null;
	let draggedBlock = null;
	let activeLinkRow = null;
	let pickerReturnFocus = null;

	const clamp = (value, min, max) => Math.max(min, Math.min(max, Number(value) || min));
	const dimensions = () => ({ columns: clamp(columnsInput.value, 1, 12), rows: clamp(rowsInput.value, 1, 12) });
	const blocks = () => Array.from(blocksLayer.querySelectorAll(':scope > .mrn-mm-block'));
	const rectFor = (block) => ({
		row: clamp(block.dataset.gridRow, 1, dimensions().rows),
		column: clamp(block.dataset.gridColumn, 1, dimensions().columns),
		span: clamp(block.dataset.columnSpan, 1, dimensions().columns)
	});
	const overlaps = (a, b) => a.row === b.row && a.column < b.column + b.span && b.column < a.column + a.span;

	function isOpen(row, column, span, ignored) {
		const size = dimensions();
		if (column + span - 1 > size.columns) return false;
		return !blocks().some((block) => block !== ignored && overlaps({ row, column, span }, rectFor(block)));
	}

	function firstOpen(span = 1, ignored = null) {
		const size = dimensions();
		for (let row = 1; row <= size.rows; row += 1) {
			for (let column = 1; column <= size.columns - span + 1; column += 1) {
				if (isOpen(row, column, span, ignored)) return { row, column };
			}
		}
		if (size.rows < 12) {
			rowsInput.value = size.rows + 1;
			return { row: size.rows + 1, column: 1 };
		}
		return null;
	}

	function setPosition(block, row, column, span) {
		const size = dimensions();
		block.dataset.gridRow = clamp(row, 1, size.rows);
		block.dataset.gridColumn = clamp(column, 1, size.columns);
		block.dataset.columnSpan = clamp(span, 1, size.columns - Number(block.dataset.gridColumn) + 1);
	}

	function normalize() {
		const size = dimensions();
		columnsInput.value = size.columns;
		rowsInput.value = size.rows;
		const placed = [];
		blocks().forEach((block) => {
			const current = rectFor(block);
			current.span = Math.min(current.span, size.columns - current.column + 1);
			const blocked = placed.some((other) => overlaps(current, rectFor(other)));
			if (blocked) {
				const open = firstOpen(current.span, block);
				if (open) setPosition(block, open.row, open.column, current.span);
			} else {
				setPosition(block, current.row, current.column, current.span);
			}
			placed.push(block);
		});
	}

	function render() {
		normalize();
		const size = dimensions();
		canvas.dataset.columns = size.columns;
		canvas.dataset.rows = size.rows;
		canvas.style.setProperty('--mrn-mm-grid-columns', size.columns);
		canvas.style.setProperty('--mrn-mm-grid-rows', size.rows);
		cellsLayer.innerHTML = '';
		for (let row = 1; row <= size.rows; row += 1) {
			for (let column = 1; column <= size.columns; column += 1) {
				const cell = document.createElement('button');
				cell.type = 'button';
				cell.className = 'mrn-mm-grid-cell';
				cell.dataset.row = row;
				cell.dataset.column = column;
				cell.style.gridArea = `${row} / ${column}`;
				cell.innerHTML = `<span>R${row} C${column}</span>`;
				cell.setAttribute('aria-label', `Row ${row}, column ${column}`);
				cellsLayer.appendChild(cell);
			}
		}
		blocks().forEach((block) => {
			const position = rectFor(block);
			block.style.gridArea = `${position.row} / ${position.column} / span 1 / span ${position.span}`;
			block.querySelector('.mrn-mm-block__position').textContent = `Row ${position.row}, Column ${position.column}`;
			const spanSelect = block.querySelector('.mrn-mm-block-span');
			spanSelect.innerHTML = '';
			for (let value = 1; value <= size.columns - position.column + 1; value += 1) {
				spanSelect.add(new Option(String(value), String(value), false, value === position.span));
			}
			block.classList.toggle('is-selected', block === selectedBlock);
		});
		serialize();
	}

	function blockData(block) {
		const data = {};
		block.querySelectorAll('[data-field]').forEach((field) => {
			if (field.dataset.field === 'product_ids') {
				data.product_ids = field.tagName === 'SELECT' ? Array.from(field.selectedOptions).map((option) => Number(option.value)).filter(Boolean) : field.value.split(',').map(Number).filter(Boolean);
			} else {
				data[field.dataset.field] = field.type === 'number' ? Number(field.value) : field.value;
			}
		});
		if (data.type === 'links') data.links = Array.from(block.querySelectorAll('.mrn-mm-link-row')).map((row) => ({ label: row.querySelector('[data-link-field="label"]').value, url: row.querySelector('[data-link-field="url"]').value, target: row.querySelector('[data-link-field="target"]').value }));
		if (data.type === 'categories') data.category_ids = Array.from(block.querySelectorAll('[data-category-id]:checked'))
			.sort((first, second) => Number(first.dataset.categoryOrder) - Number(second.dataset.categoryOrder))
			.map((input) => Number(input.dataset.categoryId));
		const position = rectFor(block);
		return { ...data, grid_row: position.row, grid_column: position.column, column_span: position.span };
	}

	function serialize() {
		dataInput.value = JSON.stringify({
			width: builder.querySelector('[name="mrn_mega_menu_width"]:checked')?.value || 'content',
			grid: { ...dimensions(), blocks: blocks().map(blockData) }
		});
	}

	function moveBlock(block, row, column) {
		const from = rectFor(block);
		const target = blocks().find((candidate) => candidate !== block && overlaps({ row, column, span: from.span }, rectFor(candidate)));
		if (target) setPosition(target, from.row, from.column, Math.min(rectFor(target).span, dimensions().columns - from.column + 1));
		setPosition(block, row, column, from.span);
		selectedBlock = block;
		render();
	}

	function initializeWooSearch() {
		if (window.jQuery) window.jQuery(document.body).trigger('wc-enhanced-select-init');
	}

	function initializeLinkSortables() {
		if (!window.jQuery?.fn?.sortable) return;
		window.jQuery(builder).find('.mrn-mm-link-rows').each(function () {
			const list = window.jQuery(this);
			if (list.hasClass('ui-sortable')) return list.sortable('refresh');
			list.sortable({ items: '> .mrn-mm-link-row', handle: '.mrn-mm-link-drag', axis: 'y', stop: serialize });
		});
	}

	function populateMenuRoots(body) {
		const menu = body.querySelector('.mrn-mm-menu-select');
		const root = body.querySelector('.mrn-mm-menu-root');
		if (!menu || !root) return;
		const previous = root.value;
		root.innerHTML = `<option value="0">${MRNMegaMenuAdmin.entireMenu}</option>`;
		(MRNMegaMenuAdmin.menuItems?.[menu.value] || []).forEach((item) => root.add(new Option(item.title, String(item.id))));
		root.value = Array.from(root.options).some((option) => option.value === previous) ? previous : '0';
		body.querySelector('.mrn-mm-branch-mode').hidden = root.value === '0';
	}

	function openLinkPicker(row, returnFocus) {
		if (!window.wpLink || !linkPickerTarget) return false;
		activeLinkRow = row;
		pickerReturnFocus = returnFocus;
		linkPickerTarget.value = '';
		window.wpLink.open(linkPickerTarget.id, row?.querySelector('[data-link-field="url"]')?.value || '', row?.querySelector('[data-link-field="label"]')?.value || '');
		const newTab = document.querySelector('#wp-link-target');
		if (newTab) newTab.checked = row?.querySelector('[data-link-field="target"]')?.value === '_blank';
		return true;
	}

	function applyPickedLink() {
		const parsed = document.createElement('div');
		parsed.innerHTML = linkPickerTarget.value;
		const anchor = parsed.querySelector('a[href]');
		if (!anchor) return;
		let row = activeLinkRow;
		if (!row) {
			const rows = pickerReturnFocus?.closest('.mrn-mm-field')?.querySelector('.mrn-mm-link-rows');
			const template = builder.querySelector('template[data-link-template]');
			if (!rows || !template) return;
			rows.appendChild(template.content.cloneNode(true));
			row = rows.lastElementChild;
			initializeLinkSortables();
		}
		row.querySelector('[data-link-field="label"]').value = anchor.textContent.trim() || anchor.href;
		row.querySelector('[data-link-field="url"]').value = anchor.getAttribute('href') || '';
		row.querySelector('[data-link-field="target"]').value = anchor.getAttribute('target') === '_blank' ? '_blank' : '';
		serialize();
	}

	const initial = blocks().map((block) => ({ block, ...rectFor(block) }));
	const initialSize = dimensions();

	blocksLayer.addEventListener('dragstart', (event) => {
		const handle = event.target.closest('.mrn-mm-block__drag');
		if (!handle) return event.preventDefault();
		draggedBlock = handle.closest('.mrn-mm-block');
		draggedBlock.classList.add('is-dragging');
		event.dataTransfer.effectAllowed = 'move';
		event.dataTransfer.setData('text/plain', draggedBlock.dataset.blockIndex || 'block');
	});
	blocksLayer.addEventListener('dragend', () => { draggedBlock?.classList.remove('is-dragging'); draggedBlock = null; });
	canvas.addEventListener('dragover', (event) => { if (draggedBlock) { event.preventDefault(); event.dataTransfer.dropEffect = 'move'; } });
	canvas.addEventListener('drop', (event) => {
		if (!draggedBlock) return;
		event.preventDefault();
		const bounds = canvas.getBoundingClientRect();
		const size = dimensions();
		const column = clamp(Math.floor((event.clientX - bounds.left) / (bounds.width / size.columns)) + 1, 1, size.columns);
		const row = clamp(Math.floor((event.clientY - bounds.top) / (bounds.height / size.rows)) + 1, 1, size.rows);
		moveBlock(draggedBlock, row, column);
	});

	builder.addEventListener('click', (event) => {
		const cell = event.target.closest('.mrn-mm-grid-cell');
		if (cell && selectedBlock) return moveBlock(selectedBlock, Number(cell.dataset.row), Number(cell.dataset.column));
		const block = event.target.closest('.mrn-mm-block');
		if (block && !event.target.closest('input,select,textarea,a,.mrn-mm-remove,.mrn-mm-pick-link,.mrn-mm-add-link,.mrn-mm-remove-link,.mrn-mm-choose-image,.mrn-mm-remove-image')) { selectedBlock = block; render(); }
		const toggle = event.target.closest('.mrn-mm-block__toggle');
		if (toggle) {
			const body = toggle.closest('.mrn-mm-block').querySelector('.mrn-mm-block__body');
			const opening = body.hidden;
			body.hidden = !opening;
			toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
			toggle.closest('.mrn-mm-block').classList.toggle('is-collapsed', !opening);
		}
		const addToggle = event.target.closest('.mrn-mm-add__toggle');
		if (addToggle) { const menu = addToggle.nextElementSibling; menu.hidden = !menu.hidden; addToggle.setAttribute('aria-expanded', menu.hidden ? 'false' : 'true'); }
		const add = event.target.closest('[data-add-block]');
		if (add) {
			const template = builder.querySelector(`template[data-block-template="${add.dataset.addBlock}"]`);
			const open = firstOpen();
			if (template && open) {
				blocksLayer.appendChild(template.content.cloneNode(true));
				const added = blocksLayer.lastElementChild;
				added.dataset.blockIndex = builder.dataset.nextBlock++;
				setPosition(added, open.row, open.column, 1);
				selectedBlock = added;
				add.closest('.mrn-mm-add__menu').hidden = true;
				initializeWooSearch(); initializeLinkSortables(); render();
			}
		}
		const remove = event.target.closest('.mrn-mm-remove');
		if (remove && window.confirm(MRNMegaMenuAdmin.confirmRemove)) { const target = remove.closest('.mrn-mm-block'); if (selectedBlock === target) selectedBlock = null; target.remove(); render(); }
		const addLink = event.target.closest('.mrn-mm-add-link');
		if (addLink && !openLinkPicker(null, addLink)) { const rows = addLink.closest('.mrn-mm-field').querySelector('.mrn-mm-link-rows'); rows.appendChild(builder.querySelector('template[data-link-template]').content.cloneNode(true)); initializeLinkSortables(); }
		const pickLink = event.target.closest('.mrn-mm-pick-link');
		if (pickLink) openLinkPicker(pickLink.closest('.mrn-mm-link-row'), pickLink);
		const removeLink = event.target.closest('.mrn-mm-remove-link');
		if (removeLink) { removeLink.closest('.mrn-mm-link-row').remove(); serialize(); }
		const chooseImage = event.target.closest('.mrn-mm-choose-image');
		if (chooseImage && window.wp?.media) {
			const frame = wp.media({ title: MRNMegaMenuAdmin.mediaTitle, button: { text: MRNMegaMenuAdmin.mediaButton }, multiple: false, library: { type: 'image' } });
			frame.on('select', () => { const image = frame.state().get('selection').first().toJSON(); const wrap = chooseImage.closest('.mrn-mm-promo-image'); wrap.querySelector('[data-field="image_id"]').value = image.id; wrap.querySelector('.mrn-mm-promo-image__preview').innerHTML = `<img src="${image.sizes?.medium?.url || image.url}" alt="">`; wrap.querySelector('.mrn-mm-remove-image').hidden = false; serialize(); }); frame.open();
		}
		const removeImage = event.target.closest('.mrn-mm-remove-image');
		if (removeImage) { const wrap = removeImage.closest('.mrn-mm-promo-image'); wrap.querySelector('[data-field="image_id"]').value = ''; wrap.querySelector('.mrn-mm-promo-image__preview').innerHTML = '<span class="dashicons dashicons-format-image"></span>'; removeImage.hidden = true; serialize(); }
	});

	builder.addEventListener('input', (event) => {
		if (event.target.matches('.mrn-mm-grid-columns,.mrn-mm-grid-rows')) return render();
		if (event.target.matches('[data-field="title"]')) event.target.closest('.mrn-mm-block').querySelector('.mrn-mm-block__toggle small').textContent = event.target.value || 'Untitled';
		serialize();
	});
	builder.addEventListener('change', (event) => {
		if (event.target.matches('.mrn-mm-block-span')) { event.target.closest('.mrn-mm-block').dataset.columnSpan = event.target.value; return render(); }
		const body = event.target.closest('.mrn-mm-block__body');
		if (event.target.matches('.mrn-mm-menu-source')) { const selected = event.target.value === 'selected'; body.querySelector('.mrn-mm-selected-menu').hidden = !selected; body.querySelector('.mrn-mm-menu-root-field').hidden = !selected; }
		if (event.target.matches('.mrn-mm-menu-select')) populateMenuRoots(body);
		if (event.target.matches('.mrn-mm-menu-root')) body.querySelector('.mrn-mm-branch-mode').hidden = event.target.value === '0';
		if (event.target.matches('[data-field="source"]')) { const manual = body?.querySelector('.mrn-mm-manual-products'); if (manual) manual.hidden = event.target.value !== 'manual'; }
		serialize();
	});
	builder.addEventListener('keydown', (event) => {
		const handle = event.target.closest('.mrn-mm-block__drag');
		if (!handle || !['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(event.key)) return;
		const block = handle.closest('.mrn-mm-block'); const position = rectFor(block);
		const row = position.row + (event.key === 'ArrowUp' ? -1 : event.key === 'ArrowDown' ? 1 : 0);
		const column = position.column + (event.key === 'ArrowLeft' ? -1 : event.key === 'ArrowRight' ? 1 : 0);
		if (row >= 1 && row <= dimensions().rows && column >= 1 && column <= dimensions().columns) { event.preventDefault(); moveBlock(block, row, column); handle.focus(); }
	});

	builder.querySelector('.mrn-mm-grid-reset').addEventListener('click', () => {
		columnsInput.value = initialSize.columns; rowsInput.value = initialSize.rows;
		initial.forEach((item) => setPosition(item.block, item.row, item.column, item.span));
		selectedBlock = null; render();
	});
	if (window.jQuery) window.jQuery(document).on('wplink-close.mrnMegaMenu', () => { applyPickedLink(); linkPickerTarget.value = ''; activeLinkRow = null; pickerReturnFocus?.focus(); pickerReturnFocus = null; });
	document.getElementById('post')?.addEventListener('submit', serialize);
	initializeWooSearch(); initializeLinkSortables(); render();
})();
