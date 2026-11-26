/**
 * organisms/mapa-marcador.js — mapa satelital (Leaflet + Esri) con un único
 * marcador arrastrable que reemplaza la captura a mano de latitud/longitud.
 * Un click o arrastre coloca el punto y llena los dos inputs; escribir en los
 * inputs mueve el marcador. Los inputs siguen siendo los que viajan en el POST.
 *
 * Cargado por `import()` dinámico desde app.js, solo cuando la página tiene un
 * `[data-ag-mapa-marcador]`.
 */

import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import iconoMarcadorUrl from 'leaflet/dist/images/marker-icon.png';
import iconoMarcador2xUrl from 'leaflet/dist/images/marker-icon-2x.png';
import sombraMarcadorUrl from 'leaflet/dist/images/marker-shadow.png';

// Ícono explícito con las imágenes que resuelve Vite: sin esto el marcador
// por defecto de Leaflet sale roto.
const ICONO_MARCADOR = L.icon({
    iconUrl: iconoMarcadorUrl,
    iconRetinaUrl: iconoMarcador2xUrl,
    shadowUrl: sombraMarcadorUrl,
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowSize: [41, 41],
});

// Respaldo cuando todavía no hay coordenadas: Santa Cruz de la Sierra.
const CENTRO_POR_DEFECTO = { lat: -17.783327, lng: -63.18214 };
const ZOOM_SIN_PUNTO = 8;
const ZOOM_CON_PUNTO = 12;
const ATRIBUCION = 'Tiles &copy; Esri — Source: Esri, Maxar, Earthstar Geographics';
const URL_SATELITE = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';

function leer(input) {
    const valor = Number.parseFloat(String(input.value).replace(',', '.'));

    return Number.isFinite(valor) ? valor : null;
}

function inicializar(contenedor) {
    if (contenedor.dataset.agMapaListo === '1') {
        return;
    }

    const lienzo = contenedor.querySelector('[data-ag-mapa-lienzo]');
    const inputLat = document.getElementById(contenedor.dataset.agMapaLat);
    const inputLng = document.getElementById(contenedor.dataset.agMapaLng);

    if (!lienzo || !inputLat || !inputLng) {
        return;
    }

    contenedor.dataset.agMapaListo = '1';

    const lat0 = leer(inputLat);
    const lng0 = leer(inputLng);
    const hayPunto = lat0 !== null && lng0 !== null;
    const centro = hayPunto ? { lat: lat0, lng: lng0 } : CENTRO_POR_DEFECTO;

    const mapa = L.map(lienzo, { zoomControl: false }).setView(
        [centro.lat, centro.lng],
        hayPunto ? ZOOM_CON_PUNTO : ZOOM_SIN_PUNTO,
    );
    L.control.zoom({ position: 'bottomright' }).addTo(mapa);
    L.tileLayer(URL_SATELITE, { attribution: ATRIBUCION, maxZoom: 19 }).addTo(mapa);

    let marcador = null;

    const escribir = (lat, lng) => {
        inputLat.value = lat.toFixed(6);
        inputLng.value = lng.toFixed(6);
        // Avisa a quien observe el formulario (barra de cambios pendientes).
        inputLat.dispatchEvent(new Event('input', { bubbles: true }));
        inputLng.dispatchEvent(new Event('input', { bubbles: true }));
    };

    const colocar = (lat, lng, escribirInputs) => {
        if (marcador) {
            marcador.setLatLng([lat, lng]);
        } else {
            marcador = L.marker([lat, lng], { draggable: true, icon: ICONO_MARCADOR }).addTo(mapa);
            marcador.on('dragend', () => {
                const p = marcador.getLatLng();
                escribir(p.lat, p.lng);
            });
        }

        if (escribirInputs) {
            escribir(lat, lng);
        }
    };

    if (hayPunto) {
        colocar(lat0, lng0, false);
    }

    mapa.on('click', (evento) => colocar(evento.latlng.lat, evento.latlng.lng, true));

    const desdeInputs = () => {
        const lat = leer(inputLat);
        const lng = leer(inputLng);

        if (lat !== null && lng !== null && Math.abs(lat) <= 90 && Math.abs(lng) <= 180) {
            colocar(lat, lng, false);
            mapa.setView([lat, lng], Math.max(mapa.getZoom(), ZOOM_CON_PUNTO));
        }
    };

    inputLat.addEventListener('change', desdeInputs);
    inputLng.addEventListener('change', desdeInputs);

    requestAnimationFrame(() => mapa.invalidateSize());
}

document.querySelectorAll('[data-ag-mapa-marcador]').forEach(inicializar);
