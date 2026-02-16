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
});
