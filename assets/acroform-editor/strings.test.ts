import { describe, it, expect } from 'vitest';
import { DEFAULT_STRINGS, escapeAttr } from './strings';

describe('DEFAULT_STRINGS', () => {
  it('is an empty fallback so translations come from Twig or window', () => {
    expect(DEFAULT_STRINGS).toEqual({});
  });

  it('allows missing keys so JS falls back to key or injected strings', () => {
    expect(DEFAULT_STRINGS.modal_edit_title).toBeUndefined();
    expect(DEFAULT_STRINGS.list_label).toBeUndefined();
  });
});

describe('escapeAttr', () => {
  it('escapes ampersand', () => {
    expect(escapeAttr('a & b')).toBe('a &amp; b');
  });

  it('escapes double quotes', () => {
    expect(escapeAttr('say "hello"')).toBe('say &quot;hello&quot;');
  });

  it('handles empty string', () => {
    expect(escapeAttr('')).toBe('');
  });

  it('handles non-string by converting to string', () => {
    expect(escapeAttr(123 as unknown as string)).toBe('123');
  });

  it('handles mixed ampersand and quotes', () => {
    expect(escapeAttr('x & "y"')).toBe('x &amp; &quot;y&quot;');
  });

  it('does not escape angle brackets (used only in attributes)', () => {
    expect(escapeAttr('<script>')).toBe('<script>');
  });

  it('returns same string when no special chars', () => {
    expect(escapeAttr('hello world')).toBe('hello world');
  });
});
