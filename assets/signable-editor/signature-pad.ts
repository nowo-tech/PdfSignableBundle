/**
 * @fileoverview Signature pad: canvas draw, clear, and optional file upload per box.
 * Uses display-sized canvas for sharp rendering, smooth curves and pressure-based line width.
 */
if (typeof console !== 'undefined') console.debug('[PdfSignable] loaded: signable-editor/signature-pad.ts');
/** Min/max line width for pressure (px in canvas space). */
const SIGNATURE_LINE_WIDTH_MIN = 1;
const SIGNATURE_LINE_WIDTH_MAX = 6;

export interface SignaturePadOptions {
  /** Called when signature data changes (draw end, clear, file upload) so overlays can refresh. */
  onOverlayUpdate: () => void;
  /** Optional debug log; no-op if not provided. */
  debugLog?: (...args: unknown[]) => void;
  /** Optional debug warn; no-op if not provided. */
  debugWarn?: (...args: unknown[]) => void;
}

/**
 * Resizes the canvas to match its display size (with devicePixelRatio for sharp rendering).
 * Drawing coordinates are in canvas buffer pixels.
 *
 * @param canvas - The signature pad canvas element
 */
export function resizeSignatureCanvas(canvas: HTMLCanvasElement): void {
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
 * under the given root that is not yet initialized. Idempotent: pads with data-pad-inited are skipped.
 *
 * @param root - Widget or container that holds .signature-pad-canvas elements
 * @param options - onOverlayUpdate callback and optional debug helpers
 */
export function initSignaturePads(root: HTMLElement | null, options: SignaturePadOptions): void {
  if (!root) {
    options.debugWarn?.('initSignaturePads: widget root not found');
    return;
  }
  const { onOverlayUpdate, debugLog, debugWarn } = options;
  const canvases = root.querySelectorAll<HTMLCanvasElement>('.signature-pad-canvas');
  debugLog?.('initSignaturePads', { canvasCount: canvases.length });

  canvases.forEach((canvas) => {
    if (canvas.dataset.padInited === '1') return;
    const item = canvas.closest('[data-pdf-signable="box-item"], .signature-box-item');
    const input = item?.querySelector<HTMLInputElement>('[data-pdf-signable="signature-data"]');
    const clearBtn = canvas.closest('.signature-pad-wrapper')?.querySelector<HTMLButtonElement>('.signature-pad-clear');
    const fileInput = item?.querySelector<HTMLInputElement>('.signature-upload-input');
    if (!item) {
      debugWarn?.('initSignaturePads: canvas not inside a box item');
      return;
    }
    if (!input) {
      debugWarn?.('initSignaturePads: box item has no signature-data input');
      return;
    }
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    canvas.dataset.padInited = '1';

    const wrapper = canvas.closest('.signature-pad-wrapper');
    const resize = (): void => resizeSignatureCanvas(canvas);
    if (typeof ResizeObserver !== 'undefined' && wrapper) {
      const ro = new ResizeObserver(resize);
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
        return { x: (t.clientX - rect.left) * scaleX, y: (t.clientY - rect.top) * scaleY };
      }
      const me = e as MouseEvent;
      return { x: (me.clientX - rect.left) * scaleX, y: (me.clientY - rect.top) * scaleY };
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
      ctx.lineWidth = SIGNATURE_LINE_WIDTH_MIN + p * (SIGNATURE_LINE_WIDTH_MAX - SIGNATURE_LINE_WIDTH_MIN);
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
      const signedAtEl = item?.querySelector<HTMLInputElement>('[data-pdf-signable="signed-at"]');
      if (signedAtEl) signedAtEl.value = new Date().toISOString();
      onOverlayUpdate();
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
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        input.value = '';
        const signedAtEl = item?.querySelector<HTMLInputElement>('[data-pdf-signable="signed-at"]');
        if (signedAtEl) signedAtEl.value = '';
        onOverlayUpdate();
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
            const signedAtEl = item?.querySelector<HTMLInputElement>('[data-pdf-signable="signed-at"]');
            if (signedAtEl) signedAtEl.value = new Date().toISOString();
            onOverlayUpdate();
          }
        };
        reader.readAsDataURL(file);
      });
    }
  });
}
