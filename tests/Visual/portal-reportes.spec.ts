import { test, expect } from '@playwright/test';
import { asegurarTema, esperarFuentes, iniciarSesionPortal } from './helpers';

/**
 * GET /portal/reportes — listado de reportes técnicos por lote del contrato
 * autenticado (HU-41, tarea 55). Tabla de solo lectura con columnas:
 * Lote, Generado (fecha/hora), y botón de descarga de PDF.
 * Fixture `portal-demo.php` proporciona los datos.
 */
test.describe('portal-reportes', () => {
    test.beforeEach(async ({ page }) => {
        await iniciarSesionPortal(page);
        await page.goto('/portal/reportes');
    });

    test('claro', async ({ page }) => {
        await asegurarTema(page, 'light');
        await esperarFuentes(page);

        await expect(page).toHaveScreenshot('portal-reportes-light.png', {
            fullPage: true,
            mask: [page.locator('.ag-portal__footer span').first()],
        });
    });

    test('oscuro', async ({ page }) => {
        await asegurarTema(page, 'dark');
        await esperarFuentes(page);

        await expect(page).toHaveScreenshot('portal-reportes-dark.png', {
            fullPage: true,
            mask: [page.locator('.ag-portal__footer span').first()],
        });
    });
});
