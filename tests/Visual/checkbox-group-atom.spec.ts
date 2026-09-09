import { test, expect, type Page } from '@playwright/test';
import { fileURLToPath } from 'node:url';

/**
 * Comportamiento del átomo `checkbox-group` (tarea 76 / HU-53, etapa 3): a
 * diferencia de `atoms/select`, acá no hay ningún control simulado que
 * probar — las casillas son `<input type="checkbox">` reales y el teclado
 * entre ellas (Tab, Espacio) es nativo del navegador, así que este spec se
 * concentra en lo único que agrega JS propio: el filtro de búsqueda que
 * aparece con más de 8 opciones, y la degradación cuando el script no carga.
 *
 * Corre sobre un arnés estático (tests/Visual/fixtures/checkbox-group-atom.html),
 * igual que select-atom.spec.ts y date-atom.spec.ts. `bin/verify` saltea
 * Playwright en esta plataforma mientras no haya capturas `-darwin.png` en
 * el árbol (ver skill `verificacion`) — este spec no toma capturas, así que
 * ese salteo no lo cubre; se corrió a mano con
 * `npx playwright test tests/Visual/checkbox-group-atom.spec.ts` antes de
 * cerrar la etapa.
 */
const FIXTURE = fileURLToPath(new URL('./fixtures/checkbox-group-atom.html', import.meta.url));

async function irAlArnes(page: Page) {
    await page.goto(`file://${FIXTURE}`);
}

test.describe('checkbox-group — 8 opciones o menos (sin búsqueda)', () => {
    test('no arma ninguna caja de búsqueda y las casillas son usables por teclado', async ({ page }) => {
        await irAlArnes(page);
        const grupo = page.locator('[data-testid="grupo-corto"]');

        await expect(grupo.locator('[data-ag-checkbox-group-search-wrap]')).toHaveCount(0);

        const primera = grupo.locator('#corto-opt-0');
        await primera.focus();
        await page.keyboard.press('Space');
        await expect(primera).toBeChecked();
    });
});

test.describe('checkbox-group — más de 8 opciones (búsqueda dentro del grupo)', () => {
    test('la búsqueda arranca oculta en el marcado y JS la muestra al inicializar', async ({ page }) => {
        await irAlArnes(page);
        const buscador = page.locator('[data-testid="grupo-largo"] [data-ag-checkbox-group-search-wrap]');

        await expect(buscador).toBeVisible();
    });

    test('filtra por substring, sin distinguir mayúsculas ni tildes', async ({ page }) => {
        await irAlArnes(page);
        const grupo = page.locator('[data-testid="grupo-largo"]');
        const input = grupo.locator('[data-ag-checkbox-group-search]');
        const items = grupo.locator('[data-ag-checkbox-group-item]:visible');

        await expect(items).toHaveCount(9);

        await input.fill('bateria');
        await expect(items).toHaveCount(2);
        await expect(items).toContainText(['Batería', 'Batería de respaldo']);
    });

    test('sin coincidencias muestra el estado vacío', async ({ page }) => {
        await irAlArnes(page);
        const grupo = page.locator('[data-testid="grupo-largo"]');
        const input = grupo.locator('[data-ag-checkbox-group-search]');
        const vacio = grupo.locator('[data-ag-checkbox-group-empty]');

        await expect(vacio).toBeHidden();
        await input.fill('zzz');
        await expect(vacio).toBeVisible();
        await expect(grupo.locator('[data-ag-checkbox-group-item]:visible')).toHaveCount(0);
    });

    test('vaciar la búsqueda vuelve a mostrar todas las opciones', async ({ page }) => {
        await irAlArnes(page);
        const grupo = page.locator('[data-testid="grupo-largo"]');
        const input = grupo.locator('[data-ag-checkbox-group-search]');
        const items = grupo.locator('[data-ag-checkbox-group-item]:visible');

        await input.fill('motor');
        await expect(items).toHaveCount(1);

        await input.fill('');
        await expect(items).toHaveCount(9);
    });

    test('marcar una casilla filtrada sigue funcionando (el control sigue siendo real)', async ({ page }) => {
        await irAlArnes(page);
        const grupo = page.locator('[data-testid="grupo-largo"]');
        const input = grupo.locator('[data-ag-checkbox-group-search]');

        await input.fill('sensor');
        const casilla = grupo.locator('#largo-opt-5');
        await casilla.check();
        await expect(casilla).toBeChecked();
    });
});

test.describe('checkbox-group — degradación sin JS', () => {
    test('sin JS la búsqueda queda oculta y todas las casillas siguen visibles y usables', async ({ page }) => {
        await page.route('**/checkbox-group.js', (route) => route.fulfill({
            status: 200,
            contentType: 'application/javascript',
            body: '// checkbox-group.js bloqueado a propósito por este test',
        }));

        await irAlArnes(page);
        const grupo = page.locator('[data-testid="grupo-largo"]');

        await expect(grupo.locator('[data-ag-checkbox-group-search-wrap]')).toBeHidden();
        await expect(grupo.locator('[data-ag-checkbox-group-item]')).toHaveCount(9);

        const casilla = grupo.locator('#largo-opt-0');
        await casilla.check();
        await expect(casilla).toBeChecked();
    });
});
