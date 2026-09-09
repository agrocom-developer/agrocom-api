import { test, expect } from '@playwright/test';
import { asegurarTema, esperarFuentes } from './helpers';

/**
 * GET /restablecer/{token} y GET /portal/restablecer/{token} (tarea 66):
 * pantalla de "elegir contraseña nueva" llegada desde el enlace del correo
 * — mismo formulario en las dos, `RestablecerContrasenaController`/
 * `RestablecerContrasenaPortalController` solo cambian a qué URL postea.
 *
 * El token de la URL no necesita ser válido para esta captura: el GET solo
 * lo pasa de vuelta al formulario como campo oculto — la validación real
 * corre recién en el POST, que esta suite visual no ejercita.
 */
const TOKEN_DE_MUESTRA = 'token-de-muestra-para-captura';
const EMAIL_DE_MUESTRA = 'demo@agrocom.example';

test.describe('restablecer (panel)', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(`/restablecer/${TOKEN_DE_MUESTRA}?email=${encodeURIComponent(EMAIL_DE_MUESTRA)}`);
    });

    test('claro', async ({ page }) => {
        await asegurarTema(page, 'light');
        await esperarFuentes(page);

        await expect(page).toHaveScreenshot('restablecer-light.png', {
            fullPage: true,
            mask: [page.locator('.ag-auth-layout__footer span').first()],
        });
    });

    test('oscuro', async ({ page }) => {
        await asegurarTema(page, 'dark');
        await esperarFuentes(page);

        await expect(page).toHaveScreenshot('restablecer-dark.png', {
            fullPage: true,
            mask: [page.locator('.ag-auth-layout__footer span').first()],
        });
    });
});

test.describe('restablecer (portal)', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(`/portal/restablecer/${TOKEN_DE_MUESTRA}?email=${encodeURIComponent(EMAIL_DE_MUESTRA)}`);
    });

    test('claro', async ({ page }) => {
        await asegurarTema(page, 'light');
        await esperarFuentes(page);

        await expect(page).toHaveScreenshot('portal-restablecer-light.png', {
            fullPage: true,
            mask: [page.locator('.ag-auth-layout__footer span').first()],
        });
    });

    test('oscuro', async ({ page }) => {
        await asegurarTema(page, 'dark');
        await esperarFuentes(page);

        await expect(page).toHaveScreenshot('portal-restablecer-dark.png', {
            fullPage: true,
            mask: [page.locator('.ag-auth-layout__footer span').first()],
        });
    });
});
