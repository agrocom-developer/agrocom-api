import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';
import { asegurarTema, elegirRolDueno, esperarFuentes, iniciarSesion } from './helpers';

/**
 * GET /panel/anticipos y GET /panel/anticipos/crear, con "Dueño" como rol
 * activo (tiene el catálogo completo de permisos, incluidos los tres
 * `finanzas.anticipo.*` de esta HU).
 *
 * HU-29 (tarea 41): ABM acotado de anticipos con tope validado — cubre el
 * arquetipo Listado (`index`, con un anticipo real cargado por
 * `fixtures/anticipos-demo.php`) y el arquetipo Formulario (`create`, con la
 * caja de "consultar disponible"). Sin gráficos ni animación propia — mismo
 * criterio que `bases.spec.ts`/`devengos.spec.ts`.
 */
test.beforeAll(() => {
    execFileSync(
        'docker',
        ['compose', 'exec', '-T', 'app', 'php', '/var/www/html/tests/Visual/fixtures/anticipos-demo.php'],
        { stdio: 'inherit' },
    );
});

test.describe('anticipos', () => {
    test.describe('index', () => {
        test.beforeEach(async ({ page }) => {
            await iniciarSesion(page);
            await elegirRolDueno(page);
            await page.goto('/panel/anticipos');
        });

        test('claro', async ({ page }) => {
            await asegurarTema(page, 'light');
            await esperarFuentes(page);

            await expect(page).toHaveScreenshot('anticipos-index-light.png', {
                fullPage: true,
                mask: [page.locator('.ag-panel__footer span').first()],
            });
        });

        test('oscuro', async ({ page }) => {
            await asegurarTema(page, 'dark');
            await esperarFuentes(page);

            await expect(page).toHaveScreenshot('anticipos-index-dark.png', {
                fullPage: true,
                mask: [page.locator('.ag-panel__footer span').first()],
            });
        });
    });

    test.describe('create', () => {
        test.beforeEach(async ({ page }) => {
            await iniciarSesion(page);
            await elegirRolDueno(page);
            await page.goto('/panel/anticipos/crear');
        });

        test('claro', async ({ page }) => {
            await asegurarTema(page, 'light');
            await esperarFuentes(page);

            await expect(page).toHaveScreenshot('anticipos-create-light.png', {
                fullPage: true,
                mask: [page.locator('.ag-panel__footer span').first()],
            });
        });

        test('oscuro', async ({ page }) => {
            await asegurarTema(page, 'dark');
            await esperarFuentes(page);

            await expect(page).toHaveScreenshot('anticipos-create-dark.png', {
                fullPage: true,
                mask: [page.locator('.ag-panel__footer span').first()],
            });
        });
    });
});
