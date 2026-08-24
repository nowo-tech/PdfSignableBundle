import { describe, expect, it } from 'vitest';

import * as AcroformIndex from './index';

describe('acroform-editor/index', () => {
  it('re-exports config, strings and move/resize helpers', () => {
    expect(AcroformIndex.getConfig).toBeTypeOf('function');
    expect(AcroformIndex.FIELD_NAME_VALUE_OTHER).toBe('__other__');
    expect(AcroformIndex.escapeAttr).toBeTypeOf('function');
    expect(AcroformIndex.DEFAULT_STRINGS).toEqual({});
    expect(AcroformIndex.createAcroformMoveResize).toBeTypeOf('function');
  });
});
