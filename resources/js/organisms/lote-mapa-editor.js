/**
 * organisms/lote-mapa-editor.js — editor del perímetro de un lote sobre mapa
 * satelital (Leaflet + Esri World Imagery + Leaflet-Geoman), en el formulario
 * de campos.
 *
 * Reemplaza al `<textarea>` donde había que pegar el GeoJSON a mano: el
 * perímetro de un lote se reconoce mirando la imagen del campo, no tipeando
 * pares de coordenadas.
 *
 * Contrato con el servidor SIN CAMBIOS: el valor sigue viajando como el mismo
 * string JSON (`{type: 'Polygon', coordinates: [[[lng, lat], ...]]}`) en el
 * input oculto que el Form Request ya validaba. Este módulo solo cambia cómo
 * se produce ese string.
 *
 * Cargado por `import()` dinámico desde app.js, solo cuando la página tiene un
 * `[data-ag-lote-mapa]` — Leaflet pesa, y no entra en el resto del panel.
 *
 * Colores desde tokens (shared/color-tokens.js), nunca hex acá: CLAUDE.md
 * invariante 11, y el editor se abre en ambos temas.
 */

import 'leaflet/dist/leaflet.css';
import '@geoman-io/leaflet-geoman-free/dist/leaflet-geoman.css';
import L from 'leaflet';
import '@geoman-io/leaflet-geoman-free';
import { leerColorToken } from '../shared/color-tokens.js';

/** Centro por defecto: la zona donde opera Agrocom (Santa Cruz). Solo se usa
 *  cuando el lote todavía no tiene perímetro y no hay ninguno cerca. */
const CENTRO_POR_DEFECTO = { lat: -17.34, lng: -62.85 };
const ZOOM_SIN_GEOMETRIA = 13;
const ZOOM_MAXIMO_AL_ENCUADRAR = 17;

/**
 * Contorno en ÁMBAR y relleno en verde de marca. El ámbar es el acento
 * editorial del sistema (`sistema_diseno_panel.md` §8): acá no se repite fila
 * a fila —es un solo contorno— y es lo único que se distingue sobre una
 * imagen satelital de campos, que es verde de punta a punta. Un contorno verde
 * sobre soja verde no se ve, y este trazo es justamente lo que hay que ver
 * para saber si el perímetro quedó bien dibujado.
 */
function estiloPoligono() {
    return {
        color: leerColorToken('--ag-color-accent'),
        fillColor: leerColorToken('--ag-color-primary'),
        fillOpacity: 0.2,
        weight: 3,
    };
}

/**
 * Superficie geodésica de un anillo `[[lng, lat], ...]`, en metros cuadrados.
 *
 * Fórmula del exceso esférico (la misma que usa Turf): sobre lotes de decenas
 * de hectáreas, calcular el área como si el polígono fuera plano se equivoca
 * lo suficiente como para que la cifra no sirva para nada. No se agrega una
 * dependencia por veinte líneas.
 */
function superficieEnMetros(anillo) {
    const RADIO_TIERRA = 6378137;
    const enRadianes = (grados) => (grados * Math.PI) / 180;

    let total = 0;

    for (let i = 0; i < anillo.length - 1; i++) {
        const [lng1, lat1] = anillo[i];
        const [lng2, lat2] = anillo[i + 1];

        total += enRadianes(lng2 - lng1) * (2 + Math.sin(enRadianes(lat1)) + Math.sin(enRadianes(lat2)));
    }

    return Math.abs((total * RADIO_TIERRA * RADIO_TIERRA) / 2);
}

function hectareasDe(geometria) {
    const anillo = geometria?.coordinates?.[0];

    if (!Array.isArray(anillo) || anillo.length < 4) {
        return null;
    }

    return superficieEnMetros(anillo) / 10000;
}

/** Lee el valor del input oculto, tolerando JSON inválido cargado a mano. */
function leerGeometria(input) {
    if (!input.value.trim()) {
        return null;
    }

    try {
        const geometria = JSON.parse(input.value);

        return geometria?.type === 'Polygon' ? geometria : null;
    } catch {
        return null;
    }
}

function inicializar(contenedor) {
    const lienzo = contenedor.querySelector('[data-ag-lote-mapa-lienzo]');
    const input = contenedor.querySelector('[data-ag-lote-geometria]');
    const medida = contenedor.querySelector('[data-ag-lote-medida]');
    const medidaTexto = contenedor.querySelector('[data-ag-lote-medida-texto]');
    const botonUsar = contenedor.querySelector('[data-ag-lote-usar-superficie]');

    if (!lienzo || !input || contenedor.dataset.agLoteMapaListo === '1') {
        return;
    }

    // Marca de inicialización: las filas se clonan desde un <template> y este
    // módulo se llama de nuevo por cada fila agregada.
    contenedor.dataset.agLoteMapaListo = '1';

    const mapa = L.map(lienzo).setView([CENTRO_POR_DEFECTO.lat, CENTRO_POR_DEFECTO.lng], ZOOM_SIN_GEOMETRIA);

    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Tiles &copy; Esri — Source: Esri, Maxar, Earthstar Geographics',
        maxZoom: 19,
    }).addTo(mapa);

    const capa = L.featureGroup().addTo(mapa);

    /** Escribe el input oculto y refresca la superficie mostrada. */
    const sincronizar = () => {
        const capas = capa.getLayers();

        if (capas.length === 0) {
            input.value = '';
            medida?.setAttribute('hidden', '');

            return;
        }

        // Un lote es UN polígono: si se dibuja otro, el anterior se descarta.
        // `toGeoJSON()` de Leaflet ya emite [lng, lat], el mismo orden que
        // guarda `com_lotes.geometria`.
        const geometria = capas[capas.length - 1].toGeoJSON().geometry;

        input.value = JSON.stringify(geometria);

        const hectareas = hectareasDe(geometria);

        if (hectareas === null || !medida || !medidaTexto) {
            return;
        }

        medidaTexto.textContent = `${hectareas.toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ha dibujadas`;
        medida.removeAttribute('hidden');
        medida.dataset.agLoteHectareas = hectareas.toFixed(2);
    };

    // Perímetro ya guardado: se dibuja y el mapa encuadra sobre él.
    const guardada = leerGeometria(input);

    if (guardada) {
        L.geoJSON(guardada, { style: estiloPoligono }).eachLayer((capaGuardada) => capa.addLayer(capaGuardada));
        mapa.fitBounds(capa.getBounds(), { padding: [16, 16], maxZoom: ZOOM_MAXIMO_AL_ENCUADRAR });
        sincronizar();
    }

    // El español que ya trae Geoman, sin diccionario propio: pasarle uno
    // parcial REEMPLAZA el del idioma entero, y los botones se quedaban sin
    // `title` — una barra de cinco íconos sin tooltip no se entiende.
    mapa.pm.setLang('es');

    // Solo polígono: un lote no es una línea ni un círculo, y cada control de
    // más es una forma de guardar una geometría que el resto del sistema no
    // sabe dibujar.
    mapa.pm.addControls({
        position: 'topright',
        drawPolygon: true,
        editMode: true,
        removalMode: true,
        drawMarker: false,
        drawCircle: false,
        drawCircleMarker: false,
        drawPolyline: false,
        drawRectangle: true,
        drawText: false,
        cutPolygon: false,
        rotateMode: false,
        dragMode: true,
    });

    mapa.pm.setPathOptions(estiloPoligono());

    mapa.on('pm:create', ({ layer }) => {
        // Uno solo: el nuevo reemplaza al anterior.
        capa.clearLayers();
        mapa.removeLayer(layer);
        capa.addLayer(layer);

        layer.on('pm:edit', sincronizar);
        layer.on('pm:dragend', sincronizar);

        sincronizar();
    });

    mapa.on('pm:remove', () => {
        capa.clearLayers();
        sincronizar();
    });

    capa.on('layeradd', ({ layer }) => {
        layer.on?.('pm:edit', sincronizar);
        layer.on?.('pm:dragend', sincronizar);
    });

    botonUsar?.addEventListener('click', () => {
        const hectareas = medida?.dataset.agLoteHectareas;
        const campoHectareas = contenedor
            .closest('[data-ag-lote-fila]')
            ?.querySelector('input[name$="[hectareas]"]');

        if (hectareas && campoHectareas) {
            campoHectareas.value = hectareas;
            campoHectareas.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });

    // El repintado al cambiar de tema: los tokens de color se resuelven al
    // inicializar, así que hay que releerlos.
    window.addEventListener('agrocom:theme-changed', () => {
        capa.eachLayer((capaDibujada) => capaDibujada.setStyle?.(estiloPoligono()));
        mapa.pm.setPathOptions(estiloPoligono());
    });

    // Leaflet en un contenedor que todavía no tiene tamaño (fila recién
    // clonada, o formulario dentro de una sección plegada) renderiza mal.
    requestAnimationFrame(() => mapa.invalidateSize());
}

function inicializarTodos(raiz = document) {
    raiz.querySelectorAll('[data-ag-lote-mapa]').forEach(inicializar);
}

inicializarTodos();

// `campos-form.js` clona filas nuevas y avisa por este evento — el editor no
// observa el DOM entero para enterarse.
document.addEventListener('agrocom:lote-agregado', (evento) => {
    if (evento.detail?.fila) {
        inicializarTodos(evento.detail.fila);
    }
});
