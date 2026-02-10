/**
 * Shared PDF viewer logic (local copy). Same code lives in PdfTemplateBundle/assets/shared-pdf-viewer.
 * When changing URL/scale behaviour, update both copies to keep bundles in sync.
 */

export { SCALE_GUTTER } from './constants';
export type { IPdfDocForScale } from './types';
export { getLoadUrl, getScaleForFitWidth, getScaleForFitPage } from './url-and-scale';
