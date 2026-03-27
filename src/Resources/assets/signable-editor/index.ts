/**
 * Signable editor modules: box overlays, drag/resize, pads, coordinates, grid, utils.
 */
if (typeof console !== 'undefined') console.debug('[PdfSignable] loaded: signable-editor/index.ts');
export { setupBoxDragResizeRotate } from './box-drag';
export type { BoxDragContext } from './box-drag';
export { updateOverlays } from './box-overlays';
export type { BoxOverlaysContext } from './box-overlays';
export { formToViewport, viewportToForm, pdfToFormCoords } from './coordinates';
export { drawGridOnCanvas } from './grid';
export { initSignaturePads } from './signature-pad';
export type { SignaturePadOptions } from './signature-pad';
export { ptToUnit, unitToPt, escapeHtml, getColorForBoxIndex } from './utils';
export * from './constants';
export type { BoxBounds } from './types';
