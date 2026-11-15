/* Gráficas interactivas de Reportes (usa Chart.js; esta página lo carga antes que este archivo). */
(() => {
  'use strict';
  const dataEl = document.getElementById('chartData');
  if (!dataEl) return;

  // Si la librería no cargó (sin internet o bloqueada), se avisa en vez de dejar cajas vacías
  if (!window.Chart) {
    document.querySelectorAll('.chart-box').forEach((box) => {
      box.textContent = 'No se pudieron cargar las gráficas. Revisa tu conexión y recarga la página.';
      box.classList.add('chart-fail');
    });
    return;
  }

  let data;
  try { data = JSON.parse(dataEl.textContent); } catch (err) { return; }

  const $id = (id) => document.getElementById(id);
  const css = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const peso = (n) => '$' + String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  const corto = (n) => {
    if (n >= 1e6) return '$' + String(Math.round(n / 1e5) / 10).replace('.', ',') + ' M';
    if (n >= 1e3) return '$' + Math.round(n / 1e3) + ' mil';
    return '$' + n;
  };
  const fecha = (iso, opts) => new Date(iso + 'T12:00:00').toLocaleDateString('es-CO', opts);
  const withAlpha = (hex, a) => {
    const m = /^#([0-9a-f]{6})$/i.exec(hex);
    if (!m) return hex;
    const n = parseInt(m[1], 16);
    return 'rgba(' + (n >> 16) + ', ' + ((n >> 8) & 255) + ', ' + (n & 255) + ', ' + a + ')';
  };
  const COLOR_PAGO = { efectivo: '#4f9d69', nequi: '#8a6fbf', transferencia: '#2f6b8f', llave: '#f2a33a' };

  // Estado que el usuario cambia con los botones
  let range = 30;
  let metric = 'unidades';

  const palette = () => ({
    ink: css('--ink'), muted: css('--muted'), line: css('--line'), accent: css('--accent'), surface: css('--surface'),
  });
  const anim = (animate) => (animate && !reduced ? { duration: 700, easing: 'easeOutQuart' } : false);
  const tooltip = (t, callbacks) => ({
    backgroundColor: t.surface, titleColor: t.ink, bodyColor: t.ink, borderColor: t.line, borderWidth: 1,
    padding: 10, boxPadding: 4, callbacks,
  });

  const diasVisibles = () => data.dias.slice(-range);
  const topOrdenado = () => [...data.top].sort((a, b) => b[metric] - a[metric]);

  /* ---------- Ingresos por día (línea) ---------- */
  function buildDias(t, animate) {
    const canvas = $id('chDias');
    if (!canvas) return null;
    const dias = diasVisibles();
    return new Chart(canvas, {
      type: 'line',
      data: {
        labels: dias.map((d) => d.d),
        datasets: [{
          label: 'Ingresos', data: dias.map((d) => d.t), borderColor: t.accent, backgroundColor: withAlpha(t.accent, 0.16),
          fill: true, tension: 0.35, borderWidth: 2.5, pointRadius: dias.length > 31 ? 0 : 3, pointHoverRadius: 6,
          pointBackgroundColor: t.accent,
        }],
      },
      options: {
        responsive: true, maintainAspectRatio: false, animation: anim(animate),
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: false },
          tooltip: tooltip(t, {
            title: (items) => fecha(diasVisibles()[items[0].dataIndex].d, { weekday: 'long', day: 'numeric', month: 'long' }),
            label: (item) => ' ' + peso(item.parsed.y),
            afterLabel: (item) => { const n = diasVisibles()[item.dataIndex].n; return ' ' + n + (n === 1 ? ' venta' : ' ventas'); },
          }),
        },
        scales: {
          x: {
            grid: { display: false },
            ticks: {
              color: t.muted, maxRotation: 0, autoSkip: true, maxTicksLimit: 8,
              callback(value) { return fecha(this.getLabelForValue(value), { day: 'numeric', month: 'short' }); },
            },
          },
          y: { beginAtZero: true, border: { display: false }, grid: { color: t.line }, ticks: { color: t.muted, callback: (v) => corto(v) } },
        },
      },
    });
  }

  function updateSummary() {
    const dias = diasVisibles();
    const total = dias.reduce((s, d) => s + d.t, 0);
    const best = dias.reduce((b, d) => (d.t > b.t ? d : b), { t: 0, d: '' });
    const set = (id, text) => { const el = $id(id); if (el) el.textContent = text; };
    set('sumTotal', peso(total));
    set('sumProm', peso(dias.length ? total / dias.length : 0));
    set('sumMejor', best.t > 0 ? fecha(best.d, { weekday: 'short', day: 'numeric', month: 'short' }) + ' · ' + peso(best.t) : 'Sin ventas');
  }

  /* ---------- Métodos de pago del mes (dona) ---------- */
  function buildMetodos(t, animate) {
    const canvas = $id('chMetodos');
    if (!canvas || !data.metodos.length) return null;
    const total = data.metodos.reduce((s, m) => s + m.total, 0);
    return new Chart(canvas, {
      type: 'doughnut',
      data: {
        labels: data.metodos.map((m) => m.nombre),
        datasets: [{
          data: data.metodos.map((m) => m.total), backgroundColor: data.metodos.map((m) => COLOR_PAGO[m.k] || '#8899a6'),
          borderColor: t.surface, borderWidth: 3, hoverOffset: 10,
        }],
      },
      options: {
        responsive: true, maintainAspectRatio: false, cutout: '62%', animation: anim(animate),
        plugins: {
          legend: { position: 'bottom', labels: { color: t.ink, usePointStyle: true, pointStyle: 'circle', padding: 16 } },
          tooltip: tooltip(t, {
            label: (item) => ' ' + item.label + ': ' + peso(item.parsed) + ' (' + Math.round((item.parsed / total) * 100) + '%)',
          }),
        },
      },
    });
  }

  /* ---------- Productos más vendidos del mes (barras horizontales) ---------- */
  function buildTop(t, animate) {
    const canvas = $id('chTop');
    if (!canvas || !data.top.length) return null;
    const rows = topOrdenado();
    return new Chart(canvas, {
      type: 'bar',
      data: {
        labels: rows.map((r) => r.nombre),
        datasets: [{
          data: rows.map((r) => r[metric]), backgroundColor: withAlpha(t.accent, 0.85), hoverBackgroundColor: t.accent,
          borderRadius: 6, borderSkipped: false,
        }],
      },
      options: {
        indexAxis: 'y', responsive: true, maintainAspectRatio: false, animation: anim(animate),
        plugins: {
          legend: { display: false },
          tooltip: tooltip(t, {
            label: (item) => (metric === 'total'
              ? ' ' + peso(item.parsed.x)
              : ' ' + item.parsed.x + (item.parsed.x === 1 ? ' unidad' : ' unidades')),
          }),
        },
        scales: {
          x: {
            beginAtZero: true, border: { display: false }, grid: { color: t.line },
            ticks: { color: t.muted, callback: (v) => (metric === 'total' ? corto(v) : (Number.isInteger(v) ? v : '')) },
          },
          y: { grid: { display: false }, ticks: { color: t.ink } },
        },
      },
    });
  }

  /* ---------- Armado, botones y cambio de tema ---------- */
  let charts = {};
  function buildAll(animate) {
    Object.values(charts).forEach((c) => { if (c) c.destroy(); });
    const t = palette();
    charts = { dias: buildDias(t, animate), metodos: buildMetodos(t, animate), top: buildTop(t, animate) };
    updateSummary();
  }

  const pressed = (buttons, attr, value) => buttons.forEach((b) => b.setAttribute('aria-pressed', String(b.dataset[attr] === String(value))));
  const rangeBtns = [...document.querySelectorAll('[data-range]')];
  const metricBtns = [...document.querySelectorAll('[data-metric]')];

  rangeBtns.forEach((btn) => btn.addEventListener('click', () => {
    range = parseInt(btn.dataset.range, 10);
    pressed(rangeBtns, 'range', range);
    if (charts.dias) {
      const dias = diasVisibles();
      charts.dias.data.labels = dias.map((d) => d.d);
      charts.dias.data.datasets[0].data = dias.map((d) => d.t);
      charts.dias.data.datasets[0].pointRadius = dias.length > 31 ? 0 : 3;
      charts.dias.update();
    }
    updateSummary();
  }));

  metricBtns.forEach((btn) => btn.addEventListener('click', () => {
    metric = btn.dataset.metric;
    pressed(metricBtns, 'metric', metric);
    if (charts.top) {
      const rows = topOrdenado();
      charts.top.data.labels = rows.map((r) => r.nombre);
      charts.top.data.datasets[0].data = rows.map((r) => r[metric]);
      charts.top.update();
    }
  }));

  pressed(rangeBtns, 'range', range);
  pressed(metricBtns, 'metric', metric);
  buildAll(true);

  // Al cambiar entre modo claro y oscuro se redibujan con los colores nuevos
  new MutationObserver(() => buildAll(false)).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
})();