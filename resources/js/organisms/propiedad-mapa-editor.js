/**
 * organisms/propiedad-mapa-editor.js — editor de coordenadas de una
 * propiedad, en `/panel/propiedades/{propiedad}/mapa` (adenda 16/9/2026 a
 * ADR 0018 punto 1 / ADR 0020: "el editor de mapa multi-polígono se
 * construye en un feature aparte" — esta es esa pieza).
 *
 * DOS piezas sobre el mismo mapa:
 * - Un MARCADOR arrastrable: el punto de referencia (`latitud`/`longitud`).
 * - Uno o más POLÍGONOS ("terrenos"): el perímetro completo de la propiedad,
 *   GeoJSON `MultiPolygon` — a diferencia del editor de Lote (un solo
 *   `Polygon`), acá puede haber varios terrenos separados (islas, caso
 *   "Gamelera", ADR 0020).
 *
 * Mismo proveedor que decide el servidor (`ResolverProveedorMapa`, llega en
 * `data-ag-propiedad-mapa-proveedor`/`-google-key`) y misma infraestructura
 * compartida que `organisms/lote-mapa-editor.js` (tokens de color, carga de
 * Google Maps, pantalla completa) — pero es un módulo INDEPENDIENTE, no una
 * extensión de ese archivo: no toca el editor de Lote, que sigue en
 * producción tal cual.
 *
 * Alcance deliberadamente MÁS CHICO que el editor de Lote para esta primera
 * versión (documentado, no un olvido): dibujar terrenos, deshacer el último
 * paso, borrar todo, centrar y alternar capa — SIN edición de vértices por
 * arrastre ni pantalla completa. Si hace falta ese nivel de edición más
 * fino, es una ampliación de este mismo archivo, no un rediseño.
 *
 * Cargado por `import()` dinámico desde app.js, solo cuando la página tiene
 * un `[data-ag-propiedad-mapa]` — mismo criterio que el editor de Lote.
 */

import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import { leerColorToken } from '../shared/color-tokens.js';
import { cargarGoogleMaps } from '../shared/cargador-google-maps.js';

const CENTRO_POR_DEFECTO = { lat: -17.34, lng: -62.85 };
const ZOOM_SIN_GEOMETRIA = 12;
const ZOOM_MAXIMO_AL_ENCUADRAR = 17;
const LIMITE_HISTORIAL = 20;

const ATRIBUCION_ESRI = 'Tiles &copy; Esri — Source: Esri, Maxar, Earthstar Geographics';
const URL_CAPA_SATELITE = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
const URL_CAPA_CALLES = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}';

function estiloPoligono() {
    return {
        color: leerColorToken('--ag-color-accent'),
        fillColor: leerColorToken('--ag-color-primary'),
        fillOpacity: 0.2,
        weight: 3,
    };
}

function estiloPoligonoGoogle() {
    return {
        strokeColor: leerColorToken('--ag-color-accent'),
        strokeWeight: 3,
        fillColor: leerColorToken('--ag-color-primary'),
        fillOpacity: 0.2,
    };
}

/** Mismo cálculo geodésico que el editor de Lote (fórmula del exceso
 *  esférico) — no se importa por veinte líneas compartidas entre dos
 *  módulos que no dependen uno del otro. */
function hectareasDeAnillo(anillo) {
    if (!Array.isArray(anillo) || anillo.length < 3) {
        return 0;
    }

    const RADIO_TIERRA = 6378137;
    const enRadianes = (grados) => (grados * Math.PI) / 180;
    const cerrado = [...anillo, anillo[0]];

    let total = 0;
    for (let i = 0; i < cerrado.length - 1; i++) {
        const [lng1, lat1] = cerrado[i];
        const [lng2, lat2] = cerrado[i + 1];

        total += enRadianes(lng2 - lng1) * (2 + Math.sin(enRadianes(lat1)) + Math.sin(enRadianes(lat2)));
    }

    return Math.abs((total * RADIO_TIERRA * RADIO_TIERRA) / 2) / 10000;
}

function formatearHectareas(valor) {
    return valor.toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fijarValor(input, valor) {
    input.value = valor ?? '';
    input.dispatchEvent(new Event('input', { bubbles: true }));
}

/** Lee `geometria` (MultiPolygon) del input oculto → lista de anillos
 *  ABIERTOS `[[lng, lat], ...]` (sin repetir el primer punto). */
function leerAnillosGuardados(input) {
    if (!input.value.trim()) {
        return [];
    }

    try {
        const geometria = JSON.parse(input.value);

        if (geometria?.type !== 'MultiPolygon' || !Array.isArray(geometria.coordinates)) {
            return [];
        }

        return geometria.coordinates
            .map((poligono) => poligono?.[0])
            .filter((anillo) => Array.isArray(anillo) && anillo.length >= 4)
            .map((anillo) => anillo.slice(0, -1));
    } catch {
        return [];
    }
}

function serializarMultiPolygon(anillosAbiertos) {
    if (anillosAbiertos.length === 0) {
        return '';
    }

    return JSON.stringify({
        type: 'MultiPolygon',
        coordinates: anillosAbiertos.map((anillo) => [[...anillo, anillo[0]]]),
    });
}

function referenciasDom(contenedor) {
    return {
        lienzo: contenedor.querySelector('[data-ag-propiedad-mapa-lienzo]'),
        inputGeometria: contenedor.querySelector('[data-ag-propiedad-geometria]'),
        inputLatitud: contenedor.querySelector('[data-ag-propiedad-latitud]'),
        inputLongitud: contenedor.querySelector('[data-ag-propiedad-longitud]'),
        medidaTexto: contenedor.querySelector('[data-ag-propiedad-medida-texto]'),
        botonDibujar: contenedor.querySelector('[data-ag-propiedad-mapa-accion="dibujar"]'),
        botonBorrarTodo: contenedor.querySelector('[data-ag-propiedad-mapa-accion="borrar-todo"]'),
        botonDeshacer: contenedor.querySelector('[data-ag-propiedad-mapa-accion="deshacer"]'),
        botonCentrar: contenedor.querySelector('[data-ag-propiedad-mapa-accion="centrar"]'),
        botonCapa: contenedor.querySelector('[data-ag-propiedad-mapa-accion="capa"]'),
    };
}

function crearHistorial({ boton, aplicar }) {
    const pila = [];

    const actualizarBoton = () => {
        if (boton) {
            boton.disabled = pila.length === 0;
        }
    };

    const registrar = (estadoAnterior) => {
        pila.push(estadoAnterior);

        if (pila.length > LIMITE_HISTORIAL) {
            pila.shift();
        }

        actualizarBoton();
    };

    boton?.addEventListener('click', () => {
        if (pila.length === 0) {
            return;
        }

        aplicar(pila.pop());
        actualizarBoton();
    });

    return { registrar };
}

function inicializarLeaflet(contenedor, refs) {
    const { lienzo, inputGeometria, inputLatitud, inputLongitud, medidaTexto, botonDibujar, botonBorrarTodo, botonDeshacer, botonCentrar, botonCapa } = refs;

    const anillosGuardados = leerAnillosGuardados(inputGeometria);
    const latInicial = Number.parseFloat(inputLatitud.value);
    const lngInicial = Number.parseFloat(inputLongitud.value);
    const tieneMarcadorInicial = Number.isFinite(latInicial) && Number.isFinite(lngInicial);

    const centroInicial = tieneMarcadorInicial
        ? { lat: latInicial, lng: lngInicial }
        : (anillosGuardados[0]?.[0] ? { lat: anillosGuardados[0][0][1], lng: anillosGuardados[0][0][0] } : CENTRO_POR_DEFECTO);

    const mapa = L.map(lienzo).setView([centroInicial.lat, centroInicial.lng], ZOOM_SIN_GEOMETRIA);

    let esSatelital = true;
    let capaBase = L.tileLayer(URL_CAPA_SATELITE, { attribution: ATRIBUCION_ESRI, maxZoom: 19 }).addTo(mapa);

    const capaPoligonos = L.featureGroup().addTo(mapa);

    // ---------- Marcador (punto de referencia) ----------
    const marcador = L.marker([centroInicial.lat, centroInicial.lng], { draggable: true }).addTo(mapa);

    if (tieneMarcadorInicial) {
        fijarValor(inputLatitud, String(latInicial));
        fijarValor(inputLongitud, String(lngInicial));
    }

    marcador.on('dragend', () => {
        const posicion = marcador.getLatLng();
        fijarValor(inputLatitud, posicion.lat.toFixed(6));
        fijarValor(inputLongitud, posicion.lng.toFixed(6));
    });

    // Tipear lat/long a mano (p. ej. copiado de un GPS) también mueve el
    // marcador — atado bidireccional, no solo arrastre.
    const moverMarcadorDesdeInputs = () => {
        const lat = Number.parseFloat(inputLatitud.value);
        const lng = Number.parseFloat(inputLongitud.value);

        if (Number.isFinite(lat) && Number.isFinite(lng)) {
            marcador.setLatLng([lat, lng]);
        }
    };

    inputLatitud.addEventListener('change', moverMarcadorDesdeInputs);
    inputLongitud.addEventListener('change', moverMarcadorDesdeInputs);

    // ---------- Polígonos (terrenos) ----------
    const mostrarMedida = () => {
        const total = capaPoligonos.getLayers().reduce((suma, capa) => {
            const anillo = capa.getLatLngs()[0].map((p) => [p.lng, p.lat]);

            return suma + hectareasDeAnillo(anillo);
        }, 0);

        if (medidaTexto) {
            medidaTexto.textContent = total > 0
                ? medidaTexto.dataset.agPropiedadMedidaPlantilla.replace(':hectareas', formatearHectareas(total))
                : '';
        }
    };

    const sincronizarGeometria = () => {
        const anillos = capaPoligonos.getLayers().map((capa) => capa.getLatLngs()[0].map((p) => [p.lng, p.lat]));

        fijarValor(inputGeometria, serializarMultiPolygon(anillos));
        mostrarMedida();
    };

    const aplicarEstado = (valorJson) => {
        capaPoligonos.clearLayers();
        fijarValor(inputGeometria, valorJson);

        leerAnillosGuardados(inputGeometria).forEach((anillo) => {
            capaPoligonos.addLayer(L.polygon(anillo.map(([lng, lat]) => [lat, lng]), estiloPoligono()));
        });

        sincronizarGeometria();
    };

    const historial = crearHistorial({ boton: botonDeshacer, aplicar: aplicarEstado });

    anillosGuardados.forEach((anillo) => {
        capaPoligonos.addLayer(L.polygon(anillo.map(([lng, lat]) => [lat, lng]), estiloPoligono()));
    });

    if (anillosGuardados.length > 0) {
        mapa.fitBounds(capaPoligonos.getBounds(), { padding: [24, 24], maxZoom: ZOOM_MAXIMO_AL_ENCUADRAR });
    }

    sincronizarGeometria();

    mapa.pm.setLang('es');
    mapa.pm.setPathOptions(estiloPoligono());

    mapa.on('pm:create', ({ layer }) => {
        historial.registrar(inputGeometria.value);
        // A diferencia del editor de Lote: NO se limpia lo anterior — cada
        // trazo nuevo es un terreno ADICIONAL (islas, caso "Gamelera").
        capaPoligonos.addLayer(layer);
        sincronizarGeometria();
        botonDibujar?.setAttribute('aria-pressed', 'false');
    });

    botonDibujar?.addEventListener('click', () => {
        if (mapa.pm.globalDrawModeEnabled()) {
            mapa.pm.disableDraw();
            botonDibujar.setAttribute('aria-pressed', 'false');

            return;
        }

        mapa.pm.enableDraw('Polygon');
        botonDibujar.setAttribute('aria-pressed', 'true');
    });

    botonBorrarTodo?.addEventListener('click', () => {
        if (capaPoligonos.getLayers().length === 0) {
            return;
        }

        historial.registrar(inputGeometria.value);
        capaPoligonos.clearLayers();
        sincronizarGeometria();
    });

    botonCentrar?.addEventListener('click', () => {
        if (capaPoligonos.getLayers().length > 0) {
            mapa.fitBounds(capaPoligonos.getBounds(), { padding: [24, 24], maxZoom: ZOOM_MAXIMO_AL_ENCUADRAR });
        } else {
            mapa.setView([marcador.getLatLng().lat, marcador.getLatLng().lng], ZOOM_SIN_GEOMETRIA);
        }
    });

    if (botonCapa) {
        const actualizarBotonCapa = () => {
            const etiqueta = esSatelital ? botonCapa.dataset.agPropiedadMapaCapaCalles : botonCapa.dataset.agPropiedadMapaCapaSatelite;
            botonCapa.title = etiqueta;
            botonCapa.setAttribute('aria-label', etiqueta);
            botonCapa.setAttribute('aria-pressed', String(!esSatelital));
        };

        botonCapa.addEventListener('click', () => {
            esSatelital = !esSatelital;
            mapa.removeLayer(capaBase);
            capaBase = L.tileLayer(esSatelital ? URL_CAPA_SATELITE : URL_CAPA_CALLES, { attribution: ATRIBUCION_ESRI, maxZoom: 19 }).addTo(mapa);
            capaBase.bringToBack();
            actualizarBotonCapa();
        });

        actualizarBotonCapa();
    }

    window.addEventListener('agrocom:theme-changed', () => {
        capaPoligonos.eachLayer((capa) => capa.setStyle?.(estiloPoligono()));
        mapa.pm.setPathOptions(estiloPoligono());
    });

    requestAnimationFrame(() => mapa.invalidateSize());
}

function inicializarGoogle(contenedor, refs, googleMapsNs) {
    const { lienzo, inputGeometria, inputLatitud, inputLongitud, medidaTexto, botonDibujar, botonBorrarTodo, botonDeshacer, botonCentrar, botonCapa } = refs;

    const anillosGuardados = leerAnillosGuardados(inputGeometria);
    const latInicial = Number.parseFloat(inputLatitud.value);
    const lngInicial = Number.parseFloat(inputLongitud.value);
    const tieneMarcadorInicial = Number.isFinite(latInicial) && Number.isFinite(lngInicial);

    const centroInicial = tieneMarcadorInicial
        ? { lat: latInicial, lng: lngInicial }
        : (anillosGuardados[0]?.[0] ? { lat: anillosGuardados[0][0][1], lng: anillosGuardados[0][0][0] } : CENTRO_POR_DEFECTO);

    const mapa = new googleMapsNs.Map(lienzo, {
        center: centroInicial,
        zoom: ZOOM_SIN_GEOMETRIA,
        mapTypeId: googleMapsNs.MapTypeId.HYBRID,
        isFractionalZoomEnabled: true,
        streetViewControl: false,
        mapTypeControl: false,
        fullscreenControl: false,
    });

    let esSatelital = true;
    let poligonos = [];

    // ---------- Marcador ----------
    const marcador = new googleMapsNs.Marker({ position: centroInicial, map: mapa, draggable: true });

    if (tieneMarcadorInicial) {
        fijarValor(inputLatitud, String(latInicial));
        fijarValor(inputLongitud, String(lngInicial));
    }

    marcador.addListener('dragend', () => {
        const posicion = marcador.getPosition();
        fijarValor(inputLatitud, posicion.lat().toFixed(6));
        fijarValor(inputLongitud, posicion.lng().toFixed(6));
    });

    // Tipear lat/long a mano también mueve el marcador — mismo criterio que
    // inicializarLeaflet.
    const moverMarcadorDesdeInputs = () => {
        const lat = Number.parseFloat(inputLatitud.value);
        const lng = Number.parseFloat(inputLongitud.value);

        if (Number.isFinite(lat) && Number.isFinite(lng)) {
            marcador.setPosition({ lat, lng });
        }
    };

    inputLatitud.addEventListener('change', moverMarcadorDesdeInputs);
    inputLongitud.addEventListener('change', moverMarcadorDesdeInputs);

    // ---------- Polígonos ----------
    const mostrarMedida = () => {
        const total = poligonos.reduce((suma, poligono) => {
            const anillo = poligono.getPath().getArray().map((p) => [p.lng(), p.lat()]);

            return suma + hectareasDeAnillo(anillo);
        }, 0);

        if (medidaTexto) {
            medidaTexto.textContent = total > 0
                ? medidaTexto.dataset.agPropiedadMedidaPlantilla.replace(':hectareas', formatearHectareas(total))
                : '';
        }
    };

    const sincronizarGeometria = () => {
        const anillos = poligonos.map((poligono) => poligono.getPath().getArray().map((p) => [p.lng(), p.lat()]));

        fijarValor(inputGeometria, serializarMultiPolygon(anillos));
        mostrarMedida();
    };

    const quitarTodosLosPoligonos = () => {
        poligonos.forEach((poligono) => poligono.setMap(null));
        poligonos = [];
    };

    const dibujarAnillo = (anillo) => {
        const poligono = new googleMapsNs.Polygon({
            paths: anillo.map(([lng, lat]) => ({ lat, lng })),
            ...estiloPoligonoGoogle(),
        });
        poligono.setMap(mapa);
        poligonos.push(poligono);

        return poligono;
    };

    const aplicarEstado = (valorJson) => {
        quitarTodosLosPoligonos();
        fijarValor(inputGeometria, valorJson);
        leerAnillosGuardados(inputGeometria).forEach(dibujarAnillo);
        sincronizarGeometria();
    };

    const historial = crearHistorial({ boton: botonDeshacer, aplicar: aplicarEstado });

    anillosGuardados.forEach(dibujarAnillo);

    if (anillosGuardados.length > 0) {
        const limites = new googleMapsNs.LatLngBounds();
        anillosGuardados.forEach((anillo) => anillo.forEach(([lng, lat]) => limites.extend({ lat, lng })));
        mapa.fitBounds(limites);
    }

    sincronizarGeometria();

    // ---------- Trazo a mano (mismo criterio que lote-mapa-editor.js: sin
    // drawing.DrawingManager, retirada de la API) ----------
    let modoDibujo = false;
    let poligonoEnCurso = null;
    let clickPendiente = null;

    const salirDeModoDibujo = () => {
        modoDibujo = false;
        window.clearTimeout(clickPendiente);
        poligonoEnCurso?.setMap(null);
        poligonoEnCurso = null;
        mapa.setOptions({ draggableCursor: null, disableDoubleClickZoom: false });
        botonDibujar?.setAttribute('aria-pressed', 'false');
    };

    const agregarVertice = (latLng) => {
        if (!poligonoEnCurso) {
            poligonoEnCurso = new googleMapsNs.Polygon({ paths: [latLng], ...estiloPoligonoGoogle() });
            poligonoEnCurso.setMap(mapa);
        } else {
            poligonoEnCurso.getPath().push(latLng);
        }
    };

    const terminarTrazo = () => {
        const ruta = poligonoEnCurso?.getPath().getArray() ?? [];
        salirDeModoDibujo();

        if (ruta.length < 3) {
            return;
        }

        historial.registrar(inputGeometria.value);
        // dibujarAnillo espera anillos [lng, lat] (mismo formato que
        // `leerAnillosGuardados`) — convierte los `LatLng` del trazo en
        // curso antes de pasarlos, y ya hace el `push` a `poligonos`.
        dibujarAnillo(ruta.map((p) => [p.lng(), p.lat()]));
        sincronizarGeometria();
    };

    mapa.addListener('click', (evento) => {
        if (!modoDibujo) {
            return;
        }

        window.clearTimeout(clickPendiente);
        clickPendiente = window.setTimeout(() => agregarVertice(evento.latLng), 250);
    });

    mapa.addListener('dblclick', () => {
        if (!modoDibujo) {
            return;
        }

        window.clearTimeout(clickPendiente);
        terminarTrazo();
    });

    botonDibujar?.addEventListener('click', () => {
        if (modoDibujo) {
            salirDeModoDibujo();

            return;
        }

        modoDibujo = true;
        mapa.setOptions({ draggableCursor: 'crosshair', disableDoubleClickZoom: true });
        botonDibujar.setAttribute('aria-pressed', 'true');
    });

    botonBorrarTodo?.addEventListener('click', () => {
        if (poligonos.length === 0) {
            return;
        }

        historial.registrar(inputGeometria.value);
        quitarTodosLosPoligonos();
        sincronizarGeometria();
    });

    botonCentrar?.addEventListener('click', () => {
        if (poligonos.length > 0) {
            const limites = new googleMapsNs.LatLngBounds();
            poligonos.forEach((poligono) => poligono.getPath().forEach((punto) => limites.extend(punto)));
            mapa.fitBounds(limites);
        } else {
            mapa.setCenter(marcador.getPosition());
            mapa.setZoom(ZOOM_SIN_GEOMETRIA);
        }
    });

    if (botonCapa) {
        const actualizarBotonCapa = () => {
            const etiqueta = esSatelital ? botonCapa.dataset.agPropiedadMapaCapaCalles : botonCapa.dataset.agPropiedadMapaCapaSatelite;
            botonCapa.title = etiqueta;
            botonCapa.setAttribute('aria-label', etiqueta);
            botonCapa.setAttribute('aria-pressed', String(!esSatelital));
        };

        botonCapa.addEventListener('click', () => {
            esSatelital = !esSatelital;
            mapa.setMapTypeId(esSatelital ? googleMapsNs.MapTypeId.HYBRID : googleMapsNs.MapTypeId.ROADMAP);
            actualizarBotonCapa();
        });

        actualizarBotonCapa();
    }

    window.addEventListener('agrocom:theme-changed', () => {
        poligonos.forEach((poligono) => poligono.setOptions(estiloPoligonoGoogle()));
        poligonoEnCurso?.setOptions(estiloPoligonoGoogle());
    });
}

function inicializar(contenedor) {
    if (contenedor.dataset.agPropiedadMapaListo === '1') {
        return;
    }

    const refs = referenciasDom(contenedor);

    if (!refs.lienzo || !refs.inputGeometria || !refs.inputLatitud || !refs.inputLongitud) {
        return;
    }

    contenedor.dataset.agPropiedadMapaListo = '1';

    const llave = contenedor.dataset.agPropiedadMapaGoogleKey;

    if (contenedor.dataset.agPropiedadMapaProveedor === 'google' && llave) {
        cargarGoogleMaps(llave)
            .then((googleMapsNs) => inicializarGoogle(contenedor, refs, googleMapsNs))
            .catch((error) => {
                console.warn('No se pudo inicializar Google Maps, se usa Leaflet como respaldo.', error);
                inicializarLeaflet(contenedor, refs);
            });

        return;
    }

    inicializarLeaflet(contenedor, refs);
}

document.querySelectorAll('[data-ag-propiedad-mapa]').forEach(inicializar);
