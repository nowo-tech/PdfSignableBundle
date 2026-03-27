/**
 * @fileoverview Signable-specific types. Re-exports shared types and adds BoxBounds.
 */
if (typeof console !== 'undefined') console.debug('[PdfSignable] loaded: signable-editor/types.ts');
export type {
  NowoPdfSignableConfig,
  PDFViewport,
  PDFDocumentProxy,
  PDFPageProxy,
} from '../shared/types';

/** Box bounds in form units (for overlap check). Signable-only. */
export interface BoxBounds {
  page: number;
  x: number;
  y: number;
  w: number;
  h: number;
}
