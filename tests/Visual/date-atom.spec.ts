import { test, expect, type Page } from '@playwright/test';
import { fileURLToPath } from 'node:url';

/**
 * Accesibilidad del átomo `date` (tarea 76 / HU-53, etapa 2) — criterio de
 * aceptación: navegación por teclado del calendario (patrón WAI-ARIA APG
 * "Date Picker Dialog": diálogo modal + grilla con foco itinerante). Corre
 * sobre un arnés estático (tests/Visual/fixtures/date-atom.html), no sobre
 * una pantalla del panel: en esta etapa el átomo todavía no está migrado a
 * ninguna pantalla real (eso es la etapa 4) y resources/js/atoms/date.js no
 * tiene imports, así que corre standalone sin Vite/Docker/login.
 *
 * `bin/verify` saltea Playwright en esta plataforma mientras no haya
 * capturas `-darwin.png` en el árbol (ver skill `verificacion`) — este spec
 * no toma capturas, así que ese salteo NO lo cubre; se corrió a mano con
 * `npx playwright test tests/Visual/date-atom.spec.ts` antes de cerrar la
 * etapa.
 */
const FIXTURE = fileURLToPath(new URL('./fixtures/date-atom.html', import.meta.url));

async function irAlArnes(page: Page) {
    await page.goto(`file://${FIXTURE}`);
}

test.describe('date — flujo general (sin min/max, valor inicial 2026-09-15)', () => {
    test('ArrowDown sobre el disparador abre el diálogo en el mes del valor, con foco real en esa celda', async ({ page }) => {
        await irAlArnes(page);
        const widget = page.locator('[data-testid="date-inicio"]');
        const trigger = widget.locator('[data-ag-date-trigger]');
        const dialogo = widget.locator('[data-ag-date-dialog]');

        await expect(trigger).toHaveAttribute('aria-expanded', 'false');
        await expect(dialogo).toBeHidden();

        await trigger.focus();
        await page.keyboard.press('ArrowDown');

        await expect(trigger).toHaveAttribute('aria-expanded', 'true');
        await expect(dialogo).toBeVisible();
        await expect(widget.locator('[data-ag-date-heading]')).toHaveText('Septiembre 2026');

        const celda = widget.locator('[data-fecha="2026-09-15"]');
        await expect(celda).toBeFocused();
        await expect(celda).toHaveAttribute('tabindex', '0');
    });

    test('ArrowRight/ArrowLeft mueven el foco DOM real un día (no aria-activedescendant)', async ({ page }) => {
        await irAlArnes(page);
        const widget = page.locator('[data-testid="date-inicio"]');
        await widget.locator('[data-ag-date-trigger]').focus();
        await page.keyboard.press('ArrowDown');

        await page.keyboard.press('ArrowRight');
        await expect(widget.locator('[data-fecha="2026-09-16"]')).toBeFocused();
        await expect(widget.locator('[data-fecha="2026-09-16"]')).toHaveAttribute('tabindex', '0');
        await expect(widget.locator('[data-fecha="2026-09-15"]')).toHaveAttribute('tabindex', '-1');

        await page.keyboard.press('ArrowLeft');
        await page.keyboard.press('ArrowLeft');
        await expect(widget.locator('[data-fecha="2026-09-14"]')).toBeFocused();

        await expect(widget.locator('[data-ag-date-trigger]')).not.toHaveAttribute('aria-activedescendant', /.+/);
    });

    test('ArrowDown/ArrowUp mueven 7 días y cruzan de mes repintando la grilla', async ({ page }) => {
        await irAlArnes(page);
        const widget = page.locator('[data-testid="date-inicio"]');
        await widget.locator('[data-ag-date-trigger]').focus();
        await page.keyboard.press('ArrowDown'); // abre en 15/09

        await page.keyboard.press('ArrowDown'); // 22/09
        await page.keyboard.press('ArrowDown'); // 29/09
        await expect(widget.locator('[data-fecha="2026-09-29"]')).toBeFocused();

        await page.keyboard.press('ArrowDown'); // +7 cruza a octubre: 06/10
        await expect(widget.locator('[data-ag-date-heading]')).toHaveText('Octubre 2026');
        await expect(widget.locator('[data-fecha="2026-10-06"]')).toBeFocused();

        await page.keyboard.press('ArrowUp');
        await expect(widget.locator('[data-ag-date-heading]')).toHaveText('Septiembre 2026');
        await expect(widget.locator('[data-fecha="2026-09-29"]')).toBeFocused();
    });

    test('Home/End mueven al lunes/domingo de la semana enfocada', async ({ page }) => {
        await irAlArnes(page);
        const widget = page.locator('[data-testid="date-inicio"]');
        await widget.locator('[data-ag-date-trigger]').focus();
        await page.keyboard.press('ArrowDown'); // 15/09, martes

        await page.keyboard.press('Home');
        await expect(widget.locator('[data-fecha="2026-09-14"]')).toBeFocused();

        await page.keyboard.press('End');
        await expect(widget.locator('[data-fecha="2026-09-20"]')).toBeFocused();
    });

    test('PageDown/PageUp cambian de mes; Shift+PageDown/PageUp cambian de año', async ({ page }) => {
        await irAlArnes(page);
        const widget = page.locator('[data-testid="date-inicio"]');
        await widget.locator('[data-ag-date-trigger]').focus();
        await page.keyboard.press('ArrowDown'); // 15/09/2026

        await page.keyboard.press('PageDown');
        await expect(widget.locator('[data-ag-date-heading]')).toHaveText('Octubre 2026');
        await expect(widget.locator('[data-fecha="2026-10-15"]')).toBeFocused();

        await page.keyboard.press('PageUp');
        await expect(widget.locator('[data-ag-date-heading]')).toHaveText('Septiembre 2026');
        await expect(widget.locator('[data-fecha="2026-09-15"]')).toBeFocused();

        await page.keyboard.press('Shift+PageDown');
        await expect(widget.locator('[data-ag-date-heading]')).toHaveText('Septiembre 2027');
        await expect(widget.locator('[data-fecha="2027-09-15"]')).toBeFocused();

        await page.keyboard.press('Shift+PageUp');
        await expect(widget.locator('[data-ag-date-heading]')).toHaveText('Septiembre 2026');
        await expect(widget.locator('[data-fecha="2026-09-15"]')).toBeFocused();
    });

    test('Enter selecciona el día enfocado, cierra el diálogo, dispara change y devuelve el foco al disparador', async ({ page }) => {
        await irAlArnes(page);
        const widget = page.locator('[data-testid="date-inicio"]');
        const trigger = widget.locator('[data-ag-date-trigger]');
        await trigger.focus();
        await page.keyboard.press('ArrowDown'); // 15/09
        await page.keyboard.press('ArrowRight'); // 16/09

        await page.keyboard.press('Enter');

        await expect(trigger).toHaveAttribute('aria-expanded', 'false');
        await expect(widget.locator('[data-ag-date-dialog]')).toBeHidden();
        await expect(trigger).toBeFocused();
        await expect(widget.locator('[data-ag-date-value]')).toHaveText('16/09/2026');

        const cambios = await page.evaluate(() => (window as unknown as { __cambios: unknown[] }).__cambios);
        expect(cambios).toEqual([{ id: 'fecha-inicio', valor: '2026-09-16' }]);
    });

    test('Escape cierra sin cambiar la selección y devuelve el foco al disparador', async ({ page }) => {
        await irAlArnes(page);
        const widget = page.locator('[data-testid="date-inicio"]');
        const trigger = widget.locator('[data-ag-date-trigger]');
        await trigger.focus();
        await page.keyboard.press('ArrowDown');
        await page.keyboard.press('ArrowRight');
        await page.keyboard.press('ArrowRight');

        await page.keyboard.press('Escape');

        await expect(trigger).toHaveAttribute('aria-expanded', 'false');
        await expect(widget.locator('[data-ag-date-dialog]')).toBeHidden();
        await expect(trigger).toBeFocused();
        await expect(widget.locator('[data-ag-date-value]')).toHaveText('15/09/2026');
    });

    test('Tab dentro del diálogo atrapa el foco: cicla entre el primer y el último elemento', async ({ page }) => {
        await irAlArnes(page);
        const widget = page.locator('[data-testid="date-inicio"]');
        await widget.locator('[data-ag-date-trigger]').focus();
        await page.keyboard.press('ArrowDown');

        const hoyBtn = widget.locator('[data-ag-date-today]');
        await hoyBtn.focus();
        await page.keyboard.press('Tab');
        await expect(widget.locator('[data-ag-date-prev-month]')).toBeFocused();

        await page.keyboard.press('Shift+Tab');
        await expect(hoyBtn).toBeFocused();
    });

    test('Backspace con el diálogo cerrado limpia la selección (no requerido)', async ({ page }) => {
        await irAlArnes(page);
        const widget = page.locator('[data-testid="date-inicio"]');
        const trigger = widget.locator('[data-ag-date-trigger]');
        await trigger.focus();

        await expect(widget.locator('[data-ag-date-value]')).toHaveText('15/09/2026');
        await page.keyboard.press('Backspace');
        await expect(widget.locator('[data-ag-date-value]')).toHaveText('Elegí una fecha');
        await expect(widget.locator('.ag-date__native')).toHaveValue('');
    });
});

test.describe('date — rango min/max (2026-09-08..2026-09-16, valor inicial 2026-09-10)', () => {
    test('los días fuera de rango se pintan deshabilitados', async ({ page }) => {
        await irAlArnes(page);
        const widget = page.locator('[data-testid="date-rango"]');
        await widget.locator('[data-ag-date-trigger]').focus();
        await page.keyboard.press('ArrowDown');

        await expect(widget.locator('[data-fecha="2026-09-07"]')).toBeDisabled();
        await expect(widget.locator('[data-fecha="2026-09-08"]')).toBeEnabled();
        await expect(widget.locator('[data-fecha="2026-09-16"]')).toBeEnabled();
        await expect(widget.locator('[data-fecha="2026-09-17"]')).toBeDisabled();
    });

    test('la navegación se clampa en los bordes del rango, no los cruza', async ({ page }) => {
        await irAlArnes(page);
        const widget = page.locator('[data-testid="date-rango"]');
        await widget.locator('[data-ag-date-trigger]').focus();
        await page.keyboard.press('ArrowDown'); // 10/09

        for (let i = 0; i < 5; i += 1) {
            await page.keyboard.press('ArrowLeft');
        }
        await expect(widget.locator('[data-fecha="2026-09-08"]')).toBeFocused();

        for (let i = 0; i < 10; i += 1) {
            await page.keyboard.press('ArrowRight');
        }
        await expect(widget.locator('[data-fecha="2026-09-16"]')).toBeFocused();
    });
});

test.describe('date — degradación sin JS', () => {
    test('el <input type="date"> nativo queda visible y usable si date.js no carga', async ({ page }) => {
        await page.route('**/date.js', (route) => route.fulfill({
            status: 200,
            contentType: 'application/javascript',
            body: '// date.js bloqueado a propósito por este test',
        }));

        await irAlArnes(page);

        const widget = page.locator('[data-testid="date-inicio"]');
        const nativo = widget.locator('.ag-date__native');
        const trigger = widget.locator('[data-ag-date-trigger]');

        await expect(trigger).toBeHidden();
        await expect(nativo).toBeVisible();

        await nativo.fill('2026-01-20');
        await expect(nativo).toHaveValue('2026-01-20');
    });
});
