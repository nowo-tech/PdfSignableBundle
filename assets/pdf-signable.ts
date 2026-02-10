/**
 * PdfSignable bundle: PDF viewer with signature box overlays, units/origin, and optional touch zoom.
 * @fileoverview Entry point. See pdf-signable/ for constants, types, utils. URL/scale from shared-pdf-viewer. Run logic remains here.
 * @requires pdfjsLib
 */
export type { NowoPdfSignableConfig } from './pdf-signable/types';
import type { PDFViewport, PDFDocumentProxy } from './pdf-signable/types';
import { getLoadUrl, getScaleForFitWidth, getScaleForFitPage } from './shared-pdf-viewer';
/** PDF.js is loaded by the page before this bundle; types in pdf-signable/types. */
declare const pdfjsLib: { getDocument(url: string): { promise: Promise<PDFDocumentProxy> } };
import { ptToUnit, unitToPt, escapeHtml, getColorForBoxIndex } from './pdf-signable/utils';

import './pdf-signable.scss';

/** Initializes the signable PDF widget: load URL, scale, thumbnails, signature boxes, units, and touch zoom. */
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

  const widget = document.querySelector<HTMLElement>('.nowo-pdf-signable-widget');
  const form = widget?.closest('form');
  if (!form) {
    debugWarn('.nowo-pdf-signable-widget or form not found, skipping init');
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
  const SNAP_THRESHOLD_PX = 10;

  debugLog('Initialized');

  const pdfUrlInput = form.querySelector<HTMLInputElement>('.pdf-url-input');
  const loadPdfBtn = document.getElementById('loadPdfBtn');
  const pdfPlaceholder = document.getElementById('pdf-placeholder');
  const pdfCanvasWrapper = document.getElementById('pdf-canvas-wrapper');
  const signatureBoxesList = document.getElementById('signature-boxes-list');
  const addBoxBtn = document.getElementById('addBoxBtn');
  const unitSelector = form.querySelector<HTMLSelectElement>('.unit-selector');
  const originSelector = form.querySelector<HTMLSelectElement>('.origin-selector');
  const pdfViewerContainer = document.getElementById('pdf-viewer-container');
  const pdfZoomValue = document.getElementById('pdfZoomValue');

  if (!pdfCanvasWrapper || !signatureBoxesList) return;
  const canvasWrapper = pdfCanvasWrapper;
  const boxesList = signatureBoxesList;

  /** Touch/pinch state for the viewer; used for click/drag coordinate conversion. */
  let touchScale = 1;
  let touchTranslate = { x: 0, y: 0 };
  let touchWrapper: HTMLElement | null = null;

  /** Maximum width in pixels for each thumbnail in the strip. */
  const THUMB_MAX_WIDTH = 80;

  /**
   * Fills the thumbnail strip with page thumbnails and wires scroll-to-thumb sync. Layout must exist (ensureThumbnailsLayout).
   */
  function buildThumbnailsAndLayout(): void {
    if (!pdfDoc || !pdfViewerContainer) return;
    const thumbStrip = pdfViewerContainer.querySelector('#pdf-thumbnails-strip');
    const scrollWrapper = pdfViewerContainer.querySelector('.pdf-viewer-scroll');
    if (!thumbStrip || !scrollWrapper) return;

    (async () => {
      for (let num = 1; num <= pdfDoc!.numPages; num++) {
        const page = await pdfDoc!.getPage(num);
        const vp1 = page.getViewport({ scale: 1 });
        const thumbScale = THUMB_MAX_WIDTH / vp1.width;
        const thumbVp = page.getViewport({ scale: thumbScale });
        const canvas = document.createElement('canvas');
        canvas.width = thumbVp.width;
        canvas.height = thumbVp.height;
        const ctx = canvas.getContext('2d');
        if (!ctx) continue;
        await page.render({ canvasContext: ctx, viewport: thumbVp }).promise;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pdf-thumb';
        btn.dataset.page = String(num);
        btn.setAttribute('aria-label', 'Page ' + num);
        btn.appendChild(canvas);
        btn.addEventListener('click', () => {
          const wrapper = canvasWrapper.querySelector(
            '.pdf-page-wrapper[data-page="' + num + '"]'
          ) as HTMLElement | null;
          if (wrapper) {
            wrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
            thumbStrip.querySelectorAll('.pdf-thumb.current').forEach((el) => el.classList.remove('current'));
            btn.classList.add('current');
          }
        });
        thumbStrip.appendChild(btn);
      }

      const updateCurrentThumb = (): void => {
        const strip = thumbStrip;
        const wrappers = canvasWrapper.querySelectorAll('.pdf-page-wrapper');
        if (wrappers.length === 0) return;
        const scrollRect = (scrollWrapper as HTMLElement).getBoundingClientRect();
        const viewCenter = scrollRect.top + scrollRect.height / 2;
        let best = 1;
        let bestDist = Infinity;
        wrappers.forEach((el) => {
          const pageNum = parseInt((el as HTMLElement).dataset.page ?? '1', 10);
          const rect = el.getBoundingClientRect();
          const elCenter = rect.top + rect.height / 2;
          const dist = Math.abs(elCenter - viewCenter);
          if (dist < bestDist) {
            bestDist = dist;
            best = pageNum;
          }
        });
        strip.querySelectorAll('.pdf-thumb.current').forEach((el) => el.classList.remove('current'));
        const currentBtn = strip.querySelector('.pdf-thumb[data-page="' + best + '"]');
        if (currentBtn) currentBtn.classList.add('current');
      };

      scrollWrapper.addEventListener('scroll', updateCurrentThumb);
      requestAnimationFrame(updateCurrentThumb);
      if (thumbStrip.firstElementChild) (thumbStrip.firstElementChild as HTMLElement).classList.add('current');
    })();
  }

  /**
   * Creates the touch wrapper and moves the canvas wrapper into it for pinch-zoom/pan. Idempotent.
   */
  function ensureTouchWrapper(): void {
    if (touchWrapper || !pdfViewerContainer) return;
    touchWrapper = document.createElement('div');
    touchWrapper.id = 'pdf-touch-wrapper';
    touchWrapper.style.transformOrigin = '0 0';
    touchWrapper.style.transform = `translate(${touchTranslate.x}px, ${touchTranslate.y}px) scale(${touchScale})`;
    pdfViewerContainer.insertBefore(touchWrapper, canvasWrapper);
    touchWrapper.appendChild(canvasWrapper);
    setupTouchListeners();
  }

  /** Attaches touch listeners for two-finger pinch zoom and pan on the PDF viewer. */
  function setupTouchListeners(): void {
    if (!touchWrapper) return;
    let initialDistance = 0;
    let initialScale = 1;
    let initialTranslate = { x: 0, y: 0 };
    let centerX = 0;
    let centerY = 0;

    touchWrapper.addEventListener(
      'touchstart',
      (e: TouchEvent) => {
        if (e.touches.length === 2) {
          e.preventDefault();
          const a = e.touches[0];
          const b = e.touches[1];
          initialDistance = Math.hypot(b.clientX - a.clientX, b.clientY - a.clientY);
          initialScale = touchScale;
          initialTranslate = { ...touchTranslate };
          centerX = (a.clientX + b.clientX) / 2;
          centerY = (a.clientY + b.clientY) / 2;
        }
      },
      { passive: false }
    );

    touchWrapper.addEventListener(
      'touchmove',
      (e: TouchEvent) => {
        if (e.touches.length === 2 && touchWrapper) {
          e.preventDefault();
          const a = e.touches[0];
          const b = e.touches[1];
          const distance = Math.hypot(b.clientX - a.clientX, b.clientY - a.clientY);
          const newScale = Math.max(0.25, Math.min(4, (initialScale * distance) / initialDistance));
          const newCenterX = (a.clientX + b.clientX) / 2;
          const newCenterY = (a.clientY + b.clientY) / 2;
          touchTranslate.x = initialTranslate.x + (newCenterX - centerX);
          touchTranslate.y = initialTranslate.y + (newCenterY - centerY);
          touchScale = newScale;
          touchWrapper!.style.transform = `translate(${touchTranslate.x}px, ${touchTranslate.y}px) scale(${touchScale})`;
        }
      },
      { passive: false }
    );
  }

  /**
   * Returns the currently selected unit from the form (e.g. mm, pt).
   *
   * @returns The selected unit string (default 'mm')
   */
  function getSelectedUnit(): string {
    return unitSelector?.value ?? 'mm';
  }

  /**
   * Returns the currently selected coordinate origin (e.g. bottom_left).
   *
   * @returns The selected origin string (default 'bottom_left')
   */
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
  /** Current PDF scale (updated on load, resize and zoom). */
  let currentPdfScale = 1.5;

  /**
   * Ensures the thumbnail strip and scroll wrapper exist so the PDF area has a defined width.
   * When present, getScaleForFitWidth() uses the scroll area width. Idempotent.
   */
  function ensureThumbnailsLayout(): void {
    if (!pdfViewerContainer || !touchWrapper) return;
    const existingScroll = pdfViewerContainer.querySelector('.pdf-viewer-scroll');
    if (existingScroll) return;
    const thumbStrip = document.createElement('div');
    thumbStrip.id = 'pdf-thumbnails-strip';
    thumbStrip.setAttribute('aria-label', 'Page thumbnails');
    const scrollWrapper = document.createElement('div');
    scrollWrapper.className = 'pdf-viewer-scroll';
    const themeToolbar = pdfViewerContainer.querySelector('.pdf-zoom-toolbar');
    if (themeToolbar) scrollWrapper.appendChild(themeToolbar);
    scrollWrapper.appendChild(touchWrapper);
    pdfViewerContainer.appendChild(scrollWrapper);
    pdfViewerContainer.insertBefore(thumbStrip, scrollWrapper);
  }

  /**
   * Draws a grid overlay on a canvas (form-unit grid, e.g. every 10 mm) for alignment.
   * PDF space uses bottom-left origin; viewport Y is top-down.
   */
  function drawGridOnCanvas(
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

  /**
   * Renders all PDF pages at the given scale into the canvas wrapper and updates signature box overlays.
   * Equivalent to PdfTemplate renderPagesAtScale (this bundle does not rescale form coordinates).
   * @param scale - Target scale factor (e.g. 1.5 = 150%)
   */
  async function renderPdfAtScale(scale: number): Promise<void> {
    if (!pdfDoc) return;
    Object.keys(pageViewports).forEach((k) => delete pageViewports[Number(k)]);
    canvasWrapper.innerHTML = '';
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
      if (showGrid && gridStep > 0) {
        const gridCanvas = document.createElement('canvas');
        gridCanvas.className = 'pdf-grid-overlay';
        gridCanvas.width = viewport.width;
        gridCanvas.height = viewport.height;
        gridCanvas.dataset.page = String(num);
        drawGridOnCanvas(gridCanvas, viewport, scale, getSelectedUnit(), gridStep);
        wrapper.appendChild(gridCanvas);
      }
      wrapper.appendChild(overlaysDiv);
      renderTask = page.render({ canvasContext: ctx, viewport }).promise;
      await renderTask;
      canvasWrapper.appendChild(wrapper);
    }
    if (pdfZoomValue) pdfZoomValue.textContent = Math.round(scale * 100) + '%';
    updateOverlays();
  }

  /**
   * Binds zoom toolbar buttons (zoom in/out, fit width, fit page). Removes legacy #pdf-zoom-toolbar if present.
   */
  function bindZoomToolbar(): void {
    pdfViewerContainer?.querySelector('#pdf-zoom-toolbar')?.remove();

    document.getElementById('pdfZoomOut')?.addEventListener('click', () => {
      if (!pdfDoc) return;
      currentPdfScale = Math.max(0.5, currentPdfScale / 1.25);
      renderPdfAtScale(currentPdfScale);
    });
    document.getElementById('pdfZoomIn')?.addEventListener('click', () => {
      if (!pdfDoc) return;
      currentPdfScale = Math.min(4, currentPdfScale * 1.25);
      renderPdfAtScale(currentPdfScale);
    });
    document.getElementById('pdfFitWidth')?.addEventListener('click', async () => {
      if (!pdfDoc) return;
      const scale = await getScaleForFitWidth(pdfDoc, pdfViewerContainer);
      currentPdfScale = scale;
      await renderPdfAtScale(currentPdfScale);
    });
    document.getElementById('pdfFitPage')?.addEventListener('click', async () => {
      if (!pdfDoc) return;
      const scale = await getScaleForFitPage(pdfDoc, pdfViewerContainer);
      currentPdfScale = scale;
      await renderPdfAtScale(currentPdfScale);
    });
  }

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
    if (scroll && touchWrapper && touchWrapper.parentElement === scroll) {
      scroll.removeChild(touchWrapper);
      pdfViewerContainer?.appendChild(touchWrapper);
    }
    strip?.remove();
    scroll?.remove();
    pdfViewerContainer?.querySelector('#pdf-zoom-toolbar')?.remove();
    touchScale = 1;
    touchTranslate = { x: 0, y: 0 };
    if (touchWrapper) {
      touchWrapper.style.transform = `translate(0px, 0px) scale(1)`;
    }

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
      pdfDoc = await pdfjsLib.getDocument(loadUrl).promise;
      Object.keys(pageViewports).forEach((k) => delete pageViewports[Number(k)]);
      ensureTouchWrapper();
      ensureThumbnailsLayout();
      const scale = await getScaleForFitWidth(pdfDoc, pdfViewerContainer);
      currentPdfScale = scale;
      await renderPdfAtScale(scale);

      if (pdfPlaceholder) pdfPlaceholder.style.display = 'none';
      canvasWrapper.style.display = 'block';
      buildThumbnailsAndLayout();
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
      alert(strings.error_load_pdf + (err instanceof Error ? err.message : String(err)));
    } finally {
      loadingOverlay.remove();
      if (loadPdfBtn) {
        loadPdfBtn.removeAttribute('disabled');
        loadPdfBtn.innerHTML = strings.load_pdf_btn;
      }
    }
  }

  /**
   * Rebuilds signature box overlays from form inputs (page, x, y, width, height, unit, origin). Skips while dragging.
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
      const angleEl = item.querySelector<HTMLInputElement>('.signature-box-angle');
      const angleVal = enableRotation && angleEl ? parseFloat(angleEl.value ?? '0') : 0;
      const signatureDataEl = item.querySelector<HTMLInputElement>('.signature-box-signature-data');
      const signatureData = signatureDataEl?.value?.trim();
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
      overlay.style.transformOrigin = '50% 50%';
      if (enableRotation) {
        overlay.style.transform = `rotate(${angleVal}deg)`;
      }
      const rotateHandleHtml = enableRotation ? '<span class="rotate-handle" title="Rotate"></span>' : '';
      overlay.innerHTML =
        (overlayLabel ? '<span class="overlay-label">' + escapeHtml(overlayLabel) + '</span>' : '') +
        rotateHandleHtml +
        '<span class="resize-handle nw" data-handle="nw"></span>' +
        '<span class="resize-handle ne" data-handle="ne"></span>' +
        '<span class="resize-handle sw" data-handle="sw"></span>' +
        '<span class="resize-handle se" data-handle="se"></span>';
      if (signatureData && signatureData.startsWith('data:')) {
        const img = document.createElement('img');
        img.src = signatureData;
        img.alt = '';
        img.setAttribute('aria-hidden', 'true');
        img.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;object-fit:contain;pointer-events:none;';
        overlay.insertBefore(img, overlay.firstChild);
      }
      overlaysDiv.appendChild(overlay);
    });
    if (selectedBoxIndex !== null) {
      const sel = canvasWrapper.querySelector(`.signature-box-overlay[data-box-index="${selectedBoxIndex}"]`);
      if (sel) sel.classList.add('selected');
    }
  }

  /** Min/max line width for pressure (px in canvas space). */
  const SIGNATURE_LINE_WIDTH_MIN = 1;
  const SIGNATURE_LINE_WIDTH_MAX = 6;

  /**
   * Resizes the canvas to match its display size (with devicePixelRatio for sharp rendering).
   * Drawing coordinates are in canvas buffer pixels; getCoords multiplies by canvas.size/rect.size.
   */
  function resizeSignatureCanvas(canvas: HTMLCanvasElement): void {
    const rect = canvas.getBoundingClientRect();
    if (rect.width <= 0 || rect.height <= 0) return;
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    const w = Math.max(1, Math.round(rect.width * dpr));
    const h = Math.max(1, Math.round(rect.height * dpr));
    if (canvas.width === w && canvas.height === h) return;
    canvas.width = w;
    canvas.height = h;
  }

  /**
   * Initializes signature pads (canvas draw + clear + optional file upload) for each .signature-pad-canvas
   * that is not yet initialized. Idempotent: pads with data-pad-inited are skipped.
   * Uses display-sized canvas for sharp rendering, smooth curves and pressure-based line width (touch/mouse).
   */
  function initSignaturePads(): void {
    const root = document.querySelector('.nowo-pdf-signable-widget');
    if (!root) return;
    root.querySelectorAll<HTMLCanvasElement>('.signature-pad-canvas').forEach((canvas) => {
      if (canvas.dataset.padInited === '1') return;
      const item = canvas.closest('.signature-box-item');
      const input = item?.querySelector<HTMLInputElement>('.signature-box-signature-data');
      const clearBtn = canvas.closest('.signature-pad-wrapper')?.querySelector<HTMLButtonElement>('.signature-pad-clear');
      const fileInput = item?.querySelector<HTMLInputElement>('.signature-upload-input');
      if (!input) return;
      const ctx = canvas.getContext('2d');
      if (!ctx) return;
      canvas.dataset.padInited = '1';

      const wrapper = canvas.closest('.signature-pad-wrapper');
      const resize = (): void => {
        resizeSignatureCanvas(canvas);
      };
      if (typeof ResizeObserver !== 'undefined' && wrapper) {
        const ro = new ResizeObserver(() => {
          resize();
        });
        ro.observe(wrapper);
      }
      requestAnimationFrame(resize);

      let isDrawing = false;
      let lastX = 0;
      let lastY = 0;
      let prevX = 0;
      let prevY = 0;

      const getCoords = (e: MouseEvent | TouchEvent): { x: number; y: number } => {
        const rect = canvas.getBoundingClientRect();
        const scaleX = rect.width ? canvas.width / rect.width : 1;
        const scaleY = rect.height ? canvas.height / rect.height : 1;
        if ('touches' in e && e.touches.length > 0) {
          const t = e.touches[0];
          return {
            x: (t.clientX - rect.left) * scaleX,
            y: (t.clientY - rect.top) * scaleY,
          };
        }
        const me = e as MouseEvent;
        return {
          x: (me.clientX - rect.left) * scaleX,
          y: (me.clientY - rect.top) * scaleY,
        };
      };

      const getPressure = (e: MouseEvent | TouchEvent): number => {
        if ('touches' in e && e.touches.length > 0) {
          const force = (e.touches[0] as Touch & { force?: number }).force;
          if (typeof force === 'number' && force >= 0) return Math.min(1, force);
        }
        const me = e as MouseEvent & { pressure?: number };
        if (typeof me.pressure === 'number' && me.pressure >= 0) return Math.min(1, me.pressure);
        return 0.5;
      };

      const setLineWidthFromPressure = (e: MouseEvent | TouchEvent): void => {
        const p = getPressure(e);
        const w = SIGNATURE_LINE_WIDTH_MIN + p * (SIGNATURE_LINE_WIDTH_MAX - SIGNATURE_LINE_WIDTH_MIN);
        ctx.lineWidth = w;
      };

      const start = (e: MouseEvent | TouchEvent): void => {
        e.preventDefault();
        resizeSignatureCanvas(canvas);
        const { x, y } = getCoords(e);
        prevX = lastX = x;
        prevY = lastY = y;
        isDrawing = true;
        ctx.strokeStyle = '#000';
        setLineWidthFromPressure(e);
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.beginPath();
        ctx.moveTo(x, y);
      };

      const move = (e: MouseEvent | TouchEvent): void => {
        e.preventDefault();
        if (!isDrawing) return;
        const { x, y } = getCoords(e);
        setLineWidthFromPressure(e);
        const midX = (lastX + x) / 2;
        const midY = (lastY + y) / 2;
        ctx.beginPath();
        ctx.moveTo(prevX, prevY);
        ctx.quadraticCurveTo(lastX, lastY, midX, midY);
        ctx.stroke();
        prevX = midX;
        prevY = midY;
        lastX = x;
        lastY = y;
      };

      const end = (e: MouseEvent | TouchEvent): void => {
        e.preventDefault();
        if (!isDrawing) return;
        isDrawing = false;
        if (canvas.width > 0 && canvas.height > 0) {
          input.value = canvas.toDataURL('image/png');
        }
        const signedAtEl = item?.querySelector<HTMLInputElement>('.signature-box-signed-at');
        if (signedAtEl) signedAtEl.value = new Date().toISOString();
        updateOverlays();
      };

      canvas.addEventListener('mousedown', start);
      canvas.addEventListener('mousemove', move);
      canvas.addEventListener('mouseup', end);
      canvas.addEventListener('mouseleave', end);
      canvas.addEventListener('touchstart', start, { passive: false });
      canvas.addEventListener('touchmove', move, { passive: false });
      canvas.addEventListener('touchend', end, { passive: false });
      if (clearBtn) {
        clearBtn.addEventListener('click', () => {
          const w = canvas.width;
          const h = canvas.height;
          ctx.clearRect(0, 0, w, h);
          input.value = '';
          const signedAtEl = item?.querySelector<HTMLInputElement>('.signature-box-signed-at');
          if (signedAtEl) signedAtEl.value = '';
          updateOverlays();
        });
      }
      if (fileInput) {
        fileInput.addEventListener('change', () => {
          const file = fileInput.files?.[0];
          if (!file || !file.type.startsWith('image/')) return;
          const reader = new FileReader();
          reader.onload = () => {
            if (typeof reader.result === 'string') {
              input.value = reader.result;
              const signedAtEl = item?.querySelector<HTMLInputElement>('.signature-box-signed-at');
              if (signedAtEl) signedAtEl.value = new Date().toISOString();
              updateOverlays();
            }
          };
          reader.readAsDataURL(file);
        });
      }
    });
  }

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
    const count = boxesList.querySelectorAll(':scope > .signature-box-item').length;
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
      debugLog('Box add skipped: max_entries reached', { max, currentCount });
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
    const angleInp = div.querySelector<HTMLInputElement>('.signature-box-angle');
    if (angleInp) angleInp.value = '0';
    if (nameInput) nameInput.value = strings.default_box_name;

    boxesList.appendChild(div);
    div.scrollIntoView({ behavior: 'smooth' });
    updateOverlays();
    initSignaturePads();
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
  boxesList.addEventListener('change', (e) => {
    const target = (e.target as HTMLElement).closest('.signature-box-item');
    const nameEl = target?.querySelector<HTMLInputElement | HTMLSelectElement>('.signature-box-name');
    if (nameEl && e.target === nameEl && Object.keys(boxDefaultsByName).length > 0) {
      const name = (nameEl.value ?? '').trim();
      const def = name ? boxDefaultsByName[name] : null;
      if (def) {
        const set = (cls: string, val: number | undefined): void => {
          const inp = target?.querySelector<HTMLInputElement>('.signature-box-' + cls);
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
        debugLog('Box removed', { index: idx });
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

  bindZoomToolbar();

  // Auto-load PDF when DOM is ready so #signature-boxes-list and form values are available for overlays
  function startAutoLoad(): void {
    if (!pdfUrlInput?.value?.trim()) return;
    debugLog('Auto-loading PDF (preset URL)');
    loadPdf();
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      startAutoLoad();
      initSignaturePads();
    });
  } else {
    startAutoLoad();
    initSignaturePads();
  }

  canvasWrapper.addEventListener('click', (e) => {
    if ((e.target as HTMLElement).closest('.signature-box-overlay')) return;
    setSelectedBoxIndex(null);
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

  /**
   * Box bounds in form units (for overlap check). Same unit for all boxes (current selector).
   */
  interface BoxBounds {
    page: number;
    x: number;
    y: number;
    w: number;
    h: number;
  }

  /**
   * Returns true if two boxes on the same page have overlapping rectangles (in form units).
   */
  function boxesOverlap(a: BoxBounds, b: BoxBounds): boolean {
    if (a.page !== b.page) return false;
    const ax2 = a.x + a.w;
    const bx2 = b.x + b.w;
    const ay2 = a.y + a.h;
    const by2 = b.y + b.h;
    return a.x < bx2 && b.x < ax2 && a.y < by2 && b.y < ay2;
  }

  /**
   * Reads all signature box bounds from the form (page, x, y, width, height in current unit).
   */
  function getBoxesFromForm(): BoxBounds[] {
    const items = boxesList.querySelectorAll<HTMLElement>(':scope > .signature-box-item');
    const result: BoxBounds[] = [];
    items.forEach((item) => {
      const pageEl = item.querySelector<HTMLSelectElement>('.signature-box-page');
      const page = parseInt(pageEl?.value ?? '1', 10);
      const x = parseFloat(item.querySelector<HTMLInputElement>('.signature-box-x')?.value ?? '0');
      const y = parseFloat(item.querySelector<HTMLInputElement>('.signature-box-y')?.value ?? '0');
      const w = parseFloat(item.querySelector<HTMLInputElement>('.signature-box-width')?.value ?? '150');
      const h = parseFloat(item.querySelector<HTMLInputElement>('.signature-box-height')?.value ?? '40');
      result.push({ page, x, y, w, h });
    });
    return result;
  }

  /**
   * Axis-aligned bounding box size for a rectangle of size (w, h) rotated by angleDeg (degrees).
   * Used so drag constraints keep the rotated overlay fully inside the page.
   */
  function getRotatedAabbSize(w: number, h: number, angleDeg: number): { aabbW: number; aabbH: number } {
    const rad = (angleDeg * Math.PI) / 180;
    const cos = Math.abs(Math.cos(rad));
    const sin = Math.abs(Math.sin(rad));
    return {
      aabbW: w * cos + h * sin,
      aabbH: w * sin + h * cos,
    };
  }

  /** Viewport bounds (left, top, right, bottom in pixels) for snap-to-boxes. */
  function getOtherBoxesViewportBounds(pageNum: number, excludeIndex: number): { left: number; top: number; right: number; bottom: number }[] {
    const vp = pageViewports[pageNum];
    if (!vp) return [];
    const unit = getSelectedUnit();
    const origin = getSelectedOrigin();
    const s = vp.scale || 1.5;
    const boxes = getBoxesFromForm();
    const out: { left: number; top: number; right: number; bottom: number }[] = [];
    boxes.forEach((b, i) => {
      if (i === excludeIndex || b.page !== pageNum) return;
      const xPt = unitToPt(b.x, unit);
      const yPt = unitToPt(b.y, unit);
      const wPt = unitToPt(b.w, unit);
      const hPt = unitToPt(b.h, unit);
      const { vpX, vpY } = formToViewport(vp, xPt, yPt, wPt, hPt, origin);
      out.push({
        left: vpX,
        top: vpY,
        right: vpX + wPt * s,
        bottom: vpY + hPt * s,
      });
    });
    return out;
  }

  /**
   * State held while the user is dragging an overlay (move or resize).
   *
   * @interface DragState
   */
  interface DragState {
    mode: 'move' | 'resize';
    handle: string | null;
    overlay: HTMLElement;
    item: HTMLElement;
    boxIndex: number;
    viewport: PDFViewport;
    startX: number;
    startY: number;
    startLeft: number;
    startTop: number;
    startRight: number;
    startBottom: number;
    /** Form input values at drag start (for revert when overlap is prevented). */
    startFormX: number;
    startFormY: number;
    startFormW: number;
    startFormH: number;
  }
  let dragState: DragState | null = null;

  /** Index of the currently selected box (for keyboard Delete). -1 or null = none selected. */
  let selectedBoxIndex: number | null = null;

  function setSelectedBoxIndex(idx: number | null): void {
    selectedBoxIndex = idx;
    canvasWrapper.querySelectorAll('.signature-box-overlay.selected').forEach((el) => el.classList.remove('selected'));
    if (idx !== null) {
      const overlay = canvasWrapper.querySelector(`.signature-box-overlay[data-box-index="${idx}"]`);
      if (overlay) overlay.classList.add('selected');
    }
  }

  interface RotateState {
    overlay: HTMLElement;
    item: HTMLElement;
    centerX: number;
    centerY: number;
    startAngle: number; // degrees from input
    startMouseAngle: number; // radians atan2(dy, dx) at mousedown
  }
  let rotateState: RotateState | null = null;

  function onRotateMove(e: MouseEvent): void {
    if (!rotateState) return;
    const { overlay, item, centerX, centerY, startAngle, startMouseAngle } = rotateState;
    const currentMouseAngle = Math.atan2(e.clientY - centerY, e.clientX - centerX);
    let deltaDeg = ((currentMouseAngle - startMouseAngle) * 180) / Math.PI;
    let newAngle = startAngle + deltaDeg;
    while (newAngle > 180) newAngle -= 360;
    while (newAngle < -180) newAngle += 360;
    const angleInp = item.querySelector<HTMLInputElement>('.signature-box-angle');
    if (angleInp) angleInp.value = String(Math.round(newAngle * 100) / 100);
    overlay.style.transform = `rotate(${newAngle}deg)`;
  }

  function onRotateEnd(): void {
    if (!rotateState) return;
    document.removeEventListener('mousemove', onRotateMove);
    document.removeEventListener('mouseup', onRotateEnd);
    rotateState = null;
  }

  /**
   * Starts a drag (move or resize) when the user mousedowns on an overlay or resize handle.
   * If mousedown is on .rotate-handle and rotation is enabled, starts rotate drag instead.
   * Attaches mousemove and mouseup listeners.
   */
  function onOverlayMouseDown(e: MouseEvent): void {
    const overlay = (e.target as HTMLElement).closest('.signature-box-overlay') as HTMLElement | null;
    if (!overlay?.dataset?.boxIndex) return;
    e.preventDefault();
    e.stopPropagation();

    const rotateHandle = (e.target as HTMLElement).closest('.rotate-handle');
    if (enableRotation && rotateHandle) {
      const boxIndex = parseInt(overlay.dataset.boxIndex, 10);
      const item = boxesList.querySelectorAll<HTMLElement>(':scope > .signature-box-item')[boxIndex];
      if (!item) return;
      const angleInp = item.querySelector<HTMLInputElement>('.signature-box-angle');
      if (!angleInp) return;
      const rect = overlay.getBoundingClientRect();
      const centerX = rect.left + rect.width / 2;
      const centerY = rect.top + rect.height / 2;
      const startAngle = parseFloat(angleInp.value) || 0;
      const startMouseAngle = Math.atan2(e.clientY - centerY, e.clientX - centerX);
      rotateState = { overlay, item, centerX, centerY, startAngle, startMouseAngle };
      document.addEventListener('mousemove', onRotateMove);
      document.addEventListener('mouseup', onRotateEnd);
      return;
    }

    const handleEl = (e.target as HTMLElement).closest('.resize-handle');
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
    const xIn = item.querySelector<HTMLInputElement>('.signature-box-x');
    const yIn = item.querySelector<HTMLInputElement>('.signature-box-y');
    const wIn = item.querySelector<HTMLInputElement>('.signature-box-width');
    const hIn = item.querySelector<HTMLInputElement>('.signature-box-height');
    dragState = {
      mode: handleEl ? 'resize' : 'move',
      handle: handleEl ? (handleEl as HTMLElement).dataset.handle ?? null : null,
      overlay,
      item,
      boxIndex,
      viewport,
      startX: e.clientX,
      startY: e.clientY,
      startLeft: left,
      startTop: top,
      startRight: left + overlayW,
      startBottom: top + overlayH,
      startFormX: xIn ? parseFloat(xIn.value) || 0 : 0,
      startFormY: yIn ? parseFloat(yIn.value) || 0 : 0,
      startFormW: wIn ? parseFloat(wIn.value) || 150 : 150,
      startFormH: hIn ? parseFloat(hIn.value) || 40 : 40,
    };
    overlay.classList.add('dragging');
    isDragging = true;
    if (dragState.mode === 'move') setSelectedBoxIndex(boxIndex);
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
    const dx = (e.clientX - dragState.startX) / touchScale;
    const dy = (e.clientY - dragState.startY) / touchScale;
    const minSize = 20;
    let newLeft: number, newTop: number, newW: number, newH: number;

    if (dragState.mode === 'move') {
      const moveW = sr - sl;
      const moveH = sb - st;
      const angleInp = dragState.item.querySelector<HTMLInputElement>('.signature-box-angle');
      const angleDeg = enableRotation && angleInp ? parseFloat(angleInp.value) || 0 : 0;
      const { aabbW, aabbH } = getRotatedAabbSize(moveW, moveH, angleDeg);
      // Allow overlay left/top to be negative so the rotated AABB can touch page edges (e.g. left edge at -90°).
      const leftMin = aabbW / 2 - moveW / 2;
      const leftMax = vp.width - aabbW / 2 - moveW / 2;
      const topMin = aabbH / 2 - moveH / 2;
      const topMax = vp.height - aabbH / 2 - moveH / 2;
      newLeft = Math.max(leftMin, Math.min(leftMax, sl + dx));
      newTop = Math.max(topMin, Math.min(topMax, st + dy));
      newW = moveW;
      newH = moveH;
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

    const s = vp.scale || 1.5;
    const pageNum = parseInt(
      (o.closest('.pdf-page-wrapper') as HTMLElement)?.dataset?.page ?? '1',
      10
    );

    if (snapGrid > 0) {
      const wPt = newW / s;
      const hPt = newH / s;
      const coord = viewportToForm(vp, newLeft, newTop, wPt, hPt, getSelectedOrigin());
      const unit = getSelectedUnit();
      let xForm = ptToUnit(coord.xPt, unit);
      let yForm = ptToUnit(coord.yPt, unit);
      let wForm = ptToUnit(wPt, unit);
      let hForm = ptToUnit(hPt, unit);
      xForm = Math.round(xForm / snapGrid) * snapGrid;
      yForm = Math.round(yForm / snapGrid) * snapGrid;
      wForm = Math.round(wForm / snapGrid) * snapGrid;
      hForm = Math.max(snapGrid, Math.round(hForm / snapGrid) * snapGrid);
      const xPt = unitToPt(xForm, unit);
      const yPt = unitToPt(yForm, unit);
      const nwPt = unitToPt(wForm, unit);
      const nhPt = unitToPt(hForm, unit);
      const snapped = formToViewport(vp, xPt, yPt, nwPt, nhPt, getSelectedOrigin());
      newLeft = snapped.vpX;
      newTop = snapped.vpY;
      newW = nwPt * s;
      newH = nhPt * s;
    }

    if (snapToBoxes) {
      const other = getOtherBoxesViewportBounds(pageNum, dragState.boxIndex);
      const newRight = newLeft + newW;
      const newBottom = newTop + newH;
      const allX = other.flatMap((b) => [b.left, b.right]);
      const allY = other.flatMap((b) => [b.top, b.bottom]);
      const snapEdge = (val: number, targets: number[]): number => {
        let best = val;
        let bestDist = SNAP_THRESHOLD_PX;
        for (const t of targets) {
          const d = Math.abs(val - t);
          if (d < bestDist) {
            bestDist = d;
            best = t;
          }
        }
        return best;
      };
      const snappedLeft = snapEdge(newLeft, allX);
      const snappedRight = snapEdge(newRight, allX);
      const snappedTop = snapEdge(newTop, allY);
      const snappedBottom = snapEdge(newBottom, allY);
      newLeft = snappedLeft;
      newTop = snappedTop;
      newW = Math.max(minSize, snappedRight - snappedLeft);
      newH = Math.max(minSize, snappedBottom - snappedTop);
    }

    o.style.left = newLeft + 'px';
    o.style.top = newTop + 'px';
    o.style.width = newW + 'px';
    o.style.height = newH + 'px';

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

  /** Cleans up drag state; if prevent_box_overlap and new position overlaps another box, reverts and shows message. Then rebuilds overlays. */
  function onDragEnd(): void {
    if (!dragState) return;
    const state = dragState;
    dragState.overlay.classList.remove('dragging');
    isDragging = false;
    document.removeEventListener('mousemove', onDragMove);
    document.removeEventListener('mouseup', onDragEnd);

    if (preventBoxOverlap) {
      const boxes = getBoxesFromForm();
      const current = boxes[state.boxIndex];
      if (current) {
        const overlapsOther = boxes.some((b, i) => i !== state.boxIndex && boxesOverlap(current, b));
        if (overlapsOther) {
          const round = (v: number): number => Math.round(v * 100) / 100;
          const xIn = state.item.querySelector<HTMLInputElement>('.signature-box-x');
          const yIn = state.item.querySelector<HTMLInputElement>('.signature-box-y');
          const wIn = state.item.querySelector<HTMLInputElement>('.signature-box-width');
          const hIn = state.item.querySelector<HTMLInputElement>('.signature-box-height');
          if (xIn) xIn.value = String(round(state.startFormX));
          if (yIn) yIn.value = String(round(state.startFormY));
          if (wIn) wIn.value = String(round(state.startFormW));
          if (hIn) hIn.value = String(round(state.startFormH));
          const msg = strings.no_overlap_message ?? 'Signature boxes on the same page cannot overlap.';
          alert(msg);
        }
      }
    }

    dragState = null;
    updateOverlays();
  }

  canvasWrapper.addEventListener('mousedown', onOverlayMouseDown);

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
      const items = boxesList.querySelectorAll<HTMLElement>(':scope > .signature-box-item');
      if (items.length > 0) {
        const last = items[items.length - 1];
        const idx = Array.from(boxesList.querySelectorAll(':scope > .signature-box-item')).indexOf(last);
        last.remove();
        if (selectedBoxIndex === idx) setSelectedBoxIndex(null);
        else if (selectedBoxIndex !== null && selectedBoxIndex > idx) setSelectedBoxIndex(selectedBoxIndex - 1);
        if (boxesList.querySelectorAll(':scope > .signature-box-item').length === 0) {
          const emptyEl = document.getElementById('signature-boxes-empty');
          if (emptyEl) emptyEl.classList.remove('d-none');
        }
        updateOverlays();
        updateAddButtonVisibility();
      }
      return;
    }

    if (e.key === 'Delete' || e.key === 'Backspace') {
      if (selectedBoxIndex === null) return;
      const items = boxesList.querySelectorAll<HTMLElement>(':scope > .signature-box-item');
      const toRemove = items[selectedBoxIndex];
      if (toRemove) {
        e.preventDefault();
        toRemove.remove();
        setSelectedBoxIndex(null);
        if (boxesList.querySelectorAll(':scope > .signature-box-item').length === 0) {
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
      if (viewport) {
        const s = viewport.scale || 1.5;
        const pageW = viewport.width / s;
        const pageH = viewport.height / s;
        addSignatureBox(pageNum, (pageW - 150) / 2, (pageH - 40) / 2, 150, 40);
      } else {
        addSignatureBox(1, 0, 0);
      }
    }
  });

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
