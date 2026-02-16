/**
 * @fileoverview AcroForm editor configuration: types and config from data attributes.
 */
if (typeof console !== 'undefined') console.debug('[PdfSignable] loaded: acroform-editor/config.ts');
/** Label option: value (saved) and optional display label. */
export interface LabelChoice {
  value: string;
  label?: string;
}

/** Descriptor for a single AcroForm field (from PDF or synthetic for new fields). */
export interface AcroFormFieldDescriptor {
  id: unknown;
  rect?: number[];
  width?: number;
  height?: number;
  fieldType?: string;
  value?: string;
  page?: number;
  subtype?: string;
  flags?: number;
  fieldName?: string;
  maxLen?: number;
  fontSize?: number;
  hidden?: boolean;
}

/** Config for the AcroForm editor panel (from data attributes on #acroform-editor-root). */
export interface AcroFormEditorConfig {
  loadUrl: string;
  postUrl: string;
  documentKey: string;
  applyUrl: string;
  processUrl: string;
  debug: boolean;
  /** Field name widget: "input" (free text) or "choice" (select from fieldNameChoices + optional "Other"). */
  fieldNameMode: 'input' | 'choice';
  fieldNameChoices: LabelChoice[];
  fieldNameOtherText: string;
  /** When false, the coordinates (rect) field is hidden in the edit-field modal. */
  showFieldRect: boolean;
  /** Allowed font sizes (pt). Empty = number input 1–72; non-empty = select with these values. */
  fontSizes: number[];
  /** Allowed font families (value + optional label). Empty = built-in list; non-empty = select with these. */
  fontFamilies: LabelChoice[];
}

/** Value used in the field name select for the "Other" free-text option. */
export const FIELD_NAME_VALUE_OTHER = '__other__';

/**
 * Parses label choices from a JSON string (array of strings or { value, label? } objects).
 * @param raw - JSON string or undefined
 * @returns Array of LabelChoice; empty array on parse error or empty input
 */
export function parseLabelChoices(raw: string | undefined): LabelChoice[] {
  if (!raw || typeof raw !== 'string') return [];
  try {
    const parsed = JSON.parse(raw) as unknown;
    if (!Array.isArray(parsed)) return [];
    return parsed
      .map((item): LabelChoice => {
        if (typeof item === 'string') {
          const pipe = item.indexOf('|');
          if (pipe >= 0) return { value: item.slice(0, pipe).trim(), label: item.slice(pipe + 1).trim() };
          return { value: item.trim(), label: item.trim() };
        }
        if (item && typeof item === 'object' && 'value' in item) {
          const v = (item as { value?: string; label?: string }).value;
          const l = (item as { value?: string; label?: string }).label;
          return { value: typeof v === 'string' ? v : '', label: typeof l === 'string' ? l : undefined };
        }
        return { value: '', label: '' };
      })
      .filter((c) => c.value !== '');
  } catch {
    return [];
  }
}

/**
 * Reads AcroForm editor config from data attributes on the root element.
 * @param root - Element with id acroform-editor-root and data-* attributes
 * @returns Config object (loadUrl, postUrl, documentKey, fieldNameMode, etc.)
 */
export function getConfig(root: HTMLElement): AcroFormEditorConfig {
  const loadUrl = root.dataset.loadUrl ?? '';
  const postUrl = root.dataset.postUrl ?? '';
  const documentKey = root.dataset.documentKey ?? '';
  const applyUrl = root.dataset.applyUrl ?? '';
  const processUrl = root.dataset.processUrl ?? '';
  const debug = root.dataset.debug === '1' || root.dataset.debug === 'true';
  const fieldNameMode = (root.dataset.fieldNameMode === 'choice' ? 'choice' : 'input') as 'input' | 'choice';
  const fieldNameChoices = parseLabelChoices(root.dataset.fieldNameChoices);
  const fieldNameOtherText = root.dataset.fieldNameOtherText?.trim() ?? '';
  const showFieldRect = root.dataset.showFieldRect !== '0' && root.dataset.showFieldRect !== 'false';
  const fontSizes = parseFontSizes(root.dataset.fontSizes);
  const fontFamilies = parseFontFamilies(root.dataset.fontFamilies);
  return { loadUrl, postUrl, documentKey, applyUrl, processUrl, debug, fieldNameMode, fieldNameChoices, fieldNameOtherText, showFieldRect, fontSizes, fontFamilies };
}

/**
 * Parses font sizes from a JSON array of numbers.
 * @param raw - JSON string or undefined
 * @returns Array of positive integers; empty array on parse error or empty input
 */
export function parseFontSizes(raw: string | undefined): number[] {
  if (!raw || typeof raw !== 'string') return [];
  try {
    const parsed = JSON.parse(raw) as unknown;
    if (!Array.isArray(parsed)) return [];
    return parsed
      .map((n) => (typeof n === 'number' && Number.isFinite(n) ? Math.max(1, Math.round(n)) : typeof n === 'string' ? parseInt(n, 10) : NaN))
      .filter((n) => !Number.isNaN(n) && n >= 1);
  } catch {
    return [];
  }
}

/**
 * Parses font families from JSON (array of strings or { value, label? }). Same format as label choices.
 * @param raw - JSON string or undefined
 * @returns Array of LabelChoice; empty array on parse error or empty input
 */
export function parseFontFamilies(raw: string | undefined): LabelChoice[] {
  if (!raw || typeof raw !== 'string') return [];
  try {
    const parsed = JSON.parse(raw) as unknown;
    if (!Array.isArray(parsed)) return [];
    return parsed
      .map((item): LabelChoice => {
        if (typeof item === 'string') {
          const pipe = item.indexOf('|');
          if (pipe >= 0) return { value: item.slice(0, pipe).trim(), label: item.slice(pipe + 1).trim() };
          return { value: item.trim(), label: item.trim() };
        }
        if (item && typeof item === 'object' && 'value' in item) {
          const v = (item as { value?: string; label?: string }).value;
          const l = (item as { value?: string; label?: string }).label;
          return { value: typeof v === 'string' ? v : '', label: typeof l === 'string' ? l : undefined };
        }
        return { value: '', label: '' };
      })
      .filter((c) => c.value !== '');
  } catch {
    return [];
  }
}
