import { describe, expect, it } from 'vitest';

import type { BoxBounds } from './types';
import * as SignableTypes from './types';

describe('signable-editor/types', () => {
  it('loads the module and accepts BoxBounds shape', () => {
    const box: BoxBounds = { page: 1, x: 0, y: 0, w: 10, h: 20 };
    expect(box.page).toBe(1);
    expect(SignableTypes).toBeTypeOf('object');
  });
});
