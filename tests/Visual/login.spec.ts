import { test, expect } from '@playwright/test';
import { asegurarTema, esperarFuentes } from './helpers';

/**
 * GET /login — la única de las tres vistas que NO resuelve el tema desde
 * `sec_user_preferencia` (login.blade.php hardcodea `data-bs-theme="light"`:
 * no hay usuario autenticado todavía de quien leer una preferencia). El
 * toggle del header sigue funcionando client-side (theme-toggle.js cambia el
 * atributo en el DOM aunque no haya URL de persistencia que postear).
 */
test.describe('login', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/login');
    });

    test('claro', async ({ page }) => {
        await asegurarTema(page, 'light');
        await esperarFuentes(page);

        await expect(page).toHaveScreenshot('login-light.png', {
            fullPage: true,
            mask: [page.locator('.ag-auth-layout__footer span').first()],
        });
    });

    test('oscuro', async ({ page }) => {
        await asegurarTema(page, 'dark');
        await esperarFuentes(page);

        await expect(page).toHaveScreenshot('login-dark.png', {
            fullPage: true,
            mask: [page.locator('.ag-auth-layout__footer span').first()],
        });
    });
});
