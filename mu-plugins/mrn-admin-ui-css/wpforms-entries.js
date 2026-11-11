/* Color only the vendor-owned Entries chart. No polling, data fetch or table
   rebuild; Chart.js keeps its datasets, filters and native update lifecycle. */
(function () {
 'use strict';
 if (!window.Chart || !document.body.classList.contains('mrn-admin-wpforms-entries')) { return; }
 var settings = document.getElementById('wpforms-dash-widget-settings-button');
 if (settings && !settings.hasAttribute('aria-label') && window.mrnEntriesAppearance) {
  settings.setAttribute('aria-label', window.mrnEntriesAppearance.chartSettings);
 }
 var id = 'wpforms-entries-overview-canvas';
 Chart.register({
  id: 'mrn-entries-appearance',
  beforeUpdate: function (chart) {
   if (chart.canvas.id !== id) { return; }
   var style = getComputedStyle(document.documentElement);
   var ink = style.getPropertyValue('--mrn-admin-muted').trim();
   var line = style.getPropertyValue('--mrn-admin-line').trim();
   Object.keys(chart.options.scales).forEach(function (key) {
    var scale = chart.options.scales[key];
    scale.ticks.color = ink; scale.grid.color = line; scale.border.color = line;
   });
  }
 });
 function update() { var chart = Chart.getChart(id); if (chart) { chart.update('none'); } }
 new MutationObserver(update).observe(document.documentElement, { attributes: true, attributeFilter: ['data-mrn-appearance'] });
 window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', update);
})();
