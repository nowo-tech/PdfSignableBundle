/**
 * Demo app entry (Vite + TypeScript).
 * Bootstrap is loaded from CDN in base.html.twig.
 */
if (typeof console !== 'undefined') console.debug('[PdfSignable] loaded: demo/symfony8/assets/app.ts');
/**
 * Initializes the demo app (e.g. log ready state). Extend for custom demo behaviour.
 */
const init = (): void => {
  console.log('PdfSignable Demo (Symfony 8) ready');
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
