import { describe, it, expect, vi } from 'vitest';
import { createAcroformMoveResize, viewportPixelsToPdfRect } from './acroform-move-resize';

describe('viewportPixelsToPdfRect', () => {
  const viewport = {
    scale: 1.5,
    height: 900,
  };

  it('converts viewport pixels to PDF rect [llx, lly, urx, ury]', () => {
    const result = viewportPixelsToPdfRect(viewport as any, 0, 0, 150, 30);
    expect(result).toHaveLength(4);
    expect(result[0]).toBeCloseTo(0); // llx
    expect(result[1]).toBeCloseTo(580); // lly (height - top - height = 900 - 0 - 30 = 870... no: ury = 900/1.5 - 0 = 600, lly = 600 - 30/1.5 = 580)
    expect(result[2]).toBeCloseTo(100); // urx (0 + 150) / 1.5
    expect(result[3]).toBeCloseTo(600); // ury (900 - 0) / 1.5
  });

  it('uses scale for conversion', () => {
    const r1 = viewportPixelsToPdfRect(
      { scale: 1, height: 100 } as any,
      10,
      10,
      50,
      20
    );
    expect(r1[0]).toBe(10);
    expect(r1[2]).toBe(60); // 10 + 50
  });

  it('inverts Y for PDF bottom-left origin', () => {
    const result = viewportPixelsToPdfRect(viewport as any, 0, 100, 10, 20);
    // top=100 in viewport (from top); bottom of rect = 100+20 = 120 from top
    // ury = (900 - 100) / 1.5 = 533.33
    // lly = (900 - 120) / 1.5 = 520
    expect(result[3]).toBeGreaterThan(result[1]);
  });

  it('uses scale 1.5 when scale is missing', () => {
    const vp = { height: 600 }; // no scale
    const result = viewportPixelsToPdfRect(vp as any, 0, 0, 15, 15);
    expect(result[2]).toBeCloseTo(10); // 15 / 1.5
  });
});

describe('createAcroformMoveResize', () => {
  it('showOverlay/hideOverlay maneja flujo base y callbacks', () => {
    const canvasWrapper = document.createElement('div');
    canvasWrapper.innerHTML = `
      <div class="pdf-page-wrapper" data-page="1">
        <div class="acroform-field-outline" data-field-id="f1" style="left:10px;top:20px;width:100px;height:40px"></div>
      </div>
    `;
    document.body.appendChild(canvasWrapper);

    const onRectChanged = vi.fn();
    const onRendered = vi.fn();
    const controller = createAcroformMoveResize({
      canvasWrapper,
      getPageViewport: () => ({ scale: 1, width: 500, height: 600 } as any),
      getTouchScale: () => 1,
      onRectChanged,
      onRendered,
    });

    controller.showOverlay('f1', '1');
    const overlay = canvasWrapper.querySelector('.acroform-move-resize-overlay') as HTMLElement;
    expect(overlay).not.toBeNull();

    overlay.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, clientX: 100, clientY: 100 }));
    document.dispatchEvent(new MouseEvent('mousemove', { bubbles: true, clientX: 130, clientY: 140 }));
    document.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));

    expect(onRectChanged).toHaveBeenCalledTimes(1);
    expect((window as any).__pdfSignableAcroFormMoveResizeFieldId).toBe('f1');

    controller.hideOverlay();
    expect(canvasWrapper.querySelector('.acroform-move-resize-layer')).toBeNull();
    expect((window as any).__pdfSignableAcroFormMoveResizeFieldId).toBeUndefined();
  });

  it('showOverlay hace return si no encuentra outline o viewport', () => {
    const canvasWrapper = document.createElement('div');
    canvasWrapper.innerHTML = `<div class="pdf-page-wrapper" data-page="1"></div>`;

    const controllerNoOutline = createAcroformMoveResize({
      canvasWrapper,
      getPageViewport: () => ({ scale: 1, width: 200, height: 200 } as any),
      getTouchScale: () => 1,
      onRectChanged: vi.fn(),
      onRendered: vi.fn(),
    });
    controllerNoOutline.showOverlay('missing', '1');
    expect(canvasWrapper.querySelector('.acroform-move-resize-layer')).toBeNull();

    const outline = document.createElement('div');
    outline.className = 'acroform-field-outline';
    outline.dataset.fieldId = 'f2';
    (canvasWrapper.querySelector('.pdf-page-wrapper') as HTMLElement).appendChild(outline);
    const controllerNoViewport = createAcroformMoveResize({
      canvasWrapper,
      getPageViewport: () => undefined,
      getTouchScale: () => 1,
      onRectChanged: vi.fn(),
      onRendered: vi.fn(),
    });
    controllerNoViewport.showOverlay('f2', '1');
    expect(canvasWrapper.querySelector('.acroform-move-resize-layer')).toBeNull();
  });

  it('cubre resize con handles y salida temprana en onEnd sin estado', () => {
    const canvasWrapper = document.createElement('div');
    canvasWrapper.innerHTML = `
      <div class="pdf-page-wrapper" data-page="1">
        <div class="acroform-field-outline" data-field-id="f3" style="left:10px;top:20px;width:80px;height:30px"></div>
      </div>
    `;
    const onRectChanged = vi.fn();
    const controller = createAcroformMoveResize({
      canvasWrapper,
      getPageViewport: () => ({ scale: 1, width: 300, height: 300 } as any),
      getTouchScale: () => 1,
      onRectChanged,
      onRendered: vi.fn(),
      minFieldWidthPt: 20,
      minFieldHeightPt: 20,
    });

    controller.showOverlay('f3', '1');
    const overlay = canvasWrapper.querySelector('.acroform-move-resize-overlay') as HTMLElement;
    const handle = overlay.querySelector('.resize-handle.nw') as HTMLElement;
    handle.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, clientX: 10, clientY: 20 }));
    document.dispatchEvent(new MouseEvent('mousemove', { bubbles: true, clientX: 0, clientY: 0 }));
    document.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));
    // Mouseup extra para cubrir guard clause (state null)
    document.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));

    expect(onRectChanged).toHaveBeenCalledTimes(1);
  });

  it('cubre ramas de resize para se/sw/ne', () => {
    const canvasWrapper = document.createElement('div');
    canvasWrapper.innerHTML = `
      <div class="pdf-page-wrapper" data-page="1">
        <div class="acroform-field-outline" data-field-id="f4" style="left:20px;top:20px;width:100px;height:40px"></div>
      </div>
    `;
    const onRectChanged = vi.fn();
    const controller = createAcroformMoveResize({
      canvasWrapper,
      getPageViewport: () => ({ scale: 1, width: 400, height: 400 } as any),
      getTouchScale: () => 1,
      onRectChanged,
      onRendered: vi.fn(),
      minFieldWidthPt: 12,
      minFieldHeightPt: 12,
    });

    const handles = ['se', 'sw', 'ne'] as const;
    for (const h of handles) {
      controller.showOverlay('f4', '1');
      const handle = canvasWrapper.querySelector(`.resize-handle.${h}`) as HTMLElement;
      handle.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, clientX: 100, clientY: 100 }));
      document.dispatchEvent(new MouseEvent('mousemove', { bubbles: true, clientX: 130, clientY: 130 }));
      document.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));
    }

    expect(onRectChanged).toHaveBeenCalledTimes(3);
  });
});
