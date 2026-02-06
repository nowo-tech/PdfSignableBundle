/**
 * Demo app entry (Vite + TypeScript).
 * Bootstrap is loaded from CDN in base.html.twig.
 */

/**
 * Initializes the demo app (e.g. log ready state). Extend for custom demo behaviour.
 */
const init = (): void => {
  console.log('PdfSignable Demo (Symfony 7) ready');
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
