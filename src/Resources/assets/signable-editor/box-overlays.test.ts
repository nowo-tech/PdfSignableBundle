import { describe, expect, it, vi } from 'vitest';

import { updateOverlays, type BoxOverlaysContext } from './box-overlays';

function buildContext(): BoxOverlaysContext {
  const canvasWrapper = document.createElement('div');
  canvasWrapper.innerHTML = `
    <div class="pdf-page-wrapper" data-page="1">
      <div class="signature-overlays"></div>
    </div>
  `;

  const boxesList = document.createElement('div');
  boxesList.innerHTML = `
    <div class="signature-box-item" data-pdf-signable="box-item">
      <input data-pdf-signable="page" value="1" />
      <input data-pdf-signable="x" value="10" />
      <input data-pdf-signable="y" value="20" />
      <input data-pdf-signable="width" value="30" />
      <input data-pdf-signable="height" value="40" />
      <input data-pdf-signable="name" value="Firmante" />
      <input data-pdf-signable="signature-data" value="" />
    </div>
  `;

  return {
    canvasWrapper,
    boxesList,
    boxItemSelector: '.signature-box-item',
    pageViewports: { 1: { scale: 1.5 } as unknown as { scale: number } },
    getSelectedUnit: () => 'mm',
    getSelectedOrigin: () => 'bottom_left',
    getPageField: (container) => container.querySelector('[data-pdf-signable="page"]') as HTMLInputElement,
    formToViewport: () => ({ vpX: 5, vpY: 6 }),
    unitToPt: (v) => v,
    getColorForBoxIndex: () => ({ border: '#000', background: 'rgba(0,0,0,.1)', color: '#111', handle: '#222' }),
    escapeHtml: (s) => s,
    enableRotation: false,
    lockBoxDimensions: false,
    selectedBoxIndex: 0,
    isDragging: false,
    debugLog: vi.fn(),
    debugWarn: vi.fn(),
  };
}

describe('signable-editor/box-overlays', () => {
  it('no modifica overlays si esta arrastrando', () => {
    const ctx = buildContext();
    const target = ctx.canvasWrapper.querySelector('.signature-overlays') as HTMLElement;
    target.innerHTML = '<span id="keep"></span>';
    ctx.isDragging = true;

    updateOverlays(ctx);

    expect(target.querySelector('#keep')).not.toBeNull();
  });

  it('pinta overlay y marca seleccionado', () => {
    const ctx = buildContext();

    updateOverlays(ctx);

    const overlay = ctx.canvasWrapper.querySelector('[data-pdf-signable="overlay"]') as HTMLElement;
    expect(overlay).not.toBeNull();
    expect(overlay.classList.contains('selected')).toBe(true);
    expect(overlay.dataset.boxIndex).toBe('0');
  });

  it('agrega rotacion, imagen y etiqueta incremental por nombre repetido', () => {
    const ctx = buildContext();
    ctx.enableRotation = true;
    ctx.lockBoxDimensions = true;
    ctx.selectedBoxIndex = null;
    ctx.boxesList.innerHTML = `
      <div class="signature-box-item" data-pdf-signable="box-item">
        <input data-pdf-signable="page" value="1" />
        <input data-pdf-signable="x" value="10" />
        <input data-pdf-signable="y" value="20" />
        <input data-pdf-signable="width" value="30" />
        <input data-pdf-signable="height" value="40" />
        <input data-pdf-signable="angle" value="30" />
        <input data-pdf-signable="name" value="Firmante" />
        <input data-pdf-signable="signature-data" value="data:image/png;base64,abc" />
      </div>
      <div class="signature-box-item" data-pdf-signable="box-item">
        <input data-pdf-signable="page" value="1" />
        <input data-pdf-signable="x" value="50" />
        <input data-pdf-signable="y" value="60" />
        <input data-pdf-signable="width" value="30" />
        <input data-pdf-signable="height" value="40" />
        <input data-pdf-signable="angle" value="10" />
        <input data-pdf-signable="name" value="Firmante" />
        <input data-pdf-signable="signature-data" value="" />
      </div>
    `;

    updateOverlays(ctx);

    const overlays = ctx.canvasWrapper.querySelectorAll<HTMLElement>('[data-pdf-signable="overlay"]');
    expect(overlays).toHaveLength(2);
    expect(overlays[0].style.transform).toContain('rotate(30deg)');
    expect(overlays[0].querySelector('.rotate-handle')).not.toBeNull();
    expect(overlays[0].querySelector('.resize-handle')).toBeNull();
    expect(overlays[0].querySelector('img')).not.toBeNull();
    expect(overlays[1].innerHTML).toContain('Firmante (2)');
  });

  it('hace warn cuando falta page/viewport o coordenadas', () => {
    const ctx = buildContext();
    ctx.selectedBoxIndex = null;
    ctx.boxesList.innerHTML = `
      <div class="signature-box-item" data-pdf-signable="box-item">
        <input data-pdf-signable="page" value="2" />
        <input data-pdf-signable="name" value="SinDatos" />
      </div>
    `;
    ctx.getPageField = (container) => container.querySelector('[data-pdf-signable="page"]') as HTMLInputElement;

    updateOverlays(ctx);

    expect((ctx.debugWarn as unknown as { mock: { calls: unknown[][] } }).mock.calls.length).toBeGreaterThan(0);
    expect(ctx.canvasWrapper.querySelector('[data-pdf-signable="overlay"]')).toBeNull();
  });

  it('cubre nombre vacio, escala por defecto y sin seleccionado', () => {
    const ctx = buildContext();
    ctx.selectedBoxIndex = 99;
    ctx.pageViewports = { 1: {} as unknown as { scale?: number } };
    ctx.boxesList.innerHTML = `
      <div class="signature-box-item" data-pdf-signable="box-item">
        <input data-pdf-signable="page" value="1" />
        <input data-pdf-signable="x" value="1" />
        <input data-pdf-signable="y" value="2" />
        <input data-pdf-signable="width" value="3" />
        <input data-pdf-signable="height" value="4" />
        <input data-pdf-signable="name" value="   " />
        <input data-pdf-signable="signature-data" value="not-data-uri" />
      </div>
    `;

    updateOverlays(ctx);
    const overlay = ctx.canvasWrapper.querySelector('[data-pdf-signable="overlay"]') as HTMLElement;
    expect(overlay).not.toBeNull();
    expect(overlay.querySelector('.overlay-label')).toBeNull();
    expect(overlay.classList.contains('selected')).toBe(false);
    expect(overlay.querySelector('img')).toBeNull();
  });
});
