import { describe, it, expect } from 'vitest';
import { SCALE_GUTTER } from './constants';

describe('SCALE_GUTTER', () => {
  it('equals 24', () => {
    expect(SCALE_GUTTER).toBe(24);
  });
  it('is a positive number', () => {
    expect(typeof SCALE_GUTTER).toBe('number');
    expect(SCALE_GUTTER).toBeGreaterThan(0);
  });
});
