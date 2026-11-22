/* ControlShop — calendario de los filtros de fecha.
   El navegador no permite teñir el popup nativo de <input type="date">, así que se
   oculta su icono y se abre este diálogo, que usa los colores de la paleta.
   El input nativo sigue siendo la fuente de verdad: el formulario envía lo mismo. */
(() => {
  'use strict';

  const dialog = document.getElementById('dateDialog');
  const fields = [...document.querySelectorAll('[data-date-field]')];
  if (!dialog || !fields.length || typeof dialog.showModal !== 'function') return;

  const grid = dialog.querySelector('[data-date-grid]');
  const title = document.getElementById('dateTitle');
  const pad = (n) => String(n).padStart(2, '0');
  const format = (date) => date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());

  const parse = (value) => {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
    if (!m) return null;
    const date = new Date(+m[1], +m[2] - 1, +m[3]);
    return format(date) === value ? date : null;
  };

  const monthLabel = new Intl.DateTimeFormat('es-CO', { month: 'long', year: 'numeric' });
  let field = null;
  let year = 0;
  let month = 0;

  const render = () => {
    const selected = field ? format(parse(field.value) || new Date(1970, 0, 1)) : '';
    const current = format(new Date());
    const first = new Date(year, month, 1);
    const offset = (first.getDay() + 6) % 7; // lunes primero
    const days = new Date(year, month + 1, 0).getDate();

    title.textContent = monthLabel.format(first).replace(/^./, (c) => c.toUpperCase());
    const cells = document.createDocumentFragment();
    for (let i = 0; i < offset; i++) {
      const blank = document.createElement('span');
      blank.className = 'date-blank';
      blank.setAttribute('aria-hidden', 'true');
      cells.append(blank);
    }
    for (let day = 1; day <= days; day++) {
      const value = year + '-' + pad(month + 1) + '-' + pad(day);
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'date-day';
      btn.dataset.date = value;
      btn.textContent = String(day);
      btn.setAttribute('aria-pressed', String(value === selected));
      if (value === current) {
        btn.setAttribute('aria-current', 'date');
        btn.setAttribute('aria-label', 'Hoy, ' + day);
      }
      cells.append(btn);
    }
    for (let end = offset + days; end % 7 !== 0; end++) {
      const blank = document.createElement('span');
      blank.className = 'date-blank';
      blank.setAttribute('aria-hidden', 'true');
      cells.append(blank);
    }
    grid.replaceChildren(cells);
  };

  const open = (input) => {
    if (!input || dialog.open) return;
    field = input;
    const start = parse(input.value) || new Date();
    year = start.getFullYear();
    month = start.getMonth();
    render();
    dialog.showModal();
    const active = grid.querySelector('[aria-pressed="true"]') || grid.querySelector('[aria-current="date"]');
    if (active) active.focus();
  };

  const choose = (value) => {
    if (!field) return;
    field.value = value;
    field.dispatchEvent(new Event('change', { bubbles: true }));
    dialog.close();
  };

  document.querySelectorAll('[data-date-open]').forEach((btn) => {
    btn.addEventListener('click', () => open(document.getElementById(btn.dataset.dateOpen)));
  });
  // Chrome pinta el segmento enfocado de azul y no lo deja teñir con CSS. Abrimos el
  // calendario antes de que el input reciba el foco y nunca lo dejamos enfocado.
  fields.forEach((input) => {
    input.addEventListener('pointerdown', (e) => { e.preventDefault(); open(input); });
    input.addEventListener('click', () => open(input));
    input.addEventListener('focus', () => input.blur());
  });
  dialog.querySelector('[data-date-prev]').addEventListener('click', () => {
    const d = new Date(year, month - 1, 1);
    year = d.getFullYear();
    month = d.getMonth();
    render();
  });
  dialog.querySelector('[data-date-next]').addEventListener('click', () => {
    const d = new Date(year, month + 1, 1);
    year = d.getFullYear();
    month = d.getMonth();
    render();
  });
  grid.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-date]');
    if (btn && grid.contains(btn)) choose(btn.dataset.date);
  });
  dialog.querySelector('[data-date-clear]').addEventListener('click', () => choose(''));
  dialog.querySelector('[data-date-today]').addEventListener('click', () => choose(format(new Date())));
  dialog.querySelector('[data-date-close]').addEventListener('click', () => dialog.close());
  dialog.addEventListener('click', (e) => { if (e.target === dialog) dialog.close(); });

  // Solo con JS activo se oculta el icono nativo y aparece el botón del calendario.
  document.documentElement.classList.add('has-datepicker');
})();
