import { test, expect } from '@playwright/test';
import { asegurarTema, elegirRolDueno, esperarFuentes, iniciarSesion } from './helpers';

/**
 * GET /panel/organizacion, con "Dueño" como rol activo (la pantalla no lleva
 * permiso propio — cualquier rol la ve, ver OrganizacionController — "Dueño"
 * se elige por consistencia con el resto de la suite).
 *
 * Caso de prueba del arquetipo formulario (tarea 31): tarjetas de
 * `form-section`, cabecera `page-header`, tabs, aside pegajoso
 * (`progress-meter` + `summary-card`) y `form-actions-bar`. Sin gráficos ni
 * animación propia — a diferencia de `dashboard.spec.ts` no hace falta
 * esperar ningún redibujado además de las fuentes.
 */
test.describe('organizacion', () => {
    test.beforeEach(async ({ page }) => {
        await iniciarSesion(page);
        await elegirRolDueno(page);
        await page.goto('/panel/organizacion');
    });

    test('claro', async ({ page }) => {
        await asegurarTema(page, 'light');
        await esperarFuentes(page);

        await expect(page).toHaveScreenshot('organizacion-light.png', {
            fullPage: true,
            mask: [page.locator('.ag-panel__footer span').first()],
        });
    });

    test('oscuro', async ({ page }) => {
        await asegurarTema(page, 'dark');
        await esperarFuentes(page);

        await expect(page).toHaveScreenshot('organizacion-dark.png', {
            fullPage: true,
            mask: [page.locator('.ag-panel__footer span').first()],
        });
    });
});
