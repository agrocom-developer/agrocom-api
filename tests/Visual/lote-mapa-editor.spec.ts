import { test, expect, type Page } from '@playwright/test';
import { elegirRolDueno, iniciarSesion } from './helpers';

/**
 * Tarea 79 (HU-56): editor de perímetro del lote — pantalla completa, barra
 * de acciones propia y superficie en vivo. No es regresión visual (no toma
 * capturas, por eso no tiene `-snapshots`): son interacciones reales sobre
 * Leaflet-Geoman que un test de Pest no puede ejercitar (HTTP, sin DOM).
 *
 * `bin/verify` saltea IGUAL este archivo en esta máquina (no hay capturas
 * `-darwin`, ver memoria "capturas visuales son win32" — la condición mira
 * la carpeta entera, no una prueba puntual). Corre a mano con
 * `npx playwright test tests/Visual/lote-mapa-editor.spec.ts`.
 *
 * Viewport alto a propósito: el formulario de campo es largo (cliente,
 * nombre, ubicación, y recién después el mapa) y `page.mouse.click(x, y)`
 * usa coordenadas de VIEWPORT — si el lienzo queda fuera de vista,
 * `elementFromPoint` en esas coordenadas da `null` y los clicks no le
 * llegan al mapa. Alto de sobra evita depender de un scroll intermedio.
 */
test.describe('editor de perímetro del lote', () => {
    test.use({ viewport: { width: 1280, height: 2000 } });

    test.beforeEach(async ({ page }) => {
        await iniciarSesion(page);
        await elegirRolDueno(page);
        await page.goto('/panel/campos/crear');
        await page.locator('.leaflet-container').first().waitFor();
    });

    /** Centro del lienzo del primer lote, en coordenadas de viewport. */
    async function centroDelLienzo(page: Page): Promise<{ cx: number; cy: number }> {
        const lienzo = page.locator('[data-ag-lote-mapa-lienzo]').first();
        const caja = await lienzo.boundingBox();

        if (!caja) {
            throw new Error('El lienzo del mapa no tiene tamaño — Leaflet no llegó a inicializar.');
        }

        return { cx: caja.x + caja.width / 2, cy: caja.y + caja.height / 2 };
    }

    /** Dibuja un rectángulo de 4 vértices sobre el lienzo del primer lote,
     *  cerrando el trazo con un segundo click sobre el primer vértice —
     *  Geoman termina el trazo al clickear el marcador del primer punto. */
    async function dibujarPoligono(page: Page): Promise<void> {
        const { cx, cy } = await centroDelLienzo(page);

        const puntos: [number, number][] = [
            [cx - 70, cy - 50],
            [cx + 70, cy - 50],
            [cx + 70, cy + 50],
            [cx - 70, cy + 50],
        ];

        await page.locator('[data-ag-lote-accion="dibujar"]').first().click();

        for (const [x, y] of puntos) {
            await page.mouse.click(x, y);
        }

        await page.mouse.click(puntos[0][0], puntos[0][1]);

        await expect(page.locator('[data-ag-lote-accion="dibujar"]').first()).toHaveAttribute('aria-pressed', 'false');
    }

    test('dibuja un polígono con superficie en vivo y lo deja en el input oculto', async ({ page }) => {
        const botonDibujar = page.locator('[data-ag-lote-accion="dibujar"]').first();
        const medida = page.locator('[data-ag-lote-medida]').first();
        const input = page.locator('[data-ag-lote-geometria]').first();

        await expect(input).toHaveValue('');

        const { cx, cy } = await centroDelLienzo(page);

        await botonDibujar.click();
        await expect(botonDibujar).toHaveAttribute('aria-pressed', 'true');

        // Superficie en vivo: aparece ANTES de cerrar el polígono, con solo
        // 3 vértices puestos (la ayuda de precisión de la tarea 79).
        await page.mouse.click(cx - 70, cy - 50);
        await page.mouse.click(cx + 70, cy - 50);
        await page.mouse.click(cx + 70, cy + 50);
        await expect(medida).toBeVisible();
        await expect(medida).toContainText('ha dibujadas');

        // Cierra sobre el primer vértice.
        await page.mouse.click(cx - 70, cy - 50);

        await expect(botonDibujar).toHaveAttribute('aria-pressed', 'false');

        const valor = await input.inputValue();
        const geometria = JSON.parse(valor);

        expect(geometria.type).toBe('Polygon');
        expect(geometria.coordinates[0].length).toBeGreaterThanOrEqual(4);
        // Anillo cerrado: primer y último punto iguales.
        expect(geometria.coordinates[0][0]).toEqual(geometria.coordinates[0][geometria.coordinates[0].length - 1]);

        await expect(page.locator('[data-ag-lote-accion="deshacer"]').first()).toBeEnabled();
    });

    test('los modos de la barra son mutuamente excluyentes', async ({ page }) => {
        await dibujarPoligono(page);

        const botonEditar = page.locator('[data-ag-lote-accion="editar"]').first();
        const botonMover = page.locator('[data-ag-lote-accion="mover"]').first();
        const botonBorrar = page.locator('[data-ag-lote-accion="borrar"]').first();

        await botonEditar.click();
        await expect(botonEditar).toHaveAttribute('aria-pressed', 'true');
        await expect(botonMover).toHaveAttribute('aria-pressed', 'false');

        await botonMover.click();
        await expect(botonMover).toHaveAttribute('aria-pressed', 'true');
        await expect(botonEditar).toHaveAttribute('aria-pressed', 'false');

        // Clickear el mismo botón de nuevo lo apaga.
        await botonMover.click();
        await expect(botonMover).toHaveAttribute('aria-pressed', 'false');
        await expect(botonBorrar).toHaveAttribute('aria-pressed', 'false');
    });

    test('deshacer vuelve al estado anterior del polígono', async ({ page }) => {
        await dibujarPoligono(page);

        const input = page.locator('[data-ag-lote-geometria]').first();
        const valorDibujado = await input.inputValue();
        expect(valorDibujado).not.toBe('');

        await page.locator('[data-ag-lote-accion="deshacer"]').first().click();

        await expect(input).toHaveValue('');
        await expect(page.locator('[data-ag-lote-medida]').first()).toBeHidden();
    });

    test('pantalla completa (con la Fullscreen API negada) entra por botón, sale por Escape, y conserva el polígono', async ({ page }) => {
        // Simula un navegador que niega el pedido, para ejercitar el
        // respaldo determinísticamente en vez de depender de si el
        // Chromium headless de esta corrida soporta la Fullscreen API real.
        await page.addInitScript(() => {
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            (Element.prototype as any).requestFullscreen = () => Promise.reject(new DOMException('denegado por el test'));
        });
        await page.goto('/panel/campos/crear');
        await page.locator('.leaflet-container').first().waitFor();

        await dibujarPoligono(page);

        const input = page.locator('[data-ag-lote-geometria]').first();
        const valorAntes = await input.inputValue();
        expect(valorAntes).not.toBe('');

        const marco = page.locator('[data-ag-lote-mapa-marco]').first();
        const boton = page.locator('[data-ag-lote-mapa-boton-pantalla-completa]').first();

        await boton.click();
        await expect(boton).toHaveAttribute('aria-pressed', 'true');
        await expect(marco).toHaveClass(/ag-mapa--pantalla-completa-respaldo/);

        // El polígono sigue siendo el mismo mientras se está en pantalla
        // completa — no se recrea el mapa, solo cambia el tamaño.
        expect(await input.inputValue()).toBe(valorAntes);

        await page.keyboard.press('Escape');

        await expect(boton).toHaveAttribute('aria-pressed', 'false');
        await expect(marco).not.toHaveClass(/ag-mapa--pantalla-completa-respaldo/);
        expect(await input.inputValue()).toBe(valorAntes);
    });
});
