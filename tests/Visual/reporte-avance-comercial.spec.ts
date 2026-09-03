import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';
import { asegurarTema, elegirRolDueno, esperarFuentes, iniciarSesion } from './helpers';

/**
 * GET /panel/reportes/comercial, con "Dueño" como rol activo — único rol con
 * el permiso `comercial.reporte.ver` (exclusivo del dueño, no entra en
 * PERMISOS_ENCARGADO_OPERACIONES).
 *
 * HU-32 (tarea 46): reporte comercial de avance por contrato — cubre el
 * arquetipo Listado (sin paginación: agrega por contrato, volumen acotado)
 * con al menos una fila con datos reales (hectáreas aplicadas/facturadas y
 * monto > 0), cargada por `fixtures/reporte-avance-comercial-demo.php`. Sin
 * gráficos ni animación propia — mismo criterio que `facturas.spec.ts`.
 */
test.beforeAll(() => {
    execFileSync(
        'docker',
        ['compose', 'exec', '-T', 'app', 'php', '/var/www/html/tests/Visual/fixtures/reporte-avance-comercial-demo.php'],
        { stdio: 'inherit' },
    );
});

test.describe('reporte-avance-comercial', () => {
    test.beforeEach(async ({ page }) => {
        await iniciarSesion(page);
        await elegirRolDueno(page);
        await page.goto('/panel/reportes/comercial');
    });

    test('claro', async ({ page }) => {
        await asegurarTema(page, 'light');
        await esperarFuentes(page);

        await expect(page).toHaveScreenshot('reporte-avance-comercial-light.png', {
            fullPage: true,
            mask: [page.locator('.ag-panel__footer span').first()],
        });
    });

    test('oscuro', async ({ page }) => {
        await asegurarTema(page, 'dark');
        await esperarFuentes(page);

        await expect(page).toHaveScreenshot('reporte-avance-comercial-dark.png', {
            fullPage: true,
            mask: [page.locator('.ag-panel__footer span').first()],
        });
    });
});
