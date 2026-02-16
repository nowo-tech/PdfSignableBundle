import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { getWorkerUrl } from './pdfjs-loader';

const CDN_DEFAULT =
  'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

describe('getWorkerUrl', () => {
  beforeEach(() => {
    vi.stubGlobal('document', { currentScript: null });
  });
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('returns CDN URL when pdfjsSource is "cdn" and no custom worker', () => {
    expect(getWorkerUrl({ pdfjsSource: 'cdn' })).toBe(CDN_DEFAULT);
  });

  it('returns custom pdfjsWorkerUrl when pdfjsSource is "cdn" and url set', () => {
    const custom = 'https://example.com/worker.js';
    expect(getWorkerUrl({ pdfjsSource: 'cdn', pdfjsWorkerUrl: custom })).toBe(custom);
  });

  it('returns custom pdfjsWorkerUrl when pdfjsSource is "npm" and url set (not 3.11 CDN)', () => {
    const custom = '/bundles/nowopdfsignable/js/pdf.worker.min.js';
    expect(getWorkerUrl({ pdfjsSource: 'npm', pdfjsWorkerUrl: custom })).toBe(custom);
  });

  it('throws when pdfjsSource is "npm" and pdfjsWorkerUrl contains 3.11.174', () => {
    expect(() =>
      getWorkerUrl({
        pdfjsSource: 'npm',
        pdfjsWorkerUrl: 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js',
      })
    ).toThrow(/3\.11 CDN/);
  });

  it('with npm and no worker url: uses script src replacement when currentScript has src', () => {
    (document as { currentScript: { src: string } }).currentScript = {
      src: 'https://example.com/assets/pdf-signable.js',
    };
    expect(getWorkerUrl({ pdfjsSource: 'npm' })).toBe(
      'https://example.com/assets/pdf.worker.min.js'
    );
  });

  it('with npm and no worker url and no script src: throws', () => {
    (document as { currentScript: HTMLScriptElement | null }).currentScript = null;
    expect(() => getWorkerUrl({ pdfjsSource: 'npm' })).toThrow(/could not resolve worker/);
  });
});
