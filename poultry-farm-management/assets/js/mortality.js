(function () {
  var m = window.__mort; if (!m || !window.Chart) return;
  PFM.base();
  if (m.daily.hasData) PFM.line('ch-daily', m.daily.labels, m.daily.sets);
  var cum = document.getElementById('ch-cum');
  if (cum && m.cum.length) new Chart(cum, { type: 'line', data: { datasets: m.cum.map(function (s, i) { var color = ['#8fd5a5', '#79b7d5', '#d5b879', '#d58fac', '#ed7777', '#a8cf77'][i % 6]; return { label: s.label, data: s.data, borderColor: color, backgroundColor: color + '22', borderWidth: 2, tension: .3, pointRadius: 2, pointHoverRadius: 5 }; }) },
    options: { parsing: false, interaction: { mode: 'nearest', intersect: false }, plugins: { legend: { position: 'bottom', labels: { color: 'rgba(244,244,245,.72)', boxWidth: 10, usePointStyle: true } }, tooltip: { backgroundColor: '#25232b', titleColor: '#f4f4f5', bodyColor: '#f4f4f5', borderColor: 'rgba(255,255,255,.1)', borderWidth: 1 } }, scales: { x: { type: 'linear', title: { display: true, text: 'Cycle day', color: 'rgba(244,244,245,.6)' }, ticks: { color: 'rgba(244,244,245,.6)', maxTicksLimit: 8 }, grid: { color: 'rgba(244,244,245,.08)' } }, y: { beginAtZero: true, ticks: { color: 'rgba(244,244,245,.6)' }, grid: { color: 'rgba(244,244,245,.08)' } } } } });
})();
