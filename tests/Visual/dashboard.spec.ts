import { test, expect, type Page } from '@playwright/test';
import { asegurarTema, elegirRolDueno, esperarFuentes, iniciarSesion } from './helpers';

/**
 * GET /panel/dashboard, con "Dueño" como rol activo (uno de los tres roles
 * vivos de carlos.ferrufino — cualquiera sirve para esta pasada, se elige este
 * por ser el de mayor alcance).
 *
 * Zona genuinamente dinámica de esta vista: los 3 gráficos ApexCharts
 * (donut/area/radialBar, pestaña "Resumen") no son una animación CSS —
 * `toHaveScreenshot({ animations: 'disabled' })` no los alcanza. Además de
 * animar su entrada al montarse, ApexCharts vuelve a dibujar el SVG entero
 * si el layout se reacomoda después de montado (p. ej. cuando terminan de
 * cargar las fuentes @fontsource y el ancho de la card cambia un par de
 * píxeles) — por eso `esperarFuentes` corre ANTES de esperar los gráficos,
 * nunca después: si el reflow por fuentes llega DESPUÉS del settle de los
 * gráficos, dispara un redibujado que la captura alcanza a mitad de camino
 * (esto se reprodujo real: `npx playwright test` sin `--update-snapshots`
 * dio ~3% de píxeles distintos en `dashboard-light` con el orden invertido,
 * ver runs/07.md).
 */
const ESPERA_ANIMACION_CHARTS_MS = 1500;

async function esperarGraficosListos(page: Page): Promise<void> {
    await page.locator('[data-ag-chart] svg').first().waitFor();
    await page.waitForTimeout(ESPERA_ANIMACION_CHARTS_MS);
}

test.describe('dashboard', () => {
    test.beforeEach(async ({ page }) => {
        await iniciarSesion(page);
        await elegirRolDueno(page);
    });

    test('claro', async ({ page }) => {
        await asegurarTema(page, 'light');
        await esperarFuentes(page);
        await esperarGraficosListos(page);

        await expect(page).toHaveScreenshot('dashboard-light.png', {
            fullPage: true,
            mask: [page.locator('.ag-panel__footer span').first()],
        });
    });

    test('oscuro', async ({ page }) => {
        await asegurarTema(page, 'dark');
        await esperarFuentes(page);
        await esperarGraficosListos(page);

        await expect(page).toHaveScreenshot('dashboard-dark.png', {
            fullPage: true,
            mask: [page.locator('.ag-panel__footer span').first()],
        });
    });
});
