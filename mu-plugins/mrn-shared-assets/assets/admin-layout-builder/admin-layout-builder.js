(function (window, $) {
	'use strict';

	var VERSION = '1.0.1';

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

	function cellKey(row, column) {
		return String(row) + ':' + String(column);
	}

	function isPositionOpen(occupied, row, column, columnSpan) {
		var cellColumn;

		for (cellColumn = column; cellColumn < column + columnSpan; cellColumn += 1) {
			if (occupied[cellKey(row, cellColumn)]) {
				return false;
			}
		}

		return true;
	}

	function markPositionOccupied(occupied, row, column, columnSpan) {
		var cellColumn;

		for (cellColumn = column; cellColumn < column + columnSpan; cellColumn += 1) {
			occupied[cellKey(row, cellColumn)] = true;
		}
	}

	function findOpenPosition(occupied, preferredRow, preferredColumn, columnSpan, rows, columns, limits) {
		var row;
		var column;

		preferredRow = clamp(preferredRow, limits.minRows, rows);
		preferredColumn = clamp(preferredColumn, limits.minColumns, Math.max(limits.minColumns, columns - columnSpan + 1));

		for (row = preferredRow; row <= rows; row += 1) {
			if (isPositionOpen(occupied, row, preferredColumn, columnSpan)) {
				return { row: row, column: preferredColumn, rows: rows };
			}
		}

		while (rows < limits.maxRows) {
			rows += 1;
			if (isPositionOpen(occupied, rows, preferredColumn, columnSpan)) {
				return { row: rows, column: preferredColumn, rows: rows };
			}
		}

		for (row = limits.minRows; row <= rows; row += 1) {
			for (column = limits.minColumns; column <= columns - columnSpan + 1; column += 1) {
				if (isPositionOpen(occupied, row, column, columnSpan)) {
					return { row: row, column: column, rows: rows };
				}
			}
		}

		return { row: preferredRow, column: preferredColumn, rows: rows };
	}

	function normalizeGridLayout(layout, defaults, items, options) {
		var limits = Object.assign({ minColumns: 1, maxColumns: 6, minRows: 1, maxRows: 12 }, options || {});
		var normalized = {};
		var defaultItems = defaults && defaults.items ? defaults.items : {};
		var rawItems = layout && layout.items ? layout.items : {};
		var keys = Object.keys(items || {});
		var defaultKeys = Object.keys(defaultItems || {});
		var occupied = {};
		var index;

		normalized.columns = clamp(layout && layout.columns ? layout.columns : defaults.columns, limits.minColumns, limits.maxColumns);
		normalized.rows = clamp(layout && layout.rows ? layout.rows : defaults.rows, limits.minRows, limits.maxRows);
		normalized.items = {};

		for (index = 0; index < defaultKeys.length; index += 1) {
			var defaultItem = defaultItems[defaultKeys[index]] || {};
			if (!rawItems[defaultKeys[index]] && defaultItem.row) {
				normalized.rows = clamp(Math.max(normalized.rows, defaultItem.row), limits.minRows, limits.maxRows);
			}
		}

		for (index = 0; index < keys.length; index += 1) {
			var itemKey = keys[index];
			var itemDefault = defaultItems[itemKey] || {};
			var item = rawItems[itemKey] || itemDefault;
			var columnSpan = clamp(item.columnSpan || itemDefault.columnSpan || 1, limits.minColumns, normalized.columns);
			var columnMax = Math.max(1, normalized.columns - columnSpan + 1);
			var row = clamp(item.row || itemDefault.row || 1, limits.minRows, normalized.rows);
			var column = clamp(item.column || itemDefault.column || 1, limits.minColumns, columnMax);
			var position;

			if (!isPositionOpen(occupied, row, column, columnSpan)) {
				position = findOpenPosition(occupied, row, column, columnSpan, normalized.rows, normalized.columns, limits);
				row = position.row;
				column = position.column;
				normalized.rows = position.rows;
			}

			markPositionOccupied(occupied, row, column, columnSpan);
			normalized.items[itemKey] = { row: row, column: column, columnSpan: columnSpan };
		}

		return normalized;
	}

	function activateTabs(tabs, panels, index, moveFocus) {
		var target = Math.max(0, Math.min(panels.length - 1, Number(index) || 0));

		tabs.forEach(function (tab, tabIndex) {
			var selected = tabIndex === target;
			tab.setAttribute('aria-selected', selected ? 'true' : 'false');
			tab.tabIndex = selected ? 0 : -1;
			if (selected && moveFocus) {
				tab.focus();
			}
		});
		panels.forEach(function (panel, panelIndex) {
			panel.hidden = panelIndex !== target;
		});

		return target;
	}

	function adjacentTabIndex(event, currentIndex, total) {
		if (!total) {
			return currentIndex;
		}
		if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
			return (currentIndex + 1) % total;
		}
		if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
			return (currentIndex - 1 + total) % total;
		}
		if (event.key === 'Home') {
			return 0;
		}
		if (event.key === 'End') {
			return total - 1;
		}
		return currentIndex;
	}

	function refreshSortableLanes(options) {
		var root = options.root;
		var listSelector = options.listSelector;

		if (!$ || !$.fn || !$.fn.sortable || !root || !listSelector) {
			return false;
		}

		$(root).find(listSelector).each(function () {
			var element = this;
			var list = $(element);
			var sortableOptions;

			if (list.hasClass('ui-sortable')) {
				list.sortable('refresh');
				return;
			}

			sortableOptions = {
				items: options.items || '> *',
				handle: options.handle || false,
				cancel: options.cancel || 'input, textarea, select, option, a, button',
				distance: options.distance || 4,
				placeholder: options.placeholder || 'mrn-admin-layout-builder__placeholder',
				forcePlaceholderSize: true,
				tolerance: 'pointer',
				start: function (event, ui) {
					ui.placeholder.height(ui.item.outerHeight());
					root.classList.add('is-sorting');
					if (typeof options.onStart === 'function') {
						options.onStart(element, event, ui);
					}
				},
				over: function (event, ui) {
					if (options.laneSelector) {
						$(element).closest(options.laneSelector).addClass('is-drag-over');
					}
					if (typeof options.onOver === 'function') {
						options.onOver(element, event, ui);
					}
				},
				out: function (event, ui) {
					if (options.laneSelector) {
						$(element).closest(options.laneSelector).removeClass('is-drag-over');
					}
					if (typeof options.onOut === 'function') {
						options.onOut(element, event, ui);
					}
				},
				stop: function (event, ui) {
					root.classList.remove('is-sorting');
					if (options.laneSelector) {
						root.querySelectorAll(options.laneSelector).forEach(function (lane) {
							lane.classList.remove('is-drag-over');
						});
					}
					if (typeof options.onStop === 'function') {
						options.onStop(element, event, ui);
					}
				}
			};

			if (typeof options.connectWith === 'function') {
				sortableOptions.connectWith = options.connectWith(element);
			} else if (options.connectWith) {
				sortableOptions.connectWith = options.connectWith;
			}
			if (options.axis) {
				sortableOptions.axis = options.axis;
			}

			list.sortable(sortableOptions);
		});

		return true;
	}

	window.MRNAdminLayoutBuilder = {
		version: VERSION,
		parseJson: parseJson,
		clone: clone,
		clamp: clamp,
		normalizeGridLayout: normalizeGridLayout,
		activateTabs: activateTabs,
		adjacentTabIndex: adjacentTabIndex,
		refreshSortableLanes: refreshSortableLanes
	};
	document.documentElement.setAttribute('data-mrn-admin-layout-builder', VERSION);
}(window, window.jQuery));
