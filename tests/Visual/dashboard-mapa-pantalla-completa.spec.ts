import { test, expect } from '@playwright/test';
import { elegirRolDueno, iniciarSesion } from './helpers';

/**
 * Tarea 79 (HU-56), etapa 3: pantalla completa del mapa operativo del
 * tablero — mismo mecanismo que el editor de perímetro del lote
 * (`shared/mapa-pantalla-completa.js`), sin la barra de siete acciones: este
 * mapa es de solo lectura. No es regresión visual (no toma capturas, no
 * tiene `-snapshots`) — `dashboard.spec.ts` ya cubre el tab "Resumen"
 * (activo por defecto); el tab "Mapa" no se captura ahí ni acá.
 *
 * `bin/verify` saltea este archivo igual que el resto de `tests/Visual/` en
 * esta máquina (memoria "capturas visuales son win32"). Corre a mano con
 * `npx playwright test tests/Visual/dashboard-mapa-pantalla-completa.spec.ts`.
 */
test.describe('mapa operativo del tablero: pantalla completa', () => {
    test.beforeEach(async ({ page }) => {
        await iniciarSesion(page);
        await elegirRolDueno(page);
        await page.goto('/panel/dashboard');
        await page.locator('[data-bs-target="#ag-tab-mapa"]').click();
        await page.locator('#ag-tab-mapa .leaflet-container').first().waitFor();
    });

    test('entra por botón, sale por Escape (respaldo forzado), y el mapa sigue ahí', async ({ page }) => {
        // Mismo criterio que el test de la etapa 1: se fuerza el respaldo en
        // vez de depender de si el Chromium headless de la corrida soporta
        // la Fullscreen API real.
        // El respaldo ya quedó activo para navegaciones futuras porque
        // `addInitScript` se registra ANTES de la navegación siguiente —
        // acá hace falta recargar para que aplique.
        await page.addInitScript(() => {
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            (Element.prototype as any).requestFullscreen = () => Promise.reject(new DOMException('denegado por el test'));
        });
        await page.reload();
        await page.locator('[data-bs-target="#ag-tab-mapa"]').click();
        await page.locator('#ag-tab-mapa .leaflet-container').first().waitFor();

        const marco = page.locator('#ag-tab-mapa [data-ag-mapa-marco]').first();
        const boton = page.locator('#ag-tab-mapa [data-ag-mapa-boton-pantalla-completa]').first();

        await expect(boton).toHaveAttribute('aria-pressed', 'false');

        await boton.click();
        await expect(boton).toHaveAttribute('aria-pressed', 'true');
        await expect(marco).toHaveClass(/ag-mapa--pantalla-completa-respaldo/);

        // El mapa (y su leyenda) siguen en el DOM, visibles, dentro del marco.
        await expect(page.locator('#ag-tab-mapa .leaflet-container').first()).toBeVisible();
        await expect(page.locator('#ag-tab-mapa .ag-mapa-operativo__leyenda').first()).toBeVisible();

        await page.keyboard.press('Escape');

        await expect(boton).toHaveAttribute('aria-pressed', 'false');
        await expect(marco).not.toHaveClass(/ag-mapa--pantalla-completa-respaldo/);
    });

    test('la llave/proveedor de Google no tiene nada que ver acá: sigue siendo Leaflet', async ({ page }) => {
        // El mapa operativo nunca ofreció Google Maps (alcance de la tarea
        // 79: "donde tenga sentido" — un mapa de solo lectura no lo necesita).
        await expect(page.locator('#ag-tab-mapa .leaflet-container').first()).toBeVisible();
        await expect(page.locator('#ag-tab-mapa [data-ag-mapa-proveedor]')).toHaveCount(0);
    });
});
