(function () {
	'use strict';

	const items = Array.from(document.querySelectorAll('.mrn-has-mega-menu'));
	const closeTimers = new WeakMap();
	const triggerAttributes = new WeakMap();
	const triggerPointerTypes = new WeakMap();
	const nativeMobileTriggers = new WeakSet();
	let touchArmedItem = null;
	if (!items.length) return;

	function cancelScheduledClose(item) {
		const timer = closeTimers.get(item);
		if (timer) window.clearTimeout(timer);
		closeTimers.delete(item);
	}

	function scheduleClose(item) {
		cancelScheduledClose(item);
		closeTimers.set(item, window.setTimeout(() => {
			closeTimers.delete(item);
			if (!item.matches(':hover') && !item.contains(document.activeElement)) close(item, false);
		}, 140));
	}

	function parts(item) {
		return {
			trigger: item.querySelector(':scope > [data-mrn-mega-menu-trigger]'),
			panel: item.querySelector(':scope > [data-mrn-mega-menu]')
		};
	}

	function usesNativeMobileMenu(item) {
		return Boolean(
			item.closest('[data-mrn-mobile-active="true"]') &&
			item.querySelector(':scope > .sub-menu')
		);
	}

	function parentClickBehavior(trigger) {
		return trigger?.dataset.mrnParentClick === 'link' ? 'link' : 'toggle';
	}

	function usesFinePointer() {
		return window.matchMedia('(hover: hover) and (pointer: fine)').matches;
	}

	function resetTouchState(item) {
		if (!item || touchArmedItem === item) touchArmedItem = null;
	}

	function firstPanelControl(panel) {
		return Array.from(panel.querySelectorAll('a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])'))
			.find((control) => !control.classList.contains('mrn-mega-menu__close') && !control.disabled && !control.hidden) ||
			panel.querySelector('.mrn-mega-menu__close');
	}

	function sizePanel(panel) {
		const surface = panel.querySelector('.mrn-mega-menu__surface');
		if (!surface) return;
		if (window.matchMedia('(max-width: 900px)').matches || panel.closest('[data-mrn-mobile-active="true"]')) {
			panel.style.removeProperty('--mrn-mega-menu-offset-left');
			panel.style.removeProperty('transform');
			surface.style.removeProperty('--mrn-mega-menu-available-height');
			return;
		}
		alignFullPanel(panel);
		const available = Math.max(240, window.innerHeight - panel.getBoundingClientRect().top - 16);
		surface.style.setProperty('--mrn-mega-menu-available-height', `${available}px`);
	}

	function alignFullPanel(panel) {
		if (!panel.classList.contains('mrn-mega-menu--full')) {
			panel.style.removeProperty('--mrn-mega-menu-offset-left');
			panel.style.removeProperty('transform');
			return;
		}

		panel.style.setProperty('--mrn-mega-menu-offset-left', '0px');
		panel.style.setProperty('transform', 'translateX(0px)');

		const offset = -panel.getBoundingClientRect().left;
		panel.style.setProperty('--mrn-mega-menu-offset-left', `${offset}px`);
		panel.style.setProperty('transform', `translateX(${offset}px)`);
	}

	function close(item, returnFocus) {
		cancelScheduledClose(item);
		const { trigger, panel } = parts(item);
		if (!trigger || !panel) return;
		item.classList.remove('is-mega-menu-open');
		if (!usesNativeMobileMenu(item)) trigger.setAttribute('aria-expanded', 'false');
		panel.hidden = true;
		resetTouchState(item);
		if (returnFocus) trigger.focus();
	}

	function closeOthers(except) {
		items.forEach((item) => { if (item !== except) close(item, false); });
	}

	function syncTriggerMode(item) {
		const { trigger } = parts(item);
		if (!trigger) return;

		if (!triggerAttributes.has(trigger)) {
			triggerAttributes.set(trigger, {
				role: trigger.getAttribute('role'),
				ariaExpanded: trigger.getAttribute('aria-expanded'),
				ariaControls: trigger.getAttribute('aria-controls'),
				ariaHaspopup: trigger.getAttribute('aria-haspopup')
			});
		}

		if (usesNativeMobileMenu(item)) {
			close(item, false);
			trigger.removeAttribute('role');
			trigger.removeAttribute('aria-expanded');
			trigger.removeAttribute('aria-controls');
			trigger.removeAttribute('aria-haspopup');
			nativeMobileTriggers.add(trigger);
			return;
		}
		if (!nativeMobileTriggers.has(trigger)) return;

		const attributes = triggerAttributes.get(trigger);
		nativeMobileTriggers.delete(trigger);
		[
			['role', attributes.role],
			['aria-expanded', attributes.ariaExpanded],
			['aria-controls', attributes.ariaControls],
			['aria-haspopup', attributes.ariaHaspopup]
		].forEach(([name, value]) => {
			if (value === null) trigger.removeAttribute(name);
			else trigger.setAttribute(name, value);
		});
	}

	function open(item) {
		cancelScheduledClose(item);
		const { trigger, panel } = parts(item);
		if (!trigger || !panel) return;
		closeOthers(item);
		item.classList.add('is-mega-menu-open');
		trigger.setAttribute('aria-expanded', 'true');
		panel.hidden = false;
		sizePanel(panel);
	}

	items.forEach((item) => {
		const { trigger, panel } = parts(item);
		if (!trigger || !panel) return;
		syncTriggerMode(item);
		trigger.addEventListener('pointerdown', (event) => {
			triggerPointerTypes.set(trigger, event.pointerType || '');
		});

		trigger.addEventListener('click', (event) => {
			if (usesNativeMobileMenu(item)) return;
			if (parentClickBehavior(trigger) === 'link') {
				// Enter produces a synthetic click (detail 0) and follows the native link.
				const pointerType = triggerPointerTypes.get(trigger) || '';
				const coarseActivation = pointerType === 'touch' || (!pointerType && window.matchMedia('(pointer: coarse)').matches);
				if (event.detail === 0 || !coarseActivation) {
					resetTouchState();
					return;
				}
				if (touchArmedItem === item && trigger.getAttribute('aria-expanded') === 'true') {
					resetTouchState();
					return;
				}
				event.preventDefault();
				event.stopPropagation();
				open(item);
				touchArmedItem = item;
				return;
			}
			event.preventDefault();
			event.stopPropagation();
			if (trigger.getAttribute('aria-expanded') === 'true') close(item, false);
			else open(item);
		});

		item.querySelector('.mrn-mega-menu__close')?.addEventListener('click', () => close(item, true));

		item.addEventListener('mouseenter', () => {
			if (!usesNativeMobileMenu(item) && usesFinePointer()) {
				cancelScheduledClose(item);
				open(item);
			}
		});

		item.addEventListener('mouseleave', () => {
			if (usesFinePointer() && !item.contains(document.activeElement)) scheduleClose(item);
		});

		item.addEventListener('focusout', (event) => {
			if (!item.contains(event.relatedTarget) && !item.matches(':hover')) close(item, false);
		});

		item.addEventListener('keydown', (event) => {
			if (usesNativeMobileMenu(item)) return;
			if (' ' === event.key && event.target === trigger) {
				event.preventDefault();
				if (trigger.getAttribute('aria-expanded') === 'true') close(item, false);
				else open(item);
			}
			if (event.key === 'Escape') {
				event.preventDefault();
				close(item, true);
			}
			if (event.key === 'ArrowDown' && event.target === trigger) {
				event.preventDefault();
				open(item);
				firstPanelControl(panel)?.focus();
			}
		});
	});

	document.addEventListener('click', (event) => {
		if (!event.target.closest('.mrn-has-mega-menu')) closeOthers(null);
	});

	window.addEventListener('resize', () => {
		items.forEach((item) => {
			syncTriggerMode(item);
			const { panel } = parts(item);
			if (panel && !panel.hidden) sizePanel(panel);
		});
	});

	document.querySelectorAll('[data-mrn-mobile-navigation]').forEach((navigation) => {
		new MutationObserver(() => {
			items.forEach((item) => {
				if (navigation.contains(item)) syncTriggerMode(item);
			});
		}).observe(navigation, { attributes: true, attributeFilter: ['data-mrn-mobile-active'] });
	});
})();
