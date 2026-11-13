(function () {
  var m = window.__mort; if (!m || !window.PFM) return;
  if (m.daily.hasData) PFM.bar('ch-daily', m.daily.labels, m.daily.sets, { dec: 0 });
  if (m.cum.length) PFM.xyLine('ch-cum', m.cum, { xTitle: 'Cycle day' });
})();
