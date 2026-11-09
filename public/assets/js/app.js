(() => {
  'use strict';
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

  /* ---------- Ojito para mostrar / ocultar contraseñas ---------- */
  $$('input[type="password"]').forEach((input) => {
    const wrap = document.createElement('span');
    wrap.className = 'pw-field';
    input.parentNode.insertBefore(wrap, input);
    wrap.append(input);

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'pw-toggle';
    btn.setAttribute('aria-label', 'Mostrar contraseña');
    btn.setAttribute('aria-pressed', 'false');
    btn.innerHTML = '<i class="fa-solid fa-eye" aria-hidden="true"></i>';
    btn.addEventListener('click', () => {
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.setAttribute('aria-pressed', String(show));
      btn.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
      btn.querySelector('i').className = show ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
    });
    wrap.append(btn);
  });

  /* ---------- Modo claro / oscuro (la elección se recuerda en este navegador) ---------- */
  const root = document.documentElement;
  const switches = $$('[data-theme-toggle]');
  const syncSwitches = () => switches.forEach((sw) => sw.setAttribute('aria-checked', String(root.dataset.theme === 'dark')));
  let animTimer;
  switches.forEach((sw) => {
    sw.addEventListener('click', () => {
      const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
      root.classList.add('theme-anim'); // activa la transición suave de colores
      root.dataset.theme = next;
      try { localStorage.setItem('cs_tema', next); } catch (err) { /* sin almacenamiento */ }
      syncSwitches();
      clearTimeout(animTimer);
      animTimer = setTimeout(() => root.classList.remove('theme-anim'), 600);
    });
  });
  syncSwitches();

  /* ---------- Menú lateral en celular ---------- */
  const menuBtn = $('#menuBtn');
  const setMenu = (open) => {
    document.body.classList.toggle('menu-open', open);
    if (menuBtn) menuBtn.setAttribute('aria-expanded', String(open));
  };
  if (menuBtn) menuBtn.addEventListener('click', () => setMenu(!document.body.classList.contains('menu-open')));
  const scrim = $('#scrim');
  if (scrim) scrim.addEventListener('click', () => setMenu(false));
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setMenu(false); });

  /* ---------- Búsqueda y filtro de tablas (inventario y equipo) ---------- */
  $$('table[data-filterable]').forEach((table) => {
    const controls = $$('[data-filter-for="' + table.id + '"]');
    const rows = $$('tbody tr[data-search]', table);
    const apply = () => {
      const textBox = controls.find((c) => c.dataset.filter === 'text');
      const catBox = controls.find((c) => c.dataset.filter === 'cat');
      const query = textBox ? textBox.value.trim().toLowerCase() : '';
      const cat = catBox ? catBox.value : '';
      rows.forEach((row) => {
        const matchText = row.dataset.search.includes(query);
        const matchCat = !cat || row.dataset.cat === cat;
        row.hidden = !(matchText && matchCat);
      });
    };
    controls.forEach((c) => c.addEventListener('input', apply));
  });

  /* ---------- Inventario: diálogo para crear / editar producto ---------- */
  const dialog = $('#productDialog');
  if (dialog) {
    const field = (id) => document.getElementById(id);
    const openDialog = (data) => {
      field('pTitle').textContent = data ? 'Editar producto' : 'Nuevo producto';
      field('pId').value = data ? data.id : '';
      field('pNombre').value = data ? data.nombre : '';
      field('pCategoria').value = data ? data.categoria : '';
      field('pPrecio').value = data ? data.precio : '';
      field('pStock').value = data ? data.stock : '';
      dialog.showModal();
      field('pNombre').focus();
    };
    const btnNuevo = $('#btnNuevo');
    if (btnNuevo) btnNuevo.addEventListener('click', () => openDialog(null));
    $$('[data-edit]').forEach((btn) => {
      btn.addEventListener('click', () => openDialog(JSON.parse(btn.dataset.edit)));
    });
    $$('[data-close]', dialog).forEach((btn) => btn.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', (e) => { if (e.target === dialog) dialog.close(); });
  }

  /* ---------- Resumen: pendientes del día (se guardan en este navegador) ---------- */
  const agenda = $('#agenda');
  if (agenda) {
    const key = agenda.dataset.key;
    const list = $('#agendaList');
    const empty = $('#agendaEmpty');
    const form = $('#agendaForm');
    const input = $('#agendaInput');
    let items = [];
    try { items = JSON.parse(localStorage.getItem(key) || '[]'); } catch (err) { items = []; }
    if (!Array.isArray(items)) items = [];
    const save = () => { try { localStorage.setItem(key, JSON.stringify(items)); } catch (err) { /* sin almacenamiento */ } };

    const render = () => {
      list.replaceChildren();
      items.forEach((text, index) => {
        const li = document.createElement('li');
        const span = document.createElement('span');
        span.textContent = text; // textContent: el texto nunca se interpreta como HTML
        const done = document.createElement('button');
        done.type = 'button';
        done.className = 'btn btn-sm';
        done.textContent = 'Listo';
        done.setAttribute('aria-label', 'Marcar como hecho: ' + text);
        done.addEventListener('click', () => { items.splice(index, 1); save(); render(); });
        li.append(span, done);
        list.append(li);
      });
      empty.hidden = items.length > 0;
    };

    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const text = input.value.trim();
      if (!text) return;
      items.push(text.slice(0, 120));
      input.value = '';
      save();
      render();
    });
    render();
  }

  /* ---------- Nueva venta: calculadora de vueltos (solo para pagos en efectivo) ---------- */
  const cash = $('#cashPanel');
  if (cash) {
    const total = Math.round(parseFloat(cash.dataset.total) || 0);
    const input = $('#pagaCon');
    const quick = $('#cashQuick');
    const result = $('#cashResult');
    const label = $('#cashLabel');
    const amount = $('#cashAmount');
    const breakdown = $('#cashBreak');
    const payBtn = $('#btnCobrar');
    const radios = $$('input[name="metodo_pago"]');
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const BILLS = [2000, 5000, 10000, 20000, 50000, 100000];
    const DENOMS = [100000, 50000, 20000, 10000, 5000, 2000, 1000, 500, 200, 100, 50]; // billetes y monedas
    const dots = (n) => String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    const money = (n) => '$' + dots(n);
    const ceilTo = (n, step) => Math.ceil(n / step) * step;
    const isCash = () => { const r = radios.find((x) => x.checked); return !r || r.value === 'efectivo'; };
    const readPaid = () => { const d = input.value.replace(/\D/g, ''); return d === '' ? null : parseInt(d, 10); };

    // Montos rápidos: el total exacto y los billetes o redondeos más comunes que lo cubren
    const suggestions = () => {
      const set = new Set([total, ceilTo(total, 5000), ceilTo(total, 10000), ceilTo(total, 50000), ceilTo(total, 100000)]);
      BILLS.forEach((b) => { if (b >= total) set.add(b); });
      return [...set].filter((v) => v >= total).sort((a, b) => a - b).slice(0, 4);
    };
    suggestions().forEach((value) => {
      const chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'cash-chip';
      chip.textContent = value === total ? 'Exacto' : money(value);
      chip.addEventListener('click', () => { input.value = dots(value); update(); });
      quick.append(chip);
    });

    // El número "cuenta" desde el valor anterior hasta el nuevo
    let shown = 0;
    let rafId = 0;
    const animateNumber = (to) => {
      cancelAnimationFrame(rafId);
      const from = shown;
      if (reduced || from === to) { shown = to; amount.textContent = money(to); return; }
      const start = performance.now();
      const step = (now) => {
        const p = Math.min((now - start) / 450, 1);
        shown = from + (to - from) * (1 - Math.pow(1 - p, 3));
        amount.textContent = money(shown);
        if (p < 1) { rafId = requestAnimationFrame(step); } else { shown = to; }
      };
      rafId = requestAnimationFrame(step);
    };

    // Con qué billetes y monedas devolver (de mayor a menor)
    const renderBreakdown = (change) => {
      breakdown.replaceChildren();
      let rest = change;
      let i = 0;
      DENOMS.forEach((d) => {
        const n = Math.floor(rest / d);
        if (n > 0) {
          rest -= n * d;
          const chip = document.createElement('span');
          chip.style.setProperty('--i', String(i++));
          chip.textContent = n + ' × ' + money(d);
          breakdown.append(chip);
        }
      });
    };

    let lastState = '';
    const setState = (state) => {
      if (state === lastState) return;
      lastState = state;
      result.dataset.state = state;
      result.classList.remove('pop');
      if (state === 'change' || state === 'exact') {
        void result.offsetWidth; // reinicia la animación
        result.classList.add('pop');
      }
    };

    function update() {
      const cashMode = isCash();
      cash.classList.toggle('open', cashMode);
      cash.inert = !cashMode;
      const paid = readPaid();
      const blocked = cashMode && paid !== null && paid < total;
      if (payBtn) {
        payBtn.disabled = blocked; // no deja cobrar si el dinero recibido no alcanza
        payBtn.title = blocked ? 'El dinero recibido no cubre el total' : '';
      }
      if (!cashMode) return;

      if (paid === null) {
        setState('idle');
        label.textContent = 'Escribe cuánto paga el cliente';
        amount.textContent = '';
        shown = 0;
        breakdown.replaceChildren();
      } else if (paid < total) {
        setState('short');
        label.textContent = 'Todavía faltan';
        animateNumber(total - paid);
        breakdown.replaceChildren();
      } else if (paid === total) {
        setState('exact');
        label.textContent = 'Pago exacto, no hay vueltos';
        animateNumber(0);
        breakdown.replaceChildren();
      } else {
        setState('change');
        label.textContent = 'Vueltos a devolver';
        animateNumber(paid - total);
        renderBreakdown(paid - total);
      }
    }

    input.addEventListener('input', () => {
      const d = input.value.replace(/\D/g, '').slice(0, 9);
      input.value = d ? dots(d) : '';
      update();
    });
    // Enter en este campo no cobra por accidente
    input.addEventListener('keydown', (e) => { if (e.key === 'Enter') e.preventDefault(); });
    radios.forEach((r) => r.addEventListener('change', update));
    update();
  }
})();