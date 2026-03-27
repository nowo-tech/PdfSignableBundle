/**
 * @fileoverview Coordinate conversion between form units (PDF space, origin-aware) and viewport pixels.
 * Used to position signature box overlays and to convert drag/resize positions back to form values.
 */
if (typeof console !== 'undefined') console.debug('[PdfSignable] loaded: signable-editor/coordinates.ts');
import type { PDFViewport } from './types';

/**
 * Converts box coordinates from form units (PDF space, origin-aware) to viewport pixel position.
 * PDF space uses bottom-left origin; viewport Y is top-down.
 *
 * @param viewport - PDF.js viewport for the page
 * @param xPt - X in points (PDF space)
 * @param yPt - Y in points (PDF space)
 * @param wPt - Width in points
 * @param hPt - Height in points
 * @param origin - Coordinate origin (top_left, bottom_left, top_right, bottom_right)
 * @returns Viewport pixel position (left, top) for the overlay
 */
export function formToViewport(
  viewport: PDFViewport,
  xPt: number,
  yPt: number,
  wPt: number,
  hPt: number,
  origin: string
): { vpX: number; vpY: number } {
  const s = viewport.scale || 1.5;
  const pageW = viewport.width / s;
  const pageH = viewport.height / s;
  let xPdf: number, yPdf: number;
  switch (origin) {
    case 'top_left':
      xPdf = xPt;
      yPdf = pageH - yPt - hPt;
      break;
    case 'top_right':
      xPdf = pageW - xPt - wPt;
      yPdf = pageH - yPt - hPt;
      break;
    case 'bottom_right':
      xPdf = pageW - xPt - wPt;
      yPdf = yPt;
      break;
    default:
      xPdf = xPt;
      yPdf = yPt;
      break;
  }
  return {
    vpX: xPdf * s,
    vpY: viewport.height - (yPdf + hPt) * s,
  };
}

/**
 * Converts viewport pixel position back to form coordinates (PDF space, origin-aware).
 *
 * @param viewport - PDF.js viewport for the page
 * @param vpLeft - Left in viewport pixels
 * @param vpTop - Top in viewport pixels
 * @param wPt - Width in points
 * @param hPt - Height in points
 * @param origin - Coordinate origin (top_left, bottom_left, etc.)
 * @returns xPt, yPt in PDF space for the form inputs
 */
export function viewportToForm(
  viewport: PDFViewport,
  vpLeft: number,
  vpTop: number,
  wPt: number,
  hPt: number,
  origin: string
): { xPt: number; yPt: number } {
  const s = viewport.scale || 1.5;
  const pageW = viewport.width / s;
  const pageH = viewport.height / s;
  const pdf = viewport.convertToPdfPoint(vpLeft, vpTop + hPt * s);
  const xPdf = pdf[0];
  const yPdf = pdf[1];
  let xPt: number, yPt: number;
  switch (origin) {
    case 'top_left':
      xPt = xPdf;
      yPt = pageH - yPdf - hPt;
      break;
    case 'top_right':
      xPt = pageW - xPdf - wPt;
      yPt = pageH - yPdf - hPt;
      break;
    case 'bottom_right':
      xPt = pageW - xPdf - wPt;
      yPt = yPdf;
      break;
    default:
      xPt = xPdf;
      yPt = yPdf;
      break;
  }
  return { xPt, yPt };
}

/**
 * Converts PDF-space box (x, y in points, origin-aware) to form-unit values for a given origin.
 * Used when adding a new box from a click (we have PDF coords and need to fill form inputs).
 *
 * @param pageW - Page width in points
 * @param pageH - Page height in points
 * @param xPdf - X in PDF points (bottom-left origin)
 * @param yPdf - Y in PDF points (bottom-left origin)
 * @param wPt - Width in points
 * @param hPt - Height in points
 * @param origin - Coordinate origin
 * @returns xForm, yForm in form units (same as form inputs)
 */
export function pdfToFormCoords(
  pageW: number,
  pageH: number,
  xPdf: number,
  yPdf: number,
  wPt: number,
  hPt: number,
  origin: string
): { xForm: number; yForm: number } {
  let xForm: number, yForm: number;
  switch (origin) {
    case 'top_left':
      xForm = xPdf;
      yForm = pageH - yPdf - hPt;
      break;
    case 'top_right':
      xForm = pageW - xPdf - wPt;
      yForm = pageH - yPdf - hPt;
      break;
    case 'bottom_right':
      xForm = pageW - xPdf - wPt;
      yForm = yPdf;
      break;
    default:
      xForm = xPdf;
      yForm = yPdf;
      break;
  }
  return { xForm, yForm };
}
