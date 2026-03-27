/**
 * @fileoverview PDF page thumbnails strip and scroll sync.
 * Builds thumbnails from the loaded document and keeps "current" page in sync with scroll.
 */
if (typeof console !== 'undefined') console.debug('[PdfSignable] loaded: signable-editor/thumbnails.ts');
import type { PDFDocumentProxy } from './types';

/** Maximum width in pixels for each thumbnail in the strip. */
export const THUMB_MAX_WIDTH = 80;

/**
 * Refs required for thumbnail layout: viewer container, canvas wrapper, and optional touch wrapper.
 */
export interface ThumbnailsRefs {
  pdfViewerContainer: HTMLElement | null;
  canvasWrapper: HTMLElement;
  touchWrapper: HTMLElement | null;
}

/**
 * Ensures the thumbnail strip and scroll wrapper exist so the PDF area has a defined width.
 * When present, getScaleForFitWidth() uses the scroll area width. Idempotent.
 *
 * @param refs - Container, canvas wrapper and touch wrapper refs
 */
export function ensureThumbnailsLayout(refs: ThumbnailsRefs): void {
  const { pdfViewerContainer, touchWrapper } = refs;
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
 * Fills the thumbnail strip with page thumbnails and wires scroll-to-thumb sync.
 * Layout must exist (ensureThumbnailsLayout). Runs async.
 *
 * @param pdfDoc - Loaded PDF document
 * @param refs - Container and canvas wrapper refs
 */
export function buildThumbnailsAndLayout(
  pdfDoc: PDFDocumentProxy,
  refs: { pdfViewerContainer: HTMLElement | null; canvasWrapper: HTMLElement }
): void {
  const { pdfViewerContainer, canvasWrapper } = refs;
  const thumbStrip = pdfViewerContainer?.querySelector('#pdf-thumbnails-strip');
  const scrollWrapper = pdfViewerContainer?.querySelector('.pdf-viewer-scroll');
  if (!thumbStrip || !scrollWrapper) return;

  (async () => {
    for (let num = 1; num <= pdfDoc.numPages; num++) {
      const page = await pdfDoc.getPage(num);
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
      thumbStrip.querySelectorAll('.pdf-thumb.current').forEach((el) => el.classList.remove('current'));
      const currentBtn = thumbStrip.querySelector('.pdf-thumb[data-page="' + best + '"]');
      if (currentBtn) currentBtn.classList.add('current');
    };

    scrollWrapper.addEventListener('scroll', updateCurrentThumb);
    requestAnimationFrame(updateCurrentThumb);
    const first = thumbStrip.firstElementChild as HTMLElement | null;
    if (first) first.classList.add('current');
  })();
}
