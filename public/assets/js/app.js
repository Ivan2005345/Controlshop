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
})();