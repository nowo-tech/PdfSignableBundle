import { describe, expect, it, vi } from 'vitest';

import { buildThumbnailsAndLayout, ensureThumbnailsLayout } from './thumbnails';

describe('signable-editor/thumbnails', () => {
  it('ensureThumbnailsLayout retorna si faltan refs y mueve toolbar si existe', () => {
    ensureThumbnailsLayout({
      pdfViewerContainer: null,
      canvasWrapper: document.createElement('div'),
      touchWrapper: document.createElement('div'),
    });

    const container = document.createElement('div');
    const toolbar = document.createElement('div');
    toolbar.className = 'pdf-zoom-toolbar';
    const touchWrapper = document.createElement('div');
    container.appendChild(toolbar);
    container.appendChild(touchWrapper);

    ensureThumbnailsLayout({
      pdfViewerContainer: container,
      canvasWrapper: document.createElement('div'),
      touchWrapper,
    });

    const scroll = container.querySelector('.pdf-viewer-scroll') as HTMLElement;
    expect(scroll).not.toBeNull();
    expect(scroll.querySelector('.pdf-zoom-toolbar')).not.toBeNull();
  });

  it('ensureThumbnailsLayout crea strip y scroll una sola vez', () => {
    const container = document.createElement('div');
    const touchWrapper = document.createElement('div');
    const refs = {
      pdfViewerContainer: container,
      canvasWrapper: document.createElement('div'),
      touchWrapper,
    };
    container.appendChild(touchWrapper);

    ensureThumbnailsLayout(refs);
    expect(container.querySelector('#pdf-thumbnails-strip')).not.toBeNull();
    expect(container.querySelector('.pdf-viewer-scroll')).not.toBeNull();

    ensureThumbnailsLayout(refs);
    expect(container.querySelectorAll('#pdf-thumbnails-strip')).toHaveLength(1);
  });

  it('buildThumbnailsAndLayout hace early return sin layout', () => {
    const pdfDoc = { numPages: 1, getPage: vi.fn() };
    const refs = { pdfViewerContainer: document.createElement('div'), canvasWrapper: document.createElement('div') };

    buildThumbnailsAndLayout(pdfDoc as never, refs);

    expect(refs.pdfViewerContainer.querySelector('#pdf-thumbnails-strip')).toBeNull();
  });

  it('buildThumbnailsAndLayout crea thumbs y sincroniza click/scroll', async () => {
    const container = document.createElement('div');
    const touchWrapper = document.createElement('div');
    const canvasWrapper = document.createElement('div');
    canvasWrapper.innerHTML = `
      <div class="pdf-page-wrapper" data-page="1"></div>
      <div class="pdf-page-wrapper" data-page="2"></div>
    `;
    container.appendChild(touchWrapper);
    ensureThumbnailsLayout({ pdfViewerContainer: container, canvasWrapper, touchWrapper });

    const wrappers = canvasWrapper.querySelectorAll<HTMLElement>('.pdf-page-wrapper');
    vi.spyOn(wrappers[0], 'getBoundingClientRect').mockReturnValue({
      top: 0,
      height: 200,
      width: 100,
      left: 0,
      right: 100,
      bottom: 200,
      x: 0,
      y: 0,
      toJSON: () => ({}),
    });
    vi.spyOn(wrappers[1], 'getBoundingClientRect').mockReturnValue({
      top: 400,
      height: 200,
      width: 100,
      left: 0,
      right: 100,
      bottom: 600,
      x: 0,
      y: 400,
      toJSON: () => ({}),
    });

    const scrollWrapper = container.querySelector('.pdf-viewer-scroll') as HTMLElement;
    vi.spyOn(scrollWrapper, 'getBoundingClientRect').mockReturnValue({
      top: 0,
      height: 300,
      width: 300,
      left: 0,
      right: 300,
      bottom: 300,
      x: 0,
      y: 0,
      toJSON: () => ({}),
    });
    vi.spyOn(globalThis, 'requestAnimationFrame').mockImplementation((cb: FrameRequestCallback) => {
      cb(0);
      return 1;
    });
    const scrollSpy = vi.fn();
    (wrappers[0] as HTMLElement & { scrollIntoView: () => void }).scrollIntoView = scrollSpy;
    (wrappers[1] as HTMLElement & { scrollIntoView: () => void }).scrollIntoView = scrollSpy;
    vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue({} as CanvasRenderingContext2D);

    const pdfDoc = {
      numPages: 2,
      getPage: vi.fn(async () => ({
        getViewport: ({ scale }: { scale: number }) => ({ width: 200 * scale, height: 300 * scale }),
        render: () => ({ promise: Promise.resolve() }),
      })),
    };

    buildThumbnailsAndLayout(pdfDoc as never, { pdfViewerContainer: container, canvasWrapper });
    await new Promise((resolve) => setTimeout(resolve, 0));
    await new Promise((resolve) => setTimeout(resolve, 0));

    const thumbs = container.querySelectorAll<HTMLButtonElement>('.pdf-thumb');
    expect(thumbs).toHaveLength(2);
    expect(thumbs[0].classList.contains('current')).toBe(true);

    thumbs[1].click();
    expect(thumbs[1].classList.contains('current')).toBe(true);
    expect(scrollSpy).toHaveBeenCalled();

    scrollWrapper.dispatchEvent(new Event('scroll'));
    expect(container.querySelector('.pdf-thumb.current')).not.toBeNull();
  });

  it('buildThumbnailsAndLayout no revienta cuando canvas context es null', async () => {
    const container = document.createElement('div');
    const touchWrapper = document.createElement('div');
    const canvasWrapper = document.createElement('div');
    container.appendChild(touchWrapper);
    ensureThumbnailsLayout({ pdfViewerContainer: container, canvasWrapper, touchWrapper });

    vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue(null);
    const pdfDoc = {
      numPages: 1,
      getPage: vi.fn(async () => ({
        getViewport: ({ scale }: { scale: number }) => ({ width: 200 * scale, height: 300 * scale }),
        render: () => ({ promise: Promise.resolve() }),
      })),
    };

    buildThumbnailsAndLayout(pdfDoc as never, { pdfViewerContainer: container, canvasWrapper });
    await new Promise((resolve) => setTimeout(resolve, 0));
    await new Promise((resolve) => setTimeout(resolve, 0));

    expect(container.querySelectorAll('.pdf-thumb')).toHaveLength(0);
  });
});
