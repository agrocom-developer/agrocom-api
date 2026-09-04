import { test, expect } from '@playwright/test';
import { asegurarTema, esperarFuentes, iniciarSesionPortal } from './helpers';

/**
 * GET /portal/avance — avance comercial del contrato autenticado (HU-41,
 * tarea 55). Tres stat-cards mostrando hectáreas contratadas, aplicadas y
 * monto facturado. Solo lectura, sin acciones. Fixture `portal-demo.php`
 * proporciona los datos.
 */
test.describe('portal-avance', () => {
    test.beforeEach(async ({ page }) => {
        await iniciarSesionPortal(page);
    });

    test('claro', async ({ page }) => {
        await asegurarTema(page, 'light');
        await esperarFuentes(page);

        await expect(page).toHaveScreenshot('portal-avance-light.png', {
            fullPage: true,
            mask: [page.locator('.ag-portal__footer span').first()],
        });
    });

    test('oscuro', async ({ page }) => {
        await asegurarTema(page, 'dark');
        await esperarFuentes(page);

        await expect(page).toHaveScreenshot('portal-avance-dark.png', {
            fullPage: true,
            mask: [page.locator('.ag-portal__footer span').first()],
        });
    });
});
