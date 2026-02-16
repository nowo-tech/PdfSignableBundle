/**
 * @fileoverview Touch/pinch zoom and pan for the PDF viewer.
 * Creates a wrapper div that holds the canvas wrapper and applies transform; state is internal.
 */
if (typeof console !== 'undefined') console.debug('[PdfSignable] loaded: shared/touch.ts');
/**
 * Controller for two-finger pinch zoom and pan on the PDF viewer.
 * Wraps the canvas container in a div and applies CSS transform on touch events.
 */
export interface TouchController {
  /** Wraps the canvas wrapper in a touch div and attaches touch listeners. Idempotent. */
  ensureWrapper: (canvasWrapper: HTMLElement) => void;
  /** Returns the touch wrapper element after ensureWrapper has been called. */
  getWrapper: () => HTMLElement | null;
  /** Returns the current pinch scale factor (1 = no zoom). */
  getScale: () => number;
  /** Returns the current pan offset in pixels. */
  getTranslate: () => { x: number; y: number };
  /** Resets scale to 1 and translate to (0, 0). */
  reset: () => void;
}

/**
 * Creates a touch controller for two-finger pinch zoom and pan on the PDF viewer.
 *
 * @param container - Viewer container (e.g. #pdf-viewer-container)
 * @returns Controller with ensureWrapper, getScale, getTranslate, reset
 */
export function createTouchController(container: HTMLElement | null): TouchController {
  let touchScale = 1;
  let touchTranslate = { x: 0, y: 0 };
  let touchWrapper: HTMLElement | null = null;

  const applyTransform = (): void => {
    if (touchWrapper) {
      touchWrapper.style.transform = `translate(${touchTranslate.x}px, ${touchTranslate.y}px) scale(${touchScale})`;
    }
  };

  const ensureWrapper = (canvasWrapper: HTMLElement): void => {
    if (touchWrapper || !container) return;
    touchWrapper = document.createElement('div');
    touchWrapper.id = 'pdf-touch-wrapper';
    touchWrapper.style.transformOrigin = '0 0';
    touchWrapper.style.transform = `translate(${touchTranslate.x}px, ${touchTranslate.y}px) scale(${touchScale})`;
    container.insertBefore(touchWrapper, canvasWrapper);
    touchWrapper.appendChild(canvasWrapper);

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
          touchScale = Math.max(0.5, Math.min(4, (initialScale * distance) / initialDistance));
          const newCenterX = (a.clientX + b.clientX) / 2;
          const newCenterY = (a.clientY + b.clientY) / 2;
          touchTranslate.x = initialTranslate.x + (newCenterX - centerX);
          touchTranslate.y = initialTranslate.y + (newCenterY - centerY);
          applyTransform();
        }
      },
      { passive: false }
    );
  };

  const reset = (): void => {
    touchScale = 1;
    touchTranslate = { x: 0, y: 0 };
    applyTransform();
  };

  return {
    ensureWrapper,
    getWrapper: () => touchWrapper,
    getScale: () => touchScale,
    getTranslate: () => ({ ...touchTranslate }),
    reset,
  };
}
