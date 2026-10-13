import { test, expect } from '@playwright/test';

/**
 * REQ-DEMO-013 — happy-path e2e against the PdfSignable demo.
 * The example PDF is external and loaded through the bundle proxy (/demo/pdf-signable/proxy).
 */
test.describe('PdfSignable demo happy path', () => {
  test('home lists the signature and signing demos', async ({ page }) => {
    const response = await page.goto('/');
    expect(response?.ok()).toBeTruthy();
    await expect(page.getByRole('heading', { name: 'Define signature areas' })).toBeVisible();
    await expect(page.getByRole('heading', { name: /Signing \(draw/ })).toBeVisible();
  });

  test('rotation demo: place a box, set its angle and save coordinates', async ({ page }) => {
    await page.goto('/demo-signature/rotation');
    const firstPage = page.locator('#pdf-canvas-wrapper .pdf-page-wrapper canvas').first();
    await expect(firstPage).toBeVisible({ timeout: 60_000 });

    await page.locator('#addBoxBtn').click();
    const box = page.locator('#signature-boxes-list .signature-box-item').first();
    await expect(box).toBeVisible();
    await expect(page.locator('#pdf-canvas-wrapper .signature-box-overlay')).toHaveCount(1);
    await box.locator('.signature-box-angle').fill('90');

    await page.getByRole('button', { name: 'Save coordinates' }).click();
    await expect(page.locator('.alert-success')).toContainText('Coordinates saved');
    await expect(page.locator('.alert-success')).toContainText('angle=90');
  });

  test('predefined boxes signing demo shows both signers', async ({ page }) => {
    await page.goto('/demo-signing/predefined-boxes');
    await expect(page.locator('#pdf-canvas-wrapper .pdf-page-wrapper canvas').first()).toBeVisible({ timeout: 60_000 });
    await expect(page.locator('#pdf-canvas-wrapper .signature-box-overlay')).toHaveCount(2);
  });
});
