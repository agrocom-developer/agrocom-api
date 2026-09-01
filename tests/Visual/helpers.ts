import { type Page } from '@playwright/test';

export const USUARIO_DEMO = 'camila.rojas';
export const PASSWORD_DEMO = 'password';
export const NOMBRE_ROL_DUENO = 'Dueño';

/**
 * Login real (no bypass de sesión): username + password contra el guard
 * `interno`, igual que un usuario de verdad — la propia pantalla /login es
 * una de las tres vistas a cubrir, así que hay que pasar por ella de todos
 * modos.
 */
export async function iniciarSesion(page: Page): Promise<void> {
    await page.goto('/login');
    await page.fill('input[name="username"]', USUARIO_DEMO);
    await page.fill('input[name="password"]', PASSWORD_DEMO);
    await Promise.all([
        page.waitForURL('**/panel/seleccionar-rol'),
        page.click('.ag-login-form__form button[type="submit"]'),
    ]);
}

/**
 * Desde /panel/seleccionar-rol, elige explícitamente la tarjeta "Dueño" por
 * NOMBRE (data-ag-role-nombre) en vez de confiar en la preselección del
 * servidor — la preselección depende de `sec_user_preferencia` (preferido >
 * último usado > rol de sesión > primero de la lista), que corridas previas
 * de esta misma suite pueden haber dejado en cualquier estado. Elegir por
 * nombre hace el test independiente de ese historial.
 */
export async function elegirRolDueno(page: Page): Promise<void> {
    await page.locator(`[data-ag-role-nombre="${NOMBRE_ROL_DUENO}"]`).click();
    await Promise.all([
        page.waitForURL('**/panel/dashboard'),
        page.click('[data-ag-role-continuar]'),
    ]);
}

/**
 * Deja `data-bs-theme` en el valor pedido, click en el toggle real del
 * header SOLO si hace falta. No asumir que el estado inicial es "light":
 * `seleccionar-rol` y `dashboard` resuelven el tema desde
 * `sec_user_preferencia.tema`, que un test de tema oscuro anterior (misma
 * corrida u otra previa) puede haber dejado en "dark" — este helper hace
 * cada test idempotente frente a ese estado compartido.
 */
export async function asegurarTema(page: Page, tema: 'light' | 'dark'): Promise<void> {
    const actual = await page.evaluate(() => document.documentElement.getAttribute('data-bs-theme'));

    if (actual === tema) {
        return;
    }

    await page.locator('[data-ag-theme-toggle]').first().click();
    await page.waitForFunction(
        (esperado) => document.documentElement.getAttribute('data-bs-theme') === esperado,
        tema,
    );
}

/** Fuentes @fontsource cargadas antes de capturar — si no, el primer render usa la fuente de sistema y la captura es inestable entre corridas. */
export async function esperarFuentes(page: Page): Promise<void> {
    await page.evaluate(() => document.fonts.ready);
}
