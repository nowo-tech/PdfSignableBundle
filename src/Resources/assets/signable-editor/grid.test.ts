import { describe, it, expect, vi, beforeEach } from 'vitest';
import { drawGridOnCanvas } from './grid';
import type { PDFViewport } from './types';

function createMockContext() {
  return {
    beginPath: vi.fn(),
    moveTo: vi.fn(),
    lineTo: vi.fn(),
    stroke: vi.fn(),
    strokeStyle: '',
    lineWidth: 0,
  };
}

function createMockViewport(width = 595, height = 842, scale = 1): PDFViewport {
  return {
    width: width * scale,
    height: height * scale,
    scale,
  } as PDFViewport;
}

describe('drawGridOnCanvas', () => {
  let gridCanvas: HTMLCanvasElement;
  let ctx: ReturnType<typeof createMockContext>;

  beforeEach(() => {
    gridCanvas = document.createElement('canvas');
    ctx = createMockContext();
    vi.spyOn(gridCanvas, 'getContext').mockReturnValue(ctx as unknown as CanvasRenderingContext2D);
  });

  it('draws grid lines in pt unit with step 72', () => {
    const viewport = createMockViewport(72 * 4, 72 * 4, 1);
    drawGridOnCanvas(gridCanvas, viewport, 1, 'pt', 72);
    expect(ctx.strokeStyle).toBe('rgba(0, 0, 0, 0.15)');
    expect(ctx.lineWidth).toBe(1);
    expect(ctx.beginPath).toHaveBeenCalled();
    expect(ctx.stroke).toHaveBeenCalled();
    expect(ctx.moveTo).toHaveBeenCalled();
    expect(ctx.lineTo).toHaveBeenCalled();
  });

  it('draws vertical and horizontal lines', () => {
    const viewport = createMockViewport(72 * 2, 72 * 2, 1);
    drawGridOnCanvas(gridCanvas, viewport, 1, 'pt', 72);
    const moveCalls = (ctx.moveTo as ReturnType<typeof vi.fn>).mock.calls;
    const lineCalls = (ctx.lineTo as ReturnType<typeof vi.fn>).mock.calls;
    expect(moveCalls.length).toBeGreaterThan(0);
    expect(lineCalls.length).toBeGreaterThan(0);
  });

  it('uses mm unit for grid step', () => {
    const viewport = createMockViewport(595, 842, 1);
    drawGridOnCanvas(gridCanvas, viewport, 1, 'mm', 10);
    expect(ctx.beginPath).toHaveBeenCalled();
    expect(ctx.stroke).toHaveBeenCalled();
  });

  it('does nothing when getContext returns null', () => {
    vi.mocked(gridCanvas.getContext).mockReturnValue(null);
    const viewport = createMockViewport(100, 100, 1);
    drawGridOnCanvas(gridCanvas, viewport, 1, 'pt', 10);
    expect(ctx.beginPath).not.toHaveBeenCalled();
    expect(ctx.stroke).not.toHaveBeenCalled();
  });

  it('respects scale for viewport dimensions', () => {
    const scale = 1.5;
    const viewport = createMockViewport(100, 100, scale);
    drawGridOnCanvas(gridCanvas, viewport, scale, 'pt', 50);
    expect(ctx.moveTo).toHaveBeenCalled();
    const firstMove = (ctx.moveTo as ReturnType<typeof vi.fn>).mock.calls[0];
    expect(firstMove[0]).toBeLessThanOrEqual(viewport.width);
    expect(firstMove[1]).toBeLessThanOrEqual(viewport.height);
  });
});
