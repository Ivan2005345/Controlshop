document.addEventListener('DOMContentLoaded', () => {
  const dataEl = document.getElementById('chartData');
  if (!dataEl || typeof Chart === 'undefined') return;

  let data;
  try {
    data = JSON.parse(dataEl.textContent);
  } catch (e) {
    console.error('Error leyendo chartData:', e);
    return;
  }

  const fmtCOP = (val) =>
    new Intl.NumberFormat('es-CO', {
      style: 'currency',
      currency: 'COP',
      maximumFractionDigits: 0,
    }).format(val || 0);

  const fmtFecha = (iso) => {
    const [, m, d] = iso.split('-');
    const meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    return `${parseInt(d, 10)} ${meses[parseInt(m, 10) - 1] || m}`;
  };

  const isDark = () =>
    document.documentElement.dataset.theme === 'dark' ||
    document.body.classList.contains('dark') ||
    window.matchMedia('(prefers-color-scheme: dark)').matches;

  const gridColor = () => (isDark() ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)');
  const textColor = () => (isDark() ? '#9ca3af' : '#4b5563');

  // 1. Gráfica: Ingresos por día
  const ctxDias = document.getElementById('chDias');
  let chartDias = null;

  const renderDias = (rango) => {
    if (!ctxDias) return;
    const dias = (data.dias || []).slice(-rango);

    const total = dias.reduce((acc, item) => acc + item.t, 0);
    const prom = dias.length ? total / dias.length : 0;
    const mejor = dias.reduce((max, item) => (item.t > max.t ? item : max), { d: '', t: 0 });

    const elTotal = document.getElementById('sumTotal');
    const elProm = document.getElementById('sumProm');
    const elMejor = document.getElementById('sumMejor');

    if (elTotal) elTotal.textContent = fmtCOP(total);
    if (elProm) elProm.textContent = fmtCOP(prom);
    if (elMejor) {
      elMejor.textContent = mejor.t > 0 ? `${fmtCOP(mejor.t)} (${fmtFecha(mejor.d)})` : '—';
    }

    if (chartDias) chartDias.destroy();

    chartDias = new Chart(ctxDias, {
      type: 'line',
      data: {
        labels: dias.map((d) => fmtFecha(d.d)),
        datasets: [
          {
            label: 'Ingresos',
            data: dias.map((d) => d.t),
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.15)',
            borderWidth: 2,
            fill: true,
            tension: 0.3,
            pointRadius: dias.length <= 30 ? 3 : 1.5,
            pointHoverRadius: 5,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (ctx) => {
                const item = dias[ctx.dataIndex];
                const ventasTxt = item.n === 1 ? '1 venta' : `${item.n} ventas`;
                return ` ${fmtCOP(ctx.parsed.y)} (${ventasTxt})`;
              },
            },
          },
        },
        scales: {
          x: {
            grid: { display: false },
            ticks: { color: textColor(), maxTicksLimit: 10 },
          },
          y: {
            beginAtZero: true,
            grid: { color: gridColor() },
            ticks: {
              color: textColor(),
              callback: (v) => fmtCOP(v),
            },
          },
        },
      },
    });
  };

  renderDias(30);

  document.querySelectorAll('[data-range]').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('[data-range]').forEach((b) => b.setAttribute('aria-pressed', 'false'));
      btn.setAttribute('aria-pressed', 'true');
      renderDias(parseInt(btn.dataset.range, 10));
    });
  });

  // 2. Gráfica: Métodos de pago del mes
  const ctxMetodos = document.getElementById('chMetodos');
  if (ctxMetodos && data.metodos && data.metodos.length) {
    const colores = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4'];
    new Chart(ctxMetodos, {
      type: 'doughnut',
      data: {
        labels: data.metodos.map((m) => m.nombre),
        datasets: [
          {
            data: data.metodos.map((m) => m.total),
            backgroundColor: data.metodos.map((_, i) => colores[i % colores.length]),
            borderWidth: 0,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '62%',
        plugins: {
          legend: {
            position: 'bottom',
            labels: { color: textColor(), padding: 14, usePointStyle: true },
          },
          tooltip: {
            callbacks: {
              label: (ctx) => ` ${ctx.label}: ${fmtCOP(ctx.parsed)}`,
            },
          },
        },
      },
    });
  }

  // 3. Gráfica: Más vendidos del mes
  const ctxTop = document.getElementById('chTop');
  let chartTop = null;

  const renderTop = (metric) => {
    if (!ctxTop || !data.top || !data.top.length) return;
    if (chartTop) chartTop.destroy();

    const esTotal = metric === 'total';
    chartTop = new Chart(ctxTop, {
      type: 'bar',
      data: {
        labels: data.top.map((p) => p.nombre),
        datasets: [
          {
            label: esTotal ? 'Ingresos' : 'Unidades',
            data: data.top.map((p) => (esTotal ? p.total : p.unidades)),
            backgroundColor: '#3b82f6',
            borderRadius: 6,
            maxBarThickness: 32,
          },
        ],
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (ctx) =>
                esTotal
                  ? ` ${fmtCOP(ctx.parsed.x)}`
                  : ` ${ctx.parsed.x} ${ctx.parsed.x === 1 ? 'unidad' : 'unidades'}`,
            },
          },
        },
        scales: {
          x: {
            beginAtZero: true,
            grid: { color: gridColor() },
            ticks: {
              color: textColor(),
              callback: (v) => (esTotal ? fmtCOP(v) : v),
            },
          },
          y: {
            grid: { display: false },
            ticks: { color: textColor() },
          },
        },
      },
    });
  };

  renderTop('unidades');

  document.querySelectorAll('[data-metric]').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('[data-metric]').forEach((b) => b.setAttribute('aria-pressed', 'false'));
      btn.setAttribute('aria-pressed', 'true');
      renderTop(btn.dataset.metric);
    });
  });
});
