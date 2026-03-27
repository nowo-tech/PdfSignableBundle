import { beforeEach, describe, expect, it, vi } from 'vitest';

import { createBundleLogger } from './logger';

describe('assets/logger', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  it('scriptLoaded loguea con y sin buildTime', () => {
    const logSpy = vi.spyOn(console, 'log').mockImplementation(() => {});

    createBundleLogger('bundle-a').scriptLoaded();
    createBundleLogger('bundle-b', { buildTime: '2026-02-16T00:00:00Z' }).scriptLoaded();

    expect(logSpy).toHaveBeenCalledTimes(2);
    expect(String(logSpy.mock.calls[0][0])).toContain('script loaded');
    expect(String(logSpy.mock.calls[1][0])).toContain('build time');
  });

  it('respeta setDebug para debug/info/warn/error', () => {
    const debug = vi.spyOn(console, 'debug').mockImplementation(() => {});
    const info = vi.spyOn(console, 'info').mockImplementation(() => {});
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});
    const error = vi.spyOn(console, 'error').mockImplementation(() => {});

    const logger = createBundleLogger('bundle-c');
    logger.debug('no');
    logger.info('no');
    logger.warn('no');
    logger.error('no');
    expect(debug).toHaveBeenCalledTimes(0);
    expect(info).toHaveBeenCalledTimes(0);
    expect(warn).toHaveBeenCalledTimes(0);
    expect(error).toHaveBeenCalledTimes(0);

    logger.setDebug(true);
    logger.debug('d', { a: 1 });
    logger.info('i');
    logger.warn('w');
    logger.error('e');

    expect(debug).toHaveBeenCalledTimes(1);
    expect(info).toHaveBeenCalledTimes(1);
    expect(warn).toHaveBeenCalledTimes(1);
    expect(error).toHaveBeenCalledTimes(1);
  });
});
