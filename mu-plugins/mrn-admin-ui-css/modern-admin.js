/* Original native list checkboxes remain the source of truth. No stored selection. */
(function() {
 'use strict';
 // AME emits decorative submenu rules as otherwise unnamed tab stops.
 document.querySelectorAll('#adminmenu a.wp-menu-separator').forEach(function(link) {
  if (link.querySelector('hr.ws-submenu-separator') && !link.textContent.trim()) { link.setAttribute('tabindex','-1'); link.setAttribute('aria-hidden','true'); }
 });
 var form = document.querySelector('body.mrn-admin-list #posts-filter');
 if (!form || !window.mrnModernAdminLabels) { return; }
 var toolbar = form.querySelector('.tablenav.top'); if (!toolbar) { return; }
 var status = document.createElement('p'); status.className = 'mrn-admin-selection'; status.setAttribute('role','status');
 toolbar.appendChild(status);
 function update() { status.hidden = !form.querySelector('tbody th.check-column input[type="checkbox"]'); var count = form.querySelectorAll('tbody th.check-column input[type="checkbox"]:checked').length; status.textContent = count ? window.mrnModernAdminLabels.selection.replace('%d',count) : window.mrnModernAdminLabels.hint; }
 form.addEventListener('change', update); update();
})();

/* Shared Actions disclosure for supported native/MRN and Media list tables.
   Keep the original row-actions node, links, handlers and form ancestry. Native
   popovers render above overflow-clipped tables without portalling any controls. */
(function() {
 'use strict';
 if ((!document.body.classList.contains('mrn-modern-admin') && !document.body.matches('.mrn-media-workspace.upload-php')) || document.body.classList.contains('mrn-admin-dashboard') || !window.mrnModernAdminLabels || !('showPopover' in HTMLElement.prototype)) { return; }
 var labels = window.mrnModernAdminLabels, serial = 0, active = null, quickEdit = null, placement = null;
 var selector = '.row-actions', buttons = new WeakMap();
 function close() { if (active && active.isConnected && active.matches(':popover-open')) { active.hidePopover(); } active = null; }
 function position(actions, button) {
  if (!actions.matches(':popover-open')) { return; }
  var anchor = button.getBoundingClientRect(), bar = document.getElementById('wpadminbar');
  var edge = 12, ceiling = Math.max(edge, bar ? bar.getBoundingClientRect().bottom + 8 : edge);
  var width = Math.min(240, window.innerWidth - edge * 2);
  actions.style.width = width + 'px';
  actions.style.maxHeight = Math.max(80, window.innerHeight - ceiling - edge) + 'px';
  var height = actions.getBoundingClientRect().height;
  var below = window.innerHeight - anchor.bottom - edge - 6, above = anchor.top - ceiling - 6;
  var up = below < Math.min(height, 240) && above > below;
  actions.style.maxHeight = Math.max(80, up ? above : below) + 'px';
  height = actions.getBoundingClientRect().height;
  actions.style.left = Math.max(edge, Math.min(anchor.left, window.innerWidth - width - edge)) + 'px';
  actions.style.top = Math.max(ceiling, up ? anchor.top - height - 6 : anchor.bottom + 6) + 'px';
  placement = { x: anchor.x, y: anchor.y, width: window.innerWidth, height: window.innerHeight };
 }
 function enhance(actions) {
  if (!actions.isConnected || buttons.has(actions) || !actions.querySelector('a,button')) { return; }
  // Adopt only the existing MRN Media disclosure. Keep the original action
  // nodes, attributes, handlers and row/form ancestry; other details stay native.
  var legacy = document.body.matches('.mrn-media-workspace.upload-php') && actions.querySelector(':scope > .mrn_media_actions > details.mrn-media-actions-menu');
  if (legacy && legacy.parentElement.children.length === 1) {
   var content = legacy.querySelector(':scope > .mrn-media-actions-menu__content');
   if (content) {
    var items = Array.from(content.children).filter(function(item) { return !item.classList.contains('mrn-media-actions-menu__separator'); });
    items.forEach(function(item) {
     ['trash','delete','delete_permanent','delete_permanently'].forEach(function(key) {
      if (item.classList.contains('mrn-media-action-' + key)) { item.classList.add(key); }
     });
    });
    legacy.parentElement.replaceWith.apply(legacy.parentElement, items);
   }
  }
  if (actions.querySelector('details,[popover]')) { return; }
  var row = actions.closest('tr'); if (!row || row.classList.contains('inline-edit-row')) { return; }
  var title = row.querySelector('.row-title,.plugin-title strong,.username strong,.column-name strong,.column-title strong');
  var name = title ? title.textContent.replace(/\s+/g, ' ').trim() : '';
  var button = document.createElement('button'); button.type = 'button';
  button.className = 'button mrn-row-actions-toggle'; button.textContent = labels.actions;
  button.setAttribute('aria-label', name ? labels.actionsFor.replace('%s', name) : labels.actions);
  if (!actions.id) { var id; do { id = 'mrn-row-actions-' + (++serial); } while (document.getElementById(id)); actions.id = id; }
  button.setAttribute('popovertarget', actions.id); button.setAttribute('aria-expanded', 'false');
  // Only core's pipe separators are discarded; action markup is never rebuilt.
  [actions].concat(Array.from(actions.children)).forEach(function(container) {
   Array.from(container.childNodes).forEach(function(node) {
    if (node.nodeType === 3 && /^[\s|]+$/.test(node.nodeValue) && node.nodeValue.indexOf('|') !== -1) { node.remove(); }
   });
  });
  var destructive = Array.from(actions.children).filter(function(item) { return item.matches('.trash,.delete,.delete_permanent,.delete_permanently'); });
  destructive.forEach(function(item, index) { if (!index) { item.classList.add('mrn-row-action-divider'); } actions.appendChild(item); });
  actions.setAttribute('popover', 'auto'); actions.classList.add('mrn-row-action-popover');
  actions.before(button); buttons.set(actions, button);
  actions.addEventListener('beforetoggle', function(event) {
   button.setAttribute('aria-expanded', String(event.newState === 'open'));
   if (event.newState === 'open') {
    active = actions;
   } else if (active === actions) { active = null; }
  });
  button.addEventListener('click', function(event) {
   event.preventDefault();
   if (actions.matches(':popover-open')) { actions.hidePopover(); }
   else { actions.showPopover({ source: button }); position(actions, button); }
  });
  button.addEventListener('keydown', function(event) {
   if (event.key !== 'ArrowDown') { return; }
   event.preventDefault();
   if (!actions.matches(':popover-open')) { actions.showPopover({ source: button }); position(actions, button); }
   actions.querySelector('a,button').focus();
  });
 }
 function scan(root) {
  if (root.nodeType !== 1) { return; }
  if (root.matches(selector)) { enhance(root); }
  root.querySelectorAll(selector).forEach(enhance);
 }
 document.querySelectorAll('.wp-list-table > tbody').forEach(function(body) {
  scan(body);
  // Quick Edit and native AJAX row replacement: inspect added subtrees only.
  new MutationObserver(function(records) {
   records.forEach(function(record) { record.addedNodes.forEach(scan); });
   // Core returns focus to its now-collapsed Quick Edit link. Return it to
   // the visible invoker after native cancel/save has restored the row.
   if (quickEdit && !document.getElementById(quickEdit.editId)) {
    var row = document.getElementById(quickEdit.rowId);
    var button = row && row.querySelector('.mrn-row-actions-toggle');
    if (button && row.getClientRects().length) { quickEdit = null; button.focus(); }
   }
  }).observe(body, { childList: true, subtree: true });
 });
 document.addEventListener('keydown', function(event) {
  if (event.key === 'Escape' && active) {
   var button = buttons.get(active); event.preventDefault(); close(); if (button && button.isConnected) { button.focus(); }
  }
 });
 document.addEventListener('focusin', function(event) {
  if (active && !active.contains(event.target) && buttons.get(active) !== event.target) { close(); }
 });
 document.addEventListener('click', function(event) {
  // Do not intercept native handlers (including Quick Edit and confirmations).
  var inline = event.target.closest('.editinline'), row = inline && inline.closest('tr');
  if (row && row.querySelector('.mrn-row-actions-toggle')) {
   quickEdit = { rowId: row.id, editId: 'edit-' + row.id.replace(/^(post|tag)-/, '') };
  }
  if (active && active.contains(event.target) && event.target.closest('a,button')) { close(); }
 });
 document.addEventListener('scroll', function(event) {
  if (active && placement && event.target !== active && !active.contains(event.target)) {
   var anchor = buttons.get(active).getBoundingClientRect();
   if (Math.abs(anchor.x - placement.x) > 0.5 || Math.abs(anchor.y - placement.y) > 0.5) { close(); }
  }
 }, { capture: true, passive: true });
 window.addEventListener('resize', function() {
  if (active && placement && (placement.width !== window.innerWidth || placement.height !== window.innerHeight)) { close(); }
 }, { passive: true });
})();
