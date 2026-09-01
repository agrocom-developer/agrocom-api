import { defineConfig, devices } from '@playwright/test';

/**
 * Regresión visual del panel (tarea 07). Corre en el HOST, nunca dentro del
 * contenedor `app` (que no tiene Node ni navegadores) — `bin/verify` no la
 * invoca a propósito, ver runs/07.md.
 *
 * Un solo navegador (Chromium) alcanza para esta primera pasada — no
 * multiplicar la matriz sin necesidad.
 *
 * `workers: 1`: las tres vistas comparten el mismo usuario demo
 * (`camila.rojas`) y su preferencia de tema persiste en Postgres
 * (`sec_user_preferencia.tema`, compartida entre tests) — correr en
 * paralelo arriesgaría una carrera entre dos tests pisándose esa fila.
 *
 * `reducedMotion: 'reduce'`: la galería de fondo de login/seleccionar-rol
 * (`auth-layout.js`) auto-avanza cada 6s con un `setInterval` — no una
 * animación CSS, así que `expect.toHaveScreenshot({ animations: 'disabled'
 * })` no la alcanza. El propio componente ya apaga ese timer bajo
 * `prefers-reduced-motion: reduce` (comportamiento existente, no algo que
 * este cambio le agregó), así que emular esa preferencia dejá la primera
 * imagen fija sin tocar CSS/Blade.
 */
export default defineConfig({
    testDir: 'tests/Visual',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    reporter: [['list']],
    globalSetup: './tests/Visual/global-setup.ts',
    use: {
        baseURL: 'http://localhost:8000',
        reducedMotion: 'reduce',
        trace: 'retain-on-failure',
    },
    expect: {
        toHaveScreenshot: { animations: 'disabled' },
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
