(function () {
  var f = window.__fin; if (!f || !window.PFM) return;
  if (f.exp && f.exp.length) PFM.donut('ch-exp', f.exp, { symbol: f.symbol, centerLabel: 'Total expenses' });
  if (f.rev && f.rev.length) PFM.donut('ch-rev', f.rev, { symbol: f.symbol, centerLabel: 'Total revenue' });
})();
