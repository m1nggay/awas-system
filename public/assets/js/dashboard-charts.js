/*
 * Shared Chart.js setup for the admin and consumer dashboards (needs Chart.js loaded first).
 * Colors, in fixed order: blue (the app's primary), orange, violet — checked for
 * color-blind separation. Every chart has a hover tooltip; text stays in the ink colors.
 */
window.AwasCharts = (function () {
  const C = { blue: '#0077b6', peak: '#023e8a', orange: '#e07a2f', violet: '#7b5ea7', grey: '#8a9ba4', grid: '#eef4f7', ink: '#1b2b34', muted: '#6b7f89' };
  const SERIES = [C.blue, C.orange, C.violet];

  Chart.defaults.font.family = "'Poppins', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif";
  Chart.defaults.color = C.muted;
  Chart.defaults.maintainAspectRatio = false;
  Object.assign(Chart.defaults.plugins.tooltip, { backgroundColor: '#1b2b34', padding: 10, cornerRadius: 8, boxPadding: 4 });

  const peso = v => '₱' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const pesoShort = v => '₱' + Number(v).toLocaleString('en-PH', { maximumFractionDigits: 0 });
  const m3 = v => Number(v).toLocaleString('en-PH', { maximumFractionDigits: 1 }) + ' m³';
  const axes = tick => ({
    x: { grid: { display: false }, border: { color: C.grid } },
    y: { beginAtZero: true, grid: { color: C.grid }, border: { display: false }, ticks: { callback: tick, maxTicksLimit: 6 } },
  });

  // Writes "Peak" above the highest bar.
  const peakLabel = {
    id: 'peakLabel',
    afterDatasetsDraw(chart) {
      const bar = chart.$peak >= 0 && chart.getDatasetMeta(0).data[chart.$peak];
      if (!bar) return;
      const ctx = chart.ctx;
      ctx.save();
      ctx.fillStyle = C.ink;
      ctx.font = '600 11px ' + Chart.defaults.font.family;
      ctx.textAlign = 'center';
      ctx.fillText('Peak', bar.x, bar.y - 6);
      ctx.restore();
    },
  };

  /**
   * Monthly usage bars; the peak month is darker and labelled "Peak".
   * opts.average draws a dashed average line; opts.extra(i) adds tooltip lines.
   */
  function usage(canvas, labels, values, opts = {}) {
    const max = Math.max(0, ...values);
    const peak = max > 0 ? values.indexOf(max) : -1;
    const datasets = [{
      type: 'bar', label: 'Consumption', data: values, borderRadius: 4, maxBarThickness: 36,
      backgroundColor: values.map((v, i) => (i === peak ? C.peak : C.blue)),
    }];
    if (opts.average != null) {
      datasets.push({ type: 'line', label: 'Your average', data: values.map(() => opts.average), borderColor: C.grey,
        borderDash: [6, 4], borderWidth: 2, pointRadius: 0, pointHoverRadius: 0 });
    }
    const chart = new Chart(canvas, {
      data: { labels, datasets },
      plugins: [peakLabel],
      options: {
        layout: { padding: { top: 18 } },
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: opts.average != null, position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
          tooltip: {
            callbacks: {
              label: item => item.datasetIndex === 0 ? ' ' + m3(item.raw) + (item.dataIndex === peak ? '  (peak)' : '') : ' Average: ' + m3(item.raw),
              afterBody: items => (opts.extra ? opts.extra(items[0].dataIndex) : []),
            },
          },
        },
        scales: axes(v => v + ' m³'),
      },
    });
    chart.$peak = peak;
    return chart;
  }

  /** Two series side by side per month (e.g. Billed vs Collected). opts.footer(i) adds a tooltip line. */
  function pair(canvas, labels, a, b, names, opts = {}) {
    return new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [a, b].map((data, i) => ({ label: names[i], data, backgroundColor: SERIES[i], borderRadius: 4, maxBarThickness: 28,
          borderColor: '#fff', borderWidth: { left: 1, right: 1 } })),
      },
      options: {
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
          tooltip: { callbacks: { label: item => ' ' + item.dataset.label + ': ' + peso(item.raw), footer: items => (opts.footer ? opts.footer(items[0].dataIndex) : '') } },
        },
        scales: axes(pesoShort),
      },
    });
  }

  /** Share of a whole (e.g. payments by method). tip(i) gives the tooltip line. */
  function doughnut(canvas, labels, values, tip) {
    return new Chart(canvas, {
      type: 'doughnut',
      data: { labels, datasets: [{ data: values, backgroundColor: labels.map((_, i) => SERIES[i % SERIES.length]), borderColor: '#fff', borderWidth: 2 }] },
      options: {
        cutout: '62%',
        plugins: {
          legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
          tooltip: { callbacks: { label: item => ' ' + tip(item.dataIndex) } },
        },
      },
    });
  }

  /** Horizontal bars, largest first (e.g. consumption by purok). extra(i) adds a tooltip line. */
  function ranked(canvas, labels, values, extra) {
    return new Chart(canvas, {
      type: 'bar',
      data: { labels, datasets: [{ label: 'Consumption', data: values, backgroundColor: C.blue, borderRadius: 4, maxBarThickness: 22 }] },
      options: {
        indexAxis: 'y',
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: item => ' ' + m3(item.raw), afterLabel: item => (extra ? extra(item.dataIndex) : '') } },
        },
        scales: {
          x: { beginAtZero: true, grid: { color: C.grid }, border: { display: false }, ticks: { callback: v => v + ' m³', maxTicksLimit: 5 } },
          y: { grid: { display: false }, border: { color: C.grid } },
        },
      },
    });
  }

  /** Money collected per day / week / month; the current one (last bar) is darker. extra(i) adds tooltip lines. */
  function collections(canvas, labels, values, extra) {
    const last = values.length - 1;
    return new Chart(canvas, {
      type: 'bar',
      data: { labels, datasets: [{ label: 'Collected', data: values, borderRadius: 4, maxBarThickness: 30,
        backgroundColor: values.map((v, i) => (i === last ? C.peak : C.blue)) }] },
      options: {
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: item => ' Collected: ' + peso(item.raw), afterBody: items => (extra ? extra(items[0].dataIndex) : []) } },
        },
        scales: Object.assign(axes(pesoShort), { x: { grid: { display: false }, border: { color: C.grid }, ticks: { autoSkip: true, maxRotation: 0, autoSkipPadding: 8 } } }),
      },
    });
  }

  return { usage, pair, doughnut, ranked, collections, peso, m3 };
})();
