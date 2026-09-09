import { type Page } from '@playwright/test';

export const USUARIO_DEMO = 'carlos.ferrufino';
export const PASSWORD_DEMO = 'password';
export const NOMBRE_ROL_DUENO = 'Dueño';
export const NOMBRE_ROL_PILOTO = 'Piloto de dron';
export const USUARIO_PORTAL_DEMO = 'cliente.visual.portal';
export const PASSWORD_PORTAL_DEMO = 'Secreta123';

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
 * Mismo mecanismo que `elegirRolDueno`, con el rol "Piloto de dron" — HU-28
 * (tarea 40): `devengos.spec.ts` necesita el rol scoped por persona, no el de
 * máximo permiso.
 *
 * Espera `/panel/**` y no una ruta concreta: adónde aterriza el piloto es el
 * primer ítem visible de su menú, y eso cambió dos veces. Hasta la tarea 62
 * era el dashboard; entre la 62 y la 67, Financiero > Devengos (el piloto no
 * tenía `seguridad.dashboard.ver`); desde la 67 vuelve a ser el tablero, que
 * ahora se compone por rol y para él son sus sesiones, sus equipos y su
 * liquidación. El helper no debería volver a romperse por esto: quien
 * necesita una pantalla puntual navega a ella.
 */
export async function elegirRolPiloto(page: Page): Promise<void> {
    await page.locator(`[data-ag-role-nombre="${NOMBRE_ROL_PILOTO}"]`).click();
    await Promise.all([
        page.waitForURL('**/panel/**'),
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

    // El click dispara :focus-visible en el botón (aro de foco) — sin este
    // blur, la captura queda atada a si el test necesitó tocar el toggle o
    // no (que a su vez depende de un `fetch` fire-and-forget de una corrida
    // anterior terminando a tiempo), y por eso `rendiciones-show` salía
    // intermitente en la suite completa aunque en aislamiento siempre pasaba.
    await page.evaluate(() => (document.activeElement as HTMLElement | null)?.blur());
}

/** Fuentes @fontsource cargadas antes de capturar — si no, el primer render usa la fuente de sistema y la captura es inestable entre corridas. */
export async function esperarFuentes(page: Page): Promise<void> {
    await page.evaluate(() => document.fonts.ready);
}

/**
 * Login real al portal del cliente (no bypass de sesión): username + password
 * contra el guard `cliente`, igual que un cliente de verdad. Redirige a
 * /portal/avance tras login exitoso (no requiere selección de rol — las
 * cuentas de portal no tienen roles).
 */
export async function iniciarSesionPortal(page: Page, username = USUARIO_PORTAL_DEMO, password = PASSWORD_PORTAL_DEMO): Promise<void> {
    await page.goto('/portal/login');
    await page.fill('input[name="username"]', username);
    await page.fill('input[name="password"]', password);

    // Espera a que el fetch POST termine y el JavaScript redirija a /portal/avance
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('.ag-login-form__form button[type="submit"]'),
    ]);
}

/**
 * Fecha con la que se capturan los formularios cuyo `<input type="date">`
 * llega del servidor rellenado con "hoy" (`old('fecha', now()->toDateString())`
 * en los `create` de anticipos, combustible, gastos y rendiciones). Es la
 * misma fecha que usan los fixtures de `tests/Visual/fixtures/`.
 */
export const FECHA_FIJA = '2026-09-18';

/**
 * Reemplaza por `FECHA_FIJA` el valor de todo `<input type="date">` que ya
 * venga con valor. Sin esto, la captura de esos formularios queda atada al
 * día calendario en que se generó la referencia y se desactualiza sola al
 * día siguiente (pasó con las cuatro `*-create` de Finanzas).
 *
 * No sirve `page.clock`: el valor lo pone PHP en el servidor, no el
 * navegador. Tampoco un `mask`: taparía el widget entero y dejaría de
 * verificarse cómo se renderiza con un valor cargado. Se asigna por DOM (no
 * `fill`) para no dejar foco ni `:focus-visible` en el campo. Los inputs
 * vacíos se dejan como están: su placeholder no depende del día.
 */
export async function fijarFechasDeHoy(page: Page): Promise<void> {
    await page.evaluate((fecha) => {
        document.querySelectorAll<HTMLInputElement>('input[type="date"]').forEach((input) => {
            if (input.value !== '') {
                input.value = fecha;
            }
        });
    }, FECHA_FIJA);
}
