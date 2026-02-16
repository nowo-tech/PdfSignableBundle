/**
 * @fileoverview AcroForm field move/resize on PDF: overlay with drag and resize handles.
 * Dispatches pdf-signable-acroform-rect-changed when the user finishes; caller re-renders.
 * Lives in acroform-editor folder (AcroForm feature); viewer imports it to draw the overlay on its canvas.
 */
if (typeof console !== 'undefined') console.debug('[PdfSignable] loaded: acroform-editor/acroform-move-resize.ts');
import type { PDFViewport } from '../shared/types';

/**
 * Converts viewport pixel rect (left, top, width, height) to PDF-space rect [llx, lly, urx, ury] in points.
 *
 * @param viewport - PDF.js viewport for the page
 * @param left - Left in viewport pixels
 * @param top - Top in viewport pixels
 * @param width - Width in viewport pixels
 * @param height - Height in viewport pixels
 * @returns [llx, lly, urx, ury] in PDF points (bottom-left origin)
 */
export function viewportPixelsToPdfRect(
  viewport: PDFViewport,
  left: number,
  top: number,
  width: number,
  height: number
): [number, number, number, number] {
  const s = viewport.scale ?? 1.5;
  const llx = left / s;
  const urx = (left + width) / s;
  const ury = (viewport.height - top) / s;
  const lly = (viewport.height - (top + height)) / s;
  return [llx, lly, urx, ury];
}

/** Options for creating the AcroForm move/resize controller. */
export interface AcroformMoveResizeOptions {
  canvasWrapper: HTMLElement;
  getPageViewport: (pageNum: number) => PDFViewport | undefined;
  getTouchScale: () => number;
  onRectChanged: (fieldId: string, rect: [number, number, number, number]) => void;
  onRendered: () => void | Promise<void>;
  /** Minimum field width in PDF points (default 12). Used when resizing. */
  minFieldWidthPt?: number;
  /** Minimum field height in PDF points (default 12). Used when resizing. */
  minFieldHeightPt?: number;
}

interface MoveResizeState {
  overlay: HTMLElement;
  fieldId: string;
  pageNum: number;
  viewport: PDFViewport;
  mode: 'move' | 'resize';
  handle: string | null;
  startX: number;
  startY: number;
  startLeft: number;
  startTop: number;
  startRight: number;
  startBottom: number;
}

/**
 * Creates the AcroForm move/resize controller. Call showOverlay(fieldId, pageStr) when
 * the editor requests move/resize (e.g. from pdf-signable-acroform-move-resize event).
 *
 * @param options - Canvas ref, viewport getter, touch scale, and callbacks
 * @returns Object with showOverlay(fieldId, pageStr) and hideOverlay()
 */
export function createAcroformMoveResize(options: AcroformMoveResizeOptions): {
  showOverlay: (fieldId: string, _pageStr: string) => void;
  hideOverlay: () => void;
} {
  const {
    canvasWrapper,
    getPageViewport,
    getTouchScale,
    onRectChanged,
    onRendered,
    minFieldWidthPt = 12,
    minFieldHeightPt = 12,
  } = options;

  let state: MoveResizeState | null = null;

  function onMove(e: MouseEvent): void {
    if (!state) return;
    const st = state;
    const dx = (e.clientX - st.startX) / getTouchScale();
    const dy = (e.clientY - st.startY) / getTouchScale();
    const scale = st.viewport.scale ?? 1.5;
    const minPxW = minFieldWidthPt * scale;
    const minPxH = minFieldHeightPt * scale;
    let newLeft: number, newTop: number, newW: number, newH: number;
    if (st.mode === 'move') {
      newW = st.startRight - st.startLeft;
      newH = st.startBottom - st.startTop;
      newLeft = Math.max(0, Math.min(st.viewport.width - newW, st.startLeft + dx));
      newTop = Math.max(0, Math.min(st.viewport.height - newH, st.startTop + dy));
    } else {
      const h = st.handle;
      let left = st.startLeft;
      let top = st.startTop;
      let right = st.startRight;
      let bottom = st.startBottom;
      if (h === 'se') {
        right = Math.min(st.viewport.width, Math.max(st.startLeft + minPxW, st.startRight + dx));
        bottom = Math.min(st.viewport.height, Math.max(st.startTop + minPxH, st.startBottom + dy));
      } else if (h === 'sw') {
        left = Math.max(0, Math.min(st.startRight - minPxW, st.startLeft + dx));
        bottom = Math.min(st.viewport.height, Math.max(st.startTop + minPxH, st.startBottom + dy));
      } else if (h === 'ne') {
        right = Math.min(st.viewport.width, Math.max(st.startLeft + minPxW, st.startRight + dx));
        top = Math.max(0, Math.min(st.startBottom - minPxH, st.startTop + dy));
      } else if (h === 'nw') {
        left = Math.max(0, Math.min(st.startRight - minPxW, st.startLeft + dx));
        top = Math.max(0, Math.min(st.startBottom - minPxH, st.startTop + dy));
      }
      newLeft = left;
      newTop = top;
      newW = right - left;
      newH = bottom - top;
    }
    st.overlay.style.left = newLeft + 'px';
    st.overlay.style.top = newTop + 'px';
    st.overlay.style.width = newW + 'px';
    st.overlay.style.height = newH + 'px';
  }

  function onEnd(): void {
    if (!state) return;
    const st = state;
    state = null;
    document.removeEventListener('mousemove', onMove);
    document.removeEventListener('mouseup', onEnd);
    const left = parseFloat(st.overlay.style.left) || 0;
    const top = parseFloat(st.overlay.style.top) || 0;
    const width = parseFloat(st.overlay.style.width) || 20;
    const height = parseFloat(st.overlay.style.height) || 14;
    const rect = viewportPixelsToPdfRect(st.viewport, left, top, width, height);
    onRectChanged(st.fieldId, rect);
    // Re-render is triggered by editor (rect-changed → notifyViewerOverrides → overrides-updated). Viewer restores overlay after render.
  }

  const win = window as Window & { __pdfSignableAcroFormMoveResizeFieldId?: string; __pdfSignableAcroFormMoveResizePage?: string };

  function showOverlay(fieldId: string, pageStr: string): void {
    canvasWrapper.querySelectorAll('.acroform-move-resize-layer').forEach((el) => el.remove());
    const outline = canvasWrapper.querySelector<HTMLElement>(
      `.acroform-field-outline[data-field-id="${fieldId}"]`
    );
    if (!outline) return;
    const wrapper = outline.closest('.pdf-page-wrapper') as HTMLElement | null;
    if (!wrapper) return;
    const pageNum = parseInt(wrapper.dataset.page ?? '1', 10);
    const viewport = getPageViewport(pageNum);
    if (!viewport) return;
    win.__pdfSignableAcroFormMoveResizeFieldId = fieldId;
    win.__pdfSignableAcroFormMoveResizePage = String(pageNum);
    const left = parseFloat(outline.style.left) || 0;
    const top = parseFloat(outline.style.top) || 0;
    const width = Math.max(20, parseFloat(outline.style.width) || 60);
    const height = Math.max(14, parseFloat(outline.style.height) || 20);
    const layer = document.createElement('div');
    layer.className = 'acroform-move-resize-layer';
    layer.setAttribute('aria-hidden', 'true');
    const overlay = document.createElement('div');
    overlay.className = 'acroform-move-resize-overlay';
    overlay.dataset.fieldId = fieldId;
    overlay.style.left = left + 'px';
    overlay.style.top = top + 'px';
    overlay.style.width = width + 'px';
    overlay.style.height = height + 'px';
    const handles = ['nw', 'ne', 'sw', 'se'] as const;
    handles.forEach((h) => {
      const handle = document.createElement('div');
      handle.className = `resize-handle ${h}`;
      handle.dataset.handle = h;
      overlay.appendChild(handle);
    });
    layer.appendChild(overlay);
    wrapper.appendChild(layer);
    if (typeof window.dispatchEvent === 'function') {
      window.dispatchEvent(new CustomEvent('pdf-signable-acroform-move-resize-opened'));
    }
    overlay.addEventListener('mousedown', (e: MouseEvent) => {
      const handle = (e.target as HTMLElement).closest('.resize-handle');
      const sl = parseFloat(overlay.style.left) || 0;
      const st = parseFloat(overlay.style.top) || 0;
      const sw = parseFloat(overlay.style.width) || width;
      const sh = parseFloat(overlay.style.height) || height;
      if (handle) {
        e.preventDefault();
        e.stopPropagation();
        state = {
          overlay,
          fieldId,
          pageNum,
          viewport,
          mode: 'resize',
          handle: (handle as HTMLElement).dataset.handle ?? null,
          startX: e.clientX,
          startY: e.clientY,
          startLeft: sl,
          startTop: st,
          startRight: sl + sw,
          startBottom: st + sh,
        };
      } else {
        e.preventDefault();
        e.stopPropagation();
        state = {
          overlay,
          fieldId,
          pageNum,
          viewport,
          mode: 'move',
          handle: null,
          startX: e.clientX,
          startY: e.clientY,
          startLeft: sl,
          startTop: st,
          startRight: sl + sw,
          startBottom: st + sh,
        };
      }
    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onEnd);
    });
  }

  function hideOverlay(): void {
    canvasWrapper.querySelectorAll('.acroform-move-resize-layer').forEach((el) => el.remove());
    win.__pdfSignableAcroFormMoveResizeFieldId = undefined;
    win.__pdfSignableAcroFormMoveResizePage = undefined;
  }

  return { showOverlay, hideOverlay };
}
