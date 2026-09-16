import fs from 'node:fs';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

/**
 * `lote-mapa-editor.js` y `propiedad-mapa-editor.js` (16/9/2026) son dos
 * `import()` dinámicos independientes que cargan las mismas dependencias
 * pesadas (`leaflet`, `@geoman-io/leaflet-geoman-free`). El code-splitting
 * automático de Rollup, cuando un módulo CJS/UMD como `@geoman-io/leaflet-geoman-free`
 * (que referencia `L` como variable libre de su propio scope, no como import
 * formal — patrón UMD clásico "el global ya está puesto") queda repartido
 * entre dos `import()` dinámicos consumidores, a veces lo separa en un chunk
 * propio distinto del chunk que define esa variable — y entonces `L` deja de
 * estar en el scope donde Geoman la usa: `ReferenceError: L is not defined`
 * en tiempo de ejecución (confirmado en vivo, 16/9/2026, probado con
 * `manualChunks` fijando el nombre del chunk — el bundling compartido sigue
 * roto aunque el chunk tenga un solo nombre).
 *
 * La única forma confiable de evitarlo es que Rollup nunca vea a
 * `propiedad-mapa-editor.js` y `lote-mapa-editor.js`/`dashboard-map.js` como
 * consumidores del MISMO módulo: este plugin resuelve `leaflet` y
 * `@geoman-io/leaflet-geoman-free` (y todo lo que ellos importen a su vez,
 * de forma transitiva) bajo un id distinto SOLO cuando el consumidor final
 * es `propiedad-mapa-editor.js`, así Rollup genera una copia standalone
 * separada para ese editor — exactamente el estado (embebido, aislado) que
 * ya tenía el editor de Lote cuando era el único consumidor, sin este bug.
 */
const PREFIJO_COPIA_AISLADA = '\0leaflet-geoman-copia-propiedad:';

function copiaAisladaDeLeafletParaEditorDePropiedad() {
    return {
        name: 'copia-aislada-leaflet-geoman-propiedad',
        // `enforce: 'pre'`: sin esto, el resolver core de Vite para paquetes
        // de node_modules resuelve `leaflet`/`geoman` primero y este plugin
        // nunca llega a intervenir (confirmado en vivo, 16/9/2026 — sin
        // `enforce`, el manifest seguía mostrando el chunk compartido).
        enforce: 'pre',
        async resolveId(source, importer, options) {
            if (!importer) {
                return null;
            }

            const importerEsCopiaAislada = importer.startsWith(PREFIJO_COPIA_AISLADA);
            const esPuntoDeEntrada = importer.includes('organisms/propiedad-mapa-editor.js')
                && (source === 'leaflet'
                    || source.startsWith('leaflet/')
                    || source === '@geoman-io/leaflet-geoman-free'
                    || source.startsWith('@geoman-io/leaflet-geoman-free/'));

            if (!importerEsCopiaAislada && !esPuntoDeEntrada) {
                return null;
            }

            const importerReal = importerEsCopiaAislada
                ? importer.slice(PREFIJO_COPIA_AISLADA.length)
                : importer;

            const resuelto = await this.resolve(source, importerReal, { ...options, skipSelf: true });

            if (!resuelto || resuelto.external) {
                return resuelto;
            }

            return PREFIJO_COPIA_AISLADA + resuelto.id;
        },
        load(id) {
            if (!id.startsWith(PREFIJO_COPIA_AISLADA)) {
                return null;
            }

            return fs.readFileSync(id.slice(PREFIJO_COPIA_AISLADA.length), 'utf-8');
        },
    };
}

export default defineConfig({
    plugins: [
        copiaAisladaDeLeafletParaEditorDePropiedad(),
        laravel({
            // `transicion-vista.css` va aparte del bundle a propósito:
            // `@view-transition` es una regla de documento y no se puede
            // acotar por selector, así que solo las pantallas del flujo de
            // autenticación la incluyen (ver ese archivo).
            input: ['resources/css/app.css', 'resources/css/transicion-vista.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
