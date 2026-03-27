import { describe, it, expect } from 'vitest';
import { ptToUnit, unitToPt, escapeHtml, hslToHex, getColorForBoxIndex } from './utils';

describe('ptToUnit', () => {
  it('converts pt to pt (identity)', () => {
    expect(ptToUnit(72, 'pt')).toBe(72);
  });

  it('converts pt to mm', () => {
    expect(ptToUnit(72, 'mm')).toBeCloseTo(25.4, 5);
  });

  it('converts pt to cm', () => {
    expect(ptToUnit(72, 'cm')).toBeCloseTo(2.54, 5);
  });

  it('converts pt to px', () => {
    expect(ptToUnit(72, 'px')).toBeCloseTo(96, 5);
  });

  it('returns value as-is for unknown unit', () => {
    expect(ptToUnit(100, 'unknown')).toBe(100);
  });

  it('converts pt to in (inches)', () => {
    expect(ptToUnit(72, 'in')).toBeCloseTo(1, 5);
  });
});

describe('unitToPt', () => {
  it('converts pt to pt (identity)', () => {
    expect(unitToPt(72, 'pt')).toBe(72);
  });

  it('converts mm to pt', () => {
    expect(unitToPt(25.4, 'mm')).toBeCloseTo(72, 5);
  });

  it('converts cm to pt', () => {
    expect(unitToPt(2.54, 'cm')).toBeCloseTo(72, 5);
  });

  it('converts px to pt', () => {
    expect(unitToPt(96, 'px')).toBeCloseTo(72, 5);
  });

  it('returns value as-is for unknown unit (divide by 1)', () => {
    expect(unitToPt(100, 'unknown')).toBe(100);
  });

  it('converts in (inches) to pt', () => {
    expect(unitToPt(1, 'in')).toBeCloseTo(72, 5);
  });
});

describe('escapeHtml', () => {
  it('escapes ampersand', () => {
    expect(escapeHtml('a & b')).toBe('a &amp; b');
  });

  it('escapes less-than and greater-than', () => {
    expect(escapeHtml('<script>')).toBe('&lt;script&gt;');
  });

  it('escapes quotes', () => {
    expect(escapeHtml('"hello"')).toBe('&quot;hello&quot;');
  });

  it('handles non-string by converting to string', () => {
    expect(escapeHtml(123 as unknown as string)).toBe('123');
  });

  it('escapes apostrophe', () => {
    expect(escapeHtml("don't")).toBe('don&#39;t');
  });
});

describe('hslToHex', () => {
  it('converts red (h=0) to hex', () => {
    expect(hslToHex(0, 100, 50)).toBe('#ff0000');
  });

  it('converts green (h=120) to hex', () => {
    expect(hslToHex(120, 100, 50)).toBe('#00ff00');
  });

  it('handles hue modulo 360', () => {
    expect(hslToHex(360, 100, 50)).toBe('#ff0000');
  });

  it('handles negative hue (normalized)', () => {
    expect(hslToHex(-60, 100, 50)).toBe(hslToHex(300, 100, 50));
  });

  it('handles blue (h=240)', () => {
    expect(hslToHex(240, 100, 50)).toBe('#0000ff');
  });
});

describe('getColorForBoxIndex', () => {
  it('returns border, background, color, handle', () => {
    const r = getColorForBoxIndex(0);
    expect(r).toHaveProperty('border');
    expect(r).toHaveProperty('background');
    expect(r).toHaveProperty('color');
    expect(r).toHaveProperty('handle');
  });

  it('returns valid hex for border', () => {
    expect(getColorForBoxIndex(0).border).toMatch(/^#[0-9a-f]{6}$/i);
  });

  it('returns different colors for different indices', () => {
    expect(getColorForBoxIndex(0).border).not.toBe(getColorForBoxIndex(1).border);
  });
});
