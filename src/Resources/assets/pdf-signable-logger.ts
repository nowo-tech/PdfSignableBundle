/**
 * Shared logger instance for PdfSignableBundle (signable-editor and acroform-editor).
 * Injected from each entry point; debug is enabled via config (window.NowoPdfSignableConfig.debug
 * or data-debug="1" on the acroform root).
 */
import { createBundleLogger, type BundleLogger } from './logger';

let bundleLogger: BundleLogger | null = null;

/** Injects the bundle logger (called from entry point so scriptLoaded/buildTime can be used). */
export function setBundleLogger(log: BundleLogger): void {
  bundleLogger = log;
}

/** Returns the injected logger or a default one. */
export function getLogger(): BundleLogger {
  if (bundleLogger === null) {
    bundleLogger = createBundleLogger('pdf-signable');
  }
  return bundleLogger;
}

/** Data attribute for debug mode: when "1" or "true", all console logs are shown. */
export const ATTR_DEBUG = 'data-debug';
