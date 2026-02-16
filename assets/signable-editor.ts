/**
 * PdfSignable bundle: PDF viewer with signature box overlays, units/origin, and optional touch zoom.
 * @fileoverview Entry point. Orchestrates load, render, overlays, thumbnails, touch zoom; delegates to signable-editor/* modules.
 * PDF.js: either from CDN (window.pdfjsLib) or via dynamic import of pdfjs-dist when pdfjsSource === 'npm'.
 */
if (typeof console !== 'undefined') console.debug('[PdfSignable] loaded: signable-editor.ts');
export type { NowoPdfSignableConfig } from './shared/types';
import type { PDFViewport, PDFDocumentProxy } from './shared/types';
import {
  getLoadUrl,
  getScaleForFitWidth,
  getScaleForFitPage,
  bindZoomToolbar,
  getPdfJsLib,
} from './shared';
import {
  ptToUnit,
  unitToPt,
  escapeHtml,
  getColorForBoxIndex,
  formToViewport,
  viewportToForm,
  pdfToFormCoords,
  drawGridOnCanvas,
  initSignaturePads,
  updateOverlays as updateBoxOverlays,
  setupBoxDragResizeRotate,
} from './signable-editor/index';
import { applyFontAutoSize } from './signable-editor/font-auto-size';
import { createTouchController } from './signable-editor/touch';
import { ensureThumbnailsLayout, buildThumbnailsAndLayout } from './signable-editor/thumbnails';
import { createAcroformMoveResize } from './acroform-editor/index';

import './pdf-signable.scss';

/**
 * Initializes the signable PDF viewer: resolves DOM refs from data attributes,
 * loads PDF on URL/button, renders pages with AcroForm annotations, thumbnails,
 * signature box overlays, unit/origin sync, zoom toolbar, and optional touch zoom.
 * Reads config from window.NowoPdfSignableConfig; no-op if config or widget root is missing.
 *
 * @internal Called once on DOMContentLoaded or when the script runs after DOM is ready.
 */
function run(): void {
  const config = window.NowoPdfSignableConfig;
  if (!config) {
    console.warn('[PdfSignable] NowoPdfSignableConfig not found, skipping init');
    return;
  }
  const { proxyUrl, strings, debug: debugMode = false } = config;

  /** Logs to console only when config.debug is true. */
  const debugLog = (...args: unknown[]): void => {
    if (debugMode) console.log('[PdfSignable]', ...args);
  };

  /** Warns to console only when config.debug is true. */
  const debugWarn = (...args: unknown[]): void => {
    if (debugMode) console.warn('[PdfSignable]', ...args);
  };

  const widget =
    document.querySelector<HTMLElement>('[data-pdf-signable="widget"]') ??
    document.querySelector<HTMLElement>('.nowo-pdf-signable-widget');
  const form = widget?.closest('form');
  if (!form || !widget) {
    debugWarn('Widget or form not found, skipping init');
    return;
  }
  const preventBoxOverlap = widget?.dataset.preventBoxOverlap === '1';
  const enableRotation = widget?.dataset.enableRotation === '1';
  let boxDefaultsByName: Record<string, { width?: number; height?: number; x?: number; y?: number; angle?: number }> = {};
  try {
    const raw = widget?.dataset.boxDefaultsByName ?? '{}';
    boxDefaultsByName = typeof raw === 'string' ? (JSON.parse(raw) as Record<string, { width?: number; height?: number; x?: number; y?: number; angle?: number }>) : {};
  } catch {
    boxDefaultsByName = {};
  }
  const snapGrid = Math.max(0, parseFloat(widget?.dataset.snapGrid ?? '0') || 0);
  const snapToBoxes = widget?.dataset.snapToBoxes === '1';
  const showGrid = widget?.dataset.showGrid === '1';
  const gridStep = Math.max(0, parseFloat(widget?.dataset.gridStep ?? '10') || 0);
  /** When true, width is fixed (no resize, use default_box_width). */
  const lockBoxWidth = widget?.dataset.lockBoxWidth === '1';
  /** When true, height is fixed (no resize, use default_box_height). */
  const lockBoxHeight = widget?.dataset.lockBoxHeight === '1';
  const defaultBoxWidthRaw = widget?.dataset.defaultBoxWidth ?? '';
  const defaultBoxHeightRaw = widget?.dataset.defaultBoxHeight ?? '';
  const defaultBoxWidth = defaultBoxWidthRaw !== '' ? parseFloat(defaultBoxWidthRaw) : null;
  const defaultBoxHeight = defaultBoxHeightRaw !== '' ? parseFloat(defaultBoxHeightRaw) : null;
  const minBoxWidthRaw = widget?.dataset.minBoxWidth ?? '';
  const minBoxHeightRaw = widget?.dataset.minBoxHeight ?? '';
  const minBoxWidthForm = minBoxWidthRaw !== '' ? parseFloat(minBoxWidthRaw) : 0;
  const minBoxHeightForm = minBoxHeightRaw !== '' ? parseFloat(minBoxHeightRaw) : 0;
  const lockBoxDimensions = lockBoxWidth && lockBoxHeight && defaultBoxWidth !== null && !Number.isNaN(defaultBoxWidth) && defaultBoxHeight !== null && !Number.isNaN(defaultBoxHeight);
  /** When true, show AcroForm/annotation outlines over the PDF so form fields are visible. */
  const showAcroform = widget?.dataset.showAcroform !== '0';
  /** When true (and showAcroform), render text fields as editable inputs so the user can type in AcroForm fields. */
  const acroformInteractive = widget?.dataset.acroformInteractive !== '0';
  const SNAP_THRESHOLD_PX = 10;

  /** Finds the page field (input or select) in a box item. Uses data-pdf-signable="page" or name fallback. */
  const getPageField = (container: Element): HTMLInputElement | HTMLSelectElement | null =>
    container.querySelector<HTMLInputElement | HTMLSelectElement>('[data-pdf-signable="page"]') ??
    container.querySelector<HTMLInputElement | HTMLSelectElement>('input[name$="[page]"], select[name$="[page]"]');

  debugLog('Initialized');

  const pdfUrlInput =
    form.querySelector<HTMLInputElement>('[data-pdf-signable="pdf-url"]') ??
    form.querySelector<HTMLInputElement>('.pdf-url-input');
  const loadPdfBtn = document.getElementById('loadPdfBtn');
  const pdfPlaceholder = document.getElementById('pdf-placeholder');
  const pdfCanvasWrapper = document.getElementById('pdf-canvas-wrapper');
  const signatureBoxesList =
    (widget?.querySelector<HTMLElement>('[data-pdf-signable="boxes-list"]') ?? document.getElementById('signature-boxes-list')) as HTMLElement | null;
  const addBoxBtn = document.getElementById('addBoxBtn');
  const unitSelector = form.querySelector<HTMLSelectElement>('[data-pdf-signable="unit"]');
  const originSelector = form.querySelector<HTMLSelectElement>('[data-pdf-signable="origin"]');
  const pdfViewerContainer = document.getElementById('pdf-viewer-container');
  const pdfZoomValue = document.getElementById('pdfZoomValue');

  if (!pdfCanvasWrapper) {
    debugWarn('Missing required container: pdf-canvas-wrapper');
    return;
  }
  const canvasWrapper = pdfCanvasWrapper;
  /** When absent (e.g. AcroForm editor page), use a dummy div so box-related code no-ops without branching. */
  const boxesList = signatureBoxesList ?? document.createElement('div');

  debugLog('DOM resolved', {
    widget: !!widget,
    boxesList: !!boxesList,
    hasPrototype: !!(boxesList?.dataset?.prototype),
    pdfUrlInput: !!pdfUrlInput,
    addBoxBtn: !!addBoxBtn,
    unitSelector: !!unitSelector,
    originSelector: !!originSelector,
  });

  /** Selector for box item rows: attribute (preferred) or class so overrides without data-pdf-signable still work. */
  const boxItemSelector =
    ':scope > [data-pdf-signable="box-item"], :scope > .signature-box-item';

  const touchController = createTouchController(pdfViewerContainer);

  /** AcroForm field rects per page (PDF coords [llx, lly, urx, ury]) to avoid placing signature boxes on top of them. */
  let acroformRectsByPage: Record<number, number[][]> = {};
  /** Collected AcroForm field definitions (full descriptor: id, rect, width, height, fieldType, value, page, fontSize, etc.) for the AcroForm editor panel. */
  const acroformFieldsList: Array<{
    id: unknown;
    rect: number[];
    width: number;
    height: number;
    fieldType: string;
    value?: string;
    page: number;
    subtype?: string;
    flags?: number;
    fieldName?: string;
    maxLen?: number;
    fontSize?: number;
  }> = [];
  function setAcroFormFieldsExposure(): void {
    (window as unknown as { __pdfSignableAcroFormFields?: typeof acroformFieldsList }).__pdfSignableAcroFormFields = acroformFieldsList;
  }
  setAcroFormFieldsExposure();

  /** Wraps buildThumbnailsAndLayout from thumbnails module (needs pdfDoc). */
  function runBuildThumbnails(): void {
    if (!pdfDoc || !pdfViewerContainer) return;
    buildThumbnailsAndLayout(pdfDoc, { pdfViewerContainer, canvasWrapper });
  }

  /** Creates touch wrapper and ensures thumbnail/scroll layout. Idempotent. */
  function ensureTouchAndLayout(): void {
    touchController.ensureWrapper(canvasWrapper);
    ensureThumbnailsLayout({
      pdfViewerContainer,
      canvasWrapper,
      touchWrapper: touchController.getWrapper(),
    });
  }

  /**
   * Returns the currently selected unit from the form (e.g. mm, pt).
   *
   * @returns The selected unit string (default 'mm')
   */
  function getSelectedUnit(): string {
    return unitSelector?.value ?? widget?.dataset.unitDefault ?? 'mm';
  }

  /**
   * Returns the currently selected coordinate origin (e.g. bottom_left).
   *
   * @returns The selected origin string (default 'bottom_left')
   */
  function getSelectedOrigin(): string {
    return originSelector?.value ?? widget?.dataset.originDefault ?? 'bottom_left';
  }

  let pdfDoc: PDFDocumentProxy | null = null;
  const pageViewports: Record<number, PDFViewport> = {};
  let renderTask: Promise<void> | null = null;
  /** Incremented on each loadPdf(); used to ignore render from a stale load (avoids duplicate/wrong pages). */
  let pdfLoadGeneration = 0;
  /** When true, updateOverlays skips to avoid clearing the overlay being dragged. */
  let isDragging = false;
  /** Set by setupBoxDragResizeRotate; used by updateOverlays and keyboard/click. */
  let boxDragController: ReturnType<typeof setupBoxDragResizeRotate> | null = null;
  /** Current PDF scale (updated on load, resize and zoom). */
  let currentPdfScale = 1.5;

  /**
   * Renders all PDF pages at the given scale into the canvas wrapper and updates signature box overlays.
   * Equivalent to PdfTemplate renderPagesAtScale (this bundle does not rescale form coordinates).
   * @param scale - Target scale factor (e.g. 1.5 = 150%)
   * @param forLoadId - If set, render is skipped when a newer load has started (avoids race where 3-page PDF shows as 4).
   */
  async function renderPdfAtScale(scale: number, forLoadId?: number): Promise<void> {
    if (!pdfDoc) return;
    if (forLoadId != null && forLoadId !== pdfLoadGeneration) return;
    scale = Math.max(0.5, scale);
    Object.keys(pageViewports).forEach((k) => delete pageViewports[Number(k)]);
    acroformRectsByPage = {};
    acroformFieldsList.length = 0;
    canvasWrapper.innerHTML = '';
    for (let num = 1; num <= pdfDoc.numPages; num++) {
      if (forLoadId != null && forLoadId !== pdfLoadGeneration) return;
      if (renderTask) await renderTask;
      const page = await pdfDoc.getPage(num);
      const viewport = page.getViewport({ scale });
      pageViewports[num] = viewport;
      const wrapper = document.createElement('div');
      wrapper.className = 'pdf-page-wrapper';
      wrapper.dataset.page = String(num);
      const canvas = document.createElement('canvas');
      const ctx = canvas.getContext('2d');
      if (!ctx) continue;
      canvas.height = viewport.height;
      canvas.width = viewport.width;
      canvas.dataset.page = String(num);
      const overlaysDiv = document.createElement('div');
      overlaysDiv.className = 'signature-overlays';
      wrapper.appendChild(canvas);
      if (showGrid && gridStep > 0) {
        const gridCanvas = document.createElement('canvas');
        gridCanvas.className = 'pdf-grid-overlay';
        gridCanvas.width = viewport.width;
        gridCanvas.height = viewport.height;
        gridCanvas.dataset.page = String(num);
        drawGridOnCanvas(gridCanvas, viewport, scale, getSelectedUnit(), gridStep);
        wrapper.appendChild(gridCanvas);
      }
      if (showAcroform) {
        const annotationLayer = document.createElement('div');
        annotationLayer.className = 'pdf-annotation-layer';
        annotationLayer.setAttribute('aria-hidden', 'true');
        wrapper.appendChild(annotationLayer);
        const inputsLayer = document.createElement('div');
        inputsLayer.className = 'pdf-annotation-inputs-layer';
        inputsLayer.setAttribute('aria-hidden', 'true');
        wrapper.appendChild(inputsLayer);
        type Annot = {
          rect?: number[];
          subtype?: string;
          fieldType?: string;
          value?: string;
          id?: unknown;
          flags?: number;
          fieldName?: string;
          maxLen?: number;
          defaultAppearanceData?: { fontSize?: number };
        };
        const MULTILINE_FLAG = 0x0001000;
        const pageWithAnnots = page as { getAnnotations?(params?: { intent?: string }): Promise<Annot[]> };
        if (typeof pageWithAnnots.getAnnotations === 'function') {
          const annotationStorage = (pdfDoc as { annotationStorage?: { setValue: (id: unknown, value: unknown) => void } }).annotationStorage;
          pageWithAnnots
            .getAnnotations({ intent: 'display' })
            .then((annotations) => {
              if (!annotations?.length) return;
              const s = viewport.scale ?? scale;
              const overrides: Record<string, Record<string, unknown>> =
                (window as Window & { __pdfSignableAcroFormOverrides?: Record<string, Record<string, unknown>> }).__pdfSignableAcroFormOverrides ?? {};
              let interactiveCount = 0;
              if (!acroformRectsByPage[num]) acroformRectsByPage[num] = [];
              const drawnIds = new Set<string>();
              annotations.forEach((ann, idx) => {
                const fieldType = (ann.fieldType ?? '') as string;
                const stableId = ann.id != null ? String(ann.id) : `p${num}-${idx}`;
                drawnIds.add(stableId);
                const override = overrides[stableId];
                if (override && (override.hidden === true || override.hidden === 'true')) return;
                const rect = (override?.rect as number[] | undefined) ?? ann.rect;
                if (!rect || rect.length < 4) return;
                const [llx, lly, urx, ury] = rect;
                const rectWidth = Math.max(0, urx - llx);
                const rectHeight = Math.max(0, ury - lly);
                acroformRectsByPage[num].push([llx, lly, urx, ury]);
                const fontSize = ann.defaultAppearanceData?.fontSize;
                acroformFieldsList.push({
                  id: ann.id != null ? ann.id : stableId,
                  rect: [llx, lly, urx, ury],
                  width: rectWidth,
                  height: rectHeight,
                  fieldType,
                  value: ann.value as string | undefined,
                  page: num,
                  subtype: ann.subtype,
                  flags: ann.flags,
                  fieldName: ann.fieldName,
                  maxLen: ann.maxLen,
                  fontSize,
                });
                const left = llx * s;
                const width = Math.max(0, (urx - llx) * s);
                const height = Math.max(0, (ury - lly) * s);
                const top = viewport.height - ury * s;
                const outline = document.createElement('div');
                outline.className = 'acroform-field-outline';
                outline.style.left = left + 'px';
                outline.style.top = top + 'px';
                outline.style.width = width + 'px';
                outline.style.height = height + 'px';
                outline.dataset.fieldId = stableId;
                if (ann.subtype) outline.dataset.subtype = String(ann.subtype);
                annotationLayer.appendChild(outline);
                const isWidget = ann.subtype === 'Widget';
                const controlType = (override?.controlType as string) ?? (fieldType === 'Ch' ? 'choice' : fieldType === 'Btn' ? 'checkbox' : '');
                const isText = fieldType === 'Tx' || (isWidget && fieldType !== 'Btn' && fieldType !== 'Sig');
                const useTextInput = controlType === 'textarea' || controlType === 'text' || (controlType === '' && isText);
                const defVal = (override?.defaultValue as string | undefined) ?? (ann.value as string | undefined) ?? '';
                if (acroformInteractive && height > 4) {
                  if ((controlType === 'select' || controlType === 'choice')) {
                    const opts = (override?.options as Array<{ value?: string; label?: string }>) ?? [];
                    const select = document.createElement('select');
                    select.className = 'acroform-field-input';
                    select.dataset.fieldId = stableId;
                    select.style.left = left + 'px';
                    select.style.top = top + 'px';
                    select.style.width = width + 'px';
                    select.style.height = height + 'px';
                    opts.forEach((o) => {
                      const opt = document.createElement('option');
                      opt.value = o.value ?? '';
                      opt.textContent = o.label ?? o.value ?? '';
                      if (opt.value === defVal) opt.selected = true;
                      select.appendChild(opt);
                    });
                    if (!opts.some((o) => (o.value ?? '') === defVal) && defVal) {
                      const opt = document.createElement('option');
                      opt.value = defVal;
                      opt.textContent = defVal;
                      opt.selected = true;
                      select.insertBefore(opt, select.firstChild);
                    }
                    select.setAttribute('aria-label', (override?.label as string) || 'PDF form field');
                    inputsLayer.appendChild(select);
                    interactiveCount++;
                    if (annotationStorage?.setValue && ann.id != null) {
                      select.addEventListener('change', () => {
                        try {
                          annotationStorage.setValue(ann.id, select.value);
                        } catch {
                          // ignore
                        }
                      });
                    }
                  } else if (controlType === 'checkbox') {
                    const ov = override as Record<string, unknown> | undefined;
                    const valueOn = (ov?.checkboxValueOn as string) ?? '1';
                    const valueOff = (ov?.checkboxValueOff as string) ?? '0';
                    const icon = (ov?.checkboxIcon as string) || 'check';
                    const input = document.createElement('input');
                    input.type = 'checkbox';
                    input.className = 'acroform-field-input acroform-checkbox-icon-' + (icon === 'cross' || icon === 'dot' ? icon : 'check');
                    input.dataset.fieldId = stableId;
                    input.style.left = left + 'px';
                    input.style.top = top + 'px';
                    const isCheckedByValue = defVal === valueOn || (valueOn === '1' && /^(1|true|yes|on)$/i.test(defVal.trim()));
                    input.checked = isCheckedByValue;
                    input.setAttribute('aria-label', (override?.label as string) || 'PDF form field');
                    inputsLayer.appendChild(input);
                    interactiveCount++;
                    if (annotationStorage?.setValue && ann.id != null) {
                      input.addEventListener('change', () => {
                        try {
                          annotationStorage.setValue(ann.id, input.checked ? valueOn : valueOff);
                        } catch {
                          // ignore
                        }
                      });
                    }
                  } else if (useTextInput) {
                    const multiline =
                      controlType === 'textarea' || !!(ann.flags && (ann.flags & MULTILINE_FLAG)) || height > 28;
                    const input = multiline
                      ? document.createElement('textarea')
                      : document.createElement('input');
                    if (input instanceof HTMLInputElement) input.setAttribute('type', 'text');
                    input.className = 'acroform-field-input';
                    input.dataset.fieldId = stableId;
                    input.style.left = left + 'px';
                    input.style.top = top + 'px';
                    input.style.width = width + 'px';
                    input.style.height = height + 'px';
                    const ov = override as Record<string, unknown> | undefined;
                    const defaultFontSize = 11;
                    const defaultFontFamily = 'sans-serif';
                    const fontSize = (ov && typeof ov.fontSize === 'number' && ov.fontSize > 0) ? (ov.fontSize as number) : defaultFontSize;
                    const fontFamily = (ov && typeof ov.fontFamily === 'string' && ov.fontFamily) ? (ov.fontFamily as string) : defaultFontFamily;
                    input.style.fontSize = String(fontSize) + 'px';
                    input.style.fontFamily = fontFamily;
                    const needFontAutoSize = !!(ov && ov.fontAutoSize === true);
                    if (needFontAutoSize) input.dataset.fontAutoSize = '1';
                    if (input instanceof HTMLTextAreaElement) input.value = defVal;
                    else (input as HTMLInputElement).value = defVal;
                    input.setAttribute('aria-label', (override?.label as string) || 'PDF form field');
                    inputsLayer.appendChild(input);
                    if (needFontAutoSize) applyFontAutoSize(input);
                    interactiveCount++;
                    if (annotationStorage?.setValue && ann.id != null) {
                      input.addEventListener('input', () => {
                        if (needFontAutoSize) applyFontAutoSize(input);
                        try {
                          annotationStorage.setValue(ann.id, (input instanceof HTMLTextAreaElement ? input.value : (input as HTMLInputElement).value));
                        } catch {
                          // ignore
                        }
                      });
                    }
                  }
                }
              });
              // Render override-only (new) fields that are not in the PDF annotations
              Object.entries(overrides).forEach(([fieldId, o]) => {
                if (drawnIds.has(fieldId)) return;
                if (o?.page !== num) return;
                if (o.hidden === true || o.hidden === 'true') return;
                const rect = o.rect as number[] | undefined;
                if (!rect || rect.length < 4) return;
                const [llx, lly, urx, ury] = rect;
                const rectWidth = Math.max(0, urx - llx);
                const rectHeight = Math.max(0, ury - lly);
                acroformRectsByPage[num].push([llx, lly, urx, ury]);
                acroformFieldsList.push({
                  id: fieldId,
                  rect: [llx, lly, urx, ury],
                  width: rectWidth,
                  height: rectHeight,
                  fieldType: (o.fieldType as string) ?? 'Tx',
                  value: (o.defaultValue as string) ?? '',
                  page: num,
                  subtype: 'Widget',
                  flags: 0,
                  fieldName: (o.label as string) ?? '',
                  maxLen: undefined,
                  fontSize: (o.fontSize as number) ?? undefined,
                });
                const left = llx * s;
                const width = Math.max(0, (urx - llx) * s);
                const height = Math.max(0, (ury - lly) * s);
                const top = viewport.height - ury * s;
                const outline = document.createElement('div');
                outline.className = 'acroform-field-outline';
                outline.style.left = left + 'px';
                outline.style.top = top + 'px';
                outline.style.width = width + 'px';
                outline.style.height = height + 'px';
                outline.dataset.fieldId = fieldId;
                outline.dataset.subtype = 'Widget';
                annotationLayer.appendChild(outline);
                const controlType = (o.controlType as string) || 'text';
                const defVal = (o.defaultValue as string) ?? '';
                if (acroformInteractive && height > 4) {
                  if ((controlType === 'select' || controlType === 'choice')) {
                    const opts = (o.options as Array<{ value?: string; label?: string }>) ?? [];
                    const select = document.createElement('select');
                    select.className = 'acroform-field-input';
                    select.dataset.fieldId = fieldId;
                    select.style.left = left + 'px';
                    select.style.top = top + 'px';
                    select.style.width = width + 'px';
                    select.style.height = height + 'px';
                    opts.forEach((opt) => {
                      const option = document.createElement('option');
                      option.value = opt.value ?? '';
                      option.textContent = opt.label ?? opt.value ?? '';
                      if (option.value === defVal) option.selected = true;
                      select.appendChild(option);
                    });
                    if (!opts.some((opt) => (opt.value ?? '') === defVal) && defVal) {
                      const option = document.createElement('option');
                      option.value = defVal;
                      option.textContent = defVal;
                      option.selected = true;
                      select.insertBefore(option, select.firstChild);
                    }
                    select.setAttribute('aria-label', (o.label as string) || 'PDF form field');
                    inputsLayer.appendChild(select);
                    interactiveCount++;
                  } else if (controlType === 'checkbox') {
                    const valueOn = (o.checkboxValueOn as string) ?? '1';
                    const valueOff = (o.checkboxValueOff as string) ?? '0';
                    const icon = (o.checkboxIcon as string) || 'check';
                    const input = document.createElement('input');
                    input.type = 'checkbox';
                    input.className = 'acroform-field-input acroform-checkbox-icon-' + (icon === 'cross' || icon === 'dot' ? icon : 'check');
                    input.dataset.fieldId = fieldId;
                    input.style.left = left + 'px';
                    input.style.top = top + 'px';
                    const isCheckedByValue = defVal === valueOn || (valueOn === '1' && /^(1|true|yes|on)$/i.test(defVal.trim()));
                    input.checked = isCheckedByValue;
                    input.setAttribute('aria-label', (o.label as string) || 'PDF form field');
                    inputsLayer.appendChild(input);
                    interactiveCount++;
                  } else {
                    const multiline = controlType === 'textarea' || height > 28;
                    const input = multiline ? document.createElement('textarea') : document.createElement('input');
                    if (input instanceof HTMLInputElement) input.setAttribute('type', 'text');
                    input.className = 'acroform-field-input';
                    input.dataset.fieldId = fieldId;
                    input.style.left = left + 'px';
                    input.style.top = top + 'px';
                    input.style.width = width + 'px';
                    input.style.height = height + 'px';
                    const ov = o as Record<string, unknown>;
                    const defaultFontSize = 11;
                    const defaultFontFamily = 'sans-serif';
                    const fontSize = (typeof ov.fontSize === 'number' && ov.fontSize > 0) ? (ov.fontSize as number) : defaultFontSize;
                    const fontFamily = (typeof ov.fontFamily === 'string' && ov.fontFamily) ? (ov.fontFamily as string) : defaultFontFamily;
                    input.style.fontSize = String(fontSize) + 'px';
                    input.style.fontFamily = fontFamily;
                    const needFontAutoSize = !!(ov.fontAutoSize === true);
                    if (needFontAutoSize) input.dataset.fontAutoSize = '1';
                    if (input instanceof HTMLTextAreaElement) input.value = defVal;
                    else (input as HTMLInputElement).value = defVal;
                    input.setAttribute('aria-label', (o.label as string) || 'PDF form field');
                    inputsLayer.appendChild(input);
                    if (needFontAutoSize) applyFontAutoSize(input);
                    interactiveCount++;
                  }
                }
              });
              debugLog('AcroForm layer', { page: num, annotationCount: annotations.length, interactiveCount });
              window.dispatchEvent(new CustomEvent('pdf-signable-acroform-fields-updated', { detail: { page: num, totalFields: acroformFieldsList.length } }));
            })
            .catch((err) => {
              debugWarn('getAnnotations failed', { page: num, err });
            });
        }
      }
      wrapper.appendChild(overlaysDiv);
      renderTask = page.render({ canvasContext: ctx, viewport }).promise;
      await renderTask;
      canvasWrapper.appendChild(wrapper);
    }
    const overlayContainers = canvasWrapper.querySelectorAll('.signature-overlays');
    debugLog('PDF DOM built', { pages: pdfDoc.numPages, overlayContainers: overlayContainers.length });
    if (pdfZoomValue) pdfZoomValue.textContent = Math.round(scale * 100) + '%';
    updateOverlays();
  }

  /** Zoom toolbar: callbacks update scale and re-render. */
  bindZoomToolbar({
    container: pdfViewerContainer,
    onZoomOut: () => {
      if (!pdfDoc) return;
      currentPdfScale = Math.max(0.5, currentPdfScale / 1.25);
      renderPdfAtScale(currentPdfScale);
    },
    onZoomIn: () => {
      if (!pdfDoc) return;
      currentPdfScale = Math.min(4, currentPdfScale * 1.25);
      renderPdfAtScale(currentPdfScale);
    },
    onFitWidth: async () => {
      if (!pdfDoc) return;
      const scale = await getScaleForFitWidth(pdfDoc, pdfViewerContainer);
      currentPdfScale = scale;
      await renderPdfAtScale(scale);
    },
    onFitPage: async () => {
      if (!pdfDoc) return;
      const scale = await getScaleForFitPage(pdfDoc, pdfViewerContainer);
      currentPdfScale = scale;
      await renderPdfAtScale(scale);
    },
  });

  /**
   * Loads the PDF from the URL (or proxy), renders pages at fit-width scale, builds thumbnails and overlays. Same name as PdfTemplateBundle.
   */
  async function loadPdf(): Promise<void> {
    const url = pdfUrlInput?.value?.trim() ?? '';
    if (!url) {
      debugWarn('Load PDF: URL required');
      alert(strings.alert_url_required);
      return;
    }
    const thisLoadId = ++pdfLoadGeneration;
    const loadUrl = getLoadUrl(proxyUrl, url);
    debugLog('Loading PDF', { url, viaProxy: loadUrl !== url });
    if (loadPdfBtn) {
      loadPdfBtn.setAttribute('disabled', '');
      loadPdfBtn.innerHTML =
        '<span class="spinner-border spinner-border-sm me-1"></span> ' + strings.loading_state;
    }
    if (pdfPlaceholder) pdfPlaceholder.style.display = 'block';
    canvasWrapper.style.display = 'none';
    canvasWrapper.innerHTML = '';
    const strip = pdfViewerContainer?.querySelector('#pdf-thumbnails-strip');
    const scroll = pdfViewerContainer?.querySelector('.pdf-viewer-scroll');
    const themeToolbar = scroll?.querySelector('.pdf-zoom-toolbar');
    if (themeToolbar && pdfViewerContainer) {
      pdfViewerContainer.insertBefore(themeToolbar, pdfViewerContainer.firstChild);
    }
    const touchWrapperEl = touchController.getWrapper();
    if (scroll && touchWrapperEl && touchWrapperEl.parentElement === scroll) {
      scroll.removeChild(touchWrapperEl);
      pdfViewerContainer?.appendChild(touchWrapperEl);
    }
    strip?.remove();
    scroll?.remove();
    pdfViewerContainer?.querySelector('#pdf-zoom-toolbar')?.remove();
    touchController.reset();

    const loadingOverlay = document.createElement('div');
    loadingOverlay.className = 'pdf-signable-loading-overlay';
    loadingOverlay.setAttribute('aria-live', 'polite');
    loadingOverlay.setAttribute('aria-busy', 'true');
    loadingOverlay.innerHTML =
      '<div class="text-center"><div class="spinner-border text-primary mb-2" role="status"><span class="visually-hidden">' +
      strings.loading_state +
      '</span></div><p class="text-muted small mb-0">' +
      strings.loading_state +
      '</p></div>';
    if (pdfViewerContainer) pdfViewerContainer.appendChild(loadingOverlay);

    try {
      const fetchRes = await fetch(loadUrl);
      if (!fetchRes.ok) {
        debugLog('PDF fetch failed', { status: fetchRes.status, url: loadUrl });
        alert(strings.pdf_not_found);
        return;
      }
      const arrayBuffer = await fetchRes.arrayBuffer();
      const pdfjsLib = await getPdfJsLib(config!);
      pdfDoc = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
      if (thisLoadId !== pdfLoadGeneration) return;
      Object.keys(pageViewports).forEach((k) => delete pageViewports[Number(k)]);
      ensureTouchAndLayout();
      const scale = await getScaleForFitWidth(pdfDoc, pdfViewerContainer);
      currentPdfScale = scale;
      await renderPdfAtScale(scale, thisLoadId);

      if (pdfPlaceholder) pdfPlaceholder.style.display = 'none';
      canvasWrapper.style.display = 'block';
      runBuildThumbnails();
      // First load (signable and acroform): fit width is already applied; scroll to first page
      const scrollToFirstPage = (): void => {
        const scrollEl = pdfViewerContainer?.querySelector<HTMLElement>('.pdf-viewer-scroll');
        if (scrollEl) {
          scrollEl.scrollTop = 0;
          scrollEl.scrollLeft = 0;
        }
        const firstPage = canvasWrapper.querySelector<HTMLElement>('.pdf-page-wrapper[data-page="1"]');
        if (firstPage) {
          firstPage.scrollIntoView({ block: 'start', behavior: 'auto' });
        }
      };
      scrollToFirstPage();
      requestAnimationFrame(scrollToFirstPage);
      setTimeout(scrollToFirstPage, 100);
      // Draw overlays when landing with data: run after paint and with retries so boxes always show
      const scheduleOverlayUpdates = (): void => {
        updateOverlays();
        setTimeout(updateOverlays, 100);
        setTimeout(updateOverlays, 350);
        setTimeout(updateOverlays, 700);
      };
      requestAnimationFrame(scheduleOverlayUpdates);
      debugLog('PDF loaded', { pages: pdfDoc!.numPages, scale });
    } catch (err) {
      debugLog('PDF load failed', err);
      alert(strings.pdf_not_found);
    } finally {
      loadingOverlay.remove();
      if (loadPdfBtn) {
        loadPdfBtn.removeAttribute('disabled');
        loadPdfBtn.innerHTML = strings.load_pdf_btn;
      }
    }
  }

  /** Syncs signature box overlays from form values; delegates to box-overlays module. */
  function updateOverlays(): void {
    updateBoxOverlays({
      canvasWrapper,
      boxesList,
      boxItemSelector,
      pageViewports,
      getSelectedUnit,
      getSelectedOrigin,
      getPageField,
      formToViewport,
      unitToPt,
      getColorForBoxIndex,
      escapeHtml,
      enableRotation,
      lockBoxDimensions,
      selectedBoxIndex: boxDragController?.getSelectedBoxIndex() ?? null,
      isDragging,
      debugLog,
      debugWarn,
    });
  }

  /** Calls the signature-pad module to init pads; use after updateOverlays is defined. */
  const runInitSignaturePads = (): void => {
    initSignaturePads(widget ?? document.querySelector<HTMLElement>('[data-pdf-signable="widget"]'), {
      onOverlayUpdate: updateOverlays,
      debugLog,
      debugWarn,
    });
  };

  /**
   * Reads the max signature boxes allowed from data attributes.
   * @returns Max entries or null if unlimited
   */
  function getMaxEntries(): number | null {
    const raw = boxesList.dataset.maxEntries ?? addBoxBtn?.dataset.maxEntries ?? '';
    if (raw === '') return null;
    const n = parseInt(raw, 10);
    return Number.isNaN(n) ? null : n;
  }

  /** Hides the Add box button when box count has reached max_entries. */
  function updateAddButtonVisibility(): void {
    if (!addBoxBtn) return;
    const max = getMaxEntries();
    const count = boxesList.querySelectorAll(boxItemSelector).length;
    if (max !== null && count >= max) {
      addBoxBtn.style.display = 'none';
    } else {
      addBoxBtn.style.display = '';
    }
  }

  /**
   * Adds a new signature box to the list and updates overlays. Respects max_entries.
   * @param page - PDF page number (1-based)
   * @param xPdf - X in PDF points
   * @param yPdf - Y in PDF points
   * @param width - Box width in points (default 150)
   * @param height - Box height in points (default 40)
   */
  /**
   * Adds a new signature box: creates form row from prototype, appends to list, syncs overlay.
   * Optionally applies box_defaults_by_name and snap. Does not run when at max_entries.
   *
   * @param pageNum - 1-based page number
   * @param boxLlx - Left X in PDF points (bottom-left origin)
   * @param boxLly - Bottom Y in PDF points
   * @param defaultWidthPt - Default width in points
   * @param defaultHeightPt - Default height in points
   */
  function addSignatureBox(
    page: number,
    xPdf: number,
    yPdf: number,
    width?: number,
    height?: number
  ): void {
    const max = getMaxEntries();
    const currentCount = boxesList.querySelectorAll(boxItemSelector).length;
    if (max !== null && currentCount >= max) {
      debugLog('Box add skipped: max_entries reached', { max, currentCount });
      return;
    }

    const unit = getSelectedUnit();
    let w = width ?? 150;
    let h = height ?? 40;
    if (lockBoxDimensions && defaultBoxWidth !== null && defaultBoxHeight !== null) {
      w = unitToPt(defaultBoxWidth, unit);
      h = unitToPt(defaultBoxHeight, unit);
    }
    const emptyEl = document.getElementById('signature-boxes-empty');
    if (emptyEl) emptyEl.classList.add('d-none');

    const prototype = boxesList.dataset.prototype ?? '';
    if (!prototype.trim()) {
      debugWarn('addBox: dataset.prototype is empty; check template has data-prototype on the boxes list');
      return;
    }
    const index = boxesList.querySelectorAll(boxItemSelector).length;
    const html = prototype.replace(/__name__/g, String(index));

    // Append the prototype root element only (it already has data-pdf-signable="box-item").
    // Do not wrap in another div, otherwise we get two box items per box
    // and on remove only the inner one is removed, leaving an empty shell that draws a box at (0,0).
    const temp = document.createElement('div');
    temp.innerHTML = html;
    const div = temp.firstElementChild as HTMLElement;
    if (!div) {
      debugWarn('addBox: prototype HTML produced no root element; check template structure');
      return;
    }
    div.dataset.index = String(index);

    const pageStr = String(page);
    const pageInput = getPageField(div);
    if (!pageInput) {
      debugWarn('addBox: new box has no page field (data-pdf-signable="page" or name$="[page]"); overlays may be wrong');
    } else {
      if (pageInput instanceof HTMLSelectElement) {
        if (!Array.from(pageInput.options).some((o) => o.value === pageStr)) {
          const opt = document.createElement('option');
          opt.value = pageStr;
          opt.textContent = pageStr;
          pageInput.appendChild(opt);
        }
      }
      pageInput.value = pageStr;
    }

    const viewport = pageViewports[page];
    const origin = getSelectedOrigin();
    const wPt = w;
    const hPt = h;
    const s = viewport ? viewport.scale : 1.5;
    const pageW = viewport ? viewport.width / s : 595;
    const pageH = viewport ? viewport.height / s : 842;
    const { xForm, yForm } = pdfToFormCoords(pageW, pageH, xPdf, yPdf, wPt, hPt, origin);

    const round = (v: number): number => Math.round(v * 100) / 100;
    const nameInput = div.querySelector<HTMLInputElement | HTMLSelectElement>('[data-pdf-signable="name"]');
    const missingCoord: string[] = [];
    for (const f of ['x', 'y', 'width', 'height']) {
      const inp = div.querySelector<HTMLInputElement>(`[data-pdf-signable="${f}"]`);
      if (!inp) {
        missingCoord.push(f);
        continue;
      }
      if (f === 'x') inp.value = String(round(ptToUnit(xForm, unit)));
      else if (f === 'y') inp.value = String(round(ptToUnit(yForm, unit)));
      else if (f === 'width') inp.value = String(round(ptToUnit(wPt, unit)));
      else if (f === 'height') inp.value = String(round(ptToUnit(hPt, unit)));
    }
    if (missingCoord.length) {
      debugWarn('addBox: new box missing coordinate inputs (template may have removed them)', { missing: missingCoord });
    }
    const angleInp = div.querySelector<HTMLInputElement>('[data-pdf-signable="angle"]');
    if (angleInp) angleInp.value = '0';
    if (nameInput) nameInput.value = strings.default_box_name;

    boxesList.appendChild(div);
    div.scrollIntoView({ behavior: 'smooth' });
    updateOverlays();
    runInitSignaturePads();
    updateAddButtonVisibility();
    debugLog('Box added', { page, x: xForm, y: yForm, width: w, height: h, unit });
  }

  if (loadPdfBtn) loadPdfBtn.addEventListener('click', () => loadPdf());

  const unitBadge = document.getElementById('unitBadge');
  const unitLabels: Record<string, string> = {
    pt: 'pt',
    mm: 'mm',
    cm: 'cm',
    px: 'px',
    in: 'in',
  };

  /** Updates the unit badge text to the currently selected unit. */
  function updateUnitBadge(): void {
    if (unitBadge)
      unitBadge.textContent = unitLabels[getSelectedUnit()] ?? getSelectedUnit();
  }
  updateUnitBadge();

  let currentDisplayUnit = getSelectedUnit();
  if (unitSelector) {
    unitSelector.addEventListener('change', () => {
      const oldUnit = currentDisplayUnit;
      const newUnit = getSelectedUnit();
      updateUnitBadge();
      boxesList.querySelectorAll(boxItemSelector).forEach((item) => {
        for (const f of ['x', 'y', 'width', 'height']) {
          const inp = item.querySelector<HTMLInputElement>(`[data-pdf-signable="${f}"]`);
          if (inp?.value) {
            const v = parseFloat(inp.value);
            inp.value = String(
              Math.round(ptToUnit(unitToPt(v, oldUnit), newUnit) * 100) / 100
            );
          }
        }
      });
      currentDisplayUnit = newUnit;
      updateOverlays();
    });
  }

  boxesList.addEventListener('input', updateOverlays);
  boxesList.addEventListener('change', (e) => {
    const target = (e.target as HTMLElement).closest('[data-pdf-signable="box-item"], .signature-box-item');
    const nameEl = target?.querySelector<HTMLInputElement | HTMLSelectElement>('[data-pdf-signable="name"]');
    if (nameEl && e.target === nameEl && Object.keys(boxDefaultsByName).length > 0) {
      const name = (nameEl.value ?? '').trim();
      const def = name ? boxDefaultsByName[name] : null;
      if (def) {
        const set = (cls: string, val: number | undefined): void => {
          const inp = target?.querySelector<HTMLInputElement>(`[data-pdf-signable="${cls}"]`);
          if (inp && val !== undefined) inp.value = String(val);
        };
        set('width', def.width);
        set('height', def.height);
        set('x', def.x);
        set('y', def.y);
        set('angle', def.angle);
      }
    }
    updateOverlays();
  });
  if (originSelector) originSelector.addEventListener('change', updateOverlays);

  boxesList.addEventListener('click', (e) => {
    if (!(e.target as HTMLElement).closest('.remove-box')) return;
    const item = (e.target as HTMLElement).closest('[data-pdf-signable="box-item"], .signature-box-item') as HTMLElement | null;
    if (item) {
      // Remove the top-level box item (direct child of list, data-pdf-signable="box-item") so we don't leave
      // an empty wrapper that would be drawn as a box at (0,0).
      let topLevel: HTMLElement | null = item;
      while (topLevel?.parentElement && topLevel.parentElement !== boxesList) {
        topLevel = topLevel.parentElement as HTMLElement;
      }
      if (topLevel) {
        const allItems = Array.from(boxesList.querySelectorAll(boxItemSelector));
        const idx = allItems.indexOf(topLevel);
        if (idx === -1) {
          debugWarn('Remove box: clicked item is not a direct box-item child of the list; template structure may be wrong', { topLevelTag: topLevel?.tagName, listChildCount: boxesList.children.length });
        } else {
          debugLog('Box removed', { index: idx, remainingBeforeRemove: allItems.length });
          topLevel.remove();
          const remaining = boxesList.querySelectorAll(boxItemSelector).length;
          debugLog('Box removed done', { remaining });
        }
      } else {
        debugWarn('Remove box: could not find top-level box item (parent chain does not reach boxesList)');
      }
    }
    if (boxesList.querySelectorAll(boxItemSelector).length === 0) {
      const emptyEl = document.getElementById('signature-boxes-empty');
      if (emptyEl) emptyEl.classList.remove('d-none');
    }
    updateOverlays();
    updateAddButtonVisibility();
  });

  if (pdfViewerContainer) {
    let resizeTimeout: ReturnType<typeof setTimeout>;
    let isReRendering = false;
    let lastObservedWidth = 0;
    const ro = new ResizeObserver(() => {
      if (isReRendering) return;
      const w = pdfViewerContainer?.clientWidth ?? 0;
      if (Math.abs(w - lastObservedWidth) < 10) return;
      lastObservedWidth = w;
      clearTimeout(resizeTimeout);
      resizeTimeout = setTimeout(async () => {
        if (!pdfDoc || canvasWrapper.style.display === 'none') return;
        if (isReRendering) return;
        isReRendering = true;
        try {
          const scale = await getScaleForFitWidth(pdfDoc, pdfViewerContainer);
          currentPdfScale = scale;
          await renderPdfAtScale(scale);
          if (pdfViewerContainer) lastObservedWidth = pdfViewerContainer.clientWidth;
        } finally {
          isReRendering = false;
        }
        }, 200);
    });
    ro.observe(pdfViewerContainer);
  }

  const acroformRoot = document.getElementById('acroform-editor-root');
  const acroformMinW = acroformRoot?.dataset.minFieldWidth != null ? parseFloat(acroformRoot.dataset.minFieldWidth) : NaN;
  const acroformMinH = acroformRoot?.dataset.minFieldHeight != null ? parseFloat(acroformRoot.dataset.minFieldHeight) : NaN;
  const acroformMoveResize = createAcroformMoveResize({
    canvasWrapper,
    getPageViewport: (pageNum: number) => pageViewports[pageNum],
    getTouchScale: () => touchController.getScale(),
    onRectChanged: (fieldId: string, rect: [number, number, number, number]) => {
      window.dispatchEvent(new CustomEvent('pdf-signable-acroform-rect-changed', { detail: { fieldId, rect } }));
    },
    onRendered: () => {
      if (pdfDoc && currentPdfScale) renderPdfAtScale(currentPdfScale);
    },
    minFieldWidthPt: Number.isNaN(acroformMinW) ? undefined : acroformMinW,
    minFieldHeightPt: Number.isNaN(acroformMinH) ? undefined : acroformMinH,
  });

  window.addEventListener('pdf-signable-acroform-move-resize', ((e: CustomEvent<{ fieldId: string; page: string }>) => {
    const { fieldId, page } = e.detail ?? {};
    if (fieldId && page) acroformMoveResize.showOverlay(fieldId, page);
  }) as EventListener);

  window.addEventListener('pdf-signable-acroform-move-resize-close', () => {
    acroformMoveResize.hideOverlay();
  });

  window.addEventListener('pdf-signable-acroform-overrides-updated', async () => {
    if (!pdfDoc || !currentPdfScale) return;
    await renderPdfAtScale(currentPdfScale);
    const win = window as Window & { __pdfSignableAcroFormMoveResizeFieldId?: string; __pdfSignableAcroFormMoveResizePage?: string };
    const fieldId = win.__pdfSignableAcroFormMoveResizeFieldId;
    const page = win.__pdfSignableAcroFormMoveResizePage;
    if (fieldId && page) acroformMoveResize.showOverlay(fieldId, page);
  });

  window.addEventListener('pdf-signable-acroform-add-field-mode', ((e: CustomEvent<{ active: boolean }>) => {
    const win = window as Window & { __pdfSignableAcroFormEditMode?: boolean };
    const active = e.detail?.active === true;
    win.__pdfSignableAcroFormEditMode = active;
    if (widget) {
      if (active) widget.classList.add('acroform-edit-mode');
      else widget.classList.remove('acroform-edit-mode');
    }
  }) as EventListener);

  window.addEventListener('pdf-signable-acroform-edit-mode', ((e: CustomEvent<{ active: boolean }>) => {
    const win = window as Window & { __pdfSignableAcroFormEditMode?: boolean };
    const active = e.detail?.active === true;
    win.__pdfSignableAcroFormEditMode = active;
    if (widget) {
      if (active) widget.classList.add('acroform-edit-mode');
      else widget.classList.remove('acroform-edit-mode');
    }
  }) as EventListener);

  /** When user focuses or clicks an AcroForm input on the PDF, notify the editor to highlight the matching list row. */
  function notifyAcroFormInputFocused(el: HTMLElement): void {
    const input = el.closest('.acroform-field-input') as HTMLElement | null;
    if (!input) return;
    const fieldId = input.getAttribute('data-field-id');
    const wrapper = input.closest('.pdf-page-wrapper') as HTMLElement | null;
    const page = wrapper?.dataset?.page ?? '1';
    if (fieldId) {
      window.dispatchEvent(
        new CustomEvent('pdf-signable-acroform-field-focused', { detail: { fieldId, page: parseInt(page, 10) } })
      );
    }
  }

  canvasWrapper.addEventListener('focusin', (e) => {
    const target = e.target as HTMLElement;
    if (target.closest('.acroform-field-input')) notifyAcroFormInputFocused(target);
  });

  // Auto-load PDF when DOM is ready so #signature-boxes-list and form values are available for overlays
  /** Loads PDF from current URL input if non-empty (e.g. preset URL). */
  function startAutoLoad(): void {
    if (!pdfUrlInput?.value?.trim()) return;
    debugLog('Auto-loading PDF (preset URL)');
    loadPdf();
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      startAutoLoad();
      runInitSignaturePads();
    });
  } else {
    startAutoLoad();
    runInitSignaturePads();
  }

  /** Returns true if the box [llx, lly, urx, ury] in PDF coords overlaps any AcroForm field on the page. */
  /**
   * Returns true if the given PDF rect (in points) overlaps any AcroForm field rect on the page.
   * Used to prevent placing signature boxes on top of form fields.
   *
   * @param pageNum - 1-based page number
   * @param llx - Left X in PDF points
   * @param lly - Bottom Y in PDF points
   * @param urx - Right X in PDF points
   * @param ury - Top Y in PDF points
   * @returns True if the rect intersects any AcroForm field on the page
   */
  function boxOverlapsAcroform(pageNum: number, llx: number, lly: number, urx: number, ury: number): boolean {
    const rects = acroformRectsByPage[pageNum];
    if (!rects?.length) return false;
    for (const r of rects) {
      if (llx < r[2] && urx > r[0] && lly < r[3] && ury > r[1]) return true;
    }
    return false;
  }

  canvasWrapper.addEventListener('click', (e) => {
    if ((e.target as HTMLElement).closest('[data-pdf-signable="overlay"]')) return;
    const target = e.target as HTMLElement;
    if (target.closest('.acroform-field-input')) notifyAcroFormInputFocused(target);
    const winEdit = window as Window & { __pdfSignableAcroFormEditMode?: boolean; __pdfSignableAcroFormAddFieldMode?: boolean };
    if (winEdit.__pdfSignableAcroFormEditMode && winEdit.__pdfSignableAcroFormAddFieldMode) {
      const input = target.closest('.acroform-field-input');
      if (input) return;
      if (target.closest('.acroform-field-outline')) return;
      // Add field: click on empty canvas places a new AcroForm field (only when Add field mode is active). Move/resize via list row button.
      const wrapper = target.closest('.pdf-page-wrapper');
      const canvas = wrapper?.querySelector<HTMLCanvasElement>('canvas');
      if (canvas && pdfDoc) {
        e.preventDefault();
        e.stopPropagation();
        const pageNum = parseInt(canvas.dataset.page ?? '1', 10);
        const viewport = pageViewports[pageNum];
        if (viewport) {
          const rect = canvas.getBoundingClientRect();
          const scaleX = canvas.width / rect.width;
          const scaleY = canvas.height / rect.height;
          const clickX = (e.clientX - rect.left) * scaleX;
          const clickY = (e.clientY - rect.top) * scaleY;
          const defaultW = 100;
          const defaultH = 20;
          const heightVp = defaultH * (viewport.scale ?? 1.5);
          const pdfCoords = viewport.convertToPdfPoint(clickX, clickY + heightVp);
          window.dispatchEvent(
            new CustomEvent('pdf-signable-acroform-add-field-place', {
              detail: { page: pageNum, llx: pdfCoords[0], lly: pdfCoords[1], width: defaultW, height: defaultH },
            }),
          );
        }
        return;
      }
      return;
    }
    if ((e.target as HTMLElement).closest('.acroform-field-input')) return;
    boxDragController?.setSelectedBoxIndex(null);
    const wrapper = (e.target as HTMLElement).closest('.pdf-page-wrapper');
    const canvas = wrapper?.querySelector<HTMLCanvasElement>('canvas');
    if (!canvas || !pdfDoc) return;
    const pageNum = parseInt(canvas.dataset.page ?? '1', 10);
    const viewport = pageViewports[pageNum];
    if (!viewport) return;
    const unit = getSelectedUnit();
    let defaultWidthPt = 150;
    let defaultHeightPt = 40;
    if (lockBoxDimensions && defaultBoxWidth !== null && defaultBoxHeight !== null) {
      defaultWidthPt = unitToPt(defaultBoxWidth, unit);
      defaultHeightPt = unitToPt(defaultBoxHeight, unit);
    }
    const rect = canvas.getBoundingClientRect();
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    const clickX = (e.clientX - rect.left) * scaleX;
    const clickY = (e.clientY - rect.top) * scaleY;
    const heightVp = defaultHeightPt * (viewport.scale || 1.5);
    const pdfCoords = viewport.convertToPdfPoint(clickX, clickY + heightVp);
    const boxLlx = pdfCoords[0];
    const boxLly = pdfCoords[1];
    const boxUrx = boxLlx + defaultWidthPt;
    const boxUry = boxLly + defaultHeightPt;
    if (boxOverlapsAcroform(pageNum, boxLlx, boxLly, boxUrx, boxUry)) {
      debugLog('Signature box skipped: would overlap AcroForm field');
      return;
    }
    addSignatureBox(pageNum, pdfCoords[0], pdfCoords[1], defaultWidthPt, defaultHeightPt);
  });

  boxDragController = setupBoxDragResizeRotate({
    canvasWrapper,
    boxesList,
    boxItemSelector,
    pageViewports,
    getPageField,
    getSelectedUnit,
    getSelectedOrigin,
    formToViewport,
    viewportToForm,
    unitToPt,
    ptToUnit,
    getTouchScale: () => touchController.getScale(),
    onOverlaysUpdate: updateOverlays,
    setIsDragging: (v: boolean) => {
      isDragging = v;
    },
    preventBoxOverlap,
    snapGrid,
    snapToBoxes,
    SNAP_THRESHOLD_PX,
    lockBoxDimensions,
    enableRotation,
    minBoxWidthForm: Number.isNaN(minBoxWidthForm) ? 0 : minBoxWidthForm,
    minBoxHeightForm: Number.isNaN(minBoxHeightForm) ? 0 : minBoxHeightForm,
    noOverlapMessage: strings.no_overlap_message ?? 'Signature boxes on the same page cannot overlap.',
    debugLog,
    debugWarn,
  });

  if (addBoxBtn) addBoxBtn.addEventListener('click', () => addSignatureBox(1, 0, 0));
  updateAddButtonVisibility();

  /**
   * Keyboard shortcuts: Ctrl+Shift+A Add box, Ctrl+Z Undo last box, Delete/Backspace Delete selected.
   * Ignored when focus is inside an input, select or textarea.
   */
  form.addEventListener('keydown', (e: KeyboardEvent) => {
    const target = e.target as HTMLElement;
    if (target?.closest('input, select, textarea')) return;

    if (e.ctrlKey && e.key === 'z') {
      e.preventDefault();
      const items = boxesList.querySelectorAll<HTMLElement>(boxItemSelector);
      if (items.length > 0) {
        const last = items[items.length - 1];
        const idx = Array.from(boxesList.querySelectorAll(boxItemSelector)).indexOf(last);
        last.remove();
        const sel = boxDragController?.getSelectedBoxIndex() ?? null;
        if (sel === idx) boxDragController?.setSelectedBoxIndex(null);
        else if (sel !== null && sel > idx) boxDragController?.setSelectedBoxIndex(sel - 1);
        if (boxesList.querySelectorAll(boxItemSelector).length === 0) {
          const emptyEl = document.getElementById('signature-boxes-empty');
          if (emptyEl) emptyEl.classList.remove('d-none');
        }
        updateOverlays();
        updateAddButtonVisibility();
      }
      return;
    }

    if (e.key === 'Delete' || e.key === 'Backspace') {
      const selectedBoxIndex = boxDragController?.getSelectedBoxIndex() ?? null;
      if (selectedBoxIndex === null) return;
      const items = boxesList.querySelectorAll<HTMLElement>(boxItemSelector);
      const toRemove = items[selectedBoxIndex];
      if (toRemove) {
        e.preventDefault();
        toRemove.remove();
        boxDragController?.setSelectedBoxIndex(null);
        if (boxesList.querySelectorAll(boxItemSelector).length === 0) {
          const emptyEl = document.getElementById('signature-boxes-empty');
          if (emptyEl) emptyEl.classList.remove('d-none');
        }
        updateOverlays();
        updateAddButtonVisibility();
      }
      return;
    }

    if (e.ctrlKey && e.shiftKey && e.key.toLowerCase() === 'a') {
      e.preventDefault();
      if (!pdfDoc) return;
      const pageNum = 1;
      const viewport = pageViewports[pageNum];
      const unit = getSelectedUnit();
      let wPt = 150;
      let hPt = 40;
      if (lockBoxDimensions && defaultBoxWidth !== null && defaultBoxHeight !== null) {
        wPt = unitToPt(defaultBoxWidth, unit);
        hPt = unitToPt(defaultBoxHeight, unit);
      }
      if (viewport) {
        const s = viewport.scale || 1.5;
        const pageW = viewport.width / s;
        const pageH = viewport.height / s;
        addSignatureBox(pageNum, (pageW - wPt) / 2, (pageH - hPt) / 2, wPt, hPt);
      } else {
        addSignatureBox(1, 0, 0, wPt, hPt);
      }
    }
  });

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const items = boxesList.querySelectorAll(boxItemSelector);
    items.forEach((item, i) => {
      item.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>('input, select, textarea').forEach((input) => {
        input.name = input.name.replace(/(\])\[\d+\](\[)/, `$1[${i}]$2`);
      });
    });
    form.submit();
  });
}

run();
