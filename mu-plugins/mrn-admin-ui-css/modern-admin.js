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
 toolbar.after(status);
 function update() { status.hidden = !form.querySelector('tbody th.check-column input[type="checkbox"]'); var count = form.querySelectorAll('tbody th.check-column input[type="checkbox"]:checked').length; status.textContent = count ? window.mrnModernAdminLabels.selection.replace('%d',count) : window.mrnModernAdminLabels.hint; }
 form.addEventListener('change', update); update();
})();
