import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';
import { asegurarTema, elegirRolDueno, esperarFuentes, iniciarSesion } from './helpers';

/**
 * GET /panel/planillas y GET /panel/planillas/{planilla}, con "Dueño" como
 * rol activo (único rol con `finanzas.planilla.aprobar`).
 *
 * HU-30 (tarea 44): planilla del período generada desde devengos y
 * anticipos, aprobada por el dueño. Cubre el arquetipo Listado (`index`, con
 * una planilla real cargada por `fixtures/planilla-demo.php`) y el
 * arquetipo Detalle (`show`, ya `aprobada`, con el recibo en PDF de cada
 * persona visible). Sin gráficos ni animación propia — mismo criterio que
 * `anticipos.spec.ts`/`devengos.spec.ts`.
 */
let planillaId: string;

test.beforeAll(() => {
    const salida = execFileSync(
        'docker',
        ['compose', 'exec', '-T', 'app', 'php', '/var/www/html/tests/Visual/fixtures/planilla-demo.php'],
    ).toString();

    console.log(salida);

    const id = salida.match(/PLANILLA_ID=(\d+)/)?.[1];
    if (! id) {
        throw new Error('planilla-demo.php no imprimió PLANILLA_ID');
    }

    planillaId = id;
});

test.describe('planilla', () => {
    test.describe('index', () => {
        test.beforeEach(async ({ page }) => {
            await iniciarSesion(page);
            await elegirRolDueno(page);
            await page.goto('/panel/planillas');
        });

        test('claro', async ({ page }) => {
            await asegurarTema(page, 'light');
            await esperarFuentes(page);

            await expect(page).toHaveScreenshot('planilla-index-light.png', {
                fullPage: true,
                mask: [page.locator('.ag-panel__footer span').first()],
            });
        });

        test('oscuro', async ({ page }) => {
            await asegurarTema(page, 'dark');
            await esperarFuentes(page);

            await expect(page).toHaveScreenshot('planilla-index-dark.png', {
                fullPage: true,
                mask: [page.locator('.ag-panel__footer span').first()],
            });
        });
    });

    test.describe('show', () => {
        test.beforeEach(async ({ page }) => {
            await iniciarSesion(page);
            await elegirRolDueno(page);
            await page.goto(`/panel/planillas/${planillaId}`);
        });

        test('claro', async ({ page }) => {
            await asegurarTema(page, 'light');
            await esperarFuentes(page);

            await expect(page).toHaveScreenshot('planilla-show-light.png', {
                fullPage: true,
                mask: [page.locator('.ag-panel__footer span').first()],
            });
        });

        test('oscuro', async ({ page }) => {
            await asegurarTema(page, 'dark');
            await esperarFuentes(page);

            await expect(page).toHaveScreenshot('planilla-show-dark.png', {
                fullPage: true,
                mask: [page.locator('.ag-panel__footer span').first()],
            });
        });
    });
});
