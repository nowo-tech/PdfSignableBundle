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

  test('AcroForm editor: Edit opens the native <dialog> edit modal (REQ-UI-001)', async ({ page }) => {
    await page.goto('/demo-signature/acroform-editor');
    const firstPage = page.locator('#pdf-canvas-wrapper .pdf-page-wrapper canvas').first();
    await expect(firstPage).toBeVisible({ timeout: 60_000 });

    const dialog = page.locator('dialog#acroform-edit-modal');
    await expect(dialog).toHaveCount(1);
    await expect(dialog).not.toHaveAttribute('open');

    // Place a new field through the viewer → editor event contract (what a click in "Add field" mode dispatches).
    await page.evaluate(() => {
      window.dispatchEvent(
        new CustomEvent('pdf-signable-acroform-add-field-place', {
          detail: { page: 1, llx: 72, lly: 700, width: 100, height: 20 },
        }),
      );
    });
    // The new field appears in the editor list; its Edit button opens the modal.
    const newRow = page.locator('#acroform-fields-list .acroform-list-row[data-field-id^="new-"]');
    await expect(newRow).toHaveCount(1);
    await newRow.locator('.acroform-btn-edit').click();
    await expect(dialog).toHaveAttribute('open', '');
    await expect(dialog.locator('.nowo-ui-modal__header')).toBeVisible();
    await expect(dialog.locator('.nowo-ui-modal__content #acroform-edit-control-type')).toBeVisible();
    await expect(dialog.locator('.nowo-ui-modal__actions #acroform-edit-save')).toBeVisible();

    await dialog.locator('#acroform-edit-cancel').click();
    await expect(dialog).not.toHaveAttribute('open');
  });

  test('predefined boxes signing demo shows both signers', async ({ page }) => {
    await page.goto('/demo-signing/predefined-boxes');
    await expect(page.locator('#pdf-canvas-wrapper .pdf-page-wrapper canvas').first()).toBeVisible({ timeout: 60_000 });
    await expect(page.locator('#pdf-canvas-wrapper .signature-box-overlay')).toHaveCount(2);
  });
});
