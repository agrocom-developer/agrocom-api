import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';
import { asegurarTema, elegirRolPiloto, esperarFuentes, iniciarSesion } from './helpers';

/**
 * GET /panel/devengos (redirige a /panel/devengos/{persona}), con "Piloto de
 * dron" como rol activo de `camila.rojas` (HU-28, tarea 40) — primera
 * pantalla del panel scoped por PERSONA, no por rol/permiso, así que no se
 * puede entrar con "Dueño" como el resto de la suite: hace falta un usuario
 * con `persona_id` real. `fixtures/devengos-demo.php` asocia esa persona a
 * `camila.rojas` (en vez de crear un `SecUser` nuevo — evita romper
 * `usuarios.spec.ts`) y genera un devengo real del mes (vía `ValidarSesion`,
 * la única vía de la app que genera uno), reusando el cliente/campo/lote/
 * contrato/orden de `NucleoComercialSeeder` (evita romper `clientes`/
 * `campos`/`contratos`/`ordenes`.spec.ts). Se corre una vez antes de las dos
 * pruebas, es idempotente.
 *
 * Caso de prueba de una tabla de solo lectura sin acciones (sin
 * alta/edición/baja): cabecera, filtro de período (`<input type="month">`) y
 * la tabla con su fila de total. Sin gráficos ni animación propia.
 */
test.beforeAll(() => {
    execFileSync(
        'docker',
        ['compose', 'exec', '-T', 'app', 'php', '/var/www/html/tests/Visual/fixtures/devengos-demo.php'],
        { stdio: 'inherit' },
    );
});

test.describe('devengos', () => {
    test.beforeEach(async ({ page }) => {
        await iniciarSesion(page);
        await elegirRolPiloto(page);
        await page.goto('/panel/devengos');
    });

    test('claro', async ({ page }) => {
        await asegurarTema(page, 'light');
        await esperarFuentes(page);

        await expect(page).toHaveScreenshot('devengos-light.png', {
            fullPage: true,
            mask: [page.locator('.ag-panel__footer span').first()],
        });
    });

    test('oscuro', async ({ page }) => {
        await asegurarTema(page, 'dark');
        await esperarFuentes(page);

        await expect(page).toHaveScreenshot('devengos-dark.png', {
            fullPage: true,
            mask: [page.locator('.ag-panel__footer span').first()],
        });
    });
});
