/**
 * organisms/dashboard-map.js — mapa satelital (Leaflet + Esri World
 * Imagery) del tab "Mapa" del dashboard. Cargado vía import() dinámico
 * desde app.js, solo cuando la página tiene un `[data-ag-map]`.
 *
 * Colores de polígonos SIEMPRE resueltos desde tokens
 * (shared/color-tokens.js) — nunca hex acá (CLAUDE.md invariante 11). El
 * chrome nativo de Leaflet (zoom, atribución) queda con su estilo propio de
 * librería, sin re-tokenizar.
 *
 * Los popups se arman con `createElement`/`textContent`, nunca `innerHTML`:
 * las properties de cada feature son datos REALES editables desde el panel
 * (razón social del cliente, código de lote) y no deben poder inyectar HTML.
 *
 * La capa de puntos de sesión se retiró en la tarea 67 junto con el mock que
 * la alimentaba: el esquema no guarda la posición de una sesión.
 */

import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import { leerColorToken } from '../shared/color-tokens.js';

const TOKEN_POR_TONO = {
    success: '--ag-color-success',
    warning: '--ag-color-warning',
    info: '--ag-color-info',
    neutral: '--ag-color-text-faint',
};

function colorDeTono(tono) {
    return leerColorToken(TOKEN_POR_TONO[tono] ?? TOKEN_POR_TONO.neutral);
}

function popupLote(props) {
    const contenedor = document.createElement('div');

    const titulo = document.createElement('strong');
    titulo.textContent = props.nombre;

    const cliente = document.createElement('div');
    cliente.textContent = props.cliente;

    const cifras = document.createElement('div');
    cifras.textContent = `${props.hectareasAplicadas} / ${props.hectareas} ha · ${props.sesiones} sesiones`;

    contenedor.append(titulo, cliente, cifras);

    return contenedor;
}

function inicializar(el) {
    const centro = JSON.parse(el.dataset.agMapCentro);
    const zoom = Number(el.dataset.agMapZoom) || 12;
    const lotes = JSON.parse(el.dataset.agMapLotes);

    const mapa = L.map(el).setView([centro.lat, centro.lng], zoom);

    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Tiles &copy; Esri — Source: Esri, Maxar, Earthstar Geographics',
        maxZoom: 19,
    }).addTo(mapa);

    const capaLotes = L.geoJSON(lotes, {
        style: (feature) => ({
            color: colorDeTono(feature.properties.tono),
            weight: 2,
            fillColor: colorDeTono(feature.properties.tono),
            fillOpacity: 0.28,
        }),
        onEachFeature: (feature, layer) => layer.bindPopup(popupLote(feature.properties)),
    }).addTo(mapa);

    // Encuadra los lotes reales en vez de confiar solo en el zoom fijo: las
    // geometrías se cargan a mano y su extensión varía por cliente.
    const limites = capaLotes.getBounds();

    if (limites.isValid()) {
        mapa.fitBounds(limites, { padding: [24, 24], maxZoom: 15 });
    }

    window.addEventListener('agrocom:theme-changed', () => {
        capaLotes.eachLayer((layer) => layer.setStyle({
            color: colorDeTono(layer.feature.properties.tono),
            fillColor: colorDeTono(layer.feature.properties.tono),
        }));
    });

    // El pane "Mapa" no es el tab activo por defecto — Bootstrap lo deja en
    // display:none hasta el primer click, y Leaflet inicializado en un
    // contenedor de tamaño cero renderiza mal. Recalcular al mostrarse.
    document
        .querySelector('[data-bs-target="#ag-tab-mapa"]')
        ?.addEventListener('shown.bs.tab', () => {
            mapa.invalidateSize();

            if (limites.isValid()) {
                mapa.fitBounds(limites, { padding: [24, 24], maxZoom: 15 });
            }
        }, { once: true });
}

// Import()ado dinámicamente DESDE un handler de DOMContentLoaded (app.js) —
// ese evento ya disparó para cuando este chunk termina de cargar, así que
// inicializa directo en vez de volver a esperarlo.
document.querySelectorAll('[data-ag-map]').forEach(inicializar);
