import { describe, it, expect } from 'vitest';
import { getRotatedAabbSize, boxesOverlap } from './box-drag';
import type { BoxBounds } from './types';

describe('getRotatedAabbSize', () => {
  it('returns same size for 0 degrees', () => {
    const r = getRotatedAabbSize(100, 50, 0);
    expect(r.aabbW).toBe(100);
    expect(r.aabbH).toBe(50);
  });

  it('returns same size for 90 degrees (width and height swap in AABB)', () => {
    const r = getRotatedAabbSize(100, 50, 90);
    expect(r.aabbW).toBe(50);
    expect(r.aabbH).toBe(100);
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
    expect(r.aabbW).toBe(80);
    expect(r.aabbH).toBe(30);
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
