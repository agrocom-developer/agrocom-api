/**
 * organisms/base-mapa-marcador.js — mapa embebido con un único marcador
 * arrastrable para la ubicación de una Base (tarea 132): reemplaza los
 * inputs numéricos de latitud/longitud del formulario por un punto que se
 * coloca directo en el mapa (arrastre o click) y llena los dos campos
 * ocultos. Mismo proveedor que decide el servidor
 * (`Compartido\Aplicacion\ResolverProveedorMapa`, llega por `data-*`) que
 * los demás editores de mapa del panel.
 *
 * Deliberadamente NO reusa `organisms/propiedad-mapa-editor.js` ni importa
 * nada de él — mismo motivo que ya explica su docblock: compartir el chunk
 * de Leaflet entre editores de mapa rompió la inicialización en producción
 * el 16/9/2026 (`L is not defined`). Una Base es un punto (cuatro campos
 * planos, sin terreno que dibujar): esta versión es deliberadamente mínima
 * frente al editor de Propiedad — sin buscador de coordenadas, sin
 * polígonos/Geoman, sin pantalla completa ni cambio de capa. Un mapa, un
 * marcador, arrastrar o hacer click lo mueve.
 *
 * Cargado por `import()` dinámico desde app.js, solo cuando la página tiene
 * un `[data-ag-base-mapa]`.
 */

import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import iconoMarcadorUrl from 'leaflet/dist/images/marker-icon.png';
import iconoMarcador2xUrl from 'leaflet/dist/images/marker-icon-2x.png';
import sombraMarcadorUrl from 'leaflet/dist/images/marker-shadow.png';

// Ícono explícito con las imágenes que resuelve Vite — sin esto el marcador
// por defecto de Leaflet sale roto (busca sus imágenes en una ruta que Vite
// no genera). Mismas medidas que `propiedad-mapa-editor.js`.
const ICONO_MARCADOR = L.icon({
    iconUrl: iconoMarcadorUrl,
    iconRetinaUrl: iconoMarcador2xUrl,
    shadowUrl: sombraMarcadorUrl,
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    tooltipAnchor: [16, -28],
    shadowSize: [41, 41],
});

/**
 * Copia intencional del loader de `propiedad-mapa-editor.js` (mismo nombre
 * de callback global a propósito, por si algún día coexisten en el mismo
 * documento) — NO un import compartido: es la misma razón por la que este
 * archivo entero no importa nada de ese módulo (chunk mezclado de Leaflet
 * rompiendo en producción, 16/9/2026).
 */
const NOMBRE_CALLBACK_GOOGLE_MAPS = '__agrocomGoogleMapsListo';

let cargaGoogleMapsEnCurso = null;
let llaveGoogleMapsEnCurso = null;

function cargarGoogleMaps(llave, mensajeError = '') {
    if (cargaGoogleMapsEnCurso && llaveGoogleMapsEnCurso === llave) {
        return cargaGoogleMapsEnCurso;
    }

    llaveGoogleMapsEnCurso = llave;
    cargaGoogleMapsEnCurso = new Promise((resolve, reject) => {
        if (window.google?.maps) {
            resolve(window.google.maps);

            return;
        }

        window[NOMBRE_CALLBACK_GOOGLE_MAPS] = () => {
            delete window[NOMBRE_CALLBACK_GOOGLE_MAPS];
            resolve(window.google.maps);
        };

        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(llave)}&loading=async&callback=${NOMBRE_CALLBACK_GOOGLE_MAPS}`;
        script.async = true;
        script.onerror = () => reject(new Error(mensajeError));
        document.head.appendChild(script);
    });

    cargaGoogleMapsEnCurso.catch(() => {
        cargaGoogleMapsEnCurso = null;
        llaveGoogleMapsEnCurso = null;
    });

    return cargaGoogleMapsEnCurso;
}

// Último respaldo cuando la base todavía no tiene coordenadas (alta, o
// edición de una base vieja sin mapa): Santa Cruz de la Sierra, Bolivia —
// mismo punto que usa el editor de Propiedad, pedido directo del dueño.
const CENTRO_POR_DEFECTO = { lat: -17.783327, lng: -63.18214 };
const ZOOM_INICIAL = 13;

const ATRIBUCION_ESRI = 'Tiles &copy; Esri — Source: Esri, Maxar, Earthstar Geographics';
const URL_CAPA_SATELITE = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';

function fijarValor(input, valor) {
    input.value = valor ?? '';
    input.dispatchEvent(new Event('input', { bubbles: true }));
}

function actualizarIndicadorCoordenadas(indicador, lat, lng) {
    if (indicador) {
        indicador.textContent = lat === null || lng === null ? '' : `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
    }
}

function referenciasDom(contenedor) {
    return {
        lienzo: contenedor.querySelector('[data-ag-base-mapa-lienzo]'),
        mapaDiv: contenedor.querySelector('[data-ag-base-mapa-mapa]'),
        inputLatitud: contenedor.querySelector('[data-ag-base-latitud]'),
        inputLongitud: contenedor.querySelector('[data-ag-base-longitud]'),
        coordenadasTexto: contenedor.querySelector('[data-ag-base-coordenadas-texto]'),
    };
}

function coordenadasIniciales(refs) {
    const lat = Number.parseFloat(refs.inputLatitud.value);
    const lng = Number.parseFloat(refs.inputLongitud.value);

    return Number.isFinite(lat) && Number.isFinite(lng) ? { lat, lng } : null;
}

function inicializarLeaflet(refs) {
    const { mapaDiv, inputLatitud, inputLongitud, coordenadasTexto } = refs;

    const inicial = coordenadasIniciales(refs);
    const centro = inicial ?? CENTRO_POR_DEFECTO;

    const mapa = L.map(mapaDiv, { zoomControl: false }).setView([centro.lat, centro.lng], ZOOM_INICIAL);
    L.control.zoom({ position: 'bottomright' }).addTo(mapa);
    L.tileLayer(URL_CAPA_SATELITE, { attribution: ATRIBUCION_ESRI, maxZoom: 19 }).addTo(mapa);

    let marcador = null;

    const posicionar = (lat, lng) => {
        fijarValor(inputLatitud, lat.toFixed(6));
        fijarValor(inputLongitud, lng.toFixed(6));
        actualizarIndicadorCoordenadas(coordenadasTexto, lat, lng);
    };

    const colocarMarcador = (lat, lng) => {
        if (marcador) {
            marcador.setLatLng([lat, lng]);
        } else {
            marcador = L.marker([lat, lng], { draggable: true, icon: ICONO_MARCADOR }).addTo(mapa);
            marcador.on('dragend', () => {
                const posicion = marcador.getLatLng();
                posicionar(posicion.lat, posicion.lng);
            });
        }

        posicionar(lat, lng);
    };

    if (inicial) {
        colocarMarcador(inicial.lat, inicial.lng);
    }

    // Sin modo especial: un click, coloca o reposiciona el marcador —
    // no hay nada más que dibujar en este mapa (a diferencia del editor de
    // Propiedad, que necesita un modo aparte para no chocar con el dibujo
    // de polígonos).
    mapa.on('click', (evento) => colocarMarcador(evento.latlng.lat, evento.latlng.lng));

    requestAnimationFrame(() => mapa.invalidateSize());
}

function inicializarGoogle(refs, googleMapsNs) {
    const { mapaDiv, inputLatitud, inputLongitud, coordenadasTexto } = refs;

    const inicial = coordenadasIniciales(refs);
    const centro = inicial ?? CENTRO_POR_DEFECTO;

    const mapa = new googleMapsNs.Map(mapaDiv, {
        center: centro,
        zoom: ZOOM_INICIAL,
        mapTypeId: googleMapsNs.MapTypeId.HYBRID,
        streetViewControl: false,
        mapTypeControl: false,
        fullscreenControl: false,
        zoomControlOptions: { position: googleMapsNs.ControlPosition.RIGHT_BOTTOM },
    });

    let marcador = null;

    const posicionar = (lat, lng) => {
        fijarValor(inputLatitud, lat.toFixed(6));
        fijarValor(inputLongitud, lng.toFixed(6));
        actualizarIndicadorCoordenadas(coordenadasTexto, lat, lng);
    };

    const colocarMarcador = (lat, lng) => {
        if (marcador) {
            marcador.setPosition({ lat, lng });
        } else {
            marcador = new googleMapsNs.Marker({ position: { lat, lng }, map: mapa, draggable: true });
            marcador.addListener('dragend', () => {
                const posicion = marcador.getPosition();
                posicionar(posicion.lat(), posicion.lng());
            });
        }

        posicionar(lat, lng);
    };

    if (inicial) {
        colocarMarcador(inicial.lat, inicial.lng);
    }

    mapa.addListener('click', (evento) => colocarMarcador(evento.latLng.lat(), evento.latLng.lng()));
}

function inicializar(contenedor) {
    if (contenedor.dataset.agBaseMapaListo === '1') {
        return;
    }

    const refs = referenciasDom(contenedor);

    if (!refs.lienzo || !refs.mapaDiv || !refs.inputLatitud || !refs.inputLongitud) {
        return;
    }

    contenedor.dataset.agBaseMapaListo = '1';

    const llave = contenedor.dataset.agBaseMapaGoogleKey;

    if (contenedor.dataset.agBaseMapaProveedor === 'google' && llave) {
        cargarGoogleMaps(llave, contenedor.dataset.agBaseMapaErrorGoogle || '')
            .then((googleMapsNs) => inicializarGoogle(refs, googleMapsNs))
            .catch((error) => {
                console.warn('No se pudo inicializar Google Maps, se usa Leaflet como respaldo.', error);
                inicializarLeaflet(refs);
            });

        return;
    }

    inicializarLeaflet(refs);
}

document.querySelectorAll('[data-ag-base-mapa]').forEach(inicializar);
