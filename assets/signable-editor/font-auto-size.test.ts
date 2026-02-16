import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { applyFontAutoSize } from './font-auto-size';

describe('applyFontAutoSize', () => {
  let input: HTMLInputElement;
  let textarea: HTMLTextAreaElement;
  let originalGetComputedStyle: typeof getComputedStyle;

  beforeEach(() => {
    input = document.createElement('input');
    input.setAttribute('type', 'text');
    input.value = 'short';
    textarea = document.createElement('textarea');
    textarea.value = 'short';

    originalGetComputedStyle = window.getComputedStyle;
    window.getComputedStyle = vi.fn(() =>
      ({
        fontSize: '16px',
      }) as CSSStyleDeclaration
    );
  });

  afterEach(() => {
    window.getComputedStyle = originalGetComputedStyle;
  });

  it('sets font size on input element', () => {
    applyFontAutoSize(input);
    expect(input.style.fontSize).toBeDefined();
    expect(input.style.fontSize).toMatch(/^\d+px$/);
  });

  it('sets font size on textarea element', () => {
    applyFontAutoSize(textarea);
    expect(textarea.style.fontSize).toBeDefined();
  });

  it('uses computed fontSize when valid', () => {
    (window.getComputedStyle as ReturnType<typeof vi.fn>).mockReturnValue({
      fontSize: '14px',
    } as CSSStyleDeclaration);
    applyFontAutoSize(input);
    expect(input.style.fontSize).toBe('14px');
  });

  it('enforces minimum font size when computed is below 8px', () => {
    (window.getComputedStyle as ReturnType<typeof vi.fn>).mockReturnValue({
      fontSize: '2px',
    } as CSSStyleDeclaration);
    applyFontAutoSize(input);
    expect(parseInt(input.style.fontSize, 10)).toBeGreaterThanOrEqual(8);
  });

  it('reduces font size when content overflows horizontally', () => {
    Object.defineProperties(input, {
      clientWidth: { value: 50, configurable: true },
      scrollWidth: { value: 200, configurable: true },
      clientHeight: { value: 20, configurable: true },
      scrollHeight: { value: 20, configurable: true },
    });
    applyFontAutoSize(input);
    const size = parseInt(input.style.fontSize, 10);
    expect(size).toBeLessThanOrEqual(16);
    expect(size).toBeGreaterThanOrEqual(8);
  });

  it('reduces font size when textarea overflows vertically', () => {
    Object.defineProperties(textarea, {
      clientWidth: { value: 100, configurable: true },
      scrollWidth: { value: 100, configurable: true },
      clientHeight: { value: 20, configurable: true },
      scrollHeight: { value: 80, configurable: true },
    });
    applyFontAutoSize(textarea);
    const size = parseInt(textarea.style.fontSize, 10);
    expect(size).toBeLessThanOrEqual(16);
    expect(size).toBeGreaterThanOrEqual(8);
  });

  it('does not go below 8px', () => {
    Object.defineProperties(input, {
      clientWidth: { value: 1, configurable: true },
      scrollWidth: { value: 1000, configurable: true },
      clientHeight: { value: 1, configurable: true },
      scrollHeight: { value: 1, configurable: true },
    });
    applyFontAutoSize(input);
    expect(parseInt(input.style.fontSize, 10)).toBe(8);
  });
});
