/**
 * @fileoverview PdfSignable bundle: PDF viewer and signature box overlays.
 *
 * Expects:
 * - window.NowoPdfSignableConfig (injected by Twig theme): { proxyUrl, strings }
 * - pdfjsLib (PDF.js) loaded from CDN before this script
 *
 * DOM structure (from form theme):
 * - .nowo-pdf-signable-widget: root container
 * - .pdf-url-input: URL input or select
 * - #loadPdfBtn: load PDF button
 * - #pdf-viewer-container, #pdf-placeholder, #pdf-canvas-wrapper
 * - #signature-boxes-list with data-prototype, data-min-entries, data-max-entries
 * - .signature-box-item, .signature-box-name, .signature-box-page, .signature-box-{x,y,width,height}
 * - #addBoxBtn, .remove-box
 * - .unit-selector, .origin-selector
 */

/**
 * Runtime configuration injected by the Twig template (window.NowoPdfSignableConfig).
 */
export interface NowoPdfSignableConfig {
  /** Base URL for the PDF proxy endpoint (e.g. /pdf-signable/proxy). */
  proxyUrl: string;
  /** Translated strings for UI messages. */
  strings: {
    error_load_pdf: string;
    alert_url_required: string;
    alert_submit_error: string;
    loading_state: string;
    load_pdf_btn: string;
    default_box_name: string;
  };
}

declare global {
  interface Window {
    NowoPdfSignableConfig?: NowoPdfSignableConfig;
  }
}

/** PDF.js viewport (scale, dimensions, point conversion). Used when loading pdfjsLib from CDN. */
interface PDFViewport {
  scale: number;
  width: number;
  height: number;
  convertToPdfPoint(x: number, y: number): [number, number];
}

/** PDF.js page proxy: viewport and render. */
interface PDFPageProxy {
  getViewport(params: { scale: number }): PDFViewport;
  render(params: {
    canvasContext: CanvasRenderingContext2D;
    viewport: PDFViewport;
  }): { promise: Promise<void> };
}

/** PDF.js document proxy: page count and getPage. */
interface PDFDocumentProxy {
  numPages: number;
  getPage(num: number): Promise<PDFPageProxy>;
}

/** Global PDF.js library (loaded from CDN). */
declare const pdfjsLib: {
  getDocument(url: string): { promise: Promise<PDFDocumentProxy> };
};

/** Conversion factors from points to each unit (multiply pt by this to get unit value). */
const PT_TO_UNIT: Record<string, number> = {
  pt: 1,
  mm: 25.4 / 72,
  cm: 2.54 / 72,
  in: 1 / 72,
  px: 96 / 72,
};

/**
 * Converts a value from points to the given unit.
 * @param val - Value in points
 * @param unit - Target unit (pt, mm, cm, in, px)
 * @returns Value in the target unit
 */
function ptToUnit(val: number, unit: string): number {
  return val * (PT_TO_UNIT[unit] ?? 1);
}

/**
 * Converts a value from the given unit to points.
 * @param val - Value in the given unit
 * @param unit - Source unit (pt, mm, cm, in, px)
 * @returns Value in points
 */
function unitToPt(val: number, unit: string): number {
  return val / (PT_TO_UNIT[unit] ?? 1);
}

/**
 * Escapes HTML special characters in a string.
 * @param s - Raw string
 * @returns HTML-escaped string
 */
function escapeHtml(s: string): string {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

/** Base hue (0–360) for the first signer; rest are derived by adding HUE_STEP per index. */
const BOX_COLOR_BASE_HUE = 220;
/** Hue step per signer index so colors stay distinct and never repeat (no fixed palette size). */
const BOX_COLOR_HUE_STEP = 37;
const BOX_COLOR_S = 65;
const BOX_COLOR_L = 48;

/**
 * Converts HSL to hex (#rrggbb).
 * @param h - Hue 0–360
 * @param s - Saturation 0–100
 * @param l - Lightness 0–100
 */
function hslToHex(h: number, s: number, l: number): string {
  h = h % 360;
  if (h < 0) h += 360;
  const sNorm = s / 100;
  const lNorm = l / 100;
  const c = (1 - Math.abs(2 * lNorm - 1)) * sNorm;
  const x = c * (1 - Math.abs(((h / 60) % 2) - 1));
  const m = lNorm - c / 2;
  let r = 0,
    g = 0,
    b = 0;
  if (h < 60) {
    r = c;
    g = x;
  } else if (h < 120) {
    r = x;
    g = c;
  } else if (h < 180) {
    g = c;
    b = x;
  } else if (h < 240) {
    g = x;
    b = c;
  } else if (h < 300) {
    r = x;
    b = c;
  } else {
    r = c;
    b = x;
  }
  const toHex = (n: number) => {
    const v = Math.round((n + m) * 255);
    return (v < 16 ? '0' : '') + Math.min(255, Math.max(0, v)).toString(16);
  };
  return '#' + toHex(r) + toHex(g) + toHex(b);
}

/**
 * Returns colors for a signer index. First index uses base hue; each next index adds HUE_STEP.
 * No palette limit and no repetition: colors are derived from the first.
 * @param index - 0 = first signer, 1 = second, etc. (by order of first occurrence of name)
 * @returns CSS values for border, background, text and handle
 */
function getColorForBoxIndex(index: number): {
  border: string;
  background: string;
  color: string;
  handle: string;
} {
  const hue = (BOX_COLOR_BASE_HUE + index * BOX_COLOR_HUE_STEP) % 360;
  const hex = hslToHex(hue, BOX_COLOR_S, BOX_COLOR_L);
  const r = parseInt(hex.slice(1, 3), 16);
  const g = parseInt(hex.slice(3, 5), 16);
  const b = parseInt(hex.slice(5, 7), 16);
  return {
    border: hex,
    background: `rgba(${r}, ${g}, ${b}, 0.15)`,
    color: hex,
    handle: hex,
  };
}

/**
 * Initializes the PDF signable widget: loads config, binds load/click/drag and overlay updates.
 * Exits early if NowoPdfSignableConfig or required DOM elements are missing.
 *
 * Binds: Load PDF button, unit/origin change, add/remove box, overlay drag (move/resize),
 * form submit (re-index collection then native submit).
 */
function run(): void {
  console.log('[PdfSignable] Script loaded');
  const config = (window as Window & { NowoPdfSignableConfig?: NowoPdfSignableConfig })
    .NowoPdfSignableConfig;
  if (!config) {
    console.warn('[PdfSignable] NowoPdfSignableConfig not found, skipping init');
    return;
  }

  const { proxyUrl, strings } = config;
  const widget = document.querySelector<HTMLElement>('.nowo-pdf-signable-widget');
  const form = widget?.closest('form');
  if (!form) {
    console.warn('[PdfSignable] .nowo-pdf-signable-widget or form not found, skipping init');
    return;
  }

  console.log('[PdfSignable] Initialized');

  const pdfUrlInput = form.querySelector<HTMLInputElement>('.pdf-url-input');
  const loadPdfBtn = document.getElementById('loadPdfBtn');
  const pdfPlaceholder = document.getElementById('pdf-placeholder');
  const pdfCanvasWrapper = document.getElementById('pdf-canvas-wrapper');
  const signatureBoxesList = document.getElementById('signature-boxes-list');
  const addBoxBtn = document.getElementById('addBoxBtn');
  const unitSelector = form.querySelector<HTMLSelectElement>('.unit-selector');
  const originSelector = form.querySelector<HTMLSelectElement>('.origin-selector');
  const pdfViewerContainer = document.getElementById('pdf-viewer-container');

  if (!pdfCanvasWrapper || !signatureBoxesList) return;
  const canvasWrapper = pdfCanvasWrapper;
  const boxesList = signatureBoxesList;

  /** Returns the currently selected unit from the form (e.g. mm, pt). */
  function getSelectedUnit(): string {
    return unitSelector?.value ?? 'mm';
  }

  /** Returns the currently selected coordinate origin (e.g. bottom_left). */
  function getSelectedOrigin(): string {
    return originSelector?.value ?? 'bottom_left';
  }

  /**
   * Converts box coordinates from form units (PDF space, origin-aware) to viewport pixel position.
   * @param viewport - PDF.js viewport for the page
   * @param xPt - X in points (PDF space)
   * @param yPt - Y in points (PDF space)
   * @param wPt - Width in points
   * @param hPt - Height in points
   * @param origin - Coordinate origin (top_left, bottom_left, etc.)
   * @returns Viewport pixel position (left, top) for the overlay
   */
  function formToViewport(
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
   * @param viewport - PDF.js viewport for the page
   * @param vpLeft - Left in viewport pixels
   * @param vpTop - Top in viewport pixels
   * @param wPt - Width in points
   * @param hPt - Height in points
   * @param origin - Coordinate origin
   * @returns xPt, yPt in PDF space for the form inputs
   */
  function viewportToForm(
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

  let pdfDoc: PDFDocumentProxy | null = null;
  const pageViewports: Record<number, PDFViewport> = {};
  let renderTask: Promise<void> | null = null;
  /** When true, updateOverlays skips to avoid clearing the overlay being dragged. */
  let isDragging = false;

  /**
   * Returns the URL to use for loading the PDF: same-origin as-is, cross-origin via proxy.
   * @param url - User-provided PDF URL
   * @returns URL to pass to pdfjsLib.getDocument
   */
  function getLoadUrl(url: string): string {
    try {
      const u = new URL(url, window.location.origin);
      return u.origin === window.location.origin
        ? url
        : proxyUrl + '?url=' + encodeURIComponent(url);
    } catch {
      return proxyUrl + '?url=' + encodeURIComponent(url);
    }
  }

  /**
   * Computes a scale so that the first page fits the viewer container width (minus padding).
   * @returns Scale factor for getViewport({ scale })
   */
  async function getScaleForContainer(): Promise<number> {
    if (!pdfDoc || !pdfViewerContainer) return 1.5;
    const page = await pdfDoc.getPage(1);
    const vp1 = page.getViewport({ scale: 1 });
    const w = pdfViewerContainer.clientWidth - 24;
    return w <= 0 ? 1.5 : Math.max(0.5, w / vp1.width);
  }

  /**
   * Loads the PDF from the URL input (or proxy), renders all pages and builds overlay containers.
   * Shows loading state and errors via config strings.
   */
  async function loadPdf(): Promise<void> {
    const url = pdfUrlInput?.value?.trim() ?? '';
    if (!url) {
      console.warn('[PdfSignable] Load PDF: URL required');
      alert(strings.alert_url_required);
      return;
    }
    const loadUrl = getLoadUrl(url);
    console.log('[PdfSignable] Loading PDF', { url, viaProxy: loadUrl !== url });
    if (loadPdfBtn) {
      loadPdfBtn.setAttribute('disabled', '');
      loadPdfBtn.innerHTML =
        '<span class="spinner-border spinner-border-sm me-1"></span> ' + strings.loading_state;
    }
    if (pdfPlaceholder) pdfPlaceholder.style.display = 'block';
    canvasWrapper.style.display = 'none';
    canvasWrapper.innerHTML = '';

    try {
      pdfDoc = await pdfjsLib.getDocument(loadUrl).promise;
      Object.keys(pageViewports).forEach((k) => delete pageViewports[Number(k)]);
      const scale = await getScaleForContainer();

      for (let num = 1; num <= pdfDoc.numPages; num++) {
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
        wrapper.appendChild(overlaysDiv);

        renderTask = page.render({ canvasContext: ctx, viewport }).promise;
        await renderTask;
        canvasWrapper.appendChild(wrapper);
      }

      if (pdfPlaceholder) pdfPlaceholder.style.display = 'none';
      canvasWrapper.style.display = 'block';
      // Draw overlays when landing with data: run after paint and with retries so boxes always show
      const scheduleOverlayUpdates = (): void => {
        updateOverlays();
        setTimeout(updateOverlays, 100);
        setTimeout(updateOverlays, 350);
        setTimeout(updateOverlays, 700);
      };
      requestAnimationFrame(scheduleOverlayUpdates);
      console.log('[PdfSignable] PDF loaded', { pages: pdfDoc.numPages, scale });
    } catch (err) {
      console.error('[PdfSignable] PDF load failed', err);
      alert(strings.error_load_pdf + (err instanceof Error ? err.message : String(err)));
    } finally {
      if (loadPdfBtn) {
        loadPdfBtn.removeAttribute('disabled');
        loadPdfBtn.innerHTML = strings.load_pdf_btn;
      }
    }
  }

  /**
   * Rebuilds all signature box overlays from the form inputs (page, x, y, width, height, unit, origin).
   * Clears existing overlays and creates one overlay per signature-box-item.
   * Skips when isDragging to avoid removing the overlay being dragged.
   */
  function updateOverlays(): void {
    if (isDragging || Object.keys(pageViewports).length === 0) return;
    const unit = getSelectedUnit();
    const origin = getSelectedOrigin();
    canvasWrapper.querySelectorAll('.signature-overlays').forEach((el) => {
      el.innerHTML = '';
    });

    const items = boxesList.querySelectorAll<HTMLElement>(':scope > .signature-box-item');
    const namesSoFar: Record<string, number> = {};
    const nameToColorIndex: Record<string, number> = {};
    let nextColorIndex = 0;
    items.forEach((item, boxIndex) => {
      const pageNum = parseInt(
        item.querySelector<HTMLInputElement>('.signature-box-page')?.value ?? '1',
        10
      );
      const xVal = parseFloat(
        item.querySelector<HTMLInputElement>('.signature-box-x')?.value ?? '0'
      );
      const yVal = parseFloat(
        item.querySelector<HTMLInputElement>('.signature-box-y')?.value ?? '0'
      );
      const wVal = parseFloat(
        item.querySelector<HTMLInputElement>('.signature-box-width')?.value ?? '150'
      );
      const hVal = parseFloat(
        item.querySelector<HTMLInputElement>('.signature-box-height')?.value ?? '40'
      );
      const nameEl = item.querySelector<HTMLInputElement | HTMLSelectElement>('.signature-box-name');
      const name = (nameEl?.value ?? '').trim();
      const nameKey = name === '' ? '__empty__' : name;
      if (!(nameKey in nameToColorIndex)) {
        nameToColorIndex[nameKey] = nextColorIndex++;
      }
      const colorIndex = nameToColorIndex[nameKey];
      namesSoFar[name] = (namesSoFar[name] ?? 0) + 1;
      const occurrence = namesSoFar[name];
      const overlayLabel =
        name === '' ? '' : name + (occurrence > 1 ? ` (${occurrence})` : '');

      const overlaysDiv = canvasWrapper.querySelector(
        `.pdf-page-wrapper[data-page="${pageNum}"] .signature-overlays`
      );
      const viewport = pageViewports[pageNum];
      if (!overlaysDiv || !viewport) return;

      const s = viewport.scale || 1.5;
      const xPt = unitToPt(xVal, unit);
      const yPt = unitToPt(yVal, unit);
      const wPt = unitToPt(wVal, unit);
      const hPt = unitToPt(hVal, unit);
      const v = formToViewport(viewport, xPt, yPt, wPt, hPt, origin);
      const vpW = wPt * s;
      const vpH = hPt * s;

      const overlay = document.createElement('div');
      overlay.className = 'signature-box-overlay';
      overlay.dataset.boxIndex = String(boxIndex);
      overlay.style.left = v.vpX + 'px';
      overlay.style.top = v.vpY + 'px';
      overlay.style.width = Math.max(vpW, 20) + 'px';
      overlay.style.height = Math.max(vpH, 14) + 'px';
      const colors = getColorForBoxIndex(colorIndex);
      overlay.style.borderColor = colors.border;
      overlay.style.backgroundColor = colors.background;
      overlay.style.color = colors.color;
      overlay.style.setProperty('--box-color', colors.handle);
      overlay.innerHTML =
        (overlayLabel ? '<span class="overlay-label">' + escapeHtml(overlayLabel) + '</span>' : '') +
        '<span class="resize-handle nw" data-handle="nw"></span>' +
        '<span class="resize-handle ne" data-handle="ne"></span>' +
        '<span class="resize-handle sw" data-handle="sw"></span>' +
        '<span class="resize-handle se" data-handle="se"></span>';
      overlaysDiv.appendChild(overlay);
    });
  }

  /**
   * Reads the max signature boxes allowed from data attributes (boxes list or add button).
   * @returns Max entries or null if unlimited
   */
  function getMaxEntries(): number | null {
    const raw = boxesList.dataset.maxEntries ?? addBoxBtn?.dataset.maxEntries ?? '';
    if (raw === '') return null;
    const n = parseInt(raw, 10);
    return Number.isNaN(n) ? null : n;
  }

  /** Hides the "Add box" button when the current box count has reached max_entries. */
  function updateAddButtonVisibility(): void {
    if (!addBoxBtn) return;
    const max = getMaxEntries();
    const count = boxesList.querySelectorAll(':scope > .signature-box-item').length;
    if (max !== null && count >= max) {
      addBoxBtn.style.display = 'none';
    } else {
      addBoxBtn.style.display = '';
    }
  }

  /**
   * Adds a new signature box entry to the list and syncs overlays. Respects max_entries.
   * @param page - PDF page number (1-based)
   * @param xPdf - X position in PDF space (points)
   * @param yPdf - Y position in PDF space (points)
   * @param width - Box width in points (default 150)
   * @param height - Box height in points (default 40)
   */
  function addSignatureBox(
    page: number,
    xPdf: number,
    yPdf: number,
    width?: number,
    height?: number
  ): void {
    const max = getMaxEntries();
    const currentCount = boxesList.querySelectorAll(':scope > .signature-box-item').length;
    if (max !== null && currentCount >= max) {
      console.log('[PdfSignable] Box add skipped: max_entries reached', { max, currentCount });
      return;
    }

    const w = width ?? 150;
    const h = height ?? 40;
    const emptyEl = document.getElementById('signature-boxes-empty');
    if (emptyEl) emptyEl.classList.add('d-none');

    const prototype = boxesList.dataset.prototype ?? '';
    const index = boxesList.querySelectorAll(':scope > .signature-box-item').length;
    const html = prototype.replace(/__name__/g, String(index));

    // Append the prototype root element only (it already has .signature-box-item).
    // Do not wrap in another div, otherwise we get two .signature-box-item per box
    // and on remove only the inner one is removed, leaving an empty shell that draws a box at (0,0).
    const temp = document.createElement('div');
    temp.innerHTML = html;
    const div = temp.firstElementChild as HTMLElement;
    if (!div) return;
    div.dataset.index = String(index);

    const pageInput = div.querySelector<HTMLInputElement>('.signature-box-page');
    if (pageInput) pageInput.value = String(page);

    const viewport = pageViewports[page];
    const unit = getSelectedUnit();
    const origin = getSelectedOrigin();
    const wPt = w;
    const hPt = h;
    const s = viewport ? viewport.scale : 1.5;
    const pageW = viewport ? viewport.width / s : 595;
    const pageH = viewport ? viewport.height / s : 842;
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

    const round = (v: number): number => Math.round(v * 100) / 100;
    const nameInput = div.querySelector<HTMLInputElement | HTMLSelectElement>('.signature-box-name');
    for (const f of ['x', 'y', 'width', 'height']) {
      const inp = div.querySelector<HTMLInputElement>('.signature-box-' + f);
      if (!inp) continue;
      if (f === 'x') inp.value = String(round(ptToUnit(xForm, unit)));
      else if (f === 'y') inp.value = String(round(ptToUnit(yForm, unit)));
      else if (f === 'width') inp.value = String(round(ptToUnit(wPt, unit)));
      else if (f === 'height') inp.value = String(round(ptToUnit(hPt, unit)));
    }
    if (nameInput) nameInput.value = strings.default_box_name;

    boxesList.appendChild(div);
    div.scrollIntoView({ behavior: 'smooth' });
    updateOverlays();
    updateAddButtonVisibility();
    console.log('[PdfSignable] Box added', { page, x: xForm, y: yForm, width: w, height: h, unit });
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
      boxesList.querySelectorAll(':scope > .signature-box-item').forEach((item) => {
        for (const f of ['x', 'y', 'width', 'height']) {
          const inp = item.querySelector<HTMLInputElement>('.signature-box-' + f);
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
  boxesList.addEventListener('change', updateOverlays);
  if (originSelector) originSelector.addEventListener('change', updateOverlays);

  boxesList.addEventListener('click', (e) => {
    if (!(e.target as HTMLElement).closest('.remove-box')) return;
    const item = (e.target as HTMLElement).closest('.signature-box-item') as HTMLElement | null;
    if (item) {
      // Remove the top-level .signature-box-item (direct child of list) so we don't leave
      // an empty wrapper that would be drawn as a box at (0,0).
      let topLevel: HTMLElement | null = item;
      while (topLevel?.parentElement && topLevel.parentElement !== boxesList) {
        topLevel = topLevel.parentElement as HTMLElement;
      }
      if (topLevel) {
        const idx = Array.from(boxesList.querySelectorAll(':scope > .signature-box-item')).indexOf(topLevel);
        topLevel.remove();
        console.log('[PdfSignable] Box removed', { index: idx });
      }
    }
    if (boxesList.querySelectorAll(':scope > .signature-box-item').length === 0) {
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
          const scale = await getScaleForContainer();
          canvasWrapper.innerHTML = '';
          Object.keys(pageViewports).forEach((k) => delete pageViewports[Number(k)]);

          for (let num = 1; num <= pdfDoc.numPages; num++) {
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
            wrapper.appendChild(overlaysDiv);
            renderTask = page.render({ canvasContext: ctx, viewport }).promise;
            await renderTask;
            canvasWrapper.appendChild(wrapper);
          }
          updateOverlays();
          if (pdfViewerContainer) lastObservedWidth = pdfViewerContainer.clientWidth;
        } finally {
          isReRendering = false;
        }
        }, 200);
    });
    ro.observe(pdfViewerContainer);
  }

  // Auto-load PDF when DOM is ready so #signature-boxes-list and form values are available for overlays
  function startAutoLoad(): void {
    if (!pdfUrlInput?.value?.trim()) return;
    console.log('[PdfSignable] Auto-loading PDF (preset URL)');
    loadPdf();
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startAutoLoad);
  } else {
    startAutoLoad();
  }

  canvasWrapper.addEventListener('click', (e) => {
    if ((e.target as HTMLElement).closest('.signature-box-overlay')) return;
    const wrapper = (e.target as HTMLElement).closest('.pdf-page-wrapper');
    const canvas = wrapper?.querySelector<HTMLCanvasElement>('canvas');
    if (!canvas || !pdfDoc) return;
    const pageNum = parseInt(canvas.dataset.page ?? '1', 10);
    const viewport = pageViewports[pageNum];
    if (!viewport) return;
    const rect = canvas.getBoundingClientRect();
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    const clickX = (e.clientX - rect.left) * scaleX;
    const clickY = (e.clientY - rect.top) * scaleY;
    const defaultHeight = 40;
    const heightVp = defaultHeight * (viewport.scale || 1.5);
    const pdfCoords = viewport.convertToPdfPoint(clickX, clickY + heightVp);
    addSignatureBox(pageNum, pdfCoords[0], pdfCoords[1], 150, defaultHeight);
  });

  /** State held while the user is dragging an overlay (move or resize). */
  interface DragState {
    mode: 'move' | 'resize';
    handle: string | null;
    overlay: HTMLElement;
    item: HTMLElement;
    viewport: PDFViewport;
    startX: number;
    startY: number;
    startLeft: number;
    startTop: number;
    startRight: number;
    startBottom: number;
  }
  let dragState: DragState | null = null;

  /**
   * Starts a drag (move or resize) when the user mousedowns on an overlay or resize handle.
   * Attaches mousemove and mouseup listeners.
   */
  function onOverlayMouseDown(e: MouseEvent): void {
    const handleEl = (e.target as HTMLElement).closest('.resize-handle');
    const overlay = (e.target as HTMLElement).closest('.signature-box-overlay') as HTMLElement | null;
    if (!overlay?.dataset?.boxIndex) return;
    e.preventDefault();
    e.stopPropagation();
    const boxIndex = parseInt(overlay.dataset.boxIndex, 10);
    const item = boxesList.querySelectorAll<HTMLElement>(':scope > .signature-box-item')[
      boxIndex
    ];
    if (!item) return;
    const wrapper = overlay.closest('.pdf-page-wrapper') as HTMLElement | null;
    const pageNum = parseInt(
      wrapper?.dataset?.page ?? '1',
      10
    );
    const viewport = pageViewports[pageNum];
    if (!viewport) return;
    const overlayW = parseFloat(overlay.style.width) || 20;
    const overlayH = parseFloat(overlay.style.height) || 14;
    const left = parseFloat(overlay.style.left) || 0;
    const top = parseFloat(overlay.style.top) || 0;
    dragState = {
      mode: handleEl ? 'resize' : 'move',
      handle: handleEl ? (handleEl as HTMLElement).dataset.handle ?? null : null,
      overlay,
      item,
      viewport,
      startX: e.clientX,
      startY: e.clientY,
      startLeft: left,
      startTop: top,
      startRight: left + overlayW,
      startBottom: top + overlayH,
    };
    overlay.classList.add('dragging');
    isDragging = true;
    document.addEventListener('mousemove', onDragMove);
    document.addEventListener('mouseup', onDragEnd);
  }

  /**
   * Updates overlay position/size and the corresponding form inputs while dragging.
   * Handles both move and resize (by handle nw/ne/sw/se).
   */
  function onDragMove(e: MouseEvent): void {
    if (!dragState) return;
    const { overlay: o, viewport: vp, startLeft: sl, startTop: st, startRight: sr, startBottom: sb } = dragState;
    const dx = e.clientX - dragState.startX;
    const dy = e.clientY - dragState.startY;
    const minSize = 20;
    let newLeft: number, newTop: number, newW: number, newH: number;

    if (dragState.mode === 'move') {
      newLeft = Math.max(0, Math.min(vp.width - (sr - sl), sl + dx));
      newTop = Math.max(0, Math.min(vp.height - (sb - st), st + dy));
      newW = sr - sl;
      newH = sb - st;
    } else {
      const h = dragState.handle;
      let left = sl;
      let top = st;
      let right = sr;
      let bottom = sb;
      if (h === 'se') {
        right = Math.min(vp.width, Math.max(sl + minSize, sr + dx));
        bottom = Math.min(vp.height, Math.max(st + minSize, sb + dy));
      } else if (h === 'sw') {
        left = Math.max(0, Math.min(sr - minSize, sl + dx));
        bottom = Math.min(vp.height, Math.max(st + minSize, sb + dy));
      } else if (h === 'ne') {
        right = Math.min(vp.width, Math.max(sl + minSize, sr + dx));
        top = Math.max(0, Math.min(sb - minSize, st + dy));
      } else if (h === 'nw') {
        left = Math.max(0, Math.min(sr - minSize, sl + dx));
        top = Math.max(0, Math.min(sb - minSize, st + dy));
      }
      newLeft = left;
      newTop = top;
      newW = right - left;
      newH = bottom - top;
    }

    o.style.left = newLeft + 'px';
    o.style.top = newTop + 'px';
    o.style.width = newW + 'px';
    o.style.height = newH + 'px';

    const s = vp.scale || 1.5;
    const wPt = newW / s;
    const hPt = newH / s;
    const coord = viewportToForm(vp, newLeft, newTop, wPt, hPt, getSelectedOrigin());
    const unit = getSelectedUnit();
    const round = (v: number): number => Math.round(v * 100) / 100;
    const xIn = dragState.item.querySelector<HTMLInputElement>('.signature-box-x');
    const yIn = dragState.item.querySelector<HTMLInputElement>('.signature-box-y');
    const wIn = dragState.item.querySelector<HTMLInputElement>('.signature-box-width');
    const hIn = dragState.item.querySelector<HTMLInputElement>('.signature-box-height');
    if (xIn) xIn.value = String(round(ptToUnit(coord.xPt, unit)));
    if (yIn) yIn.value = String(round(ptToUnit(coord.yPt, unit)));
    if (wIn) wIn.value = String(round(ptToUnit(wPt, unit)));
    if (hIn) hIn.value = String(round(ptToUnit(hPt, unit)));
  }

  /** Cleans up drag state, rebuilds overlays from form (already updated in onDragMove), removes listeners. */
  function onDragEnd(): void {
    if (!dragState) return;
    dragState.overlay.classList.remove('dragging');
    isDragging = false;
    document.removeEventListener('mousemove', onDragMove);
    document.removeEventListener('mouseup', onDragEnd);
    dragState = null;
    updateOverlays();
  }

  canvasWrapper.addEventListener('mousedown', onOverlayMouseDown);

  if (addBoxBtn) addBoxBtn.addEventListener('click', () => addSignatureBox(1, 0, 0));
  updateAddButtonVisibility();

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const items = boxesList.querySelectorAll(':scope > .signature-box-item');
    items.forEach((item, i) => {
      item.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>('input, select, textarea').forEach((input) => {
        input.name = input.name.replace(/(\])\[\d+\](\[)/, `$1[${i}]$2`);
      });
    });
    form.submit();
  });
}

run();
