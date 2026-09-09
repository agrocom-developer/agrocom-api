import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';
import { asegurarTema, elegirRolDueno, esperarFuentes, iniciarSesion } from './helpers';

/**
 * GET /panel/reportes/comercial, con "Dueño" como rol activo — único rol con
 * el permiso `comercial.reporte.ver` (exclusivo del dueño, no entra en
 * PERMISOS_ENCARGADO_OPERACIONES).
 *
 * HU-52 (tarea 75, espec §9.1): informe de avance de contratos, por cultivo y
 * por cliente — reemplazó en la misma ruta a la pantalla plana de HU-32
 * (tarea 46). Entrada obligatoria de cliente y cultivo: se llega a la
 * pantalla de resultados marcando las casillas por TEXTO (nombre real del
 * cliente/cultivo, no un id numérico — más estable que hardcodear ids que
 * dependen del orden de los seeders) y enviando el formulario, en vez de
 * navegar directo con querystring. Con al menos una fila con datos reales
 * (hectáreas aplicadas > 0), cargada por
 * `fixtures/reporte-avance-comercial-demo.php`. Sin gráficos ni animación
 * propia — mismo criterio que `facturas.spec.ts`.
 */
const CLIENTE_DEMO = 'Agropecuaria San Jorge S.R.L.';
const CULTIVO_DEMO = 'Maíz';

test.beforeAll(() => {
    execFileSync(
        'docker',
        ['compose', 'exec', '-T', 'app', 'php', '/var/www/html/tests/Visual/fixtures/reporte-avance-comercial-demo.php'],
        { stdio: 'inherit' },
    );
});

async function marcarOpcion(scope: import('@playwright/test').Locator, texto: string): Promise<void> {
    await scope.locator('.ag-checkbox-group__option', { hasText: texto }).locator('input[type=checkbox]').check({ force: true });
}

async function irAResultados(page: import('@playwright/test').Page): Promise<void> {
    await page.goto('/panel/reportes/comercial');
    // Scopeado a la pantalla de entrada: el offcanvas de filtros repite los
    // mismos checkbox-group (cliente/cultivo, para poder editarlos ahí
    // también). Por texto de la opción, no `getByLabel`: el nombre accesible
    // de esta casilla (envuelta en su propio `<label>`, con un ícono
    // `aria-hidden` de por medio) no resolvió de forma confiable en la
    // verificación manual de la tarea 75 — el texto visible sí.
    const entrada = page.locator('.ag-reportes-comerciales__entrada');
    await marcarOpcion(entrada, CLIENTE_DEMO);
    await marcarOpcion(entrada, CULTIVO_DEMO);
    await Promise.all([
        page.waitForURL('**consultado=1**'),
        page.locator('#boton-generar').click(),
    ]);
}

test.describe('reporte-avance-comercial', () => {
    test.beforeEach(async ({ page }) => {
        await iniciarSesion(page);
        await elegirRolDueno(page);
        await irAResultados(page);
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
