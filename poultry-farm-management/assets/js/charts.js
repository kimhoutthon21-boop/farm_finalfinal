// Shared Chart.js helpers. Charts sit on the dark "bento" cards, so every colour is tuned for that surface.
// Palette: validated for colour-blind separation on #17161c, no orange/yellow on purpose.
window.PFM = (function () {
  var SURFACE = '#17161c';
  var INK = '#f4f4f5', INK2 = 'rgba(244,244,245,.72)', MUTED = 'rgba(244,244,245,.5)', GRID = 'rgba(244,244,245,.07)';
  var PALETTE = ['#199e70', '#3987e5', '#d55181', '#9085e9', '#e66767', '#008300'];
  var OTHER = '#6b6a72';
  var tr = function (s) { return window.pfmTranslate ? window.pfmTranslate(s) : s; };
  var fmt = function (v, dec) { return v == null || isNaN(v) ? '—' : Number(v).toLocaleString(undefined, { maximumFractionDigits: dec == null ? 1 : dec }); };

  // Colour follows the house, not its position: "House 3" is always slot 3, even if House 1 has no data.
  function colorFor(label, i) {
    var m = /(\d+)/.exec(label || '');
    return PALETTE[((m ? +m[1] - 1 : i) % PALETTE.length + PALETTE.length) % PALETTE.length];
  }
  function alpha(hex, a) {
    var n = parseInt(hex.slice(1), 16);
    return 'rgba(' + (n >> 16 & 255) + ',' + (n >> 8 & 255) + ',' + (n & 255) + ',' + a + ')';
  }
  function base() {
    if (!window.Chart || base.done) return; base.done = true;
    var d = Chart.defaults;
    d.font.family = '"Segoe UI","Khmer UI","Noto Sans Khmer",system-ui,sans-serif'; d.font.size = 12;
    d.color = MUTED; d.borderColor = GRID; d.maintainAspectRatio = false;
    d.animation.duration = 600; d.animation.easing = 'easeOutCubic';
    d.plugins.legend.labels.color = INK2; d.plugins.legend.labels.usePointStyle = true; d.plugins.legend.labels.pointStyle = 'circle';
    d.plugins.legend.labels.boxWidth = 8; d.plugins.legend.labels.boxHeight = 8; d.plugins.legend.labels.padding = 16;
    Object.assign(d.plugins.tooltip, {
      backgroundColor: '#24232b', titleColor: INK, bodyColor: INK2, borderColor: 'rgba(255,255,255,.1)', borderWidth: 1,
      padding: 10, cornerRadius: 10, boxPadding: 5, usePointStyle: true, titleFont: { weight: '600' }, caretSize: 5
    });
  }
  function legend(n) { return { display: n > 1, position: 'bottom', align: 'start' }; }
  function axes(opts) {
    var narrow = window.innerWidth < 560;
    return {
      x: { stacked: !!opts.stacked, grid: { display: false }, border: { color: 'rgba(244,244,245,.16)' },
           ticks: { color: MUTED, autoSkip: true, maxTicksLimit: narrow ? 5 : 8, maxRotation: 0 } },
      y: { stacked: !!opts.stacked, beginAtZero: true, grid: { color: GRID }, border: { display: false },
           ticks: { color: MUTED, precision: 0, maxTicksLimit: 5, padding: 8, callback: function (v) { return fmt(v, 0); } } }
    };
  }
  // Soft vertical wash under a line, fading out toward the baseline.
  function wash(color) {
    return function (ctx) {
      var a = ctx.chart.chartArea; if (!a) return alpha(color, .1);
      var g = ctx.chart.ctx.createLinearGradient(0, a.top, 0, a.bottom);
      g.addColorStop(0, alpha(color, .22)); g.addColorStop(1, alpha(color, 0)); return g;
    };
  }

  function line(id, labels, sets, opts) {
    var el = document.getElementById(id); if (!el || !window.Chart) return null; base(); opts = opts || {};
    var single = sets.length === 1;
    return new Chart(el, {
      type: 'line',
      data: { labels: labels.map(tr), datasets: sets.map(function (s, i) {
        var c = s.color || (single ? PALETTE[0] : colorFor(s.label, i));
        var last = s.data.length - 1;
        return { label: tr(s.label), data: s.data, borderColor: c, backgroundColor: c, borderWidth: 2,
          borderCapStyle: 'round', borderJoinStyle: 'round', cubicInterpolationMode: 'monotone', spanGaps: true,
          fill: single ? { target: 'origin', above: wash(c) } : false,
          pointRadius: function (ctx) { return ctx.dataIndex === last ? 4 : 0; },
          pointHoverRadius: 5, pointBorderColor: SURFACE, pointBorderWidth: 2, pointHoverBorderWidth: 2, pointHitRadius: 12 };
      }) },
      options: { interaction: { mode: 'index', intersect: false },
        plugins: { legend: legend(sets.length), tooltip: { callbacks: { label: function (c) { return ' ' + c.dataset.label + ': ' + fmt(c.parsed.y, opts.dec); } } } },
        scales: axes(opts) }
    });
  }

  function bar(id, labels, sets, opts) {
    var el = document.getElementById(id); if (!el || !window.Chart) return null; base(); opts = opts || {};
    var stacked = opts.stacked !== false, top = sets.length - 1;
    return new Chart(el, {
      type: 'bar',
      data: { labels: labels.map(tr), datasets: sets.map(function (s, i) {
        var c = s.color || colorFor(s.label, i);
        return { label: tr(s.label), data: s.data, backgroundColor: c, hoverBackgroundColor: alpha(c, .85),
          // 2px surface gap between stacked segments; only the top segment gets rounded corners.
          borderColor: SURFACE, borderWidth: stacked ? { top: 2 } : 0, borderSkipped: 'start',
          borderRadius: !stacked || i === top ? { topLeft: 4, topRight: 4 } : 0,
          maxBarThickness: 24, categoryPercentage: .7, barPercentage: .8 };
      }) },
      options: { interaction: { mode: 'index', intersect: false },
        plugins: { legend: legend(sets.length), tooltip: { itemSort: function (a, b) { return b.datasetIndex - a.datasetIndex; },
          callbacks: {
            label: function (c) { return c.parsed.y == null ? null : ' ' + c.dataset.label + ': ' + fmt(c.parsed.y, opts.dec); },
            footer: stacked && sets.length > 1 ? function (items) { var t = 0; items.forEach(function (x) { t += x.parsed.y || 0; }); return tr('Total') + ': ' + fmt(t, opts.dec); } : undefined
          } } },
        scales: axes({ stacked: stacked }) }
    });
  }

  // Donut with the total in the middle. Small slices beyond `max` fold into "Other" so colours stay distinct.
  // Table rows in the same card tagged data-slice="i" get a matching colour dot (.slice-dot).
  function donut(id, items, opts) {
    var el = document.getElementById(id); if (!el || !window.Chart) return null; base(); opts = opts || {};
    var max = opts.max || 5, list = items.slice(0, max), rest = items.slice(max);
    if (rest.length) list.push({ name: 'Other', total: rest.reduce(function (s, x) { return s + (+x.total); }, 0), other: true });
    var colors = list.map(function (x, i) { return x.other ? OTHER : PALETTE[i]; });
    var total = list.reduce(function (s, x) { return s + (+x.total); }, 0);
    var money = function (v) { return (opts.symbol || '') + fmt(v, 0); };
    var card = el.closest('.card');
    if (card) card.querySelectorAll('[data-slice]').forEach(function (row) {
      var i = +row.getAttribute('data-slice'), dot = row.querySelector('.slice-dot');
      if (dot) dot.style.background = i < max ? PALETTE[i] : OTHER;
    });
    var center = { id: 'centerTotal', afterDraw: function (ch) {
      var m = ch.getDatasetMeta(0).data[0]; if (!m) return; var x = m.x, y = m.y, c = ch.ctx;
      var big = Math.max(14, Math.min(22, m.innerRadius / 3.6));
      c.save(); c.textAlign = 'center'; c.textBaseline = 'middle';
      c.fillStyle = MUTED; c.font = '500 12px ' + Chart.defaults.font.family; c.fillText(tr(opts.centerLabel || 'Total'), x, y - big * .75);
      c.fillStyle = INK; c.font = '700 ' + big + 'px ' + Chart.defaults.font.family; c.fillText(money(total), x, y + big * .45);
      c.restore();
    } };
    return new Chart(el, {
      type: 'doughnut', plugins: [center],
      data: { labels: list.map(function (x) { return tr(x.name); }), datasets: [{ data: list.map(function (x) { return +x.total; }),
        backgroundColor: colors, hoverBackgroundColor: colors, borderColor: SURFACE, borderWidth: list.length > 1 ? 2 : 0, hoverOffset: 6, borderRadius: list.length > 1 ? 4 : 0 }] },
      options: { cutout: '72%', layout: { padding: 8 },
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) {
          return ' ' + money(c.parsed) + ' · ' + (total ? (c.parsed / total * 100).toFixed(1) : 0) + '%'; } } } } }
    });
  }

  // Line chart on a numeric x axis (e.g. cycle day); data points are {x, y}.
  function xyLine(id, sets, opts) {
    var el = document.getElementById(id); if (!el || !window.Chart) return null; base(); opts = opts || {};
    var sc = axes(opts);
    sc.x = Object.assign(sc.x, { type: 'linear', min: 0, title: { display: true, text: tr(opts.xTitle || ''), color: MUTED } });
    return new Chart(el, {
      type: 'line',
      data: { datasets: sets.map(function (s, i) {
        var c = colorFor(s.label, i), last = s.data.length - 1;
        return { label: tr(s.label), data: s.data, borderColor: c, backgroundColor: c, borderWidth: 2, cubicInterpolationMode: 'monotone',
          pointRadius: function (ctx) { return ctx.dataIndex === last ? 4 : 0; }, pointHoverRadius: 5,
          pointBorderColor: SURFACE, pointBorderWidth: 2, pointHitRadius: 12 };
      }) },
      options: { parsing: false, interaction: { mode: 'nearest', axis: 'x', intersect: false },
        plugins: { legend: legend(sets.length), tooltip: { callbacks: {
          title: function (items) { return items.length ? tr(opts.xTitle || '') + ' ' + items[0].parsed.x : ''; },
          label: function (c) { return ' ' + c.dataset.label + ': ' + fmt(c.parsed.y, 0); } } } },
        scales: sc }
    });
  }

  return { palette: PALETTE, colorFor: colorFor, base: base, line: line, bar: bar, donut: donut, xyLine: xyLine };
})();
