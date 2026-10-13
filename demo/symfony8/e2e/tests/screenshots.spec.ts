import { test, expect, type Page } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * REQ-DEMO-013 — screenshots for README (docs/images/demo/*.png, converted to WebP by scripts/to-webp.mjs).
 * Crops to the demo content area (title + configuration card + widget in situ), without the
 * sidebar/top navbar chrome.
 */
const outDir = process.env.SCREENSHOT_DIR
  ? resolve(process.env.SCREENSHOT_DIR)
  : resolve(__dirname, '../../../../docs/images/demo');

test.beforeAll(() => {
  mkdirSync(outDir, { recursive: true });
});

const content = (page: Page) => page.locator('main > .container-fluid').first();

async function waitForPdf(page: Page): Promise<void> {
  await expect(page.locator('#pdf-canvas-wrapper .pdf-page-wrapper canvas').first()).toBeVisible({ timeout: 60_000 });
  await expect(page.locator('.pdf-signable-loading-overlay')).toHaveCount(0, { timeout: 60_000 });
  await expect(page.locator('#pdf-thumbnails-strip .pdf-thumb').first()).toBeVisible({ timeout: 60_000 });
  // Let PDF.js finish painting the visible pages and thumbnails.
  await page.waitForTimeout(1500);
}

/** Fit the whole page width in the viewer so the signature boxes are visible. */
async function fitWidth(page: Page): Promise<void> {
  await page.locator('#pdfFitWidth').click();
  await page.waitForTimeout(1500);
}

/** Hide the Symfony web debug toolbar (fixed at the bottom of dev pages). */
async function hideDevChrome(page: Page): Promise<void> {
  await page.addStyleTag({ content: '.sf-toolbar, .sf-minitoolbar { display: none !important; }' });
}

test.describe('feature screenshots', () => {
  test('demo home — catalogue of signature-area and signing demos', async ({ page }) => {
    await page.goto('/');
    await expect(page.getByRole('heading', { name: 'Define signature areas' })).toBeVisible();
    await hideDevChrome(page);
    await content(page).screenshot({ path: resolve(outDir, 'demo-home.png') });
  });

  test('signature coordinates form — rotated box saved', async ({ page }) => {
    await page.goto('/demo-signature/rotation');
    await waitForPdf(page);

    await page.locator('#addBoxBtn').click();
    const box = page.locator('#signature-boxes-list .signature-box-item').first();
    await expect(box).toBeVisible();
    await page.locator('#signature_page_signatureCoordinates_origin').selectOption('bottom_left');
    await box.locator('.signature-box-width').fill('60');
    await box.locator('.signature-box-height').fill('20');
    await box.locator('.signature-box-x').fill('110');
    await box.locator('.signature-box-y').fill('230');
    await box.locator('.signature-box-angle').fill('-15');
    await box.locator('.signature-box-angle').blur();

    await page.getByRole('button', { name: 'Save coordinates' }).click();
    await expect(page.locator('.alert-success')).toContainText('Coordinates saved');
    await waitForPdf(page);
    await expect(page.locator('#pdf-canvas-wrapper .signature-box-overlay')).toHaveCount(1);
    await fitWidth(page);
    await hideDevChrome(page);
    await content(page).screenshot({ path: resolve(outDir, 'demo-signature-form.png') });
  });

  test('signature form — predefined boxes, sign only (draw)', async ({ page }) => {
    await page.goto('/demo-signing/predefined-boxes');
    await waitForPdf(page);
    await expect(page.locator('#pdf-canvas-wrapper .signature-box-overlay')).toHaveCount(2);

    // Draw a short signature stroke on the first signer's pad.
    const pad = page.locator('.signature-pad-canvas').first();
    await pad.scrollIntoViewIfNeeded();
    const b = await pad.boundingBox();
    if (b) {
      await page.mouse.move(b.x + 30, b.y + 80);
      await page.mouse.down();
      const points: Array<[number, number]> = [
        [55, 40], [75, 85], [95, 35], [115, 80], [140, 50], [165, 75], [190, 45], [230, 70],
      ];
      for (const [dx, dy] of points) {
        await page.mouse.move(b.x + dx, b.y + dy, { steps: 6 });
      }
      await page.mouse.up();
    }
    await hideDevChrome(page);
    await page.locator('main').evaluate((el) => el.scrollTo(0, 0));
    await page.waitForTimeout(500);
    await content(page).screenshot({ path: resolve(outDir, 'demo-signature-form-2.png') });
  });
});
