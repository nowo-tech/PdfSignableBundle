import { describe, expect, it, vi } from 'vitest';

import { createBundleLogger } from './logger';
import { ATTR_DEBUG, getLogger, setBundleLogger } from './pdf-signable-logger';

describe('pdf-signable-logger', () => {
  it('exports ATTR_DEBUG for the debug data attribute', () => {
    expect(ATTR_DEBUG).toBe('data-debug');
  });

  it('creates a default logger when none was injected', () => {
    const log = getLogger();
    expect(log.setDebug).toBeTypeOf('function');
    expect(log.debug).toBeTypeOf('function');
    expect(getLogger()).toBe(log);
  });

  it('returns the injected logger after setBundleLogger', () => {
    const custom = createBundleLogger('injected-test');
    const debug = vi.spyOn(console, 'debug').mockImplementation(() => {});
    setBundleLogger(custom);
    const resolved = getLogger();
    expect(resolved).toBe(custom);
    resolved.setDebug(true);
    resolved.debug('from-injected');
    expect(debug).toHaveBeenCalled();
    debug.mockRestore();
  });
});
