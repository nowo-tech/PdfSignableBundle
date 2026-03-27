import { beforeEach, describe, expect, it, vi } from 'vitest';

import { initSignaturePads, resizeSignatureCanvas } from './signature-pad';

describe('signable-editor/signature-pad', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  it('resizeSignatureCanvas no cambia si no hay tamano visible', () => {
    const canvas = document.createElement('canvas');
    canvas.width = 10;
    canvas.height = 10;
    vi.spyOn(canvas, 'getBoundingClientRect').mockReturnValue({
      width: 0,
      height: 0,
      left: 0,
      top: 0,
      right: 0,
      bottom: 0,
      x: 0,
      y: 0,
      toJSON: () => ({}),
    });

    resizeSignatureCanvas(canvas);

    expect(canvas.width).toBe(10);
    expect(canvas.height).toBe(10);
  });

  it('initSignaturePads hace early return con root null', () => {
    const warn = vi.fn();

    initSignaturePads(null, { onOverlayUpdate: vi.fn(), debugWarn: warn });

    expect(warn).toHaveBeenCalledTimes(1);
  });

  it('inicializa canvas, dibuja y limpia con boton clear', () => {
    document.body.innerHTML = `
      <div id="root">
        <div class="signature-box-item" data-pdf-signable="box-item">
          <input data-pdf-signable="signature-data" value="" />
          <input data-pdf-signable="signed-at" value="" />
          <div class="signature-pad-wrapper">
            <canvas class="signature-pad-canvas"></canvas>
            <button class="signature-pad-clear" type="button">Clear</button>
          </div>
        </div>
      </div>
    `;

    const root = document.getElementById('root') as HTMLElement;
    const canvas = root.querySelector('canvas') as HTMLCanvasElement;

    vi.spyOn(globalThis, 'requestAnimationFrame').mockImplementation((cb: FrameRequestCallback) => {
      cb(0);
      return 1;
    });
    vi.spyOn(canvas, 'getBoundingClientRect').mockReturnValue({
      width: 100,
      height: 40,
      left: 0,
      top: 0,
      right: 100,
      bottom: 40,
      x: 0,
      y: 0,
      toJSON: () => ({}),
    });

    const ctx = {
      beginPath: vi.fn(),
      moveTo: vi.fn(),
      quadraticCurveTo: vi.fn(),
      stroke: vi.fn(),
      clearRect: vi.fn(),
      lineWidth: 1,
      strokeStyle: '#000',
      lineCap: 'round' as CanvasLineCap,
      lineJoin: 'round' as CanvasLineJoin,
    };
    vi.spyOn(canvas, 'getContext').mockReturnValue(ctx as unknown as CanvasRenderingContext2D);
    vi.spyOn(canvas, 'toDataURL').mockReturnValue('data:image/png;base64,abc');

    const onOverlayUpdate = vi.fn();
    initSignaturePads(root, { onOverlayUpdate });

    expect(canvas.dataset.padInited).toBe('1');

    canvas.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, clientX: 10, clientY: 10 }));
    canvas.dispatchEvent(new MouseEvent('mousemove', { bubbles: true, clientX: 15, clientY: 15 }));
    canvas.dispatchEvent(new MouseEvent('mouseup', { bubbles: true, clientX: 20, clientY: 20 }));

    const input = root.querySelector('[data-pdf-signable="signature-data"]') as HTMLInputElement;
    expect(input.value).toContain('data:image/png');

    (root.querySelector('.signature-pad-clear') as HTMLButtonElement).click();
    expect(input.value).toBe('');
    expect(onOverlayUpdate).toHaveBeenCalled();
  });

  it('omite canvas sin item o sin input y evita crash si no hay 2d context', () => {
    document.body.innerHTML = `
      <div id="root">
        <canvas class="signature-pad-canvas" id="c1"></canvas>
        <div class="signature-box-item" data-pdf-signable="box-item">
          <div class="signature-pad-wrapper"><canvas class="signature-pad-canvas" id="c2"></canvas></div>
        </div>
        <div class="signature-box-item" data-pdf-signable="box-item">
          <input data-pdf-signable="signature-data" value="" />
          <div class="signature-pad-wrapper"><canvas class="signature-pad-canvas" id="c3"></canvas></div>
        </div>
      </div>
    `;
    const root = document.getElementById('root') as HTMLElement;
    const c1 = document.getElementById('c1') as HTMLCanvasElement;
    const c2 = document.getElementById('c2') as HTMLCanvasElement;
    const c3 = document.getElementById('c3') as HTMLCanvasElement;
    vi.spyOn(c1, 'getContext').mockReturnValue({} as CanvasRenderingContext2D);
    vi.spyOn(c2, 'getContext').mockReturnValue({} as CanvasRenderingContext2D);
    vi.spyOn(c3, 'getContext').mockReturnValue(null);

    const warn = vi.fn();
    initSignaturePads(root, { onOverlayUpdate: vi.fn(), debugWarn: warn });

    expect(warn).toHaveBeenCalled();
    expect(c1.dataset.padInited).not.toBe('1');
    expect(c2.dataset.padInited).not.toBe('1');
    expect(c3.dataset.padInited).not.toBe('1');
  });

  it('procesa file upload de imagen y descarta tipo invalido', () => {
    document.body.innerHTML = `
      <div id="root">
        <div class="signature-box-item" data-pdf-signable="box-item">
          <input data-pdf-signable="signature-data" value="" />
          <input data-pdf-signable="signed-at" value="" />
          <input class="signature-upload-input" type="file" />
          <div class="signature-pad-wrapper">
            <canvas class="signature-pad-canvas"></canvas>
          </div>
        </div>
      </div>
    `;
    const root = document.getElementById('root') as HTMLElement;
    const canvas = root.querySelector('canvas') as HTMLCanvasElement;
    vi.spyOn(canvas, 'getBoundingClientRect').mockReturnValue({
      width: 100, height: 40, left: 0, top: 0, right: 100, bottom: 40, x: 0, y: 0, toJSON: () => ({})
    });
    vi.spyOn(canvas, 'getContext').mockReturnValue({ beginPath: vi.fn(), moveTo: vi.fn(), quadraticCurveTo: vi.fn(), stroke: vi.fn(), clearRect: vi.fn() } as unknown as CanvasRenderingContext2D);
    vi.spyOn(globalThis, 'requestAnimationFrame').mockImplementation((cb: FrameRequestCallback) => { cb(0); return 1; });

    const fileInput = root.querySelector('.signature-upload-input') as HTMLInputElement;
    const input = root.querySelector('[data-pdf-signable="signature-data"]') as HTMLInputElement;
    const onOverlayUpdate = vi.fn();

    class MockReader {
      result: string | ArrayBuffer | null = null;
      onload: null | (() => void) = null;
      readAsDataURL(_file: Blob): void {
        this.result = 'data:image/png;base64,file';
        this.onload?.();
      }
    }
    // @ts-expect-error test mock
    globalThis.FileReader = MockReader;

    initSignaturePads(root, { onOverlayUpdate });

    Object.defineProperty(fileInput, 'files', {
      value: [new File(['x'], 'a.png', { type: 'image/png' })],
      configurable: true,
    });
    fileInput.dispatchEvent(new Event('change', { bubbles: true }));
    expect(input.value).toContain('data:image/png');
    expect(onOverlayUpdate).toHaveBeenCalled();

    Object.defineProperty(fileInput, 'files', {
      value: [new File(['x'], 'a.txt', { type: 'text/plain' })],
      configurable: true,
    });
    input.value = '';
    fileInput.dispatchEvent(new Event('change', { bubbles: true }));
    expect(input.value).toBe('');
  });

  it('cubre touch pressure y evita toDataURL cuando canvas no tiene tamano', () => {
    document.body.innerHTML = `
      <div id="root">
        <div class="signature-box-item" data-pdf-signable="box-item">
          <input data-pdf-signable="signature-data" value="" />
          <div class="signature-pad-wrapper">
            <canvas class="signature-pad-canvas"></canvas>
          </div>
        </div>
      </div>
    `;
    const root = document.getElementById('root') as HTMLElement;
    const canvas = root.querySelector('canvas') as HTMLCanvasElement;
    vi.spyOn(globalThis, 'requestAnimationFrame').mockImplementation((cb: FrameRequestCallback) => {
      cb(0);
      return 1;
    });
    vi.spyOn(canvas, 'getBoundingClientRect').mockReturnValue({
      width: 100,
      height: 40,
      left: 0,
      top: 0,
      right: 100,
      bottom: 40,
      x: 0,
      y: 0,
      toJSON: () => ({}),
    });
    const ctx = {
      beginPath: vi.fn(),
      moveTo: vi.fn(),
      quadraticCurveTo: vi.fn(),
      stroke: vi.fn(),
      clearRect: vi.fn(),
      lineWidth: 1,
      strokeStyle: '#000',
      lineCap: 'round' as CanvasLineCap,
      lineJoin: 'round' as CanvasLineJoin,
    };
    vi.spyOn(canvas, 'getContext').mockReturnValue(ctx as unknown as CanvasRenderingContext2D);
    const toDataSpy = vi.spyOn(canvas, 'toDataURL').mockReturnValue('data:image/png;base64,abc');
    initSignaturePads(root, { onOverlayUpdate: vi.fn() });

    // forzar no tamaño para cubrir rama canvas.width/height == 0 al finalizar
    vi.spyOn(canvas, 'getBoundingClientRect').mockReturnValue({
      width: 0,
      height: 0,
      left: 0,
      top: 0,
      right: 0,
      bottom: 0,
      x: 0,
      y: 0,
      toJSON: () => ({}),
    });
    canvas.width = 0;
    canvas.height = 0;
    const touchStart = new Event('touchstart', { bubbles: true, cancelable: true }) as Event & { touches: unknown[] };
    Object.defineProperty(touchStart, 'touches', { value: [{ clientX: 10, clientY: 10, force: 0.8 }] });
    canvas.dispatchEvent(touchStart);
    const touchMove = new Event('touchmove', { bubbles: true, cancelable: true }) as Event & { touches: unknown[] };
    Object.defineProperty(touchMove, 'touches', { value: [{ clientX: 20, clientY: 20, force: 0.2 }] });
    canvas.dispatchEvent(touchMove);
    const touchEnd = new Event('touchend', { bubbles: true, cancelable: true }) as Event & { touches: unknown[] };
    Object.defineProperty(touchEnd, 'touches', { value: [] });
    canvas.dispatchEvent(touchEnd);

    expect(ctx.lineWidth).toBeGreaterThanOrEqual(1);
    expect(toDataSpy).toHaveBeenCalledTimes(0);
  });
});
