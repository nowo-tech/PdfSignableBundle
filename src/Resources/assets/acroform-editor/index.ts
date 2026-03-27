/**
 * AcroForm editor modules: config, strings, move/resize.
 */
if (typeof console !== 'undefined') console.debug('[PdfSignable] loaded: acroform-editor/index.ts');
export { getConfig, FIELD_NAME_VALUE_OTHER } from './config';
export type { LabelChoice, AcroFormFieldDescriptor } from './config';
export { DEFAULT_STRINGS, escapeAttr } from './strings';
export { createAcroformMoveResize } from './acroform-move-resize';
