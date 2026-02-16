import { describe, it, expect } from 'vitest';
import {
  PT_TO_UNIT,
  BOX_COLOR_BASE_HUE,
  BOX_COLOR_HUE_STEP,
  BOX_COLOR_S,
  BOX_COLOR_L,
} from './constants';

describe('PT_TO_UNIT', () => {
  it('pt is 1', () => {
    expect(PT_TO_UNIT.pt).toBe(1);
  });
  it('72 pt in mm equals 25.4', () => {
    expect(72 * PT_TO_UNIT.mm).toBeCloseTo(25.4, 10);
  });
  it('has px and cm', () => {
    expect(PT_TO_UNIT.px).toBeGreaterThan(0);
    expect(PT_TO_UNIT.cm).toBeGreaterThan(0);
  });
  it('has in (inches), 72 pt = 1 in', () => {
    expect(PT_TO_UNIT.in).toBeDefined();
    expect(72 * PT_TO_UNIT.in).toBeCloseTo(1, 10);
  });
  it('has all expected unit keys', () => {
    expect(Object.keys(PT_TO_UNIT).sort()).toEqual(['cm', 'in', 'mm', 'pt', 'px']);
  });
});

describe('BOX_COLOR constants', () => {
  it('BOX_COLOR_BASE_HUE is 220', () => {
    expect(BOX_COLOR_BASE_HUE).toBe(220);
  });
  it('BOX_COLOR_HUE_STEP is 37', () => {
    expect(BOX_COLOR_HUE_STEP).toBe(37);
  });
  it('S and L in 0-100', () => {
    expect(BOX_COLOR_S).toBeGreaterThanOrEqual(0);
    expect(BOX_COLOR_L).toBeLessThanOrEqual(100);
  });
});
