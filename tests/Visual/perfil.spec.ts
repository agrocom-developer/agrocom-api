import { test, expect } from '@playwright/test';
import { asegurarTema, elegirRolDueno, esperarFuentes, iniciarSesion, iniciarSesionPortal } from './helpers';

/**
 * GET /panel/perfil y GET /portal/perfil (tarea 66): autoservicio de
 * nombre/correo/contraseña, mismo formulario en las dos pantallas — panel
 * sobre `panel-layout`, portal sobre `portal-layout`.
 */
test.describe('perfil (panel)', () => {
    test.beforeEach(async ({ page }) => {
        await iniciarSesion(page);
        await elegirRolDueno(page);
        await page.goto('/panel/perfil');
    });

    test('claro', async ({ page }) => {
        await asegurarTema(page, 'light');
        await esperarFuentes(page);

        await expect(page).toHaveScreenshot('perfil-light.png', {
            fullPage: true,
            mask: [page.locator('.ag-panel__footer span').first()],
        });
    });

    test('oscuro', async ({ page }) => {
        await asegurarTema(page, 'dark');
        await esperarFuentes(page);

        await expect(page).toHaveScreenshot('perfil-dark.png', {
            fullPage: true,
            mask: [page.locator('.ag-panel__footer span').first()],
        });
    });
});

test.describe('perfil (portal)', () => {
    test.beforeEach(async ({ page }) => {
        await iniciarSesionPortal(page);
        await page.goto('/portal/perfil');
    });

    test('claro', async ({ page }) => {
        await asegurarTema(page, 'light');
        await esperarFuentes(page);

        await expect(page).toHaveScreenshot('portal-perfil-light.png', {
            fullPage: true,
            mask: [page.locator('.ag-portal__footer span').first()],
        });
    });

    test('oscuro', async ({ page }) => {
        await asegurarTema(page, 'dark');
        await esperarFuentes(page);

        await expect(page).toHaveScreenshot('portal-perfil-dark.png', {
            fullPage: true,
            mask: [page.locator('.ag-portal__footer span').first()],
        });
    });
});
