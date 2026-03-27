import { describe, expect, it, vi } from 'vitest';

import { bindZoomToolbar } from './zoom-toolbar';

describe('shared/zoom-toolbar', () => {
  it('elimina toolbar previo y enlaza handlers de zoom', async () => {
    document.body.innerHTML = `
      <div id="root">
        <div id="pdf-zoom-toolbar"></div>
      </div>
      <button id="pdfZoomOut"></button>
      <button id="pdfZoomIn"></button>
      <button id="pdfFitWidth"></button>
      <button id="pdfFitPage"></button>
    `;

    const onZoomOut = vi.fn();
    const onZoomIn = vi.fn();
    const onFitWidth = vi.fn(async () => {});
    const onFitPage = vi.fn(async () => {});

    bindZoomToolbar({
      onZoomOut,
      onZoomIn,
      onFitWidth,
      onFitPage,
      container: document.getElementById('root') as HTMLElement,
    });

    expect(document.querySelector('#root #pdf-zoom-toolbar')).toBeNull();

    (document.getElementById('pdfZoomOut') as HTMLButtonElement).click();
    (document.getElementById('pdfZoomIn') as HTMLButtonElement).click();
    (document.getElementById('pdfFitWidth') as HTMLButtonElement).click();
    (document.getElementById('pdfFitPage') as HTMLButtonElement).click();

    expect(onZoomOut).toHaveBeenCalledTimes(1);
    expect(onZoomIn).toHaveBeenCalledTimes(1);
    expect(onFitWidth).toHaveBeenCalledTimes(1);
    expect(onFitPage).toHaveBeenCalledTimes(1);
  });
});
