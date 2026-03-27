import { describe, it, expect, vi } from 'vitest';
import { getRotatedAabbSize, boxesOverlap, setupBoxDragResizeRotate } from './box-drag';
import type { BoxBounds } from './types';

describe('getRotatedAabbSize', () => {
  it('returns same size for 0 degrees', () => {
    const r = getRotatedAabbSize(100, 50, 0);
    expect(r.aabbW).toBe(100);
    expect(r.aabbH).toBe(50);
  });

  it('returns same size for 90 degrees (width and height swap in AABB)', () => {
    const r = getRotatedAabbSize(100, 50, 90);
    expect(r.aabbW).toBeCloseTo(50, 10);
    expect(r.aabbH).toBeCloseTo(100, 10);
  });

  it('returns larger AABB for 45 degrees', () => {
    const r = getRotatedAabbSize(100, 50, 45);
    const expectedW = 100 * Math.SQRT2 / 2 + 50 * Math.SQRT2 / 2;
    const expectedH = 100 * Math.SQRT2 / 2 + 50 * Math.SQRT2 / 2;
    expect(r.aabbW).toBeCloseTo(expectedW, 10);
    expect(r.aabbH).toBeCloseTo(expectedH, 10);
  });

  it('handles 180 degrees (same as 0)', () => {
    const r = getRotatedAabbSize(80, 30, 180);
    expect(r.aabbW).toBeCloseTo(80, 10);
    expect(r.aabbH).toBeCloseTo(30, 10);
  });
});

describe('boxesOverlap', () => {
  const page = 1;

  it('returns false for different pages', () => {
    const a: BoxBounds = { page: 1, x: 0, y: 0, w: 100, h: 50 };
    const b: BoxBounds = { page: 2, x: 0, y: 0, w: 100, h: 50 };
    expect(boxesOverlap(a, b)).toBe(false);
  });

  it('returns true when boxes intersect', () => {
    const a: BoxBounds = { page, x: 0, y: 0, w: 100, h: 50 };
    const b: BoxBounds = { page, x: 50, y: 25, w: 100, h: 50 };
    expect(boxesOverlap(a, b)).toBe(true);
  });

  it('returns false when boxes are adjacent but do not overlap', () => {
    const a: BoxBounds = { page, x: 0, y: 0, w: 100, h: 50 };
    const b: BoxBounds = { page, x: 100, y: 0, w: 50, h: 50 };
    expect(boxesOverlap(a, b)).toBe(false);
  });

  it('returns true when one box contains the other', () => {
    const a: BoxBounds = { page, x: 0, y: 0, w: 200, h: 200 };
    const b: BoxBounds = { page, x: 50, y: 50, w: 50, h: 50 };
    expect(boxesOverlap(a, b)).toBe(true);
  });

  it('returns false when boxes are vertically separated', () => {
    const a: BoxBounds = { page, x: 0, y: 0, w: 100, h: 50 };
    const b: BoxBounds = { page, x: 0, y: 50, w: 100, h: 50 };
    expect(boxesOverlap(a, b)).toBe(false);
  });
});

describe('setupBoxDragResizeRotate', () => {
  function buildCtx(overrides: Partial<Parameters<typeof setupBoxDragResizeRotate>[0]> = {}) {
    const canvasWrapper = document.createElement('div');
    canvasWrapper.innerHTML = `
      <div class="pdf-page-wrapper" data-page="1">
        <div data-pdf-signable="overlay" data-box-index="0" style="left:10px;top:10px;width:120px;height:40px">
          <span class="resize-handle se" data-handle="se"></span>
        </div>
      </div>
    `;
    const boxesList = document.createElement('div');
    boxesList.innerHTML = `
      <div class="signature-box-item" data-pdf-signable="box-item">
        <input data-pdf-signable="page" value="1" />
        <input data-pdf-signable="x" value="10" />
        <input data-pdf-signable="y" value="10" />
        <input data-pdf-signable="width" value="120" />
        <input data-pdf-signable="height" value="40" />
        <input data-pdf-signable="angle" value="0" />
      </div>
      <div class="signature-box-item" data-pdf-signable="box-item">
        <input data-pdf-signable="page" value="1" />
        <input data-pdf-signable="x" value="200" />
        <input data-pdf-signable="y" value="200" />
        <input data-pdf-signable="width" value="50" />
        <input data-pdf-signable="height" value="40" />
      </div>
    `;

    const ctx: Parameters<typeof setupBoxDragResizeRotate>[0] = {
      canvasWrapper,
      boxesList,
      boxItemSelector: '.signature-box-item',
      pageViewports: { 1: { width: 600, height: 800, scale: 1 } as any },
      getPageField: (container) => container.querySelector('[data-pdf-signable="page"]') as HTMLInputElement,
      getSelectedUnit: () => 'pt',
      getSelectedOrigin: () => 'bottom_left',
      formToViewport: (_vp, xPt, yPt) => ({ vpX: xPt, vpY: yPt }),
      viewportToForm: (_vp, vpLeft, vpTop) => ({ xPt: vpLeft, yPt: vpTop }),
      unitToPt: (v) => v,
      ptToUnit: (v) => v,
      getTouchScale: () => 1,
      onOverlaysUpdate: vi.fn(),
      setIsDragging: vi.fn(),
      preventBoxOverlap: false,
      snapGrid: 0,
      snapToBoxes: false,
      SNAP_THRESHOLD_PX: 8,
      lockBoxDimensions: false,
      enableRotation: false,
      minBoxWidthForm: 0,
      minBoxHeightForm: 0,
      noOverlapMessage: 'no-overlap',
      debugLog: vi.fn(),
      debugWarn: vi.fn(),
      ...overrides,
    };

    return { ctx, canvasWrapper, boxesList };
  }

  it('permite mover overlay y actualiza inputs', () => {
    const { ctx, canvasWrapper, boxesList } = buildCtx();
    const api = setupBoxDragResizeRotate(ctx);
    const overlay = canvasWrapper.querySelector('[data-pdf-signable="overlay"]') as HTMLElement;

    overlay.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, clientX: 20, clientY: 20 }));
    document.dispatchEvent(new MouseEvent('mousemove', { bubbles: true, clientX: 60, clientY: 80 }));
    document.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));

    const item = boxesList.querySelector('.signature-box-item') as HTMLElement;
    const x = item.querySelector('[data-pdf-signable="x"]') as HTMLInputElement;
    const y = item.querySelector('[data-pdf-signable="y"]') as HTMLInputElement;
    expect(parseFloat(x.value)).toBeGreaterThan(10);
    expect(parseFloat(y.value)).toBeGreaterThan(10);
    expect((ctx.onOverlaysUpdate as any).mock.calls.length).toBe(1);

    api.setSelectedBoxIndex(0);
    expect(api.getSelectedBoxIndex()).toBe(0);
  });

  it('si preventBoxOverlap=true revierte y lanza alert', () => {
    const alertSpy = vi.spyOn(window, 'alert').mockImplementation(() => {});
    const { ctx, canvasWrapper, boxesList } = buildCtx({
      preventBoxOverlap: true,
      // Forzar colision con la caja 2 al finalizar drag.
      viewportToForm: () => ({ xPt: 205, yPt: 205 }),
    });
    setupBoxDragResizeRotate(ctx);
    const overlay = canvasWrapper.querySelector('[data-pdf-signable="overlay"]') as HTMLElement;

    overlay.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, clientX: 20, clientY: 20 }));
    document.dispatchEvent(new MouseEvent('mousemove', { bubbles: true, clientX: 30, clientY: 30 }));
    document.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));

    const item = boxesList.querySelector('.signature-box-item') as HTMLElement;
    const x = item.querySelector('[data-pdf-signable="x"]') as HTMLInputElement;
    expect(parseFloat(x.value)).toBe(10);
    expect(alertSpy).toHaveBeenCalledTimes(1);
  });

  it('permite resize con handle y respeta lockBoxDimensions', () => {
    const { ctx, canvasWrapper, boxesList } = buildCtx();
    setupBoxDragResizeRotate(ctx);
    const handle = canvasWrapper.querySelector('.resize-handle.se') as HTMLElement;

    handle.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, clientX: 130, clientY: 50 }));
    document.dispatchEvent(new MouseEvent('mousemove', { bubbles: true, clientX: 180, clientY: 100 }));
    document.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));

    const item = boxesList.querySelector('.signature-box-item') as HTMLElement;
    const w = item.querySelector('[data-pdf-signable="width"]') as HTMLInputElement;
    expect(parseFloat(w.value)).toBeGreaterThan(120);

    const { ctx: ctxLocked, canvasWrapper: cwLocked, boxesList: blLocked } = buildCtx({ lockBoxDimensions: true });
    setupBoxDragResizeRotate(ctxLocked);
    const handleLocked = cwLocked.querySelector('.resize-handle.se') as HTMLElement;
    const wBefore = parseFloat((blLocked.querySelector('[data-pdf-signable="width"]') as HTMLInputElement).value);
    handleLocked.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, clientX: 130, clientY: 50 }));
    document.dispatchEvent(new MouseEvent('mousemove', { bubbles: true, clientX: 200, clientY: 120 }));
    document.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));
    const wAfter = parseFloat((blLocked.querySelector('[data-pdf-signable="width"]') as HTMLInputElement).value);
    expect(wAfter).toBe(wBefore);
  });

  it('cubre flujo de rotacion cuando enableRotation=true', () => {
    const { ctx, canvasWrapper, boxesList } = buildCtx({ enableRotation: true });
    const overlay = canvasWrapper.querySelector('[data-pdf-signable="overlay"]') as HTMLElement;
    overlay.innerHTML += '<span class="rotate-handle"></span>';
    setupBoxDragResizeRotate(ctx);

    const rotateHandle = overlay.querySelector('.rotate-handle') as HTMLElement;
    rotateHandle.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, clientX: 100, clientY: 100 }));
    document.dispatchEvent(new MouseEvent('mousemove', { bubbles: true, clientX: 140, clientY: 120 }));
    document.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));

    const angle = (boxesList.querySelector('[data-pdf-signable="angle"]') as HTMLInputElement).value;
    expect(Number.isFinite(parseFloat(angle))).toBe(true);
  });

  it('aplica snapGrid, snapToBoxes y minBox* durante resize', () => {
    const { ctx, canvasWrapper, boxesList } = buildCtx({
      snapGrid: 10,
      snapToBoxes: true,
      minBoxWidthForm: 140,
      minBoxHeightForm: 60,
      getSelectedOrigin: () => 'bottom_left',
      pageViewports: { 1: { width: 600, height: 800, scale: 2 } as any },
    });
    setupBoxDragResizeRotate(ctx);
    const handle = canvasWrapper.querySelector('.resize-handle.se') as HTMLElement;

    handle.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, clientX: 130, clientY: 50 }));
    document.dispatchEvent(new MouseEvent('mousemove', { bubbles: true, clientX: 210, clientY: 120 }));
    document.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));

    const item = boxesList.querySelector('.signature-box-item') as HTMLElement;
    const w = parseFloat((item.querySelector('[data-pdf-signable="width"]') as HTMLInputElement).value);
    const h = parseFloat((item.querySelector('[data-pdf-signable="height"]') as HTMLInputElement).value);
    expect(w).toBeGreaterThanOrEqual(140);
    expect(h).toBeGreaterThanOrEqual(60);
  });

  it('sale temprano cuando overlay no tiene boxIndex o no hay viewport', () => {
    const { ctx, canvasWrapper } = buildCtx({ pageViewports: {} as any });
    setupBoxDragResizeRotate(ctx);
    const fake = document.createElement('div');
    fake.dataset.pdfSignable = 'overlay';
    canvasWrapper.appendChild(fake);
    fake.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, clientX: 10, clientY: 10 }));
    document.dispatchEvent(new MouseEvent('mousemove', { bubbles: true, clientX: 20, clientY: 20 }));
    document.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));
    expect((ctx.onOverlaysUpdate as any).mock.calls.length).toBe(0);
  });

  it('cubre resize handles sw/ne/nw y rama snap sin umbral', () => {
    const { ctx, canvasWrapper, boxesList } = buildCtx({
      snapToBoxes: true,
      SNAP_THRESHOLD_PX: 1,
      pageViewports: { 1: { width: 600, height: 800, scale: 1 } as any },
    });
    setupBoxDragResizeRotate(ctx);
    const overlay = canvasWrapper.querySelector('[data-pdf-signable="overlay"]') as HTMLElement;

    const handles = ['sw', 'ne', 'nw'] as const;
    for (const h of handles) {
      overlay.innerHTML = `<span class="resize-handle ${h}" data-handle="${h}"></span>`;
      const handle = overlay.querySelector('.resize-handle') as HTMLElement;
      handle.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, clientX: 120, clientY: 40 }));
      // Movimiento amplio para evitar snap por umbral y cubrir rama d >= threshold
      document.dispatchEvent(new MouseEvent('mousemove', { bubbles: true, clientX: 260, clientY: 220 }));
      document.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));
    }

    const item = boxesList.querySelector('.signature-box-item') as HTMLElement;
    const w = parseFloat((item.querySelector('[data-pdf-signable="width"]') as HTMLInputElement).value);
    expect(w).toBeGreaterThan(0);
  });
});
