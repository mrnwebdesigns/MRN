(function () {
	'use strict';

	function read(control) {
		const input = control.querySelector('[data-icon-value]');
		if (!input?.value) return { type: '', value: '' };
		try {
			const icon = JSON.parse(input.value);
			return icon && typeof icon === 'object' ? icon : { type: '', value: '' };
		} catch (error) {
			return { type: '', value: '' };
		}
	}

	function write(control, icon) {
		const input = control.querySelector('[data-icon-value]');
		if (!input) return;
		input.value = JSON.stringify({ type: icon?.type || '', value: icon?.value || '' });
		input.dispatchEvent(new Event('input', { bubbles: true }));
		input.dispatchEvent(new Event('change', { bubbles: true }));
		render(control);
	}

	function render(control) {
		const icon = read(control);
		const preview = control.querySelector('[data-icon-preview]');
		const clear = control.querySelector('[data-clear-icon]');
		if (!preview) return;
		preview.replaceChildren();
		if (icon.type === 'media' && icon.value) {
			const image = document.createElement('img');
			image.src = icon.value;
			image.alt = '';
			preview.appendChild(image);
		} else if (icon.value) {
			const glyph = document.createElement('span');
			glyph.className = icon.type === 'dashicons' ? `dashicons ${icon.value}` : icon.value;
			preview.appendChild(glyph);
		}
		if (clear) clear.hidden = !icon.value;
		control.classList.toggle('has-icon', Boolean(icon.value));
	}

	function open(control) {
		if (window.MRNSharedIconChooser?.open) {
			window.MRNSharedIconChooser.open({
				current: read(control),
				previewUrl: read(control).type === 'media' ? read(control).value : '',
				onSelect: (selection) => write(control, selection),
				onClear: () => write(control, { type: '', value: '' })
			});
			return;
		}
		const value = window.prompt('Enter a WordPress Dashicon class, for example dashicons-star-filled.', read(control).value || '');
		if (value !== null) write(control, { type: value ? 'dashicons' : '', value: value.trim() });
	}

	document.addEventListener('click', (event) => {
		const choose = event.target.closest('[data-choose-icon]');
		if (choose) {
			event.preventDefault();
			open(choose.closest('[data-mrn-icon-control]'));
			return;
		}
		const clear = event.target.closest('[data-clear-icon]');
		if (clear) {
			event.preventDefault();
			write(clear.closest('[data-mrn-icon-control]'), { type: '', value: '' });
		}
	});

	function initialize(root = document) {
		root.querySelectorAll('[data-mrn-icon-control]').forEach(render);
	}

	window.MRNMegaMenuIconControls = { initialize, read, write };
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => initialize());
	else initialize();
}());
