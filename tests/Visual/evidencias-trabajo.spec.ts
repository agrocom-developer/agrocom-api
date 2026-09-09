import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';
import { asegurarTema, elegirRolDueno, esperarFuentes, iniciarSesion } from './helpers';

/**
 * GET /panel/trabajos/{trabajo}/evidencias, con "Dueño" como rol activo
 * (tiene el catálogo completo de permisos, incluido `operaciones.trabajo.ver`
 * que gatea esta pantalla).
 *
 * HU-42 (tarea 56): galería de evidencias de un trabajo — cubre el estado
 * "más interesante" (mismo criterio que `rendiciones-demo.php`): las tres
 * secciones pobladas a la vez (imagen de campo, firma del acta, incidencia
 * con foto), cargadas por `fixtures/evidencias-trabajo-demo.php`. El estado
 * vacío (sin ninguna evidencia) ya está cubierto por
 * `tests/Feature/Operaciones/TrabajosPanelTest.php`, no por esta regresión
 * visual.
 *
 * `panel.evidencias.archivo` intercepted vía `page.route`: el disco `r2` no
 * tiene credenciales reales en este entorno local (`R2_*` vacías,
 * `config/filesystems.php`), y sin ellas `Storage::disk('r2')->exists()` no
 * devuelve `false` — TIRA `League\Flysystem\UnableToCheckFileExistence`
 * (Laravel no lo atrapa). El error 500 resultante es lento de renderizar en
 * este entorno (Whoops/Ignition leyendo decenas de frames de `vendor/` sobre
 * un bind mount de Docker en Windows), suficiente para tumbar el `load` de
 * la página con las tres miniaturas en paralelo. Es una limitación real del
 * entorno local, preexistente y compartida con `panel.trabajos.acta-pdf`/
 * `reporte-pdf` (mismo disco) — nunca expuesta antes porque esas son
 * descargas por click, no un `<img src>` que el navegador pide solo. No es
 * de esta HU arreglarla (tocaría cuatro controladores y la config de
 * filesystems), así que la captura se aísla de esa dependencia externa con
 * un mock de red, igual que se aislaría de cualquier servicio de terceros:
 * la miniatura no es lo que este test verifica, el layout/tokens del grid sí.
 */
const PIXEL_PNG_1X1 = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
    'base64',
);

let idTrabajoDemo: number;

test.beforeAll(() => {
    const salida = execFileSync(
        'docker',
        ['compose', 'exec', '-T', 'app', 'php', '/var/www/html/tests/Visual/fixtures/evidencias-trabajo-demo.php'],
        { encoding: 'utf-8' },
    );
    process.stdout.write(salida);

    const ultimaLinea = salida.trim().split('\n').pop() ?? '{}';
    idTrabajoDemo = (JSON.parse(ultimaLinea) as { trabajo_id: number }).trabajo_id;
});

test.describe('evidencias de trabajo', () => {
    test.describe('galería', () => {
        test.beforeEach(async ({ page }) => {
            await page.route('**/panel/evidencias/*/archivo', (route) => route.fulfill({
                status: 200,
                contentType: 'image/png',
                body: PIXEL_PNG_1X1,
            }));

            await iniciarSesion(page);
            await elegirRolDueno(page);
            await page.goto(`/panel/trabajos/${idTrabajoDemo}/evidencias`);
        });

        test('claro', async ({ page }) => {
            await asegurarTema(page, 'light');
            await esperarFuentes(page);

            await expect(page).toHaveScreenshot('evidencias-trabajo-galeria-light.png', {
                fullPage: true,
                mask: [page.locator('.ag-panel__footer span').first()],
            });
        });

        test('oscuro', async ({ page }) => {
            await asegurarTema(page, 'dark');
            await esperarFuentes(page);

            await expect(page).toHaveScreenshot('evidencias-trabajo-galeria-dark.png', {
                fullPage: true,
                mask: [page.locator('.ag-panel__footer span').first()],
            });
        });
    });
});
