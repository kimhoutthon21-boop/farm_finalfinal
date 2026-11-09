// Shared Chart.js helpers (vanilla). Palette is deliberately restrained.
window.PFM = {
  colors: ['#14663b', '#c58b1f', '#1f6f8b', '#8a4f7d', '#b42a2a', '#5b7f2b'],
  base: function () {
    if (window.Chart) {
      Chart.defaults.font.family = '"Segoe UI",system-ui,sans-serif';
      Chart.defaults.color = '#66756b';
      Chart.defaults.maintainAspectRatio = false;
      Chart.defaults.animation.duration = 650;
      Chart.defaults.animation.easing = 'easeOutQuart';
      Chart.defaults.datasets.bar.categoryPercentage = .55;
      Chart.defaults.datasets.bar.barPercentage = .7;
      Chart.defaults.datasets.bar.maxBarThickness = 14;
    }
  },
  line: function (id, labels, sets, opts) {
    var el = document.getElementById(id); if (!el || !window.Chart) return null; PFM.base(); opts = opts || {};
    var isBento = !!el.closest('.chart-bento');
    var palette = isBento ? ['#8fd5a5', '#79b7d5', '#d5b879', '#d58fac', '#ed7777', '#a8cf77'] : PFM.colors;
    var axisColor = isBento ? 'rgba(244,244,245,.58)' : undefined;
    var gridColor = isBento ? 'rgba(244,244,245,.08)' : '#edf1ed';
    var tooltip = {};
    if (opts.wholeNumbers) tooltip.callbacks = { label: function (context) {
      return (context.dataset.label ? context.dataset.label + ': ' : '') + Math.round(context.parsed.y).toLocaleString();
    } };
    return new Chart(el, { type: opts.type || 'line', data: { labels: labels.map(function (label) { return window.pfmTranslate ? window.pfmTranslate(label) : label; }), datasets: sets.map(function (s, i) { var c = palette[i % palette.length];
      return { label: window.pfmTranslate ? window.pfmTranslate(s.label) : s.label, data: s.data, borderColor: c, backgroundColor: opts.type === 'bar' && isBento ? c + 'a8' : opts.type === 'bar' ? c : c + '22', pointBackgroundColor: c, pointBorderColor: c, borderWidth: 2, tension: .3, spanGaps: true, pointRadius: labels.length > 40 ? 0 : 3, pointHoverRadius: 5, borderRadius: 4, fill: false }; }) },
      options: { interaction: { mode: 'index', intersect: false }, plugins: {
          legend: { display: sets.length > 1, position: 'bottom', labels: { color: axisColor, boxWidth: 10, usePointStyle: true } },
          tooltip: Object.assign(tooltip, isBento ? { backgroundColor: '#25232b', titleColor: '#f4f4f5', bodyColor: '#f4f4f5', borderColor: 'rgba(255,255,255,.1)', borderWidth: 1 } : {})
        },
        scales: { x: { stacked: !!opts.stacked, grid: { display: isBento ? undefined : false, color: gridColor }, ticks: { color: axisColor, autoSkip: true, maxTicksLimit: window.innerWidth < 560 ? 5 : 8, maxRotation: 0 } }, y: { stacked: !!opts.stacked, beginAtZero: true, grid: { color: gridColor }, ticks: { color: axisColor, precision: 0 } } } } });
  }
};
