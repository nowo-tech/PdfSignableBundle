import { describe, it, expect } from 'vitest';
import { formToViewport, viewportToForm, pdfToFormCoords } from './coordinates';

/** Mock viewport: scale 1.5, width/height for A4-like page in pt (595x842 at scale 1). */
function createMockViewport(scale = 1.5) {
  const width = 595 * scale;
  const height = 842 * scale;
  return {
    scale,
    width,
    height,
    convertToPdfPoint: (x: number, y: number) => [x / scale, (height - y) / scale] as [number, number],
  };
}

describe('formToViewport', () => {
  it('bottom_left: xPt,yPt map to vpX and vpY from bottom', () => {
    const vp = createMockViewport(1.5);
    const r = formToViewport(vp, 100, 100, 50, 20, 'bottom_left');
    expect(r.vpX).toBe(100 * 1.5);
    expect(r.vpY).toBe(vp.height - (100 + 20) * 1.5);
  });

  it('top_left: y is flipped from top', () => {
    const vp = createMockViewport(1.5);
    const r = formToViewport(vp, 0, 0, 100, 50, 'top_left');
    expect(r.vpX).toBe(0);
    expect(r.vpY).toBe(0);
  });

  it('default origin is bottom_left', () => {
    const vp = createMockViewport(1);
    const rDefault = formToViewport(vp, 10, 20, 30, 40, 'bottom_left');
    const rOther = formToViewport(vp, 10, 20, 30, 40, 'other' as string);
    expect(rDefault.vpX).toBe(rOther.vpX);
    expect(rDefault.vpY).toBe(rOther.vpY);
  });

  it('top_right y bottom_right ajustan X respecto al ancho de pagina', () => {
    const vp = createMockViewport(1);
    const tr = formToViewport(vp, 10, 20, 30, 40, 'top_right');
    const br = formToViewport(vp, 10, 20, 30, 40, 'bottom_right');

    expect(tr.vpX).toBe(595 - 10 - 30);
    expect(br.vpX).toBe(595 - 10 - 30);
    // top_right y bottom_right solo difieren en Y
    expect(tr.vpY).not.toBe(br.vpY);
  });
});

describe('viewportToForm', () => {
  it('round-trips with formToViewport for bottom_left', () => {
    const vp = createMockViewport(1.5);
    const xPt = 100;
    const yPt = 200;
    const wPt = 80;
    const hPt = 25;
    const { vpX, vpY } = formToViewport(vp, xPt, yPt, wPt, hPt, 'bottom_left');
    const back = viewportToForm(vp, vpX, vpY, wPt, hPt, 'bottom_left');
    expect(back.xPt).toBeCloseTo(xPt, 5);
    expect(back.yPt).toBeCloseTo(yPt, 5);
  });

  it('convierte correctamente para top_right y bottom_right', () => {
    const vp = createMockViewport(1);
    const wPt = 30;
    const hPt = 20;
    const x = 50;
    const y = 60;

    const trVp = formToViewport(vp, x, y, wPt, hPt, 'top_right');
    const trBack = viewportToForm(vp, trVp.vpX, trVp.vpY, wPt, hPt, 'top_right');
    expect(trBack.xPt).toBeCloseTo(x, 5);
    expect(trBack.yPt).toBeCloseTo(y, 5);

    const brVp = formToViewport(vp, x, y, wPt, hPt, 'bottom_right');
    const brBack = viewportToForm(vp, brVp.vpX, brVp.vpY, wPt, hPt, 'bottom_right');
    expect(brBack.xPt).toBeCloseTo(x, 5);
    expect(brBack.yPt).toBeCloseTo(y, 5);
  });
});

describe('pdfToFormCoords', () => {
  const pageW = 595;
  const pageH = 842;

  it('bottom_left: xForm = xPdf, yForm = yPdf', () => {
    const r = pdfToFormCoords(pageW, pageH, 50, 100, 60, 20, 'bottom_left');
    expect(r.xForm).toBe(50);
    expect(r.yForm).toBe(100);
  });

  it('top_left: yForm = pageH - yPdf - hPt', () => {
    const r = pdfToFormCoords(pageW, pageH, 0, 800, 100, 42, 'top_left');
    expect(r.xForm).toBe(0);
    expect(r.yForm).toBe(pageH - 800 - 42);
  });

  it('top_right: xForm = pageW - xPdf - wPt, yForm from top', () => {
    const r = pdfToFormCoords(pageW, pageH, 400, 800, 100, 30, 'top_right');
    expect(r.xForm).toBe(pageW - 400 - 100);
    expect(r.yForm).toBe(pageH - 800 - 30);
  });

  it('bottom_right: xForm = pageW - xPdf - wPt', () => {
    const r = pdfToFormCoords(pageW, pageH, 500, 50, 95, 15, 'bottom_right');
    expect(r.xForm).toBe(pageW - 500 - 95);
    expect(r.yForm).toBe(50);
  });

  it('default origin usa coordenadas PDF directas', () => {
    const r = pdfToFormCoords(pageW, pageH, 11, 22, 33, 44, 'unknown');
    expect(r.xForm).toBe(11);
    expect(r.yForm).toBe(22);
  });
});
