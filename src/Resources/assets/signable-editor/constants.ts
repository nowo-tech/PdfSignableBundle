/**
 * @fileoverview PdfSignable-specific constants.
 * SCALE_GUTTER and URL/scale logic live in shared-pdf-viewer.
 */
if (typeof console !== 'undefined') console.debug('[PdfSignable] loaded: signable-editor/constants.ts');
/** Point-to-unit conversion factors (multiply pt by value to get unit). */
export const PT_TO_UNIT: Record<string, number> = {
  pt: 1,
  mm: 25.4 / 72,
  cm: 2.54 / 72,
  in: 1 / 72,
  px: 96 / 72,
};

/** Base hue for signature box colors (HSL). */
export const BOX_COLOR_BASE_HUE = 220;
/** Hue step per box index. */
export const BOX_COLOR_HUE_STEP = 37;
/** Saturation for box colors (0–100). */
export const BOX_COLOR_S = 65;
/** Lightness for box colors (0–100). */
export const BOX_COLOR_L = 48;
