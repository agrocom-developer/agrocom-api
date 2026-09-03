import { test, expect } from '@playwright/test';
import { asegurarTema, elegirRolDueno, esperarFuentes, iniciarSesion } from './helpers';

/**
 * GET /panel/ordenes y GET /panel/ordenes/crear, con "Dueño" como rol activo
 * (tiene el catálogo completo de permisos, incluidos los cinco
 * `operaciones.orden.*` de esta HU).
 *
 * HU-25 (tarea 38): órdenes de aplicación con su propia máquina de estados —
 * cubre el arquetipo Listado (`index`) y el arquetipo Formulario (`create`,
 * sin sub-entidad repetible). Sin gráficos ni animación propia — mismo
 * criterio que `contratos.spec.ts`/`drones.spec.ts`.
 */
test.describe('ordenes', () => {
    test.describe('index', () => {
        test.beforeEach(async ({ page }) => {
            await iniciarSesion(page);
            await elegirRolDueno(page);
            await page.goto('/panel/ordenes');
        });

        test('claro', async ({ page }) => {
            await asegurarTema(page, 'light');
            await esperarFuentes(page);

            await expect(page).toHaveScreenshot('ordenes-index-light.png', {
                fullPage: true,
                mask: [page.locator('.ag-panel__footer span').first()],
            });
        });

        test('oscuro', async ({ page }) => {
            await asegurarTema(page, 'dark');
            await esperarFuentes(page);

            await expect(page).toHaveScreenshot('ordenes-index-dark.png', {
                fullPage: true,
                mask: [page.locator('.ag-panel__footer span').first()],
            });
        });
    });

    test.describe('create', () => {
        test.beforeEach(async ({ page }) => {
            await iniciarSesion(page);
            await elegirRolDueno(page);
            await page.goto('/panel/ordenes/crear');
        });

        test('claro', async ({ page }) => {
            await asegurarTema(page, 'light');
            await esperarFuentes(page);

            await expect(page).toHaveScreenshot('ordenes-create-light.png', {
                fullPage: true,
                mask: [page.locator('.ag-panel__footer span').first()],
            });
        });

        test('oscuro', async ({ page }) => {
            await asegurarTema(page, 'dark');
            await esperarFuentes(page);

            await expect(page).toHaveScreenshot('ordenes-create-dark.png', {
                fullPage: true,
                mask: [page.locator('.ag-panel__footer span').first()],
            });
        });
    });
});
