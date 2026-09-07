import { test, expect } from '@playwright/test';
import { asegurarTema, esperarFuentes, iniciarSesion } from './helpers';

/**
 * GET /panel/seleccionar-rol, tras loguear con el usuario demo multirol
 * (`carlos.ferrufino`, cuatro roles — Demo\PersonalDemoSeeder). Con 2+ roles vivos y
 * sin preferencia fijada, el selector siempre se muestra (RolActivoController
 * ::create()).
 */
test.describe('seleccionar-rol', () => {
    test.beforeEach(async ({ page }) => {
        await iniciarSesion(page);
    });

    test('claro', async ({ page }) => {
        await asegurarTema(page, 'light');
        await esperarFuentes(page);

        await expect(page).toHaveScreenshot('seleccionar-rol-light.png', {
            fullPage: true,
            mask: [page.locator('.ag-auth-layout__footer span').first()],
        });
    });

    test('oscuro', async ({ page }) => {
        await asegurarTema(page, 'dark');
        await esperarFuentes(page);

        await expect(page).toHaveScreenshot('seleccionar-rol-dark.png', {
            fullPage: true,
            mask: [page.locator('.ag-auth-layout__footer span').first()],
        });
    });
});
