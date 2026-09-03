import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';
import { asegurarTema, elegirRolDueno, esperarFuentes, iniciarSesion } from './helpers';

/**
 * GET /panel/rendiciones (index), GET /panel/rendiciones/crear (create), y
 * GET /panel/rendiciones/{rendicion} (show) con "Dueño" como rol activo
 * (tiene permisos `finanzas.rendicion.*` de esta HU).
 *
 * HU-34 (tarea 48): Rendiciones de campo — cubre los tres arquetipos:
 * - Listado (index, con filtros y tabla de rendiciones cargadas por
 *   `fixtures/rendiciones-demo.php`).
 * - Formulario (create, con selects de base y jefe de campo).
 * - Detalle (show, con resumen, tabla de gastos asociados, tabla de gastos
 *   disponibles, y botones de transición de estado — es la vista con más
 *   elementos nuevos).
 *
 * Para el test de `show`, se captura una rendición en estado `presentada`
 * (cargada por el fixture) porque es el más interesante: muestra el chip de
 * estado, la tabla de gastos asociados, y el botón "Aprobar" habilitado.
 * El fixture asegura idempotencia y no toca datos de otras pantallas.
 */
test.beforeAll(() => {
    execFileSync(
        'docker',
        ['compose', 'exec', '-T', 'app', 'php', '/var/www/html/tests/Visual/fixtures/rendiciones-demo.php'],
        { stdio: 'inherit' },
    );
});

test.describe('rendiciones', () => {
    test.describe('index', () => {
        test.beforeEach(async ({ page }) => {
            await iniciarSesion(page);
            await elegirRolDueno(page);
            await page.goto('/panel/rendiciones');
        });

        test('claro', async ({ page }) => {
            await asegurarTema(page, 'light');
            await esperarFuentes(page);

            await expect(page).toHaveScreenshot('rendiciones-index-light.png', {
                fullPage: true,
                mask: [page.locator('.ag-panel__footer span').first()],
            });
        });

        test('oscuro', async ({ page }) => {
            await asegurarTema(page, 'dark');
            await esperarFuentes(page);

            await expect(page).toHaveScreenshot('rendiciones-index-dark.png', {
                fullPage: true,
                mask: [page.locator('.ag-panel__footer span').first()],
            });
        });
    });

    test.describe('create', () => {
        test.beforeEach(async ({ page }) => {
            await iniciarSesion(page);
            await elegirRolDueno(page);
            await page.goto('/panel/rendiciones/crear');
        });

        test('claro', async ({ page }) => {
            await asegurarTema(page, 'light');
            await esperarFuentes(page);

            await expect(page).toHaveScreenshot('rendiciones-create-light.png', {
                fullPage: true,
                mask: [page.locator('.ag-panel__footer span').first()],
            });
        });

        test('oscuro', async ({ page }) => {
            await asegurarTema(page, 'dark');
            await esperarFuentes(page);

            await expect(page).toHaveScreenshot('rendiciones-create-dark.png', {
                fullPage: true,
                mask: [page.locator('.ag-panel__footer span').first()],
            });
        });
    });

    test.describe('show', () => {
        test.beforeEach(async ({ page }) => {
            await iniciarSesion(page);
            await elegirRolDueno(page);
            // Navega a la lista de rendiciones y accede al primer detalle
            // (el fixture carga al menos una rendición)
            await page.goto('/panel/rendiciones');
            // Espera la tabla y haz clic en el primer botón "Ver"
            await page.waitForSelector('[role="table"]');
            await page.click('a:has-text("Ver")');
        });

        test('claro', async ({ page }) => {
            await asegurarTema(page, 'light');
            await esperarFuentes(page);

            await expect(page).toHaveScreenshot('rendiciones-show-light.png', {
                fullPage: true,
                mask: [page.locator('.ag-panel__footer span').first()],
            });
        });

        test('oscuro', async ({ page }) => {
            await asegurarTema(page, 'dark');
            await esperarFuentes(page);

            await expect(page).toHaveScreenshot('rendiciones-show-dark.png', {
                fullPage: true,
                mask: [page.locator('.ag-panel__footer span').first()],
            });
        });
    });
});
