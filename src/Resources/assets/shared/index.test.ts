import { describe, expect, it } from 'vitest';

import * as SharedIndex from './index';

describe('shared/index', () => {
  it('re-exporta utilidades principales', () => {
    expect(SharedIndex.SCALE_GUTTER).toBeTypeOf('number');
    expect(SharedIndex.getLoadUrl).toBeTypeOf('function');
    expect(SharedIndex.getScaleForFitWidth).toBeTypeOf('function');
    expect(SharedIndex.getScaleForFitPage).toBeTypeOf('function');
    expect(SharedIndex.bindZoomToolbar).toBeTypeOf('function');
    expect(SharedIndex.getPdfJsLib).toBeTypeOf('function');
    expect(SharedIndex.getWorkerUrl).toBeTypeOf('function');
  });
});
