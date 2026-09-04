import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';
import { asegurarTema, elegirRolDueno, esperarFuentes, iniciarSesion } from './helpers';

/**
 * GET /panel/combustible y GET /panel/combustible/crear, con "Dueño" como
 * rol activo (tiene el catálogo completo de permisos, incluidos los tres
 * `finanzas.combustible.*` de esta HU).
 *
 * HU-35 (tarea 49): ABM acotado de combustible del generador y de
 * vehículos — cubre el arquetipo Listado (`index`, con dos cargas reales
 * cargadas por `fixtures/combustible-demo.php`, una de cada destino) y el
 * arquetipo Formulario (`create`). Sin gráficos ni animación propia —
 * mismo criterio que `gastos.spec.ts`.
 *
 * `create` enmascara `#fecha`: el campo defaultea a `now()->toDateString()`
 * (`pages/combustible/create.blade.php`), así que sin máscara el snapshot
 * queda atado al día calendario en que se generó (hallazgo de tarea 55).
 */
test.beforeAll(() => {
    execFileSync(
        'docker',
        ['compose', 'exec', '-T', 'app', 'php', '/var/www/html/tests/Visual/fixtures/combustible-demo.php'],
        { stdio: 'inherit' },
    );
});

test.describe('combustible', () => {
    test.describe('index', () => {
        test.beforeEach(async ({ page }) => {
            await iniciarSesion(page);
            await elegirRolDueno(page);
            await page.goto('/panel/combustible');
        });

        test('claro', async ({ page }) => {
            await asegurarTema(page, 'light');
            await esperarFuentes(page);

            await expect(page).toHaveScreenshot('combustible-index-light.png', {
                fullPage: true,
                mask: [page.locator('.ag-panel__footer span').first()],
            });
        });

        test('oscuro', async ({ page }) => {
            await asegurarTema(page, 'dark');
            await esperarFuentes(page);

            await expect(page).toHaveScreenshot('combustible-index-dark.png', {
                fullPage: true,
                mask: [page.locator('.ag-panel__footer span').first()],
            });
        });
    });

    test.describe('create', () => {
        test.beforeEach(async ({ page }) => {
            await iniciarSesion(page);
            await elegirRolDueno(page);
            await page.goto('/panel/combustible/crear');
        });

        test('claro', async ({ page }) => {
            await asegurarTema(page, 'light');
            await esperarFuentes(page);

            await expect(page).toHaveScreenshot('combustible-create-light.png', {
                fullPage: true,
                mask: [page.locator('.ag-panel__footer span').first(), page.locator('#fecha')],
            });
        });

        test('oscuro', async ({ page }) => {
            await asegurarTema(page, 'dark');
            await esperarFuentes(page);

            await expect(page).toHaveScreenshot('combustible-create-dark.png', {
                fullPage: true,
                mask: [page.locator('.ag-panel__footer span').first(), page.locator('#fecha')],
            });
        });
    });
});
