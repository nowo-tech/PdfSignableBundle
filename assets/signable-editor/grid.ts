/**
 * @fileoverview Grid overlay drawn on the PDF canvas for alignment (e.g. every N mm).
 * PDF space uses bottom-left origin; viewport Y is top-down.
 */
if (typeof console !== 'undefined') console.debug('[PdfSignable] loaded: signable-editor/grid.ts');
import type { PDFViewport } from './types';
import { ptToUnit, unitToPt } from './utils';

/**
 * Draws a grid overlay on a canvas (form-unit grid, e.g. every 10 mm) for alignment.
 * Grid lines are drawn in PDF coordinate space; the canvas is viewport-sized.
 *
 * @param gridCanvas - Canvas element to draw on (same size as viewport)
 * @param viewport - PDF.js viewport for the page
 * @param scale - Current scale factor (viewport.width / scale = page width in pt)
 * @param unit - Unit string for grid step (mm, cm, pt, etc.)
 * @param gridStep - Step in form units (e.g. 10 for 10 mm)
 */
export function drawGridOnCanvas(
  gridCanvas: HTMLCanvasElement,
  viewport: PDFViewport,
  scale: number,
  unit: string,
  gridStep: number
): void {
  const ctx = gridCanvas.getContext('2d');
  if (!ctx) return;
  const pageWidthPt = viewport.width / scale;
  const pageHeightPt = viewport.height / scale;
  const pageWidthUnit = ptToUnit(pageWidthPt, unit);
  const pageHeightUnit = ptToUnit(pageHeightPt, unit);
  ctx.strokeStyle = 'rgba(0, 0, 0, 0.15)';
  ctx.lineWidth = 1;
  for (let xUnit = 0; xUnit <= pageWidthUnit; xUnit += gridStep) {
    const xPt = unitToPt(xUnit, unit);
    const vpX = xPt * scale;
    ctx.beginPath();
    ctx.moveTo(vpX, 0);
    ctx.lineTo(vpX, viewport.height);
    ctx.stroke();
  }
  for (let yUnit = 0; yUnit <= pageHeightUnit; yUnit += gridStep) {
    const yPt = unitToPt(yUnit, unit);
    const vpY = viewport.height - yPt * scale;
    ctx.beginPath();
    ctx.moveTo(0, vpY);
    ctx.lineTo(viewport.width, vpY);
    ctx.stroke();
  }
}
