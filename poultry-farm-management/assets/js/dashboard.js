(function () {
  var d = window.__dash; if (!d || !window.PFM) return;
  if (d.hasData) {
    PFM.line('ch-deaths', d.deathLabels, d.deaths);
    PFM.line('ch-feed', d.labels, d.feed, { type: 'bar', stacked: true, wholeNumbers: true });
  }
})();
