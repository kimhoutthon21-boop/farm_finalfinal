(function () {
  var d = window.__dash; if (!d || !window.PFM || !d.hasData) return;
  PFM.line('ch-deaths', d.labels, d.deaths, { dec: 0 });
  PFM.bar('ch-feed', d.labels, d.feed);
})();
