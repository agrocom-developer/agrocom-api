import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';
import { asegurarTema, elegirRolDueno, esperarFuentes, iniciarSesion } from './helpers';

/**
 * GET /panel/reportes/tecnicos, con "Dueño" como rol activo — `dueno` tiene
 * TODOS los permisos (incluido `operaciones.reporte.ver`, ya usado por
 * `panel.trabajos.reporte-pdf`), mismo criterio que
 * `reporte-avance-comercial.spec.ts`.
 *
 * HU-43 (tarea 57): listado de reportes técnicos — cubre el arquetipo
 * Listado (sin paginación: volumen acotado por trabajos conformados) con al
 * menos una fila real (lote, cliente, fecha de generación, botón de
 * descarga), cargada por `fixtures/reportes-tecnicos-demo.php`. Sin
 * gráficos ni animación propia — mismo criterio que `facturas.spec.ts`.
 */
test.beforeAll(() => {
    execFileSync(
        'docker',
        ['compose', 'exec', '-T', 'app', 'php', '/var/www/html/tests/Visual/fixtures/reportes-tecnicos-demo.php'],
        { stdio: 'inherit' },
    );
});

test.describe('reportes-tecnicos', () => {
    test.beforeEach(async ({ page }) => {
        await iniciarSesion(page);
        await elegirRolDueno(page);
        await page.goto('/panel/reportes/tecnicos');
    });

    test('claro', async ({ page }) => {
        await asegurarTema(page, 'light');
        await esperarFuentes(page);

        await expect(page).toHaveScreenshot('reportes-tecnicos-light.png', {
            fullPage: true,
            mask: [page.locator('.ag-panel__footer span').first()],
        });
    });

    test('oscuro', async ({ page }) => {
        await asegurarTema(page, 'dark');
        await esperarFuentes(page);

        await expect(page).toHaveScreenshot('reportes-tecnicos-dark.png', {
            fullPage: true,
            mask: [page.locator('.ag-panel__footer span').first()],
        });
    });
});
