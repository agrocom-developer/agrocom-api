import { test, expect } from '@playwright/test';
import { asegurarTema, elegirRolDueno, esperarFuentes, iniciarSesion } from './helpers';

/**
 * GET /panel/ordenes-mantenimiento y GET /panel/ordenes-mantenimiento/crear,
 * con "Dueño" como rol activo (tiene el catálogo completo de permisos,
 * incluidos los cuatro `mantenimiento.orden.*` de esta HU).
 *
 * HU-37 (tarea 53): apertura y cierre de órdenes de mantenimiento — cubre el
 * arquetipo Listado (`index`) y el arquetipo Formulario (`create`, con el
 * toggle de equipo dron/vehículo). Sin gráficos ni animación propia — mismo
 * criterio que `vehiculos.spec.ts`/`repuestos.spec.ts`. El detalle/cierre
 * (`edit`) queda fuera, mismo criterio que esos dos specs (sin cobertura
 * visual de la pantalla de edición/detalle).
 */
test.describe('ordenes-mantenimiento', () => {
    test.describe('index', () => {
        test.beforeEach(async ({ page }) => {
            await iniciarSesion(page);
            await elegirRolDueno(page);
            await page.goto('/panel/ordenes-mantenimiento');
        });

        test('claro', async ({ page }) => {
            await asegurarTema(page, 'light');
            await esperarFuentes(page);

            await expect(page).toHaveScreenshot('ordenes-mantenimiento-index-light.png', {
                fullPage: true,
                mask: [page.locator('.ag-panel__footer span').first()],
            });
        });

        test('oscuro', async ({ page }) => {
            await asegurarTema(page, 'dark');
            await esperarFuentes(page);

            await expect(page).toHaveScreenshot('ordenes-mantenimiento-index-dark.png', {
                fullPage: true,
                mask: [page.locator('.ag-panel__footer span').first()],
            });
        });
    });

    test.describe('create', () => {
        test.beforeEach(async ({ page }) => {
            await iniciarSesion(page);
            await elegirRolDueno(page);
            await page.goto('/panel/ordenes-mantenimiento/crear');
        });

        test('claro', async ({ page }) => {
            await asegurarTema(page, 'light');
            await esperarFuentes(page);

            await expect(page).toHaveScreenshot('ordenes-mantenimiento-create-light.png', {
                fullPage: true,
                mask: [page.locator('.ag-panel__footer span').first()],
            });
        });

        test('oscuro', async ({ page }) => {
            await asegurarTema(page, 'dark');
            await esperarFuentes(page);

            await expect(page).toHaveScreenshot('ordenes-mantenimiento-create-dark.png', {
                fullPage: true,
                mask: [page.locator('.ag-panel__footer span').first()],
            });
        });
    });
});
