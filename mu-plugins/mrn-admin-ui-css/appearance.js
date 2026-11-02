/* User meta is authoritative. No shared cookie or browser-wide theme cache. */
(function () {
 'use strict';
 var form = document.querySelector('.mrn-appearance-form');
 if (!form || !window.mrnAdminAppearance) { return; }
 var select = form.querySelector('select'), submit = form.querySelector('button'), status = form.querySelector('[role="status"]'), saved = select.value;
 function save(event) {
  event.preventDefault();
  var requested = select.value, focused = document.activeElement, data = new FormData(form);
  data.set('action', 'mrn_admin_appearance');
  document.documentElement.dataset.mrnAppearance = requested;
  select.disabled = true;
  submit.disabled = true;
  status.textContent = window.mrnAdminAppearance.saving;
  fetch(window.mrnAdminAppearance.url, { method: 'POST', credentials: 'same-origin', body: data })
   .then(function (response) { if (!response.ok) { throw new Error('save'); } return response.json(); })
   .then(function (result) { if (!result.success || result.data.appearance !== requested) { throw new Error('save'); } saved = requested; status.textContent = window.mrnAdminAppearance.saved; })
   .catch(function () { select.value = saved; document.documentElement.dataset.mrnAppearance = saved; status.textContent = window.mrnAdminAppearance.failed; })
   .finally(function () {
    select.disabled = false; submit.disabled = false;
    if ((focused === select || focused === submit) && document.activeElement === document.body && disclosure.classList.contains('mrn-appearance-open')) { focused.focus(); }
   });
 }
 select.addEventListener('change', save);
 form.addEventListener('submit', save);
 // Keep WordPress's menu arrow handling out of native select/button interaction.
 form.addEventListener('keydown', function (event) { if (event.key !== 'Escape' && !event.ctrlKey && !event.metaKey) { event.stopPropagation(); } });
 // WP's toolbar keyboard handler targets links. Enter the native select
 // explicitly, and return focus to its trigger when this control closes.
 var trigger = document.querySelector('#wp-admin-bar-mrn-appearance > a');
 var disclosure = document.querySelector('#wp-admin-bar-mrn-appearance');
 function close() { disclosure.classList.remove('hover', 'mrn-appearance-open'); trigger.setAttribute('aria-expanded', 'false'); }
 function open() { disclosure.classList.add('hover', 'mrn-appearance-open'); trigger.setAttribute('aria-expanded', 'true'); select.focus(); }
 if (trigger) {
  trigger.addEventListener('click', function (event) { event.preventDefault(); event.stopPropagation(); open(); });
  trigger.addEventListener('keydown', function (event) { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); event.stopPropagation(); open(); } });
  document.addEventListener('click', function (event) { if (!disclosure.contains(event.target)) { close(); } });
  disclosure.addEventListener('keydown', function (event) { if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); trigger.focus(); close(); } });
  disclosure.addEventListener('focusout', function (event) { if (event.relatedTarget && !disclosure.contains(event.relatedTarget)) { close(); } });
 }
 // AME submenu separators are decorative, not unnamed interactive links.
 document.querySelectorAll('#adminmenu a.wp-menu-separator').forEach(function (link) {
  if (link.querySelector('hr.ws-submenu-separator') && !link.textContent.trim()) { link.tabIndex = -1; link.setAttribute('aria-hidden', 'true'); }
 });
 // Preserve configured branding/destinations while naming an image-only link.
 var logo = document.querySelector('#wp-admin-bar-wp-logo > a');
 if (logo && !logo.textContent.trim() && !logo.hasAttribute('aria-label')) {
  var img = logo.querySelector('img');
  if (img && !img.alt && !logo.title) { img.alt = ''; logo.setAttribute('aria-label', window.mrnAdminAppearance.visit.replace('%s', new URL(logo.href).hostname)); }
 }
 // This plugin-provided link participates in WordPress's existing toolbar menu.
 var indexing = document.querySelector('#wpadminbar .wrap-seopress-noindex');
 if (indexing) { indexing.setAttribute('role', 'menuitem'); }
 // Recolor only these two known monochrome shell assets. AME's configured
 // destinations, image shape and application interiors remain unchanged.
 if (window.CSS && CSS.supports('mask-image', 'url("")')) {
  var seoIcon = document.querySelector('#wp-admin-bar-seopress #seopress-ab-icon');
  if (seoIcon) {
   var image = getComputedStyle(seoIcon).backgroundImage;
   if (image !== 'none') { seoIcon.style.setProperty('--mrn-shell-icon', image); seoIcon.classList.add('mrn-shell-mask'); seoIcon.style.setProperty('background-image', 'none', 'important'); seoIcon.style.setProperty('background-color', 'var(--mrn-admin-ink)', 'important'); }
  }
  var relevanssiIcon = document.querySelector('#toplevel_page_relevanssi-relevanssi .wp-menu-image img');
  if (relevanssiIcon && /relevanssi-icon\.svg(?:[?#]|$)/.test(relevanssiIcon.src)) {
   var holder = relevanssiIcon.parentElement;
   holder.style.setProperty('--mrn-shell-icon', 'url("' + relevanssiIcon.src.replace(/["\\\n\r]/g, '') + '")');
   holder.classList.add('mrn-shell-mask');
  }
 }
 // A compact page utility row on the existing, explicitly audited native/MRN
 // screens. The native panels, IDs, events and form controls remain intact.
 if (document.body.classList.contains('mrn-modern-admin')) {
  var heading = document.querySelector('#wpbody-content > .wrap > h1');
  var utilities = document.getElementById('screen-meta-links');
  if (heading && utilities) {
   var header = document.createElement('div'), title = document.createElement('div');
   header.className = 'mrn-page-header'; title.className = 'mrn-page-heading';
   heading.before(header); header.appendChild(title);
   var action = heading.nextElementSibling;
   title.appendChild(heading);
   if (action && action.classList.contains('page-title-action')) { title.appendChild(action); }
   header.appendChild(utilities);
  }
 }
})();
