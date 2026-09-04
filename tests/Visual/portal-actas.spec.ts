import { test, expect } from '@playwright/test';
import { asegurarTema, esperarFuentes, iniciarSesionPortal } from './helpers';

/**
 * GET /portal/actas — listado de actas de conformidad firmadas del contrato
 * autenticado (HU-41, tarea 55). Tabla de solo lectura con columnas:
 * Acta, Hectáreas conformadas, y botón de descarga de PDF.
 * Fixture `portal-demo.php` proporciona los datos.
 */
test.describe('portal-actas', () => {
    test.beforeEach(async ({ page }) => {
        await iniciarSesionPortal(page);
        await page.goto('/portal/actas');
    });

    test('claro', async ({ page }) => {
        await asegurarTema(page, 'light');
        await esperarFuentes(page);

        await expect(page).toHaveScreenshot('portal-actas-light.png', {
            fullPage: true,
            mask: [page.locator('.ag-portal__footer span').first()],
        });
    });

    test('oscuro', async ({ page }) => {
        await asegurarTema(page, 'dark');
        await esperarFuentes(page);

        await expect(page).toHaveScreenshot('portal-actas-dark.png', {
            fullPage: true,
            mask: [page.locator('.ag-portal__footer span').first()],
        });
    });
});
