(function () {
  var f = window.__fin, el = document.getElementById('ch-exp'); if (!f || !el || !window.Chart) return; PFM.base();
  new Chart(el, { type: 'doughnut', data: { labels: f.exp.map(function (x) { return x.name; }), datasets: [{ data: f.exp.map(function (x) { return +x.total; }), backgroundColor: PFM.colors.concat(['#9aa5a0', '#d0a35a', '#4a8f9c', '#7b5ea7']), borderWidth: 2 }] }, options: { cutout: '62%', plugins: { legend: { position: 'right', labels: { boxWidth: 10, usePointStyle: true } } } } });
})();
