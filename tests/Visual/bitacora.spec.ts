import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';
import { asegurarTema, elegirRolDueno, esperarFuentes, iniciarSesion } from './helpers';

/**
 * GET /panel/bitacora (tarea 63), con "Dueño" como rol activo (uno de los
 * dos únicos roles con `seguridad.bitacora.ver`). Arquetipo Listado: cabecera
 * → filtros → tabla → paginación, con un `<details>` desplegable por fila
 * para el diff antes/después.
 *
 * Filtra explícitamente por `desde=2026-09-04&hasta=2026-09-04` (misma fecha
 * fija que usa `fixtures/bitacora-demo.php`): sin el filtro, el resto de la
 * suite visual (y cualquier otro dato de la base del compose) también deja
 * bitácora con fecha de HOY, más reciente que la del fixture — el listado
 * por defecto (más reciente primero) podría dejar las cuatro filas del
 * fixture fuera de la primera página. El filtro por fecha aísla la captura
 * de ese ruido sin depender de cuántas otras filas haya en la base.
 */
test.beforeAll(() => {
    execFileSync(
        'docker',
        ['compose', 'exec', '-T', 'app', 'php', '/var/www/html/tests/Visual/fixtures/bitacora-demo.php'],
        { stdio: 'inherit' },
    );
});

test.describe('bitacora', () => {
    test.describe('index', () => {
        test.beforeEach(async ({ page }) => {
            await iniciarSesion(page);
            await elegirRolDueno(page);
            await page.goto('/panel/bitacora?desde=2026-09-04&hasta=2026-09-04');
        });

        test('claro', async ({ page }) => {
            await asegurarTema(page, 'light');
            await esperarFuentes(page);

            await expect(page).toHaveScreenshot('bitacora-index-light.png', {
                fullPage: true,
                mask: [page.locator('.ag-panel__footer span').first()],
            });
        });

        test('oscuro', async ({ page }) => {
            await asegurarTema(page, 'dark');
            await esperarFuentes(page);

            await expect(page).toHaveScreenshot('bitacora-index-dark.png', {
                fullPage: true,
                mask: [page.locator('.ag-panel__footer span').first()],
            });
        });

        test('detalle desplegado muestra el diff antes/después', async ({ page }) => {
            await asegurarTema(page, 'light');
            await esperarFuentes(page);

            // La fila "Actualizado" (rol editado) es la más reciente del
            // fixture: primer <details> de la tabla.
            await page.locator('.ag-bitacora__details').first().locator('summary').click();

            await expect(page).toHaveScreenshot('bitacora-index-detalle-light.png', {
                fullPage: true,
                mask: [page.locator('.ag-panel__footer span').first()],
            });
        });
    });
});
