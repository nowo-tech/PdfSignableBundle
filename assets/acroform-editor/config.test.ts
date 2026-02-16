import { describe, it, expect } from 'vitest';
import {
  parseLabelChoices,
  parseFontSizes,
  parseFontFamilies,
  getConfig,
  FIELD_NAME_VALUE_OTHER,
} from './config';

describe('parseLabelChoices', () => {
  it('returns empty array for undefined', () => {
    expect(parseLabelChoices(undefined)).toEqual([]);
  });

  it('returns empty array for empty string', () => {
    expect(parseLabelChoices('')).toEqual([]);
  });

  it('returns empty array for invalid JSON', () => {
    expect(parseLabelChoices('not json')).toEqual([]);
  });

  it('returns empty array when parsed value is not an array', () => {
    expect(parseLabelChoices('{"a":1}')).toEqual([]);
  });

  it('parses array of strings as value and label', () => {
    expect(parseLabelChoices(JSON.stringify(['a', 'b']))).toEqual([
      { value: 'a', label: 'a' },
      { value: 'b', label: 'b' },
    ]);
  });

  it('parses string with pipe as value|label', () => {
    expect(parseLabelChoices(JSON.stringify(['v1|Label 1']))).toEqual([
      { value: 'v1', label: 'Label 1' },
    ]);
  });

  it('parses objects with value and optional label', () => {
    const input = JSON.stringify([
      { value: 'v1' },
      { value: 'v2', label: 'Label 2' },
    ]);
    expect(parseLabelChoices(input)).toEqual([
      { value: 'v1', label: undefined },
      { value: 'v2', label: 'Label 2' },
    ]);
  });

  it('filters out entries with empty value', () => {
    const input = JSON.stringify(['', 'ok', '  ']);
    expect(parseLabelChoices(input)).toEqual([{ value: 'ok', label: 'ok' }]);
  });
});

describe('FIELD_NAME_VALUE_OTHER', () => {
  it('is __other__', () => {
    expect(FIELD_NAME_VALUE_OTHER).toBe('__other__');
  });
});

describe('parseFontSizes', () => {
  it('returns empty array for undefined', () => {
    expect(parseFontSizes(undefined)).toEqual([]);
  });

  it('returns empty array for empty string', () => {
    expect(parseFontSizes('')).toEqual([]);
  });

  it('returns empty array for invalid JSON', () => {
    expect(parseFontSizes('not json')).toEqual([]);
  });

  it('parses array of numbers', () => {
    expect(parseFontSizes('[10, 12, 14]')).toEqual([10, 12, 14]);
  });

  it('maps 0 to 1 via Math.max and filters NaN', () => {
    expect(parseFontSizes('[0, 1, 12]')).toEqual([1, 1, 12]);
  });

  it('rounds decimal values', () => {
    expect(parseFontSizes('[10.7, 12.2]')).toEqual([11, 12]);
  });

  it('parses string numbers in array', () => {
    expect(parseFontSizes('["10", "12", "14"]')).toEqual([10, 12, 14]);
  });

  it('filters invalid and negative numbers', () => {
    expect(parseFontSizes('[10, "x", 12]')).toEqual([10, 12]);
  });
});

describe('parseFontFamilies', () => {
  it('returns empty array for undefined', () => {
    expect(parseFontFamilies(undefined)).toEqual([]);
  });

  it('parses array of strings', () => {
    expect(parseFontFamilies(JSON.stringify(['Helvetica', 'Times']))).toEqual([
      { value: 'Helvetica', label: 'Helvetica' },
      { value: 'Times', label: 'Times' },
    ]);
  });

  it('parses string with pipe as value|label', () => {
    expect(parseFontFamilies(JSON.stringify(['Times|Times Roman']))).toEqual([
      { value: 'Times', label: 'Times Roman' },
    ]);
  });

  it('filters out entries with empty value', () => {
    const input = JSON.stringify([{ value: '' }, { value: 'Arial', label: 'Arial' }]);
    expect(parseFontFamilies(input)).toEqual([{ value: 'Arial', label: 'Arial' }]);
  });
});

describe('getConfig', () => {
  function createMockRoot(attrs: Record<string, string> = {}): HTMLElement {
    const dataset: Record<string, string> = { ...attrs };
    return { dataset } as unknown as HTMLElement;
  }

  it('reads loadUrl and postUrl from data attributes', () => {
    const root = createMockRoot({
      loadUrl: '/load',
      postUrl: '/post',
      applyUrl: '/apply',
      processUrl: '/process',
    });
    const config = getConfig(root);
    expect(config.loadUrl).toBe('/load');
    expect(config.postUrl).toBe('/post');
  });

  it('defaults fieldNameMode to input when not choice', () => {
    const root = createMockRoot({ loadUrl: '', postUrl: '', applyUrl: '', processUrl: '' });
    const config = getConfig(root);
    expect(config.fieldNameMode).toBe('input');
  });

  it('uses fieldNameMode choice when dataset.fieldNameMode is choice', () => {
    const root = createMockRoot({
      loadUrl: '',
      postUrl: '',
      applyUrl: '',
      processUrl: '',
      fieldNameMode: 'choice',
    });
    const config = getConfig(root);
    expect(config.fieldNameMode).toBe('choice');
  });

  it('defaults showFieldRect to true when not 0 or false', () => {
    const root = createMockRoot({ loadUrl: '', postUrl: '', applyUrl: '', processUrl: '' });
    const config = getConfig(root);
    expect(config.showFieldRect).toBe(true);
  });

  it('sets showFieldRect false when dataset is 0', () => {
    const root = createMockRoot({
      loadUrl: '',
      postUrl: '',
      applyUrl: '',
      processUrl: '',
      showFieldRect: '0',
    });
    const config = getConfig(root);
    expect(config.showFieldRect).toBe(false);
  });

  it('reads debug as true when dataset.debug is 1 or true', () => {
    const root1 = createMockRoot({ loadUrl: '', postUrl: '', applyUrl: '', processUrl: '', debug: '1' });
    expect(getConfig(root1).debug).toBe(true);
    const root2 = createMockRoot({ loadUrl: '', postUrl: '', applyUrl: '', processUrl: '', debug: 'true' });
    expect(getConfig(root2).debug).toBe(true);
  });

  it('reads debug as false when dataset.debug is missing or 0', () => {
    const root = createMockRoot({ loadUrl: '', postUrl: '', applyUrl: '', processUrl: '' });
    expect(getConfig(root).debug).toBe(false);
  });

  it('reads documentKey and fieldNameOtherText from dataset', () => {
    const root = createMockRoot({
      loadUrl: '',
      postUrl: '',
      applyUrl: '',
      processUrl: '',
      documentKey: 'doc-123',
      fieldNameOtherText: 'Otro',
    });
    const config = getConfig(root);
    expect(config.documentKey).toBe('doc-123');
    expect(config.fieldNameOtherText).toBe('Otro');
  });

  it('defaults fieldNameOtherText to empty when missing', () => {
    const root = createMockRoot({ loadUrl: '', postUrl: '', applyUrl: '', processUrl: '' });
    expect(getConfig(root).fieldNameOtherText).toBe('');
  });
});
