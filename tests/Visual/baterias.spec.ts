import { test, expect } from '@playwright/test';
import { asegurarTema, elegirRolDueno, esperarFuentes, iniciarSesion } from './helpers';

/**
 * GET /panel/baterias y GET /panel/baterias/crear, con "Dueño" como rol
 * activo (tiene el catálogo completo de permisos, incluidos los cuatro
 * `mantenimiento.bateria.*` de esta HU).
 *
 * HU-39 (tarea 51): ABM del catálogo de baterías — cubre el arquetipo
 * Listado (`index`, con la columna de alerta) y el arquetipo Formulario
 * (`create`, sin sub-entidad repetible). Sin gráficos ni animación propia
 * — mismo criterio que `vehiculos.spec.ts`.
 */
test.describe('baterias', () => {
    test.describe('index', () => {
        test.beforeEach(async ({ page }) => {
            await iniciarSesion(page);
            await elegirRolDueno(page);
            await page.goto('/panel/baterias');
        });

        test('claro', async ({ page }) => {
            await asegurarTema(page, 'light');
            await esperarFuentes(page);

            await expect(page).toHaveScreenshot('baterias-index-light.png', {
                fullPage: true,
                mask: [page.locator('.ag-panel__footer span').first()],
            });
        });

        test('oscuro', async ({ page }) => {
            await asegurarTema(page, 'dark');
            await esperarFuentes(page);

            await expect(page).toHaveScreenshot('baterias-index-dark.png', {
                fullPage: true,
                mask: [page.locator('.ag-panel__footer span').first()],
            });
        });
    });

    test.describe('create', () => {
        test.beforeEach(async ({ page }) => {
            await iniciarSesion(page);
            await elegirRolDueno(page);
            await page.goto('/panel/baterias/crear');
        });

        test('claro', async ({ page }) => {
            await asegurarTema(page, 'light');
            await esperarFuentes(page);

            await expect(page).toHaveScreenshot('baterias-create-light.png', {
                fullPage: true,
                mask: [page.locator('.ag-panel__footer span').first()],
            });
        });

        test('oscuro', async ({ page }) => {
            await asegurarTema(page, 'dark');
            await esperarFuentes(page);

            await expect(page).toHaveScreenshot('baterias-create-dark.png', {
                fullPage: true,
                mask: [page.locator('.ag-panel__footer span').first()],
            });
        });
    });
});
