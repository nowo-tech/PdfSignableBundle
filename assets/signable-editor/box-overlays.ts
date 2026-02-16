/**
 * @fileoverview Signature box overlays: build overlay DOM from form values (page, x, y, width, height, unit, origin).
 * Positions overlays, applies colors and rotation; does not attach drag/resize (handled by box-drag).
 */
if (typeof console !== 'undefined') console.debug('[PdfSignable] loaded: signable-editor/box-overlays.ts');
import type { PDFViewport } from './types';

/** Context passed to updateOverlays; all refs and helpers provided by the caller. */
export interface BoxOverlaysContext {
  canvasWrapper: HTMLElement;
  boxesList: HTMLElement;
  boxItemSelector: string;
  pageViewports: Record<number, PDFViewport>;
  getSelectedUnit: () => string;
  getSelectedOrigin: () => string;
  getPageField: (container: Element) => HTMLInputElement | HTMLSelectElement | null;
  formToViewport: (
    viewport: PDFViewport,
    xPt: number,
    yPt: number,
    wPt: number,
    hPt: number,
    origin: string
  ) => { vpX: number; vpY: number };
  unitToPt: (val: number, unit: string) => number;
  getColorForBoxIndex: (index: number) => { border: string; background: string; color: string; handle: string };
  escapeHtml: (s: string) => string;
  enableRotation: boolean;
  lockBoxDimensions: boolean;
  selectedBoxIndex: number | null;
  isDragging: boolean;
  debugLog: (...args: unknown[]) => void;
  debugWarn: (...args: unknown[]) => void;
}

/**
 * Rebuilds signature box overlays from form inputs. Skips when isDragging or when there are no viewports.
 * Clears existing .signature-overlays content and appends one overlay per box; marks selected overlay.
 *
 * @param ctx - Context with DOM refs, getters and flags
 */
export function updateOverlays(ctx: BoxOverlaysContext): void {
  if (ctx.isDragging || Object.keys(ctx.pageViewports).length === 0) return;
  const unit = ctx.getSelectedUnit();
  const origin = ctx.getSelectedOrigin();
  ctx.canvasWrapper.querySelectorAll('.signature-overlays').forEach((el) => {
    el.innerHTML = '';
  });

  const items = ctx.boxesList.querySelectorAll<HTMLElement>(ctx.boxItemSelector);
  ctx.debugLog('updateOverlays', {
    boxItemCount: items.length,
    pageViewportCount: Object.keys(ctx.pageViewports).length,
  });
  const namesSoFar: Record<string, number> = {};
  const nameToColorIndex: Record<string, number> = {};
  let nextColorIndex = 0;
  items.forEach((item, boxIndex) => {
    const pageField = ctx.getPageField(item);
    const pageNum = parseInt(pageField?.value ?? '1', 10);
    const xEl = item.querySelector<HTMLInputElement>('[data-pdf-signable="x"]');
    const yEl = item.querySelector<HTMLInputElement>('[data-pdf-signable="y"]');
    const wEl = item.querySelector<HTMLInputElement>('[data-pdf-signable="width"]');
    const hEl = item.querySelector<HTMLInputElement>('[data-pdf-signable="height"]');
    if (!pageField) ctx.debugWarn('updateOverlays: box item missing page field', { boxIndex });
    if (!xEl || !yEl || !wEl || !hEl) {
      ctx.debugWarn('updateOverlays: box item missing coordinate inputs', {
        boxIndex,
        hasX: !!xEl,
        hasY: !!yEl,
        hasWidth: !!wEl,
        hasHeight: !!hEl,
      });
    }
    const xVal = parseFloat(xEl?.value ?? '0');
    const yVal = parseFloat(yEl?.value ?? '0');
    const wVal = parseFloat(wEl?.value ?? '150');
    const hVal = parseFloat(hEl?.value ?? '40');
    const angleEl = item.querySelector<HTMLInputElement>('[data-pdf-signable="angle"]');
    const angleVal = ctx.enableRotation && angleEl ? parseFloat(angleEl.value ?? '0') : 0;
    const signatureDataEl = item.querySelector<HTMLInputElement>('[data-pdf-signable="signature-data"]');
    const signatureData = signatureDataEl?.value?.trim();
    const nameEl = item.querySelector<HTMLInputElement | HTMLSelectElement>('[data-pdf-signable="name"]');
    const name = (nameEl?.value ?? '').trim();
    const nameKey = name === '' ? '__empty__' : name;
    if (!(nameKey in nameToColorIndex)) {
      nameToColorIndex[nameKey] = nextColorIndex++;
    }
    const colorIndex = nameToColorIndex[nameKey];
    namesSoFar[name] = (namesSoFar[name] ?? 0) + 1;
    const occurrence = namesSoFar[name];
    const overlayLabel = name === '' ? '' : name + (occurrence > 1 ? ` (${occurrence})` : '');

    const overlaysDiv = ctx.canvasWrapper.querySelector(
      `.pdf-page-wrapper[data-page="${pageNum}"] .signature-overlays`
    );
    const viewport = ctx.pageViewports[pageNum];
    if (!overlaysDiv || !viewport) {
      ctx.debugWarn('updateOverlays: no overlay container or viewport for page', {
        boxIndex,
        pageNum,
        hasOverlaysDiv: !!overlaysDiv,
        hasViewport: !!viewport,
      });
      return;
    }

    const s = viewport.scale || 1.5;
    const xPt = ctx.unitToPt(xVal, unit);
    const yPt = ctx.unitToPt(yVal, unit);
    const wPt = ctx.unitToPt(wVal, unit);
    const hPt = ctx.unitToPt(hVal, unit);
    const v = ctx.formToViewport(viewport, xPt, yPt, wPt, hPt, origin);
    const vpW = wPt * s;
    const vpH = hPt * s;

    const overlay = document.createElement('div');
    overlay.className = 'signature-box-overlay';
    overlay.dataset.pdfSignable = 'overlay';
    overlay.dataset.boxIndex = String(boxIndex);
    overlay.style.left = v.vpX + 'px';
    overlay.style.top = v.vpY + 'px';
    overlay.style.width = Math.max(vpW, 20) + 'px';
    overlay.style.height = Math.max(vpH, 14) + 'px';
    const colors = ctx.getColorForBoxIndex(colorIndex);
    overlay.style.borderColor = colors.border;
    overlay.style.backgroundColor = colors.background;
    overlay.style.color = colors.color;
    overlay.style.setProperty('--box-color', colors.handle);
    overlay.style.transformOrigin = '50% 50%';
    if (ctx.enableRotation) {
      overlay.style.transform = `rotate(${angleVal}deg)`;
    }
    const rotateHandleHtml = ctx.enableRotation
      ? '<span class="rotate-handle" title="Rotate"></span>'
      : '';
    const resizeHandlesHtml = ctx.lockBoxDimensions
      ? ''
      : '<span class="resize-handle nw" data-handle="nw"></span>' +
        '<span class="resize-handle ne" data-handle="ne"></span>' +
        '<span class="resize-handle sw" data-handle="sw"></span>' +
        '<span class="resize-handle se" data-handle="se"></span>';
    overlay.innerHTML =
      (overlayLabel ? '<span class="overlay-label">' + ctx.escapeHtml(overlayLabel) + '</span>' : '') +
      rotateHandleHtml +
      resizeHandlesHtml;
    if (signatureData && signatureData.startsWith('data:')) {
      const img = document.createElement('img');
      img.src = signatureData;
      img.alt = '';
      img.setAttribute('aria-hidden', 'true');
      img.style.cssText =
        'position:absolute;inset:0;width:100%;height:100%;object-fit:contain;pointer-events:none;';
      overlay.insertBefore(img, overlay.firstChild);
    }
    overlaysDiv.appendChild(overlay);
  });
  if (ctx.selectedBoxIndex !== null) {
    const sel = ctx.canvasWrapper.querySelector(
      `[data-pdf-signable="overlay"][data-box-index="${ctx.selectedBoxIndex}"]`
    );
    if (sel) sel.classList.add('selected');
  }
}
