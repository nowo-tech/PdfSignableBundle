import { describe, expect, it } from 'vitest';

import * as SignableIndex from './index';

describe('signable-editor/index', () => {
  it('re-exporta API de editor', () => {
    expect(SignableIndex.setupBoxDragResizeRotate).toBeTypeOf('function');
    expect(SignableIndex.updateOverlays).toBeTypeOf('function');
    expect(SignableIndex.formToViewport).toBeTypeOf('function');
    expect(SignableIndex.viewportToForm).toBeTypeOf('function');
    expect(SignableIndex.pdfToFormCoords).toBeTypeOf('function');
    expect(SignableIndex.drawGridOnCanvas).toBeTypeOf('function');
    expect(SignableIndex.initSignaturePads).toBeTypeOf('function');
    expect(SignableIndex.ptToUnit).toBeTypeOf('function');
    expect(SignableIndex.unitToPt).toBeTypeOf('function');
    expect(SignableIndex.escapeHtml).toBeTypeOf('function');
    expect(SignableIndex.getColorForBoxIndex).toBeTypeOf('function');
  });
});
