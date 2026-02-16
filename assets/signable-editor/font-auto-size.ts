/**
 * @fileoverview Auto-size font for AcroForm inputs so content fits without scroll.
 * Used when data-font-auto-size="1" is set on an input or textarea.
 */
if (typeof console !== 'undefined') console.debug('[PdfSignable] loaded: signable-editor/font-auto-size.ts');
/** Minimum font size in pixels when auto-sizing down. */
const FONT_AUTO_SIZE_MIN_PX = 8;

/**
 * Reduces font size of an input or textarea until its content fits (no scroll).
 * Call after appending the element and on input when data-font-auto-size="1" is set.
 *
 * @param el - Input or textarea element to auto-size
 */
export function applyFontAutoSize(el: HTMLInputElement | HTMLTextAreaElement): void {
  const isTextarea = el instanceof HTMLTextAreaElement;
  let fontSize = parseInt(getComputedStyle(el).fontSize, 10) || 16;
  if (fontSize < FONT_AUTO_SIZE_MIN_PX) fontSize = FONT_AUTO_SIZE_MIN_PX;
  el.style.fontSize = fontSize + 'px';
  while (fontSize >= FONT_AUTO_SIZE_MIN_PX) {
    const overflowV = isTextarea && el.scrollHeight > el.clientHeight;
    const overflowH = el.scrollWidth > el.clientWidth;
    if (!overflowV && !overflowH) break;
    fontSize -= 1;
    el.style.fontSize = fontSize + 'px';
  }
  if (fontSize < FONT_AUTO_SIZE_MIN_PX) el.style.fontSize = FONT_AUTO_SIZE_MIN_PX + 'px';
}
