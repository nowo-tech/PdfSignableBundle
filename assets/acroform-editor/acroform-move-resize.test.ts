import { describe, it, expect } from 'vitest';
import { viewportPixelsToPdfRect } from './acroform-move-resize';

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
