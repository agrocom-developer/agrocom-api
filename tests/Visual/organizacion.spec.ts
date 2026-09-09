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

/**
 * Pestaña "Facturación" (tarea 78, HU-55): a diferencia de la pestaña
 * "Organización" de arriba, ES real — formulario propio con los cinco datos
 * fiscales. No son secretos (a diferencia de `/panel/configuracion`), así que
 * no hay restricción de "sin datos reales cargados" acá.
 */
test.describe('organizacion facturación', () => {
    test.beforeEach(async ({ page }) => {
        await iniciarSesion(page);
        await elegirRolDueno(page);
        await page.goto('/panel/organizacion?tab=facturacion');
    });

    test('claro', async ({ page }) => {
        await asegurarTema(page, 'light');
        await esperarFuentes(page);

        await expect(page).toHaveScreenshot('organizacion-facturacion-light.png', {
            fullPage: true,
            mask: [page.locator('.ag-panel__footer span').first()],
        });
    });

    test('oscuro', async ({ page }) => {
        await asegurarTema(page, 'dark');
        await esperarFuentes(page);

        await expect(page).toHaveScreenshot('organizacion-facturacion-dark.png', {
            fullPage: true,
            mask: [page.locator('.ag-panel__footer span').first()],
        });
    });
});
