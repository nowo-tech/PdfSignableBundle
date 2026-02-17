import { describe, it, expect } from 'vitest';
import type { NowoPdfSignableConfig, IPdfDocForScale } from './types';

describe('shared types', () => {
  it('NowoPdfSignableConfig type allows valid config shape', () => {
    const config: NowoPdfSignableConfig = {
      proxyUrl: '/proxy',
      strings: {
        error_load_pdf: 'Error',
        pdf_not_found: 'Not found',
        alert_url_required: 'URL required',
        alert_submit_error: 'Submit error',
        loading_state: 'Loading',
        load_pdf_btn: 'Load',
        default_box_name: 'Signature',
      },
    };
    expect(config.proxyUrl).toBe('/proxy');
    expect(config.strings.load_pdf_btn).toBe('Load');
  });

  it('NowoPdfSignableConfig accepts optional pdfjsSource and pdfjsWorkerUrl', () => {
    const config: NowoPdfSignableConfig = {
      proxyUrl: '/p',
      pdfjsSource: 'npm',
      pdfjsWorkerUrl: '/worker.js',
      strings: {
        error_load_pdf: 'e',
        pdf_not_found: 'n',
        alert_url_required: 'u',
        alert_submit_error: 's',
        loading_state: 'l',
        load_pdf_btn: 'b',
        default_box_name: 'x',
      },
    };
    expect(config.pdfjsSource).toBe('npm');
    expect(config.pdfjsWorkerUrl).toBe('/worker.js');
  });

  it('IPdfDocForScale type allows getPage returning viewport', async () => {
    const doc: IPdfDocForScale = {
      getPage: async () => ({
        getViewport: () => ({ width: 100, height: 200, scale: 1 }),
      }),
    };
    const page = await doc.getPage(1);
    const vp = page.getViewport({ scale: 1 });
    expect(vp.width).toBe(100);
    expect(vp.height).toBe(200);
  });
});
