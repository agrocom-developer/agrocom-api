import { test, expect, type Page } from '@playwright/test';
import { fileURLToPath } from 'node:url';

/**
 * Accesibilidad del átomo `select` (tarea 76 / HU-53) — criterio de
 * aceptación: "se abre, se navega y se elige solo con teclado, y expone
 * role/aria-expanded/aria-activedescendant". Corre sobre un arnés estático
 * (tests/Visual/fixtures/select-atom.html), no sobre una pantalla del
 * panel: en la etapa 1 el átomo todavía no está migrado a ninguna pantalla
 * real (eso es la etapa 4) y `resources/js/atoms/select.js` no tiene
 * imports, así que corre standalone sin Vite/Docker/login.
 *
 * `bin/verify` saltea Playwright en esta plataforma mientras no haya
 * capturas `-darwin.png` en el árbol (ver skill `verificacion`) — este spec
 * no toma capturas, así que ese salteo NO lo cubre; se corrió a mano con
 * `npx playwright test tests/Visual/select-atom.spec.ts` antes de cerrar la
 * etapa.
 */
const FIXTURE = fileURLToPath(new URL('./fixtures/select-atom.html', import.meta.url));

async function irAlArnes(page: Page) {
    await page.goto(`file://${FIXTURE}`);
}

test.describe('select — solo 8 o menos opciones (type-ahead, sin caja de búsqueda)', () => {
    test('se abre, se navega y se elige enteramente con teclado', async ({ page }) => {
        await irAlArnes(page);

        const trigger = page.locator('[data-testid="select-rol"] [data-ag-select-trigger]');
        const listbox = page.locator('#rol-select-listbox');

        await expect(trigger).toHaveAttribute('role', 'combobox');
        await expect(trigger).toHaveAttribute('aria-haspopup', 'listbox');
        await expect(trigger).toHaveAttribute('aria-expanded', 'false');
        await expect(trigger).not.toHaveAttribute('aria-activedescendant', /.+/);
        await expect(listbox).toBeHidden();

        // Nada de mouse: foco por teclado (Tab llega al único elemento
        // enfocable de la página) y todo lo demás por teclas.
        await page.keyboard.press('Tab');
        await expect(trigger).toBeFocused();

        await page.keyboard.press('ArrowDown');
        await expect(trigger).toHaveAttribute('aria-expanded', 'true');
        await expect(listbox).toBeVisible();

        const activaId = await trigger.getAttribute('aria-activedescendant');
        expect(activaId).toBeTruthy();
        await expect(page.locator(`#${activaId}`)).toHaveText('Administrador');
        await expect(page.locator(`#${activaId}`)).toHaveClass(/ag-select__option--active/);

        await page.keyboard.press('ArrowDown');
        const segundaActivaId = await trigger.getAttribute('aria-activedescendant');
        expect(segundaActivaId).not.toBe(activaId);
        await expect(page.locator(`#${segundaActivaId}`)).toHaveText('Piloto');

        // Type-ahead: "c" salta directo a "Cliente" sin caja de búsqueda visible.
        await page.keyboard.press('c');
        const idPorTypeahead = await trigger.getAttribute('aria-activedescendant');
        await expect(page.locator(`#${idPorTypeahead}`)).toHaveText('Cliente');
        await expect(page.locator('.ag-select__search-row')).toHaveCount(0);

        await page.keyboard.press('Enter');
        await expect(trigger).toHaveAttribute('aria-expanded', 'false');
        await expect(listbox).toBeHidden();
        await expect(trigger).not.toHaveAttribute('aria-activedescendant', /.+/);
        await expect(trigger.locator('[data-ag-select-value]')).toHaveText('Cliente');
        await expect(trigger).toBeFocused();

        const cambios = await page.evaluate(() => (window as unknown as { __cambios: unknown[] }).__cambios);
        expect(cambios).toEqual([{ id: 'rol-select', valor: 'cliente' }]);
    });

    test('Escape cierra sin cambiar la selección', async ({ page }) => {
        await irAlArnes(page);
        const trigger = page.locator('[data-testid="select-rol"] [data-ag-select-trigger]');

        await trigger.focus();
        await page.keyboard.press('ArrowDown');
        await page.keyboard.press('ArrowDown');
        await page.keyboard.press('Escape');

        await expect(trigger).toHaveAttribute('aria-expanded', 'false');
        await expect(trigger.locator('[data-ag-select-value]')).toHaveText('Elegí un rol');
        await expect(trigger.locator('[data-ag-select-value]')).toHaveClass(/ag-select__value--placeholder/);
    });

    test('Backspace con el combobox cerrado limpia la selección (no requerido)', async ({ page }) => {
        await irAlArnes(page);
        const trigger = page.locator('[data-testid="select-rol"] [data-ag-select-trigger]');

        await trigger.focus();
        await page.keyboard.press('ArrowDown');
        await page.keyboard.press('Enter');
        await expect(trigger.locator('[data-ag-select-value]')).toHaveText('Administrador');

        await page.keyboard.press('Backspace');
        await expect(trigger.locator('[data-ag-select-value]')).toHaveText('Elegí un rol');
    });
});

test.describe('select — más de 8 opciones (búsqueda dentro del desplegable)', () => {
    test('el valor preseleccionado se muestra sin abrir', async ({ page }) => {
        await irAlArnes(page);
        const trigger = page.locator('[data-testid="select-lote"] [data-ag-select-trigger]');

        await expect(trigger.locator('[data-ag-select-value]')).toHaveText('Lote 3');
        await expect(trigger.locator('[data-ag-select-value]')).not.toHaveClass(/ag-select__value--placeholder/);
    });

    test('filtra por substring y expone la fila de búsqueda dentro del listbox', async ({ page }) => {
        await irAlArnes(page);
        const trigger = page.locator('[data-testid="select-lote"] [data-ag-select-trigger]');
        const listbox = page.locator('#lote-select-listbox');

        await trigger.focus();
        await page.keyboard.press('ArrowDown');
        await expect(listbox.locator('[role="option"]')).toHaveCount(10);

        await page.keyboard.press('7');
        await expect(listbox.locator('[role="option"]')).toHaveCount(1);
        await expect(listbox.locator('[role="option"]')).toHaveText('Lote 7');
        await expect(page.locator('.ag-select__search-row span:not(.ag-icon)')).toHaveText('7');

        await page.keyboard.press('Enter');
        await expect(trigger.locator('[data-ag-select-value]')).toHaveText('Lote 7');

        const cambios = await page.evaluate(() => (window as unknown as { __cambios: unknown[] }).__cambios);
        expect(cambios).toEqual([{ id: 'lote-select', valor: 'lote-7' }]);
    });

    test('sin coincidencias muestra el estado vacío y no deja aria-activedescendant colgado', async ({ page }) => {
        await irAlArnes(page);
        const trigger = page.locator('[data-testid="select-lote"] [data-ag-select-trigger]');
        const listbox = page.locator('#lote-select-listbox');

        await trigger.focus();
        await page.keyboard.press('ArrowDown');
        for (const tecla of 'zzz') {
            await page.keyboard.press(tecla);
        }

        await expect(listbox.locator('[role="option"]')).toHaveCount(0);
        await expect(listbox.locator('.ag-select__empty')).toHaveText('Sin resultados');
        await expect(trigger).not.toHaveAttribute('aria-activedescendant', /.+/);
    });

    test('Backspace dentro de la búsqueda borra un carácter y reamplía el filtro', async ({ page }) => {
        await irAlArnes(page);
        const trigger = page.locator('[data-testid="select-lote"] [data-ag-select-trigger]');
        const listbox = page.locator('#lote-select-listbox');

        await trigger.focus();
        await page.keyboard.press('ArrowDown');
        for (const tecla of 'lote 1') {
            await page.keyboard.press(tecla === ' ' ? 'Space' : tecla);
        }
        await expect(listbox.locator('[role="option"]')).toHaveCount(2); // Lote 1, Lote 10

        await page.keyboard.press('Backspace');
        await expect(listbox.locator('[role="option"]')).toHaveCount(10);
    });
});

test.describe('select — degradación sin JS', () => {
    test('el <select> nativo queda visible y usable si select.js no carga', async ({ page }) => {
        await page.route('**/select.js', (route) => route.fulfill({
            status: 200,
            contentType: 'application/javascript',
            body: '// select.js bloqueado a propósito por este test',
        }));

        await irAlArnes(page);

        const nativo = page.locator('#rol-select');
        const trigger = page.locator('[data-testid="select-rol"] [data-ag-select-trigger]');

        await expect(trigger).toBeHidden();
        await expect(nativo).toBeVisible();

        await nativo.selectOption('piloto');
        await expect(nativo).toHaveValue('piloto');
    });
});
