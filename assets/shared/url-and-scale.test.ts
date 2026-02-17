import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { getLoadUrl, getScaleForFitWidth, getScaleForFitPage } from './url-and-scale';

describe('getLoadUrl', () => {
  const proxy = '/proxy';
  beforeEach(() => {
    vi.stubGlobal('window', { location: { origin: 'https://example.com' } });
  });
  afterEach(() => {
    vi.unstubAllGlobals();
  });
  it('same-origin URL as-is', () => {
    expect(getLoadUrl(proxy, 'https://example.com/a.pdf')).toBe('https://example.com/a.pdf');
  });
  it('cross-origin uses proxy', () => {
    const r = getLoadUrl(proxy, 'https://other.com/a.pdf');
    expect(r).toContain(proxy);
    expect(r).toContain('url=');
  });

  it('invalid URL uses proxy with encoded url param', () => {
    const r = getLoadUrl(proxy, 'not-a-valid-url');
    expect(r).toContain(proxy);
    expect(r).toContain('url=');
    expect(r).toContain(encodeURIComponent('not-a-valid-url'));
  });

  it('empty proxy base still produces query string for cross-origin', () => {
    const r = getLoadUrl('', 'https://other.com/file.pdf');
    expect(r).toBe('?url=' + encodeURIComponent('https://other.com/file.pdf'));
  });
});

describe('getScaleForFitWidth', () => {
  it('null doc returns 1.5', async () => {
    expect(await getScaleForFitWidth(null, null)).toBe(1.5);
  });
  it('null container returns 1.5', async () => {
    expect(await getScaleForFitWidth({ getPage: vi.fn() } as never, null)).toBe(1.5);
  });
  it('returns scale from container width minus gutter and viewport when container has no scroll child', async () => {
    const doc = {
      getPage: vi.fn().mockResolvedValue({
        getViewport: () => ({ width: 200, height: 300 }),
      }),
    };
    const container = document.createElement('div');
    Object.defineProperty(container, 'clientWidth', { value: 276, configurable: true }); // 276 - 24 gutter = 252
    Object.defineProperty(container, 'clientHeight', { value: 400, configurable: true });
    const scale = await getScaleForFitWidth(doc as never, container);
    expect(scale).toBe((276 - 24) / 200);
    expect(scale).toBeGreaterThanOrEqual(0.5);
  });
  it('returns 1.5 when container clientWidth minus gutter is <= 0', async () => {
    const doc = {
      getPage: vi.fn().mockResolvedValue({
        getViewport: () => ({ width: 100, height: 200 }),
      }),
    };
    const container = document.createElement('div');
    Object.defineProperty(container, 'clientWidth', { value: 10, configurable: true });
    const scale = await getScaleForFitWidth(doc as never, container);
    expect(scale).toBe(1.5);
  });
});

describe('getScaleForFitPage', () => {
  it('null doc returns 1.5', async () => {
    expect(await getScaleForFitPage(null, null)).toBe(1.5);
  });
  it('null container returns 1.5', async () => {
    const doc = {
      getPage: vi.fn().mockResolvedValue({
        getViewport: () => ({ width: 100, height: 200 }),
      }),
    };
    expect(await getScaleForFitPage(doc as never, null)).toBe(1.5);
  });
  it('returns min of scaleW and scaleH when container has dimensions', async () => {
    const doc = {
      getPage: vi.fn().mockResolvedValue({
        getViewport: () => ({ width: 100, height: 200 }),
      }),
    };
    const container = document.createElement('div');
    const cw = 276;
    const ch = 376;
    Object.defineProperty(container, 'clientWidth', { value: cw, configurable: true });
    Object.defineProperty(container, 'clientHeight', { value: ch, configurable: true });
    const scale = await getScaleForFitPage(doc as never, container);
    const scaleW = (cw - 24) / 100;
    const scaleH = (ch - 24) / 200;
    expect(scale).toBe(Math.max(0.5, Math.min(scaleW, scaleH)));
    expect(scale).toBeGreaterThanOrEqual(0.5);
  });
});
