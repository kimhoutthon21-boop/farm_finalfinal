(function () {
  var form = document.getElementById('report-form'); if (!form) return;
  var $ = function (id) { return document.getElementById(id); };
  var house = $('f-house'), date = $('f-date'), deaths = $('f-deaths'), feed = $('f-feed'), cause = $('f-cause');
  var A = window.__app, checkTimer = null;
  function opt() { return house.options[house.selectedIndex]; }
  function addDays(s, n) { var d = new Date(s + 'T00:00:00'); d.setDate(d.getDate() + n); return d.toISOString().slice(0, 10); }
  function fmt(n) { return Number(n).toLocaleString('en-US'); }

  function syncHouse() {
    var o = opt(); $('f-cycle-id').value = o.dataset.cycleId; $('f-cycle-name').value = o.dataset.cycle;
    var min = addDays(form.dataset.today, -parseInt(form.dataset.backdays, 10)); if (o.dataset.start > min) min = o.dataset.start; date.min = min;
    checkDup();
  }
  function syncCause() {
    var n = parseInt(deaths.value || '0', 10), show = n > 0; $('cause-wrap').hidden = !show;
    var needs = show && cause.options[cause.selectedIndex] && cause.options[cause.selectedIndex].dataset.detail === '1'; $('detail-wrap').hidden = !needs;
  }
  function checkDup() {
    clearTimeout(checkTimer); checkTimer = setTimeout(function () {
      if (!date.value) return;
      fetch(A.base + '/api/report_exists.php?cycle_id=' + opt().dataset.cycleId + '&date=' + date.value).then(function (r) { return r.json(); }).then(function (d) {
        var w = $('dup-warning'); w.hidden = !d.exists;
        if (d.exists) w.querySelector('span').innerHTML = 'A report for this house and date already exists (' + d.status + '). <a href="' + d.url + '">Open it</a>. You cannot submit another one; an administrator can correct it.';
      }).catch(function () {});
    }, 250);
  }
  house.addEventListener('change', syncHouse); date.addEventListener('change', checkDup); deaths.addEventListener('input', syncCause); cause.addEventListener('change', syncCause);
  form.addEventListener('click', function (e) {
    var b = e.target.closest('[data-step]'); if (!b) return;
    var t = $(b.dataset.target), v = parseFloat(t.value || '0') + parseFloat(b.dataset.step); if (v < 0) v = 0; t.value = Math.round(v * 100) / 100; t.dispatchEvent(new Event('input'));
  });

  function problems() {
    var p = [], o = opt(), n = parseInt(deaths.value, 10), f = parseFloat(String(feed.value).replace(',', '.'));
    if (!date.value) p.push('Choose the report date.'); else if (date.min && date.value < date.min) p.push('That date is outside the allowed range.'); else if (date.value > form.dataset.today) p.push('The date cannot be in the future.');
    if (feed.value === '' || isNaN(f) || f < 0) p.push('Enter the feed consumed in bags (0 or more).');
    if (deaths.value === '' || isNaN(n) || n < 0) p.push('Enter the number of dead chickens (0 or more).');
    else if (n > parseInt(o.dataset.living, 10)) p.push('Deaths are more than the ' + fmt(o.dataset.living) + ' chickens living.');
    if (n > 0) { if (!cause.value) p.push('Choose a cause of death.'); else if (cause.options[cause.selectedIndex].dataset.detail === '1' && $('f-detail').value.trim().length < 3) p.push('Describe the cause of death.'); }
    var tasks = form.querySelectorAll('input[name="tasks[]"]:checked').length;
    if (!tasks && !$('f-desc').value.trim()) p.push('Select at least one task or describe the work done.');
    return p;
  }
  function row(k, v) { var dt = document.createElement('dt'), dd = document.createElement('dd'); dt.textContent = k; dd.textContent = v; $('review-list').appendChild(dt); $('review-list').appendChild(dd); }
  $('btn-review').addEventListener('click', function () {
    var p = problems(); if (p.length) { p.forEach(function (m) { toast(m, 'error'); }); return; }
    var o = opt(), n = parseInt(deaths.value, 10), wk = $('f-worker'), wname = wk.tagName === 'SELECT' ? wk.options[wk.selectedIndex].text : wk.value;
    var tasks = Array.prototype.map.call(form.querySelectorAll('input[name="tasks[]"]:checked'), function (c) { return c.dataset.label; });
    $('review-list').innerHTML = '';
    row('Date', new Date(date.value + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }));
    row('House', o.text); row('Cycle', o.dataset.cycle); row('Worker', wname);
    row('Feed consumed', parseFloat(feed.value) + ' bags'); row('Deaths', String(n));
    row('Cause of death', n > 0 ? cause.options[cause.selectedIndex].text + ($('f-detail').value.trim() ? ' — ' + $('f-detail').value.trim() : '') : '—');
    row('Living after this report', fmt(parseInt(o.dataset.living, 10) - n));
    row('Work performed', (tasks.join(', ') + ($('f-desc').value.trim() ? (tasks.length ? '. ' : '') + $('f-desc').value.trim() : '')) || '—');
    row('Notes', $('f-notes').value.trim() || '—');
    $('step-form').hidden = true; $('step-review').hidden = false; window.scrollTo({ top: 0, behavior: 'smooth' });
  });
  $('btn-edit').addEventListener('click', function () { $('step-review').hidden = true; $('step-form').hidden = false; });
  form.addEventListener('submit', function () { var b = $('btn-save'); setTimeout(function () { b.disabled = true; b.classList.add('loading'); }, 0); }); // blocks double taps
  syncHouse(); syncCause();
})();
