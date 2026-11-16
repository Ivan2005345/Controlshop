/* ControlShop — paleta de colores personalizada.
   Guarda solo el color base elegido y deriva los tonos (hover, fondos suaves, texto)
   para que funcionen tanto en modo claro como oscuro. */
(() => {
  'use strict';

  const KEY = 'cs_color';
  const DEFAULT = '#2f6b8f';
  const root = document.documentElement;
  const PROPS = ['--steel', '--steel-dark', '--accent', '--accent-hover', '--mist', '--hover', '--on-steel', '--brand-ring'];

  let current = DEFAULT;
  let animTimer;

  const clamp = (n, min, max) => Math.min(max, Math.max(min, n));

  const normalize = (value) => {
    if (typeof value !== 'string') return null;
    const v = value.trim().toLowerCase();
    if (/^#[0-9a-f]{6}$/.test(v)) return v;
    if (/^#[0-9a-f]{3}$/.test(v)) return '#' + v.slice(1).split('').map((c) => c + c).join('');
    return null;
  };

  const rgb = (color) => {
    const n = parseInt(color.slice(1), 16);
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
  };

  const hex = (r, g, b) => '#' + [r, g, b]
    .map((v) => clamp(Math.round(v), 0, 255).toString(16).padStart(2, '0'))
    .join('');

  // Mezcla: amount = proporción del color sobre el fondo.
  const tint = (color, bg, amount) => {
    const a = rgb(color);
    const b = rgb(bg);
    return hex(a[0] * amount + b[0] * (1 - amount), a[1] * amount + b[1] * (1 - amount), a[2] * amount + b[2] * (1 - amount));
  };

  const rgbToHsl = (color) => {
    const [r, g, b] = rgb(color).map((v) => v / 255);
    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    const l = (max + min) / 2;
    let h = 0;
    let s = 0;
    if (max !== min) {
      const d = max - min;
      s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
      if (max === r) h = (g - b) / d + (g < b ? 6 : 0);
      else if (max === g) h = (b - r) / d + 2;
      else h = (r - g) / d + 4;
      h /= 6;
    }
    return { h, s, l };
  };

  const hslToHex = ({ h, s, l }) => {
    const hue2rgb = (p, q, t) => {
      if (t < 0) t += 1;
      if (t > 1) t -= 1;
      if (t < 1 / 6) return p + (q - p) * 6 * t;
      if (t < 1 / 2) return q;
      if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
      return p;
    };
    if (s === 0) return hex(l * 255, l * 255, l * 255);
    const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
    const p = 2 * l - q;
    return hex(hue2rgb(p, q, h + 1 / 3) * 255, hue2rgb(p, q, h) * 255, hue2rgb(p, q, h - 1 / 3) * 255);
  };

  const lighten = (color, amount) => {
    const hsl = rgbToHsl(color);
    hsl.l = clamp(hsl.l + amount, 0, 1);
    return hslToHex(hsl);
  };
  const darken = (color, amount) => lighten(color, -amount);

  const luminance = (color) => {
    const [r, g, b] = rgb(color).map((v) => {
      const n = v / 255;
      return n <= 0.04045 ? n / 12.92 : Math.pow((n + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
  };

  const contrast = (a, b) => {
    const l1 = luminance(a);
    const l2 = luminance(b);
    return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
  };

  // Ajusta el tono hasta que sea legible sobre el fondo (enlaces e iconos).
  const readable = (color, bg, ratio, direction) => {
    let out = color;
    for (let i = 0; i < 40 && contrast(out, bg) < ratio; i++) {
      out = direction === 'dark' ? darken(out, 0.02) : lighten(out, 0.02);
    }
    return out;
  };

  // Blanco o tinta oscura, el que mejor contraste dé sobre el color principal.
  const onColor = (color) => (contrast(color, '#ffffff') >= contrast(color, '#1d3442') ? '#ffffff' : '#1d3442');

  const varsFor = (color, theme) => {
    const dark = theme === 'dark';
    const accent = readable(color, dark ? '#121c23' : '#ffffff', 4.5, dark ? 'light' : 'dark');
    return {
      '--steel': color,
      '--steel-dark': darken(color, 0.16),
      '--accent': accent,
      '--accent-hover': dark ? lighten(accent, 0.1) : darken(accent, 0.1),
      '--mist': dark ? tint(color, '#1a2730', 0.22) : tint(color, '#ffffff', 0.1),
      '--hover': dark ? tint(color, '#121c23', 0.28) : tint(color, '#ffffff', 0.04),
      '--on-steel': onColor(color),
      '--brand-ring': 'rgba(' + rgb(color).join(', ') + ', .28)',
    };
  };

  // El azul original se deja tal cual: así el aspecto de fábrica no cambia.
  const apply = (color, theme) => {
    if (color === DEFAULT) {
      PROPS.forEach((prop) => root.style.removeProperty(prop));
      root.dataset.palette = 'default';
      return;
    }
    const vars = varsFor(color, theme);
    PROPS.forEach((prop) => root.style.setProperty(prop, vars[prop]));
    root.dataset.palette = 'custom';
  };

  const set = (color) => {
    const value = normalize(color);
    if (!value) return current;
    current = value;
    apply(current, root.dataset.theme);
    try {
      if (current === DEFAULT) localStorage.removeItem(KEY);
      else localStorage.setItem(KEY, current);
    } catch (err) { /* sin almacenamiento */ }
    root.classList.add('theme-anim');
    clearTimeout(animTimer);
    animTimer = setTimeout(() => root.classList.remove('theme-anim'), 600);
    document.dispatchEvent(new CustomEvent('cs:palette', { detail: { color: current } }));
    return current;
  };

  const refresh = () => apply(current, root.dataset.theme);

  let saved = null;
  try {
    saved = normalize(localStorage.getItem(KEY));
    if (!saved) localStorage.removeItem(KEY);
  } catch (err) { /* sin almacenamiento */ }
  current = saved || DEFAULT;
  apply(current, root.dataset.theme);

  // Si el color se cambia en otra pestaña, esta se actualiza sola.
  addEventListener('storage', (e) => {
    if (e.key !== KEY) return;
    current = normalize(e.newValue) || DEFAULT;
    apply(current, root.dataset.theme);
    document.dispatchEvent(new CustomEvent('cs:palette', { detail: { color: current } }));
  });

  window.CS_Palette = { DEFAULT, get: () => current, set, refresh, normalize };
})();
