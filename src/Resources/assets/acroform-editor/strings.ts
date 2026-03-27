/**
 * @fileoverview AcroForm editor UI strings.
 *
 * Translations are not stored here; they are provided in a scalable way by the bundle:
 * 1. The bundle's Twig template (editor_root.html.twig) injects translated strings via
 *    <script type="application/json" id="acroform-editor-strings">...</script>, using
 *    nowo_pdf_signable_acroform_strings() which reads from the nowo_pdf_signable domain
 *    (acroform_editor.* keys in Resources/translations/).
 * 2. Optional: the page can override or extend via window.NowoPdfSignableAcroFormEditorStrings.
 *
 * DEFAULT_STRINGS is an empty fallback so that when no JSON script is present (e.g. tests
 * or partial integration), str(key) falls back to the key itself. In normal use the Twig-
 * injected JSON supplies all values.
 */
if (typeof console !== 'undefined') console.debug('[PdfSignable] loaded: acroform-editor/strings.ts');

/** Fallback map; empty so translations come from Twig-injected JSON or window override. */
export const DEFAULT_STRINGS: Record<string, string> = {};

/**
 * Escapes a string for safe use in HTML attributes (e.g. title, data-*).
 *
 * @param s - Raw string
 * @returns HTML-attribute-safe string
 */
export function escapeAttr(s: string): string {
  return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;');
}
