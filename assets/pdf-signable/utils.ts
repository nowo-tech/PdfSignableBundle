import {
  PT_TO_UNIT,
  BOX_COLOR_BASE_HUE,
  BOX_COLOR_HUE_STEP,
  BOX_COLOR_S,
  BOX_COLOR_L,
} from './constants';

/** Converts a value from points to the given unit (pt, mm, cm, in, px). */
export function ptToUnit(val: number, unit: string): number {
  return val * (PT_TO_UNIT[unit] ?? 1);
}

/** Converts a value from the given unit to points. */
export function unitToPt(val: number, unit: string): number {
  return val / (PT_TO_UNIT[unit] ?? 1);
}

/** Escapes HTML special characters for safe use in innerHTML or attributes. */
export function escapeHtml(s: string): string {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

/** Converts HSL (0–360, 0–1, 0–1) to hex #rrggbb. */
export function hslToHex(h: number, s: number, l: number): string {
  h = h % 360;
  if (h < 0) h += 360;
  const sNorm = s / 100;
  const lNorm = l / 100;
  const c = (1 - Math.abs(2 * lNorm - 1)) * sNorm;
  const x = c * (1 - Math.abs(((h / 60) % 2) - 1));
  const m = lNorm - c / 2;
  let r = 0,
    g = 0,
    b = 0;
  if (h < 60) {
    r = c;
    g = x;
  } else if (h < 120) {
    r = x;
    g = c;
  } else if (h < 180) {
    g = c;
    b = x;
  } else if (h < 240) {
    g = x;
    b = c;
  } else if (h < 300) {
    r = x;
    b = c;
  } else {
    r = c;
    b = x;
  }
  const toHex = (n: number) => {
    const v = Math.round((n + m) * 255);
    return (v < 16 ? '0' : '') + Math.min(255, Math.max(0, v)).toString(16);
  };
  return '#' + toHex(r) + toHex(g) + toHex(b);
}

/** Returns border/background/color/handle for a signature box by signer index (by first occurrence of name). */
export function getColorForBoxIndex(index: number) {
  const hue = (BOX_COLOR_BASE_HUE + index * BOX_COLOR_HUE_STEP) % 360;
  const hex = hslToHex(hue, BOX_COLOR_S, BOX_COLOR_L);
  const r = parseInt(hex.slice(1, 3), 16);
  const g = parseInt(hex.slice(3, 5), 16);
  const b = parseInt(hex.slice(5, 7), 16);
  return {
    border: hex,
    background: `rgba(${r}, ${g}, ${b}, 0.15)`,
    color: hex,
    handle: hex,
  };
}
