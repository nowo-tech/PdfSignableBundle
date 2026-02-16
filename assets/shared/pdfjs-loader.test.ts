import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { getWorkerUrl, getPdfJsLib } from './pdfjs-loader';

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

  it('converts relative worker URL to absolute when window has location', () => {
    vi.stubGlobal('window', { location: { origin: 'http://localhost:8006' }, URL: globalThis.URL });
    expect(getWorkerUrl({ pdfjsSource: 'npm', pdfjsWorkerUrl: '/bundles/nowopdfsignable/js/pdf.worker.min.js' })).toBe(
      'http://localhost:8006/bundles/nowopdfsignable/js/pdf.worker.min.js'
    );
  });

  it('leaves absolute and protocol-relative URLs unchanged', () => {
    vi.stubGlobal('window', { location: { origin: 'http://localhost:8006' }, URL: globalThis.URL });
    const absolute = 'https://cdn.example.com/pdf.worker.js';
    expect(getWorkerUrl({ pdfjsSource: 'npm', pdfjsWorkerUrl: absolute })).toBe(absolute);
    const protocolRel = '//cdn.example.com/pdf.worker.js';
    expect(getWorkerUrl({ pdfjsSource: 'npm', pdfjsWorkerUrl: protocolRel })).toBe(protocolRel);
  });

  it('throws when pdfjsSource is "npm" and pdfjsWorkerUrl contains 3.11.174', () => {
    expect(() =>
      getWorkerUrl({
        pdfjsSource: 'npm',
        pdfjsWorkerUrl: 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js',
      })
    ).toThrow(/3\.11 CDN/);
  });

  it('with npm and empty string pdfjsWorkerUrl: falls through to script resolution', () => {
    (document as { currentScript: { src: string } }).currentScript = {
      src: 'http://app.local/pdf-signable.js',
    };
    expect(getWorkerUrl({ pdfjsSource: 'npm', pdfjsWorkerUrl: '' })).toBe(
      'http://app.local/pdf.worker.min.js'
    );
  });

  it('with npm and no worker url: uses script src replacement when currentScript has src', () => {
    (document as { currentScript: { src: string } }).currentScript = {
      src: 'https://example.com/assets/pdf-signable.js',
    };
    expect(getWorkerUrl({ pdfjsSource: 'npm' })).toBe(
      'https://example.com/assets/pdf.worker.min.js'
    );
  });

  it('with npm and no currentScript: uses querySelector bundle script fallback', () => {
    const scriptSrc = 'https://example.com/bundles/nowopdfsignable/js/acroform-editor.js';
    vi.stubGlobal('document', {
      currentScript: null,
      querySelector: vi.fn((sel: string) =>
        sel.includes('acroform-editor') ? { src: scriptSrc } : null
      ),
    });
    expect(getWorkerUrl({ pdfjsSource: 'npm' })).toBe(
      'https://example.com/bundles/nowopdfsignable/js/pdf.worker.min.js'
    );
  });

  it('with npm and no worker url and no script src: throws', () => {
    (document as { currentScript: HTMLScriptElement | null }).currentScript = null;
    expect(() => getWorkerUrl({ pdfjsSource: 'npm' })).toThrow(/could not resolve worker/);
  });
});

describe('getPdfJsLib', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('throws when pdfjsSource is "cdn" and window.pdfjsLib is undefined', async () => {
    vi.stubGlobal('window', {});
    await expect(getPdfJsLib({ pdfjsSource: 'cdn' })).rejects.toThrow(/PDF.js not loaded/);
  });

  it('returns window.pdfjsLib when pdfjsSource is "cdn" and it is defined', async () => {
    const fakePdfJs = { getDocument: () => ({}), GlobalWorkerOptions: {} };
    vi.stubGlobal('window', { pdfjsLib: fakePdfJs });
    const result = await getPdfJsLib({ pdfjsSource: 'cdn' });
    expect(result).toBe(fakePdfJs);
  });
});
