/**
 * organisms/propiedad-mapa-editor.js — editor de coordenadas de una
 * propiedad, en `/panel/propiedades/{propiedad}/mapa` (adenda 16/9/2026 a
 * ADR 0018 punto 1 / ADR 0020: "el editor de mapa multi-polígono se
 * construye en un feature aparte" — esta es esa pieza).
 *
 * DOS piezas sobre el mismo mapa:
 * - Un MARCADOR: el punto de referencia (`latitud`/`longitud`) — ya no se
 *   tipea a mano (los inputs viajan ocultos en el `<form>`): sale de
 *   arrastrar el marcador, de un click en modo "Colocar marcador" o del
 *   buscador de coordenadas.
 * - Uno o más POLÍGONOS ("terrenos"): el perímetro completo de la propiedad,
 *   GeoJSON `MultiPolygon` — a diferencia del editor de Lote (un solo
 *   `Polygon`), acá puede haber varios terrenos separados (islas, caso
 *   "Gamelera", ADR 0020).
 *
 * Mismo proveedor que decide el servidor (`ResolverProveedorMapa`, llega en
 * `data-ag-propiedad-mapa-proveedor`/`-google-key`) que `organisms/lote-mapa-editor.js`
 * — pero es un módulo INDEPENDIENTE, no una extensión de ese archivo: no
 * toca el editor de Lote, que sigue en producción tal cual. Deliberadamente
 * NO comparte código con él más allá de paquetes de node_modules resueltos
 * por separado (ver el comentario de `activarPantallaCompleta` más abajo):
 * compartir el chunk de Leaflet+Geoman entre los dos editores rompe la
 * inicialización en tiempo de ejecución (`L is not defined`, confirmado en
 * vivo el 16/9/2026).
 *
 * Las acciones (dibujar/editar, deshacer, borrar, capa, marcador, zoom
 * completo, centrar, expandir) viven DENTRO del lienzo, en una columna
 * flotante — el mapa ocupa todo el alto disponible de la pantalla, sin barra
 * externa ni scroll propio (pedido directo del dueño, 16/9/2026).
 *
 * Cargado por `import()` dinámico desde app.js, solo cuando la página tiene
 * un `[data-ag-propiedad-mapa]` — mismo criterio que el editor de Lote.
 */

import 'leaflet/dist/leaflet.css';
import '@geoman-io/leaflet-geoman-free/dist/leaflet-geoman.css';
import L from 'leaflet';
import '@geoman-io/leaflet-geoman-free';
import { leerTokenCrudo } from '../shared/color-tokens.js';

/**
 * Copia intencional de `shared/cargador-google-maps.js` (el que usa
 * `lote-mapa-editor.js`) — NO un import compartido, mismo motivo que
 * `activarPantallaCompleta` más abajo: compartir ESTE módulo entre los dos
 * editores es lo que produce el chunk mezclado que revienta con
 * `L is not defined` en tiempo de ejecución (confirmado en vivo, 16/9/2026:
 * el chunk fusionado terminaba mezclando leaflet+geoman con el loader del
 * SDK de Google, en un orden de inicialización que rompe el UMD de Leaflet).
 * `NOMBRE_CALLBACK_GLOBAL` es el mismo string en ambos archivos a propósito:
 * si las dos páginas llegaran a coexistir en el mismo documento (hoy no
 * pasa, cada una es una ruta propia), comparten el mismo callback global y
 * el SDK de Google sigue sin cargarse dos veces.
 */
const NOMBRE_CALLBACK_GOOGLE_MAPS = '__agrocomGoogleMapsListo';

let cargaGoogleMapsEnCurso = null;
let llaveGoogleMapsEnCurso = null;

/**
 * @param {string} llave - `mapas.google_maps_api_key` ya resuelta por el servidor.
 * @returns {Promise<typeof google.maps>} el namespace `google.maps`, listo para usar.
 */
function cargarGoogleMaps(llave) {
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
        script.onerror = () => reject(new Error('No se pudo cargar el SDK de Google Maps'));
        document.head.appendChild(script);
    });

    cargaGoogleMapsEnCurso.catch(() => {
        cargaGoogleMapsEnCurso = null;
        llaveGoogleMapsEnCurso = null;
    });

    return cargaGoogleMapsEnCurso;
}

/**
 * Pantalla completa de un contenedor de mapa — copia intencional de
 * `shared/mapa-pantalla-completa.js` (el módulo que usa `lote-mapa-editor.js`
 * y `dashboard-map.js`), NO un import compartido: importarlo de ahí hace que
 * Rollup fusione el chunk de leaflet+geoman de este archivo con el del
 * editor de Lote (ambos lo referencian), y ese chunk compartido rompe la
 * carga en tiempo de ejecución (`L is not defined` — confirmado en vivo,
 * 16/9/2026). Mismo criterio que ya usa `hectareasDeAnillo` en este mismo
 * archivo: no se comparte por veinte líneas entre dos módulos que tienen que
 * poder cargar cada uno por su cuenta.
 */
const CLASE_RESPALDO_PANTALLA_COMPLETA = 'ag-mapa--pantalla-completa-respaldo';

function activarPantallaCompleta({
    contenedor,
    alRedimensionar,
    boton,
    iconoBoton = null,
    etiquetaEntrar,
    etiquetaSalir,
}) {
    let estabaFullscreen = false;

    const actualizar = (activo) => {
        boton.setAttribute('aria-pressed', String(activo));

        const etiqueta = activo ? etiquetaSalir : etiquetaEntrar;
        boton.dataset.agPropiedadMapaTooltip = etiqueta;
        boton.setAttribute('aria-label', etiqueta);

        if (iconoBoton) {
            iconoBoton.textContent = activo ? 'fullscreen_exit' : 'fullscreen';
        }

        requestAnimationFrame(alRedimensionar);
    };

    boton.addEventListener('click', async () => {
        if (contenedor.classList.contains(CLASE_RESPALDO_PANTALLA_COMPLETA)) {
            contenedor.classList.remove(CLASE_RESPALDO_PANTALLA_COMPLETA);
            actualizar(false);

            return;
        }

        if (document.fullscreenElement === contenedor) {
            await document.exitFullscreen?.();

            return;
        }

        if (contenedor.requestFullscreen) {
            try {
                await contenedor.requestFullscreen();

                return;
            } catch {
                // El navegador negó el pedido: sigue al respaldo en vez de
                // dejar el botón sin efecto.
            }
        }

        contenedor.classList.add(CLASE_RESPALDO_PANTALLA_COMPLETA);
        actualizar(true);
    });

    document.addEventListener('fullscreenchange', () => {
        const activo = document.fullscreenElement === contenedor;

        if (!activo && !estabaFullscreen) {
            return;
        }

        estabaFullscreen = activo;
        actualizar(activo);
    });

    contenedor.addEventListener('keydown', (evento) => {
        if (evento.key === 'Escape' && contenedor.classList.contains(CLASE_RESPALDO_PANTALLA_COMPLETA)) {
            contenedor.classList.remove(CLASE_RESPALDO_PANTALLA_COMPLETA);
            actualizar(false);
        }
    });
}

// Último respaldo cuando la propiedad no tiene marcador, ni polígono, ni
// centro de referencia por departamento (`PropiedadMapaController`): Santa
// Cruz de la Sierra, Bolivia — pedido directo del dueño.
const CENTRO_POR_DEFECTO = { lat: -17.783327, lng: -63.18214 };
// Vista amplia por defecto (pedido directo del dueño: "que no sea tan
// cerca") — a nivel 12 un único punto sin perímetro quedaba con la cámara
// casi pegada al suelo.
const ZOOM_SIN_GEOMETRIA = 12;
const ZOOM_MAXIMO_AL_ENCUADRAR = 17;
const LIMITE_HISTORIAL = 20;

// Azul de Google Maps: fallback del color del polígono cuando la propiedad
// no tiene `color` propio cargado. Declarado en tokens/ (CLAUDE.md invariante
// 11: todo literal de color, de marca o no, vive ahí) y leído acá con el
// mismo mecanismo que ya usan los otros editores de mapa — cacheado porque
// es fijo, no reasignado por tema.
let colorPorDefectoCache = null;

function colorPorDefecto() {
    colorPorDefectoCache ??= leerTokenCrudo('--ag-color-mapa-poligono-defecto');

    return colorPorDefectoCache;
}

const ATRIBUCION_ESRI = 'Tiles &copy; Esri — Source: Esri, Maxar, Earthstar Geographics';
const URL_CAPA_SATELITE = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
// Nombres de calles/avenidas y límites superpuestos sobre la imagen satelital:
// la vista "híbrida" de Esri, equivalente a MapTypeId.HYBRID de Google — un
// satelital solo no sirve de referencia contra los marcadores ya cargados.
const URL_CAPA_ETIQUETAS = 'https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}';
const URL_CAPA_CALLES = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}';

function estiloPoligono(colorPropiedad) {
    const color = colorPropiedad || colorPorDefecto();

    return { color, fillColor: color, fillOpacity: 0.2, weight: 3 };
}

function estiloPoligonoGoogle(colorPropiedad) {
    const color = colorPropiedad || colorPorDefecto();

    return { strokeColor: color, strokeWeight: 3, fillColor: color, fillOpacity: 0.2 };
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

/** Config leída una sola vez del `data-*` del contenedor — ni color ni
 *  centro de referencia cambian durante la vida del editor. */
function leerConfiguracion(contenedor) {
    const colorPropiedad = contenedor.dataset.agPropiedadMapaColor || null;

    const crudo = contenedor.dataset.agPropiedadMapaCentroDefecto;
    const [lat, lng] = (crudo ?? '').split(',').map(Number.parseFloat);
    const centroDefecto = Number.isFinite(lat) && Number.isFinite(lng) ? { lat, lng } : CENTRO_POR_DEFECTO;

    return { colorPropiedad, centroDefecto };
}

function referenciasDom(contenedor) {
    return {
        lienzo: contenedor.querySelector('[data-ag-propiedad-mapa-lienzo]'),
        mapaDiv: contenedor.querySelector('[data-ag-propiedad-mapa-mapa]'),
        inputGeometria: contenedor.querySelector('[data-ag-propiedad-geometria]'),
        inputLatitud: contenedor.querySelector('[data-ag-propiedad-latitud]'),
        inputLongitud: contenedor.querySelector('[data-ag-propiedad-longitud]'),
        medidaTexto: contenedor.querySelector('[data-ag-propiedad-medida-texto]'),
        coordenadasTexto: contenedor.querySelector('[data-ag-propiedad-coordenadas-texto]'),
        inputBuscador: contenedor.querySelector('[data-ag-propiedad-buscador-input]'),
        botonBuscador: contenedor.querySelector('[data-ag-propiedad-buscador-boton]'),
        buscadorError: contenedor.querySelector('[data-ag-propiedad-buscador-error]'),
        botonDibujar: contenedor.querySelector('[data-ag-propiedad-mapa-accion="dibujar"]'),
        botonBorrarUltimo: contenedor.querySelector('[data-ag-propiedad-mapa-accion="borrar-ultimo"]'),
        botonDeshacer: contenedor.querySelector('[data-ag-propiedad-mapa-accion="deshacer"]'),
        botonCapa: contenedor.querySelector('[data-ag-propiedad-mapa-accion="capa"]'),
        botonMarcador: contenedor.querySelector('[data-ag-propiedad-mapa-accion="marcador"]'),
        botonZoomCompleto: contenedor.querySelector('[data-ag-propiedad-mapa-accion="zoom-completo"]'),
        botonExpandir: contenedor.querySelector('[data-ag-propiedad-mapa-accion="expandir"]'),
        iconoExpandir: contenedor.querySelector('[data-ag-propiedad-mapa-icono-expandir]'),
        iconoMarcador: contenedor.querySelector('[data-ag-propiedad-mapa-icono-marcador]'),
        iconoDibujar: contenedor.querySelector('[data-ag-propiedad-mapa-icono-dibujar]'),
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

/** Acepta "lat, lng" o "lat lng" (con o sin coma) — lo que se copia de
 *  cualquier app de mapas o GPS. */
function parsearCoordenadas(texto) {
    const coincidencia = texto.trim().match(/^(-?\d+(?:\.\d+)?)[,\s]+(-?\d+(?:\.\d+)?)$/);

    if (!coincidencia) {
        return null;
    }

    const lat = Number.parseFloat(coincidencia[1]);
    const lng = Number.parseFloat(coincidencia[2]);

    if (!Number.isFinite(lat) || !Number.isFinite(lng) || Math.abs(lat) > 90 || Math.abs(lng) > 180) {
        return null;
    }

    return { lat, lng };
}

/** El buscador SOLO centra la vista — no mueve el marcador (para eso está
 *  la acción "Colocar marcador"), así el usuario puede ubicar un punto de
 *  referencia visual sin perder el marcador ya puesto. No es un `<form>`
 *  (vive dentro del `<form>` del formulario, HTML no admite anidarlos): el
 *  botón dispara la búsqueda y Enter en el input hace lo mismo, sin dejar
 *  que ese Enter llegue a disparar el submit del formulario contenedor. */
function inicializarBuscador(refs, centrarEn) {
    const { inputBuscador, botonBuscador, buscadorError } = refs;

    if (!inputBuscador) {
        return;
    }

    const intentarCentrar = () => {
        const coordenadas = parsearCoordenadas(inputBuscador.value);

        if (!coordenadas) {
            buscadorError?.removeAttribute('hidden');

            return;
        }

        buscadorError?.setAttribute('hidden', '');
        centrarEn(coordenadas.lat, coordenadas.lng);
    };

    botonBuscador?.addEventListener('click', intentarCentrar);

    inputBuscador.addEventListener('keydown', (evento) => {
        if (evento.key === 'Enter') {
            evento.preventDefault();
            intentarCentrar();
        }
    });

    inputBuscador.addEventListener('input', () => buscadorError?.setAttribute('hidden', ''));
}

function actualizarIndicadorCoordenadas(indicador, lat, lng) {
    if (indicador) {
        indicador.textContent = lat === null || lng === null ? '' : `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
    }
}

function inicializarLeaflet(contenedor, refs) {
    const {
        lienzo, mapaDiv, inputGeometria, inputLatitud, inputLongitud, medidaTexto, coordenadasTexto,
        botonDibujar, iconoDibujar, botonBorrarUltimo, botonDeshacer, botonCapa, botonMarcador, iconoMarcador,
        botonZoomCompleto, botonExpandir, iconoExpandir,
    } = refs;

    // El lápiz cambia de "Dibujar" a "Terminar" mientras hay un trazo en
    // curso — pedido directo del dueño: que el propio ícono anticipe que
    // volver a presionarlo confirma el terreno, no solo cancela.
    const actualizarBotonDibujar = (activo) => {
        if (!botonDibujar) {
            return;
        }

        const etiqueta = activo ? botonDibujar.dataset.agPropiedadMapaDibujarTerminar : botonDibujar.dataset.agPropiedadMapaDibujarIniciar;
        botonDibujar.dataset.agPropiedadMapaTooltip = etiqueta;
        botonDibujar.setAttribute('aria-label', etiqueta);
        botonDibujar.setAttribute('aria-pressed', String(activo));

        if (iconoDibujar) {
            iconoDibujar.textContent = activo ? 'account_tree' : 'draw';
        }
    };

    const { colorPropiedad, centroDefecto } = leerConfiguracion(contenedor);

    const anillosGuardados = leerAnillosGuardados(inputGeometria);
    const latInicial = Number.parseFloat(inputLatitud.value);
    const lngInicial = Number.parseFloat(inputLongitud.value);
    const tieneMarcadorInicial = Number.isFinite(latInicial) && Number.isFinite(lngInicial);

    const centroInicial = tieneMarcadorInicial
        ? { lat: latInicial, lng: lngInicial }
        : (anillosGuardados[0]?.[0] ? { lat: anillosGuardados[0][0][1], lng: anillosGuardados[0][0][0] } : centroDefecto);

    const mapa = L.map(mapaDiv, { zoomControl: false }).setView([centroInicial.lat, centroInicial.lng], ZOOM_SIN_GEOMETRIA);
    L.control.zoom({ position: 'bottomleft' }).addTo(mapa);

    let esSatelital = true;
    let capaBase = L.tileLayer(URL_CAPA_SATELITE, { attribution: ATRIBUCION_ESRI, maxZoom: 19 }).addTo(mapa);
    let capaEtiquetas = L.tileLayer(URL_CAPA_ETIQUETAS, { attribution: ATRIBUCION_ESRI, maxZoom: 19 }).addTo(mapa);

    const capaPoligonos = L.featureGroup().addTo(mapa);

    // ---------- Marcador (punto de referencia): un único marcador por
    // propiedad, que se crea/destruye con la acción "Colocar"/"Sacar
    // marcador" — no existe de entrada si la propiedad todavía no tiene uno. ----------
    let marcador = null;

    const actualizarPosicionMarcador = (lat, lng) => {
        fijarValor(inputLatitud, lat.toFixed(6));
        fijarValor(inputLongitud, lng.toFixed(6));
        actualizarIndicadorCoordenadas(coordenadasTexto, lat, lng);
    };

    const actualizarBotonMarcador = () => {
        if (!botonMarcador) {
            return;
        }

        const colocado = marcador !== null;
        const etiqueta = colocado ? botonMarcador.dataset.agPropiedadMapaMarcadorSacar : botonMarcador.dataset.agPropiedadMapaMarcadorColocar;
        botonMarcador.dataset.agPropiedadMapaTooltip = etiqueta;
        botonMarcador.setAttribute('aria-label', etiqueta);

        if (iconoMarcador) {
            iconoMarcador.textContent = colocado ? 'location_off' : 'add_location';
        }
    };

    const crearMarcadorEn = (lat, lng) => {
        marcador = L.marker([lat, lng], { draggable: true }).addTo(mapa);
        marcador.on('dragend', () => {
            const posicion = marcador.getLatLng();
            actualizarPosicionMarcador(posicion.lat, posicion.lng);
        });
        actualizarPosicionMarcador(lat, lng);
        actualizarBotonMarcador();
    };

    const quitarMarcador = () => {
        if (!marcador) {
            return;
        }

        mapa.removeLayer(marcador);
        marcador = null;
        fijarValor(inputLatitud, '');
        fijarValor(inputLongitud, '');
        actualizarIndicadorCoordenadas(coordenadasTexto, null, null);
        actualizarBotonMarcador();
    };

    if (tieneMarcadorInicial) {
        crearMarcadorEn(latInicial, lngInicial);
    } else {
        actualizarBotonMarcador();
    }

    // ---------- Modo "Colocar marcador": el próximo click del mapa lo crea ----------
    let modoColocarMarcador = false;

    const salirDeModoColocarMarcador = () => {
        modoColocarMarcador = false;
        lienzo.classList.remove('ag-propiedad-mapa__lienzo--colocando');
        botonMarcador?.setAttribute('aria-pressed', 'false');
    };

    botonMarcador?.addEventListener('click', () => {
        if (marcador) {
            quitarMarcador();

            return;
        }

        if (modoColocarMarcador) {
            salirDeModoColocarMarcador();

            return;
        }

        if (mapa.pm.globalDrawModeEnabled()) {
            mapa.pm.disableDraw();
            actualizarBotonDibujar(false);
        }

        modoColocarMarcador = true;
        lienzo.classList.add('ag-propiedad-mapa__lienzo--colocando');
        botonMarcador.setAttribute('aria-pressed', 'true');
    });

    mapa.on('click', (evento) => {
        if (!modoColocarMarcador) {
            return;
        }

        crearMarcadorEn(evento.latlng.lat, evento.latlng.lng);
        salirDeModoColocarMarcador();
    });

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

    // Habilita edición de vértices por arrastre en la capa recién agregada —
    // "editar un polígono" no es un modo separado: cualquier terreno ya
    // trazado se puede ajustar en cualquier momento, sin un botón aparte.
    const agregarPoligono = (anillo) => {
        const capa = L.polygon(anillo.map(([lng, lat]) => [lat, lng]), estiloPoligono(colorPropiedad));
        capa.pm.enable({ allowSelfIntersection: false });
        capaPoligonos.addLayer(capa);

        return capa;
    };

    const aplicarEstado = (valorJson) => {
        capaPoligonos.clearLayers();
        fijarValor(inputGeometria, valorJson);
        leerAnillosGuardados(inputGeometria).forEach(agregarPoligono);
        sincronizarGeometria();
    };

    const historial = crearHistorial({ boton: botonDeshacer, aplicar: aplicarEstado });

    anillosGuardados.forEach(agregarPoligono);

    if (anillosGuardados.length > 0) {
        mapa.fitBounds(capaPoligonos.getBounds(), { padding: [24, 24], maxZoom: ZOOM_MAXIMO_AL_ENCUADRAR });
    }

    sincronizarGeometria();

    mapa.pm.setLang('es');
    mapa.pm.setPathOptions(estiloPoligono(colorPropiedad));

    mapa.on('pm:create', ({ layer }) => {
        historial.registrar(inputGeometria.value);
        layer.setStyle(estiloPoligono(colorPropiedad));
        layer.pm.enable({ allowSelfIntersection: false });
        // A diferencia del editor de Lote: NO se limpia lo anterior — cada
        // trazo nuevo es un terreno ADICIONAL (islas, caso "Gamelera").
        capaPoligonos.addLayer(layer);
        sincronizarGeometria();
        actualizarBotonDibujar(false);
    });

    // Cualquier edición de vértices de un polígono ya trazado (arrastre,
    // agregar/quitar vértice) tiene que volver a serializar la geometría.
    capaPoligonos.on('pm:edit pm:markerdragend pm:vertexadded pm:vertexremoved', () => {
        historial.registrar(inputGeometria.value);
        sincronizarGeometria();
    });

    botonDibujar?.addEventListener('click', () => {
        if (mapa.pm.globalDrawModeEnabled()) {
            // Volver a presionar el lápiz CONFIRMA el terreno en curso (si
            // ya tiene 3+ vértices) en vez de solo cancelar el trazo —
            // pedido directo del dueño: una acción explícita de "listo", sin
            // depender de acertarle al primer vértice para cerrar. Geoman no
            // expone esto en su API pública; `_finishShape` es el método que
            // usa su propia UI de "Finish" — si en algún update deja de
            // existir, el `typeof` cae al comportamiento anterior (cancela)
            // en vez de romper.
            const manejador = mapa.pm.Draw?.Polygon;

            if (manejador && typeof manejador._finishShape === 'function') {
                manejador._finishShape();
            } else {
                mapa.pm.disableDraw();
            }

            actualizarBotonDibujar(false);

            return;
        }

        salirDeModoColocarMarcador();
        mapa.pm.enableDraw('Polygon');
        actualizarBotonDibujar(true);
    });

    // Borra de a un terreno por click, el ÚLTIMO dibujado primero — pedido
    // directo del dueño: no todo de golpe. `getLayers()` respeta el orden de
    // inserción (Leaflet asigna `_leaflet_id` incremental como clave), así
    // que el último elemento del array es siempre el terreno más reciente.
    botonBorrarUltimo?.addEventListener('click', () => {
        const capas = capaPoligonos.getLayers();

        if (capas.length === 0) {
            return;
        }

        historial.registrar(inputGeometria.value);
        capaPoligonos.removeLayer(capas[capas.length - 1]);
        sincronizarGeometria();
    });

    // Bounds de todo lo que exista hoy (polígonos + marcador) — `null` si el
    // mapa está vacío (propiedad nueva, sin nada cargado todavía).
    const limitesActuales = () => {
        const puntos = [];
        capaPoligonos.eachLayer((capa) => capa.getLatLngs()[0].forEach((punto) => puntos.push(punto)));

        if (marcador) {
            puntos.push(marcador.getLatLng());
        }

        return puntos.length > 0 ? L.latLngBounds(puntos) : null;
    };

    botonZoomCompleto?.addEventListener('click', () => {
        const limites = limitesActuales();

        if (limites) {
            mapa.fitBounds(limites, { padding: [24, 24], maxZoom: ZOOM_MAXIMO_AL_ENCUADRAR });
        } else {
            mapa.setView(centroInicial, ZOOM_SIN_GEOMETRIA);
        }
    });

    if (botonCapa) {
        const actualizarBotonCapa = () => {
            const etiqueta = esSatelital ? botonCapa.dataset.agPropiedadMapaCapaCalles : botonCapa.dataset.agPropiedadMapaCapaSatelite;
            botonCapa.dataset.agPropiedadMapaTooltip = etiqueta;
            botonCapa.setAttribute('aria-label', etiqueta);
            botonCapa.setAttribute('aria-pressed', String(!esSatelital));
        };

        botonCapa.addEventListener('click', () => {
            esSatelital = !esSatelital;
            mapa.removeLayer(capaBase);

            if (capaEtiquetas) {
                mapa.removeLayer(capaEtiquetas);
                capaEtiquetas = null;
            }

            capaBase = L.tileLayer(esSatelital ? URL_CAPA_SATELITE : URL_CAPA_CALLES, { attribution: ATRIBUCION_ESRI, maxZoom: 19 }).addTo(mapa);
            capaBase.bringToBack();

            // Solo el satelital necesita el overlay de etiquetas — la capa
            // de calles ya las trae dibujadas.
            if (esSatelital) {
                capaEtiquetas = L.tileLayer(URL_CAPA_ETIQUETAS, { attribution: ATRIBUCION_ESRI, maxZoom: 19 }).addTo(mapa);
            }

            actualizarBotonCapa();
        });

        actualizarBotonCapa();
    }

    if (botonExpandir) {
        activarPantallaCompleta({
            contenedor: lienzo,
            alRedimensionar: () => mapa.invalidateSize(),
            boton: botonExpandir,
            iconoBoton: iconoExpandir,
            etiquetaEntrar: botonExpandir.dataset.agPropiedadMapaExpandirEntrar ?? botonExpandir.dataset.agPropiedadMapaTooltip,
            etiquetaSalir: botonExpandir.dataset.agPropiedadMapaExpandirSalir ?? botonExpandir.dataset.agPropiedadMapaTooltip,
        });
    }

    inicializarBuscador(refs, (lat, lng) => {
        mapa.setView([lat, lng], Math.max(mapa.getZoom(), ZOOM_SIN_GEOMETRIA));
    });

    requestAnimationFrame(() => mapa.invalidateSize());
}

function inicializarGoogle(contenedor, refs, googleMapsNs) {
    const {
        lienzo, mapaDiv, inputGeometria, inputLatitud, inputLongitud, medidaTexto, coordenadasTexto,
        botonDibujar, iconoDibujar, botonBorrarUltimo, botonDeshacer, botonCapa, botonMarcador, iconoMarcador,
        botonZoomCompleto, botonExpandir, iconoExpandir,
    } = refs;

    // El lápiz cambia de "Dibujar" a "Terminar" mientras hay un trazo en
    // curso — pedido directo del dueño: que el propio ícono anticipe que
    // volver a presionarlo confirma el terreno, no solo cancela.
    const actualizarBotonDibujar = (activo) => {
        if (!botonDibujar) {
            return;
        }

        const etiqueta = activo ? botonDibujar.dataset.agPropiedadMapaDibujarTerminar : botonDibujar.dataset.agPropiedadMapaDibujarIniciar;
        botonDibujar.dataset.agPropiedadMapaTooltip = etiqueta;
        botonDibujar.setAttribute('aria-label', etiqueta);
        botonDibujar.setAttribute('aria-pressed', String(activo));

        if (iconoDibujar) {
            iconoDibujar.textContent = activo ? 'account_tree' : 'draw';
        }
    };

    const { colorPropiedad, centroDefecto } = leerConfiguracion(contenedor);

    const anillosGuardados = leerAnillosGuardados(inputGeometria);
    const latInicial = Number.parseFloat(inputLatitud.value);
    const lngInicial = Number.parseFloat(inputLongitud.value);
    const tieneMarcadorInicial = Number.isFinite(latInicial) && Number.isFinite(lngInicial);

    const centroInicial = tieneMarcadorInicial
        ? { lat: latInicial, lng: lngInicial }
        : (anillosGuardados[0]?.[0] ? { lat: anillosGuardados[0][0][1], lng: anillosGuardados[0][0][0] } : centroDefecto);

    const mapa = new googleMapsNs.Map(mapaDiv, {
        center: centroInicial,
        zoom: ZOOM_SIN_GEOMETRIA,
        mapTypeId: googleMapsNs.MapTypeId.HYBRID,
        isFractionalZoomEnabled: true,
        streetViewControl: false,
        mapTypeControl: false,
        fullscreenControl: false,
        // Zoom nativo abajo a la izquierda: la columna de acciones propia
        // vive arriba a la derecha (mismo acomodo que la rama Leaflet).
        zoomControlOptions: { position: googleMapsNs.ControlPosition.LEFT_BOTTOM },
    });

    let esSatelital = true;
    let poligonos = [];

    // ---------- Marcador: un único marcador por propiedad, que se
    // crea/destruye con la acción "Colocar"/"Sacar marcador". ----------
    let marcador = null;

    const actualizarPosicionMarcador = (lat, lng) => {
        fijarValor(inputLatitud, lat.toFixed(6));
        fijarValor(inputLongitud, lng.toFixed(6));
        actualizarIndicadorCoordenadas(coordenadasTexto, lat, lng);
    };

    const actualizarBotonMarcador = () => {
        if (!botonMarcador) {
            return;
        }

        const colocado = marcador !== null;
        const etiqueta = colocado ? botonMarcador.dataset.agPropiedadMapaMarcadorSacar : botonMarcador.dataset.agPropiedadMapaMarcadorColocar;
        botonMarcador.dataset.agPropiedadMapaTooltip = etiqueta;
        botonMarcador.setAttribute('aria-label', etiqueta);

        if (iconoMarcador) {
            iconoMarcador.textContent = colocado ? 'location_off' : 'add_location';
        }
    };

    const crearMarcadorEn = (lat, lng) => {
        if (marcador) {
            marcador.setPosition({ lat, lng });
        } else {
            marcador = new googleMapsNs.Marker({ position: { lat, lng }, map: mapa, draggable: true });
            marcador.addListener('dragend', () => {
                const posicion = marcador.getPosition();
                actualizarPosicionMarcador(posicion.lat(), posicion.lng());
            });
        }

        actualizarPosicionMarcador(lat, lng);
        actualizarBotonMarcador();
    };

    const quitarMarcador = () => {
        if (!marcador) {
            return;
        }

        marcador.setMap(null);
        marcador = null;
        fijarValor(inputLatitud, '');
        fijarValor(inputLongitud, '');
        actualizarIndicadorCoordenadas(coordenadasTexto, null, null);
        actualizarBotonMarcador();
    };

    if (tieneMarcadorInicial) {
        crearMarcadorEn(latInicial, lngInicial);
    } else {
        actualizarBotonMarcador();
    }

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

    // `editable: true`: los vértices de un terreno ya trazado se pueden
    // arrastrar en cualquier momento, sin un modo "editar" aparte — mismo
    // criterio que `layer.pm.enable()` en la rama Leaflet.
    const dibujarAnillo = (anillo) => {
        const poligono = new googleMapsNs.Polygon({
            paths: anillo.map(([lng, lat]) => ({ lat, lng })),
            editable: true,
            ...estiloPoligonoGoogle(colorPropiedad),
        });
        poligono.setMap(mapa);
        poligonos.push(poligono);

        const ruta = poligono.getPath();
        googleMapsNs.event.addListener(ruta, 'set_at', sincronizarGeometria);
        googleMapsNs.event.addListener(ruta, 'insert_at', sincronizarGeometria);
        googleMapsNs.event.addListener(ruta, 'remove_at', sincronizarGeometria);

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

    // ---------- Modo de interacción con click: "Colocar marcador" o trazo
    // a mano del polígono (mismo criterio que lote-mapa-editor.js: sin
    // drawing.DrawingManager, retirada de la API) — mutuamente excluyentes,
    // un solo listener de click decide cuál aplica. ----------
    let modoDibujo = false;
    let modoColocarMarcador = false;
    let poligonoEnCurso = null;
    let clickPendiente = null;

    const salirDeModoDibujo = () => {
        modoDibujo = false;
        window.clearTimeout(clickPendiente);
        poligonoEnCurso?.setMap(null);
        poligonoEnCurso = null;
        actualizarBotonDibujar(false);
    };

    const salirDeModoColocarMarcador = () => {
        modoColocarMarcador = false;
        botonMarcador?.setAttribute('aria-pressed', 'false');
    };

    const actualizarCursor = () => {
        const activo = modoDibujo || modoColocarMarcador;
        mapa.setOptions({ draggableCursor: activo ? 'crosshair' : null, disableDoubleClickZoom: modoDibujo });
    };

    const agregarVertice = (latLng) => {
        if (!poligonoEnCurso) {
            poligonoEnCurso = new googleMapsNs.Polygon({ paths: [latLng], ...estiloPoligonoGoogle(colorPropiedad) });
            poligonoEnCurso.setMap(mapa);
        } else {
            poligonoEnCurso.getPath().push(latLng);
        }
    };

    const terminarTrazo = () => {
        const ruta = poligonoEnCurso?.getPath().getArray() ?? [];
        salirDeModoDibujo();
        actualizarCursor();

        if (ruta.length < 3) {
            return;
        }

        historial.registrar(inputGeometria.value);
        dibujarAnillo(ruta.map((p) => [p.lng(), p.lat()]));
        sincronizarGeometria();
    };

    mapa.addListener('click', (evento) => {
        if (modoColocarMarcador) {
            crearMarcadorEn(evento.latLng.lat(), evento.latLng.lng());
            salirDeModoColocarMarcador();
            actualizarCursor();

            return;
        }

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
            // Volver a presionar el lápiz CONFIRMA el terreno en curso (si
            // ya tiene 3+ vértices) en vez de solo cancelar — pedido directo
            // del dueño: una acción explícita de "listo", sin depender del
            // doble click. `terminarTrazo()` ya sale del modo y descarta el
            // trazo si tiene menos de 3 puntos.
            terminarTrazo();
            actualizarCursor();

            return;
        }

        salirDeModoColocarMarcador();
        modoDibujo = true;
        actualizarBotonDibujar(true);
        actualizarCursor();
    });

    botonMarcador?.addEventListener('click', () => {
        if (marcador) {
            quitarMarcador();

            return;
        }

        if (modoColocarMarcador) {
            salirDeModoColocarMarcador();
            actualizarCursor();

            return;
        }

        salirDeModoDibujo();
        modoColocarMarcador = true;
        botonMarcador.setAttribute('aria-pressed', 'true');
        actualizarCursor();
    });

    // Borra de a un terreno por click, el ÚLTIMO dibujado primero — pedido
    // directo del dueño: no todo de golpe.
    botonBorrarUltimo?.addEventListener('click', () => {
        if (poligonos.length === 0) {
            return;
        }

        historial.registrar(inputGeometria.value);
        poligonos.pop().setMap(null);
        sincronizarGeometria();
    });

    // Bounds de todo lo que exista hoy (polígonos + marcador) — `null` si el
    // mapa está vacío (propiedad nueva, sin nada cargado todavía).
    const limitesActuales = () => {
        if (poligonos.length === 0 && !marcador) {
            return null;
        }

        const limites = new googleMapsNs.LatLngBounds();
        poligonos.forEach((poligono) => poligono.getPath().forEach((punto) => limites.extend(punto)));

        if (marcador) {
            limites.extend(marcador.getPosition());
        }

        return limites;
    };

    botonZoomCompleto?.addEventListener('click', () => {
        const limites = limitesActuales();

        if (limites) {
            mapa.fitBounds(limites);
        } else {
            mapa.setCenter(centroInicial);
            mapa.setZoom(ZOOM_SIN_GEOMETRIA);
        }
    });

    if (botonCapa) {
        const actualizarBotonCapa = () => {
            const etiqueta = esSatelital ? botonCapa.dataset.agPropiedadMapaCapaCalles : botonCapa.dataset.agPropiedadMapaCapaSatelite;
            botonCapa.dataset.agPropiedadMapaTooltip = etiqueta;
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

    if (botonExpandir) {
        activarPantallaCompleta({
            contenedor: lienzo,
            alRedimensionar: () => googleMapsNs.event.trigger(mapa, 'resize'),
            boton: botonExpandir,
            iconoBoton: iconoExpandir,
            etiquetaEntrar: botonExpandir.dataset.agPropiedadMapaExpandirEntrar ?? botonExpandir.dataset.agPropiedadMapaTooltip,
            etiquetaSalir: botonExpandir.dataset.agPropiedadMapaExpandirSalir ?? botonExpandir.dataset.agPropiedadMapaTooltip,
        });
    }

    inicializarBuscador(refs, (lat, lng) => {
        mapa.setCenter({ lat, lng });
        mapa.setZoom(Math.max(mapa.getZoom(), ZOOM_SIN_GEOMETRIA));
    });
}

const PREFIJO_DRAFT_MAPA = 'agrocom:propiedad-mapa:';

function claveDraftMapa(propiedadId) {
    return `${PREFIJO_DRAFT_MAPA}${propiedadId}`;
}

/** Progreso sin guardar restaurado desde `localStorage` — pedido directo del
 *  dueño: recargar la página sin apretar "Guardar" no puede perder el
 *  dibujo. Best-effort: `localStorage` puede no estar disponible (modo
 *  privado, cuota agotada) y eso nunca tiene que romper la carga del mapa,
 *  solo perder la conveniencia del autoguardado. */
function restaurarDraftSiExiste(contenedor, refs) {
    const propiedadId = contenedor.dataset.agPropiedadMapaId;

    if (!propiedadId) {
        return;
    }

    let draft;

    try {
        const crudo = localStorage.getItem(claveDraftMapa(propiedadId));
        draft = crudo ? JSON.parse(crudo) : null;
    } catch {
        return;
    }

    if (!draft) {
        return;
    }

    if (typeof draft.latitud === 'string') {
        fijarValor(refs.inputLatitud, draft.latitud);
    }

    if (typeof draft.longitud === 'string') {
        fijarValor(refs.inputLongitud, draft.longitud);
    }

    if (typeof draft.geometria === 'string') {
        fijarValor(refs.inputGeometria, draft.geometria);
    }
}

/** Guarda cada cambio de inmediato — no hay un borrador a medio armar que
 *  valga la pena debounciar, y perder el último trazo por un debounce
 *  cancelado justo antes de recargar es el escenario que se quiere evitar.
 *  Se limpia al enviar el formulario: el POST tradicional redirige de
 *  vuelta a esta misma pantalla con los datos ya persistidos en el servidor. */
function inicializarAutoguardado(contenedor, refs) {
    const propiedadId = contenedor.dataset.agPropiedadMapaId;

    if (!propiedadId) {
        return;
    }

    const clave = claveDraftMapa(propiedadId);

    const guardar = () => {
        try {
            localStorage.setItem(clave, JSON.stringify({
                latitud: refs.inputLatitud.value,
                longitud: refs.inputLongitud.value,
                geometria: refs.inputGeometria.value,
            }));
        } catch {
            // Autoguardado best-effort — ver el comentario de restaurarDraftSiExiste.
        }
    };

    [refs.inputLatitud, refs.inputLongitud, refs.inputGeometria].forEach((input) => {
        input.addEventListener('input', guardar);
    });

    contenedor.closest('form')?.addEventListener('submit', () => {
        try {
            localStorage.removeItem(clave);
        } catch {
            // Ver arriba.
        }
    });
}

function inicializar(contenedor) {
    if (contenedor.dataset.agPropiedadMapaListo === '1') {
        return;
    }

    const refs = referenciasDom(contenedor);

    if (!refs.lienzo || !refs.mapaDiv || !refs.inputGeometria || !refs.inputLatitud || !refs.inputLongitud) {
        return;
    }

    contenedor.dataset.agPropiedadMapaListo = '1';

    restaurarDraftSiExiste(contenedor, refs);
    inicializarAutoguardado(contenedor, refs);

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
