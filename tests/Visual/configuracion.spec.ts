import { test, expect } from '@playwright/test';
import { asegurarTema, elegirRolDueno, esperarFuentes, iniciarSesion } from './helpers';

/**
 * GET /panel/configuracion (tarea 78, HU-55) — exclusiva del rol Dueño.
 *
 * Deliberadamente SIN cargar ninguna llave real antes de la captura: la
 * pantalla muestra el sector "Mapas" (el que abre por defecto) con sus dos
 * campos en estado "Sin configurar" — ni la suite de Playwright ni el
 * snapshot resultante tocan nunca un secreto de verdad (tarea 78, "los
 * snapshots de esta pantalla se toman sin ninguna llave real cargada").
 */
test.describe('configuracion', () => {
    test.beforeEach(async ({ page }) => {
        await iniciarSesion(page);
        await elegirRolDueno(page);
        await page.goto('/panel/configuracion');
    });

    test('claro', async ({ page }) => {
        await asegurarTema(page, 'light');
        await esperarFuentes(page);

        await expect(page).toHaveScreenshot('configuracion-light.png', {
            fullPage: true,
            mask: [page.locator('.ag-panel__footer span').first()],
        });
    });

    test('oscuro', async ({ page }) => {
        await asegurarTema(page, 'dark');
        await esperarFuentes(page);

        await expect(page).toHaveScreenshot('configuracion-dark.png', {
            fullPage: true,
            mask: [page.locator('.ag-panel__footer span').first()],
        });
    });
});
