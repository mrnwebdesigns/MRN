/* User meta is authoritative. No shared cookie or browser-wide theme cache. */
(function () {
 'use strict';
 var labels = window.mrnAdminAppearance;
 if (!labels) { return; }
 var form = document.querySelector('.mrn-appearance-form');
 var account = document.getElementById('wp-admin-bar-my-account');
 var accountTrigger = account && account.querySelector(':scope > a');
 if (form) {
  var choices = Array.from(form.querySelectorAll('button[name="appearance"]'));
  var status = form.querySelector('[role="status"]');
  var saved = document.documentElement.dataset.mrnAppearance, saving = false;
  function selected(value) { choices.forEach(function (button) { button.setAttribute('aria-pressed', String(button.value === value)); }); }
  form.addEventListener('submit', function (event) {
   event.preventDefault();
   var submitter = event.submitter;
   if (saving || !submitter || !choices.includes(submitter)) { return; }
   var requested = submitter.value;
   if (requested === saved) { return; }
   var data = new FormData(form);
   data.set('action', 'mrn_admin_appearance'); data.set('appearance', requested);
   document.documentElement.dataset.mrnAppearance = requested; selected(requested);
   saving = true; choices.forEach(function (button) { button.disabled = true; });
   status.textContent = labels.saving;
   fetch(labels.url, { method: 'POST', credentials: 'same-origin', body: data })
    .then(function (response) { if (!response.ok) { throw new Error('save'); } return response.json(); })
    .then(function (result) { if (!result.success || result.data.appearance !== requested) { throw new Error('save'); } saved = requested; status.textContent = labels.saved; })
    .catch(function () { document.documentElement.dataset.mrnAppearance = saved; selected(saved); status.textContent = labels.failed; })
    .finally(function () {
     saving = false; choices.forEach(function (button) { button.disabled = false; });
     if (document.activeElement === document.body && account && account.classList.contains('hover')) { submitter.focus(); }
    });
  });
  // Native toolbar link navigation must not consume button activation or arrows.
  form.addEventListener('keydown', function (event) {
   event.stopPropagation();
   if (event.key === 'Escape' && accountTrigger) { event.preventDefault(); accountTrigger.focus(); account.classList.remove('hover'); }
  });
  form.addEventListener('click', function (event) { event.stopPropagation(); });
 }
 // Move only the original native toggle controls; screen panels, forms and core
 // screenMeta handlers retain their IDs, location and lifecycle.
 var screen = document.getElementById('wp-admin-bar-mrn-screen');
 var links = document.getElementById('screen-meta-links');
 var slot = document.querySelector('#wp-admin-bar-mrn-screen-controls > .ab-item');
 var meta = document.getElementById('screen-meta');
 if (screen && links && slot && meta && links.querySelector('.show-settings')) {
  var trigger = screen.querySelector(':scope > a');
  var options = document.getElementById('show-settings-link'), settingsLink;
  if (options) { options.textContent = labels.viewOptions; }
  slot.appendChild(links); document.body.classList.add('mrn-screen-tools-ready');
  trigger.setAttribute('aria-controls', 'screen-meta-links');
  function contextualOptions() {
   if (!document.body.classList.contains('mrn-workspace')) { return; }
   var prefs = meta.querySelector('.metabox-prefs');
   if (!prefs || settingsLink) { return; }
   var heading = prefs.querySelector('legend'), hint = prefs.querySelector('p');
   if (heading) { heading.textContent = labels.settingsPanels; }
   if (!hint) { hint = document.createElement('p'); prefs.prepend(hint); }
   hint.textContent = labels.settingsPanelsHint; hint.classList.add('mrn-view-hint');
   settingsLink = document.createElement('button'); settingsLink.type = 'button';
   settingsLink.className = 'button mrn-view-settings'; settingsLink.textContent = labels.openSettings;
   hint.after(settingsLink);
   settingsLink.addEventListener('click', function () {
    var target = document.querySelector('.mrn-mode-settings');
    if (target) { closePanel(); target.click(); target.focus(); }
   });
  }
  function closeScreenMenu(restore) {
   screen.classList.remove('hover', 'mrn-screen-open'); trigger.setAttribute('aria-expanded', 'false');
   if (restore) { trigger.focus(); screen.classList.remove('hover'); }
  }
  function openScreenMenu() {
   contextualOptions();
   screen.classList.add('hover', 'mrn-screen-open'); trigger.setAttribute('aria-expanded', 'true');
   links.querySelector('.show-settings').focus();
  }
  trigger.addEventListener('click', function (event) { event.preventDefault(); event.stopPropagation(); openScreenMenu(); });
  trigger.addEventListener('keydown', function (event) {
   if (['Enter', ' ', 'ArrowDown'].includes(event.key)) { event.preventDefault(); event.stopPropagation(); openScreenMenu(); }
  });
  screen.addEventListener('keydown', function (event) {
   if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); closeScreenMenu(true); }
   else if (event.target.closest('#screen-meta-links')) { event.stopPropagation(); }
  });
  links.addEventListener('click', function (event) { if (event.target.closest('.show-settings')) { contextualOptions(); closeScreenMenu(false); } });
  document.addEventListener('click', function (event) { if (!screen.contains(event.target)) { closeScreenMenu(false); } });
  screen.addEventListener('focusout', function (event) { if (event.relatedTarget && !screen.contains(event.relatedTarget)) { closeScreenMenu(false); } });
  var close = document.createElement('button'); close.type = 'button'; close.className = 'mrn-screen-close'; close.textContent = labels.closeScreen;
  close.setAttribute('aria-label', labels.closeScreenLabel);
  function closePanel() {
   var active = links.querySelector('.screen-meta-active');
   // Core adds its active class only after the opening slide finishes. A fast
   // Close/Escape must also work during that transition. Complete only the
   // native panel's queued animation, then use its original toggle handler.
   if (!active) {
    active = Array.from(links.querySelectorAll('.show-settings')).find(function (button) {
     var panel = document.getElementById(button.getAttribute('aria-controls'));
     return panel && panel.getClientRects().length;
    });
   }
   if (active) {
    var panel = document.getElementById(active.getAttribute('aria-controls'));
    if (panel && window.jQuery) { window.jQuery(panel).finish(); }
    if (panel && panel.getClientRects().length) { active.click(); }
    closeScreenMenu(true);
   }
  }
  close.addEventListener('click', closePanel); meta.prepend(close);
  meta.addEventListener('keydown', function (event) { if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); closePanel(); } });
 } else if (screen) { screen.remove(); }
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
 // Recolor only the explicitly identified monochrome shell assets. AME's configured
 // destinations, image shape and application interiors remain unchanged.
 if (window.CSS && CSS.supports('mask-image', 'url("")')) {
  var seoIcon = document.querySelector('#wp-admin-bar-seopress #seopress-ab-icon');
  if (seoIcon) {
   var image = getComputedStyle(seoIcon).backgroundImage;
   if (image !== 'none') { seoIcon.style.setProperty('--mrn-shell-icon', image); seoIcon.classList.add('mrn-shell-mask'); seoIcon.style.setProperty('background-image', 'none', 'important'); seoIcon.style.setProperty('background-color', 'var(--mrn-admin-ink)', 'important'); }
  }
  // WordPress paints these two installed SVG data backgrounds for its dark
  // sidebar. Reuse their actual alpha shape in the semantic shell palette.
  document.querySelectorAll('#toplevel_page_wpforms-overview .wp-menu-image, #toplevel_page_seopress-option .wp-menu-image').forEach(function (holder) {
   var background = getComputedStyle(holder).backgroundImage;
   if (/^url\(["']?data:image\/svg\+xml[;,]/.test(background)) {
    holder.style.setProperty('--mrn-shell-icon', background);
    holder.classList.add('mrn-shell-mask'); holder.style.setProperty('background-image', 'none', 'important');
   }
  });
  [
   ['#toplevel_page_relevanssi-relevanssi .wp-menu-image img', /relevanssi-icon\.svg(?:[?#]|$)/],
   ['#toplevel_page_options-general-page-updraftplus .wp-menu-image img', /\/updraftplus\/images\/dashicon-white\.png(?:[?#]|$)/]
  ].forEach(function (entry) {
   var icon = document.querySelector(entry[0]);
   if (icon && entry[1].test(icon.src)) {
    var holder = icon.parentElement;
    holder.style.setProperty('--mrn-shell-icon', 'url("' + icon.src.replace(/["\\\n\r]/g, '') + '")');
    holder.classList.add('mrn-shell-mask');
   }
  });
 }
 // Keep the title and native Add action together on the audited native screens.
 if (document.body.classList.contains('mrn-modern-admin')) {
  var heading = document.querySelector('#wpbody-content > .wrap > h1');
  if (heading) {
   var header = document.createElement('div'), title = document.createElement('div');
   header.className = 'mrn-page-header'; title.className = 'mrn-page-heading';
   heading.before(header); header.appendChild(title);
   var action = heading.nextElementSibling;
   title.appendChild(heading);
   if (action && action.classList.contains('page-title-action')) { title.appendChild(action); }
  }
 }
})();
