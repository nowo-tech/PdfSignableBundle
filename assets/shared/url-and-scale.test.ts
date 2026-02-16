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
});

describe('getScaleForFitWidth', () => {
  it('null doc returns 1.5', async () => {
    expect(await getScaleForFitWidth(null, null)).toBe(1.5);
  });
  it('null container returns 1.5', async () => {
    expect(await getScaleForFitWidth({ getPage: vi.fn() } as never, null)).toBe(1.5);
  });
});

describe('getScaleForFitPage', () => {
  it('null doc returns 1.5', async () => {
    expect(await getScaleForFitPage(null, null)).toBe(1.5);
  });
});
