import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';
import { asegurarTema, esperarFuentes } from './helpers';

/**
 * GET /portal/login — pantalla de login del portal del cliente.
 * Reutiliza `auth-layout` y `login-form` del panel interno (ADR 0002 punto 6).
 * A diferencia del login del panel interno (/login), el tema no se resuelve
 * desde `sec_user_preferencia` porque no hay usuario autenticado todavía.
 * El formulario postea a /portal/login (guard `cliente`) en vez de /login
 * (guard `interno`).
 *
 * `fixtures/portal-demo.php` crea una cuenta de portal (`cliente.visual.portal`
 * / `Secreta123`) con su contrato/orden/acta/reporte asociados, para que las
 * siguientes tres specs (portal-avance, portal-actas, portal-reportes) tengan
 * datos reales que mostrar.
 */
test.beforeAll(() => {
    execFileSync(
        'docker',
        ['compose', 'exec', '-T', 'app', 'php', '/var/www/html/tests/Visual/fixtures/portal-demo.php'],
        { stdio: 'inherit' },
    );
});

test.describe('portal-login', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/portal/login');
    });

    test('claro', async ({ page }) => {
        await asegurarTema(page, 'light');
        await esperarFuentes(page);

        await expect(page).toHaveScreenshot('portal-login-light.png', {
            fullPage: true,
            mask: [page.locator('.ag-auth-layout__footer span').first()],
        });
    });

    test('oscuro', async ({ page }) => {
        await asegurarTema(page, 'dark');
        await esperarFuentes(page);

        await expect(page).toHaveScreenshot('portal-login-dark.png', {
            fullPage: true,
            mask: [page.locator('.ag-auth-layout__footer span').first()],
        });
    });
});
