/**
 * shared/cargador-google-maps.js — carga diferida del SDK de Google Maps
 * (tarea 79, HU-56): solo se pide cuando un mapa concreto lo necesita y hay
 * llave configurada (`mapas.google_maps_api_key`, tarea 78) — mismo criterio
 * que ya usa Leaflet, que se carga por `import()` dinámico y no entra en el
 * resto del panel.
 *
 * Una sola carga por página aunque haya más de un mapa (el editor de lote y,
 * a futuro, el mapa del tablero): la promesa se cachea por llave, así una
 * segunda llamada con la MISMA llave reusa la carga en curso o ya resuelta
 * en vez de inyectar el `<script>` de nuevo — el SDK de Google revienta si
 * se carga dos veces.
 */

const NOMBRE_CALLBACK_GLOBAL = '__agrocomGoogleMapsListo';

let cargaEnCurso = null;
let llaveEnCurso = null;

/**
 * @param {string} llave - `mapas.google_maps_api_key` ya resuelta por el servidor.
 * @returns {Promise<typeof google.maps>} el namespace `google.maps`, listo para usar.
 */
export function cargarGoogleMaps(llave) {
    if (cargaEnCurso && llaveEnCurso === llave) {
        return cargaEnCurso;
    }

    llaveEnCurso = llave;
    cargaEnCurso = new Promise((resolve, reject) => {
        if (window.google?.maps) {
            resolve(window.google.maps);

            return;
        }

        window[NOMBRE_CALLBACK_GLOBAL] = () => {
            delete window[NOMBRE_CALLBACK_GLOBAL];
            resolve(window.google.maps);
        };

        // Sin `libraries=drawing`: `google.maps.drawing.DrawingManager` (el
        // único motivo para pedirla) ya no existe en la API (retirada en la
        // v3.65, confirmado en vivo el 15/9/2026) — el dibujo del polígono
        // se arma a mano sobre `Polygon` en `organisms/lote-mapa-editor.js`.
        // `loading=async` saca el warning de rendimiento de Google
        // ("loaded directly without loading=async").
        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(llave)}&loading=async&callback=${NOMBRE_CALLBACK_GLOBAL}`;
        script.async = true;
        script.onerror = () => reject(new Error('No se pudo cargar el SDK de Google Maps'));
        document.head.appendChild(script);
    });

    // Una carga que falló no queda cacheada: una llave mala hoy no debería
    // dejar el mapa roto para siempre si se corrige más tarde en la misma
    // sesión de navegación (aunque en la práctica la página se recarga).
    cargaEnCurso.catch(() => {
        cargaEnCurso = null;
        llaveEnCurso = null;
    });

    return cargaEnCurso;
}
