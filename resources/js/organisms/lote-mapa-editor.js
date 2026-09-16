/**
 * organisms/lote-mapa-editor.js — editor del perímetro de un lote sobre mapa
 * satelital, en el formulario de campos. Dos proveedores posibles (tarea 79,
 * HU-56):
 *
 * - **Leaflet + Esri World Imagery + Leaflet-Geoman**: el camino de siempre
 *   (tarea 68), y el que corre siempre que no hay llave de Google Maps
 *   configurada. Sigue funcionando completo — Google Maps Platform se
 *   factura por uso y la llave puede faltar o agotarse.
 * - **Google Maps JS API** (trazo del polígono a mano, ver docblock de
 *   `inicializarGoogle` — sin `google.maps.drawing`, retirada de la API):
 *   solo cuando
 *   `/panel/configuracion` tiene cargada `mapas.google_maps_api_key`
 *   (tarea 78) y el SDK carga bien. Si la carga falla, cae a Leaflet en vez
 *   de dejar el formulario sin mapa.
 *
 * El proveedor lo decide el SERVIDOR (`ResolverProveedorMapa`, módulo
 * Comercial) y llega en `data-ag-lote-mapa-proveedor`/`-google-key` del
 * contenedor — este archivo no vuelve a consultar `/panel/configuracion`.
 *
 * Contrato con el servidor SIN CAMBIOS, con cualquiera de los dos
 * proveedores: el valor sigue viajando como el mismo string JSON
 * (`{type: 'Polygon', coordinates: [[[lng, lat], ...]]}`) en el input oculto
 * que el Form Request ya validaba. Este módulo solo cambia cómo se produce
 * ese string — el proveedor nunca toca `com_lotes.geometria`.
 *
 * Cargado por `import()` dinámico desde app.js, solo cuando la página tiene un
 * `[data-ag-lote-mapa]` — Leaflet pesa, y no entra en el resto del panel. El
 * SDK de Google se carga, a su vez, por su propio `<script>` diferido
 * (`shared/cargador-google-maps.js`), solo si hay llave.
 *
 * Barra de acciones propia con Material Symbols y texto en español (dibujar,
 * editar vértices, mover, borrar, deshacer, centrar, alternar capa) y
 * pantalla completa de verdad (`shared/mapa-pantalla-completa.js`),
 * IGUALES para los dos proveedores — lo que cambia por debajo es qué API de
 * mapas responde a cada botón, nunca la barra en sí.
 *
 * Colores desde tokens (shared/color-tokens.js), nunca hex acá: CLAUDE.md
 * invariante 11, y el editor se abre en ambos temas.
 *
 * Capas de REFERENCIA (16/9/2026, pedido directo): el límite de la
 * propiedad elegida y sus lotes ya cargados se dibujan punteados y sin
 * interacción, para no cargar un lote nuevo a ciegas ni superpuesto con uno
 * existente — se leen de `[data-ag-lote-mapa-referencia]`
 * (`lotes/_formulario.blade.php`, ver `LotesController::referenciaMapa()`)
 * y se redibujan solas al cambiar el `<select propiedad_id>`. Es la ÚNICA
 * parte de este archivo que sabe que existe `Propiedad`; todo lo demás
 * sigue siendo agnóstico de qué formulario lo incluye.
 */

import 'leaflet/dist/leaflet.css';
import '@geoman-io/leaflet-geoman-free/dist/leaflet-geoman.css';
import L from 'leaflet';
import '@geoman-io/leaflet-geoman-free';
import { leerColorToken } from '../shared/color-tokens.js';
import { activarPantallaCompleta } from '../shared/mapa-pantalla-completa.js';
import { cargarGoogleMaps } from '../shared/cargador-google-maps.js';

/** Centro por defecto: la zona donde opera Agrocom (Santa Cruz). Solo se usa
 *  cuando el lote todavía no tiene perímetro y no hay ninguno cerca. */
const CENTRO_POR_DEFECTO = { lat: -17.34, lng: -62.85 };
const ZOOM_SIN_GEOMETRIA = 13;
const ZOOM_MAXIMO_AL_ENCUADRAR = 17;
// La cámara no aleja más allá de una vista tipo país (16/9/2026, pedido
// directo): con la máscara del tamaño de Sudamérica de fondo, alejar del
// todo mostraba el rectángulo entero — esto lo corta antes.
const ZOOM_MINIMO = 6;

/** Historial de deshacer: alcanza con unos pocos pasos — no es un editor de
 *  vectores, es el perímetro de UN lote. */
const LIMITE_HISTORIAL = 20;

const ATRIBUCION_ESRI = 'Tiles &copy; Esri — Source: Esri, Maxar, Earthstar Geographics';
const URL_CAPA_SATELITE = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
const URL_CAPA_CALLES = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}';

/**
 * Contorno en ÁMBAR y relleno en verde de marca (Leaflet). El ámbar es el
 * acento editorial del sistema (`sistema_diseno_panel.md` §8): acá no se
 * repite fila a fila —es un solo contorno— y es lo único que se distingue
 * sobre una imagen satelital de campos, que es verde de punta a punta. Un
 * contorno verde sobre soja verde no se ve, y este trazo es justamente lo
 * que hay que ver para saber si el perímetro quedó bien dibujado.
 */
function estiloPoligono() {
    return {
        color: leerColorToken('--ag-color-accent'),
        fillColor: leerColorToken('--ag-color-primary'),
        fillOpacity: 0.2,
        weight: 3,
    };
}

/** Mismos tokens que {@see estiloPoligono}, con los nombres de propiedad que
 *  espera `google.maps.Polygon`/`PolygonOptions` en vez de los de Leaflet. */
function estiloPoligonoGoogle() {
    return {
        strokeColor: leerColorToken('--ag-color-accent'),
        strokeWeight: 3,
        fillColor: leerColorToken('--ag-color-primary'),
        fillOpacity: 0.2,
    };
}

/**
 * Capas de REFERENCIA (16/9/2026, pedido directo del dueño): el límite de
 * la propiedad elegida y sus lotes ya cargados, para no dibujar un lote
 * nuevo a ciegas — "la idea es dibujar un polígono dentro de la propiedad y
 * cuando se agregue otro lote se muestre el polígono de los lotes previos,
 * hasta llenar o terminar toda la propiedad". Trazo punteado + SIN
 * interacción (`interactive`/`clickable: false`, nunca editable ni
 * clickeable — no son la capa que se guarda) para que nunca se confundan
 * con el polígono editable de ESTE lote, que sigue en ámbar sólido
 * ({@see estiloPoligono}).
 *
 * Dos estilos distintos entre sí — el límite de la propiedad (contorno
 * nada más, es un contenedor) y los lotes hermanos (con relleno tenue, ya
 * son terreno ocupado) — con el mismo criterio de tokens que el resto del
 * archivo (CLAUDE.md invariante 11).
 */
function estiloReferenciaPropiedad() {
    return {
        color: leerColorToken('--ag-color-border-strong'),
        weight: 2,
        dashArray: '6 4',
        fill: false,
        interactive: false,
    };
}

function estiloReferenciaLote() {
    return {
        color: leerColorToken('--ag-color-text-faint'),
        weight: 1.5,
        dashArray: '4 3',
        fillColor: leerColorToken('--ag-color-text-faint'),
        fillOpacity: 0.15,
        interactive: false,
    };
}

/**
 * Máscara fuera del límite de la propiedad (16/9/2026, pedido directo):
 * "que el mapa salga cortado, solo el polígono, y lo demás oculto o negro
 * o difuminado, dando la idea de prohibido/inaccesible" — pero "no tan
 * oscuro, un color con opacity" (ajuste en vivo, mismo día): un neutral
 * ya usado en este archivo para las referencias (`--ag-color-border-strong`,
 * el contorno de `estiloReferenciaPropiedad`), a media opacidad — no el
 * scrim casi opaco del fondo del login, que ahí sí busca tapar del todo.
 * No es un recorte real de las teselas — es un polígono con un AGUJERO
 * por cada terreno de la propiedad.
 *
 * Puramente visual, nunca bloqueante (decisión del dueño, 16/9/2026): si
 * la propiedad no tiene geometría todavía, no hay máscara y el lote se
 * dibuja libremente — la ayuda visual solo aparece cuando hay algo contra
 * qué guiarse.
 */
function colorMascaraFueraDePropiedad() {
    return leerColorToken('--ag-color-border-strong');
}

const OPACIDAD_MASCARA_FUERA_DE_PROPIEDAD = 0.45;

/**
 * Rectángulo "todo el mapa" — Sudamérica entera con Bolivia centrada, NO
 * el planeta (16/9/2026, bug real, no solo cautela): cerca del polo, la
 * proyección Mercator de Google Maps distorsiona tanto que el algoritmo de
 * relleno por sentido de giro ("nonzero") queda confundido con un polígono
 * tan degenerado y termina pintando el agujero (el lote) en vez del resto
 * — confirmado en vivo, el lote quedaba oscurecido y la propiedad
 * alrededor sin tocar, justo al revés. Leaflet no tiene este problema
 * (regla de relleno "evenodd", no depende del sentido de giro), pero el
 * rectángulo se comparte entre los dos proveedores, así que se ajusta para
 * el que sí es sensible. Agrocom opera en Bolivia — un rectángulo del
 * tamaño de Sudamérica sigue cubriendo cualquier zoom razonable sin
 * acercarse a la zona degenerada.
 */
const ANILLO_MUNDO_LATLNG = [[-40, -85], [-40, -35], [10, -35], [10, -85]];

/**
 * Área con signo de un anillo `[[x, y], ...]` (fórmula del lazo/shoelace):
 * positiva en un sentido de giro, negativa en el otro — el signo exacto no
 * importa, solo que sea consistente para comparar dos anillos entre sí.
 */
function areaConSigno(anillo) {
    let suma = 0;

    for (let i = 0; i < anillo.length; i++) {
        const [x1, y1] = anillo[i];
        const [x2, y2] = anillo[(i + 1) % anillo.length];

        suma += x1 * y2 - x2 * y1;
    }

    return suma;
}

/**
 * Google Maps (a diferencia de Leaflet) SÍ le importa el sentido de giro
 * para tratar un anillo como agujero: tiene que girar al REVÉS que el
 * anillo exterior, o el "agujero" se pinta relleno en vez de vacío
 * (confirmado en vivo, 16/9/2026 — el polígono del lote quedaba pintado
 * de negro sólido en vez de la propiedad oscurecida alrededor). Como el
 * anillo de la propiedad lo traza un usuario a mano, su sentido no está
 * garantizado — se fuerza acá en vez de confiar en cómo se dibujó.
 */
function conSentidoOpuestoA(anilloBase, anillo) {
    const mismoSentido = (areaConSigno(anilloBase) >= 0) === (areaConSigno(anillo) >= 0);

    return mismoSentido ? [...anillo].reverse() : anillo;
}

/**
 * El anillo EXTERIOR de cada polígono de un GeoJSON `Polygon`/`MultiPolygon`
 * — una propiedad con terrenos separados ("islas") deja un agujero por
 * cada una. Ignora agujeros propios de la propiedad (nunca los tiene: es
 * un perímetro de referencia, no terreno editable) — alcanza con el
 * primer anillo de cada polígono.
 *
 * @return {Array<Array<[number, number]>>} lista de anillos `[lat, lng]`.
 */
function anillosExterioresLatLng(geometria) {
    if (!geometria) {
        return [];
    }

    const poligonos = geometria.type === 'MultiPolygon' ? geometria.coordinates : [geometria.coordinates];

    return poligonos
        .filter((anillos) => Array.isArray(anillos) && Array.isArray(anillos[0]))
        .map((anillos) => anillos[0].map(([lng, lat]) => [lat, lng]));
}

function estiloReferenciaPropiedadGoogle() {
    return {
        strokeColor: leerColorToken('--ag-color-border-strong'),
        strokeWeight: 2,
        fillOpacity: 0,
        clickable: false,
        editable: false,
    };
}

function estiloReferenciaLoteGoogle() {
    return {
        strokeColor: leerColorToken('--ag-color-text-faint'),
        strokeWeight: 1.5,
        fillColor: leerColorToken('--ag-color-text-faint'),
        fillOpacity: 0.15,
        clickable: false,
        editable: false,
    };
}

/**
 * Lee `[data-ag-lote-mapa-referencia]` (JSON embebido por
 * `lotes/_formulario.blade.php`, ver `LotesController::referenciaMapa()`) y
 * el `<select propiedad_id>` del mismo formulario — mismo patrón "estrategia
 * embebida" que `propiedades-form.js`/`contratos-form.js`: sin AJAX, todo
 * ya viajó con la página.
 *
 * `null` si el formulario no tiene ninguna de las dos piezas (p. ej. un
 * contexto futuro donde `_lote-fila.blade.php` se reuse sin este
 * formulario) — la referencia es opcional, nunca requerida para que el
 * editor funcione.
 */
function leerReferencia(contenedor) {
    const formulario = contenedor.closest('form');
    const scriptDatos = formulario?.querySelector('[data-ag-lote-mapa-referencia]');
    const selectPropiedad = formulario?.querySelector('[data-ag-lote-propiedad]');

    if (!scriptDatos || !selectPropiedad) {
        return null;
    }

    let datos;
    try {
        datos = JSON.parse(scriptDatos.textContent);
    } catch {
        return null;
    }

    return { selectPropiedad, propiedades: datos.propiedades ?? {}, lotesPorPropiedad: datos.lotesPorPropiedad ?? {} };
}

/**
 * Superficie geodésica de un anillo `[[lng, lat], ...]` YA CERRADO (primer y
 * último punto iguales), en metros cuadrados.
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

/** Hectáreas de un anillo ABIERTO (sin repetir el primer punto al final). */
function hectareasDeAnillo(anillo) {
    if (!Array.isArray(anillo) || anillo.length < 3) {
        return null;
    }

    const cerrado = [...anillo, anillo[0]];

    return superficieEnMetros(cerrado) / 10000;
}

/** El anillo de un GeoJSON `Polygon` ya viene CERRADO (primer punto repetido
 *  al final) — se abre antes de pasarlo a `hectareasDeAnillo`, que cierra el
 *  suyo propio. Independiente del proveedor: los dos escriben el mismo
 *  GeoJSON en el input oculto. */
function hectareasDe(geometria) {
    const anillo = geometria?.coordinates?.[0];

    return Array.isArray(anillo) ? hectareasDeAnillo(anillo.slice(0, -1)) : null;
}

function formatearHectareas(valor) {
    return valor.toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/** Escribe el input oculto Y avisa el cambio (`input`, con burbujeo): sin
 *  esto, `shared/barra-acciones-dirty.js` nunca se entera de que se dibujó
 *  o movió un polígono — el valor cambia, pero ningún evento del DOM lo
 *  dice. */
function fijarGeometria(input, valor) {
    input.value = valor ?? '';
    input.dispatchEvent(new Event('input', { bubbles: true }));
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

/** Todas las referencias DOM que necesita cualquiera de los dos proveedores
 *  — un solo lugar que sabe los selectores, para no repetirlos. */
function referenciasDom(contenedor) {
    return {
        marco: contenedor.querySelector('[data-ag-lote-mapa-marco]'),
        lienzo: contenedor.querySelector('[data-ag-lote-mapa-lienzo]'),
        mapaDiv: contenedor.querySelector('[data-ag-lote-mapa-mapa]'),
        input: contenedor.querySelector('[data-ag-lote-geometria]'),
        medida: contenedor.querySelector('[data-ag-lote-medida]'),
        medidaTexto: contenedor.querySelector('[data-ag-lote-medida-texto]'),
        botonUsar: contenedor.querySelector('[data-ag-lote-usar-superficie]'),
        botonDibujar: contenedor.querySelector('[data-ag-lote-accion="dibujar"]'),
        iconoDibujar: contenedor.querySelector('[data-ag-lote-mapa-icono-dibujar]'),
        botonBorrar: contenedor.querySelector('[data-ag-lote-accion="borrar"]'),
        botonDeshacer: contenedor.querySelector('[data-ag-lote-accion="deshacer"]'),
        botonCentrar: contenedor.querySelector('[data-ag-lote-accion="centrar"]'),
        botonCapa: contenedor.querySelector('[data-ag-lote-accion="capa"]'),
        botonPantallaCompleta: contenedor.querySelector('[data-ag-lote-mapa-boton-pantalla-completa]'),
        iconoPantallaCompleta: contenedor.querySelector('[data-ag-lote-mapa-icono-pantalla-completa]'),
    };
}

/**
 * Muestra/esconde la superficie dibujada, comparada contra las hectáreas
 * DECLARADAS del lote (invariante 6: lo contratado no lo pisa un polígono —
 * se compara, nunca se pisa solo). Provider-agnóstico: solo lee/escribe DOM.
 */
function crearMedidor({ contenedor, medida, medidaTexto }) {
    // `[data-ag-lote-ficha]` (16/9/2026), no `[data-ag-lote-fila]`: el mapa
    // vive en su propia sección "Mapa", hermana de "Datos del lote" — hay
    // que subir un nivel más para encontrar el input de hectáreas.
    const leerHectareasDeclaradas = () => {
        const campo = contenedor.closest('[data-ag-lote-ficha]')?.querySelector('input[name$="[hectareas]"]');
        const valor = campo ? Number.parseFloat(campo.value) : NaN;

        return Number.isFinite(valor) && valor > 0 ? valor : null;
    };

    const mostrarMedida = (hectareas) => {
        if (hectareas === null || !medida || !medidaTexto) {
            medida?.setAttribute('hidden', '');

            return;
        }

        const declaradas = leerHectareasDeclaradas();
        const plantilla = declaradas
            ? medida.dataset.agLoteMapaMedidaPlantillaDeclarada
            : medida.dataset.agLoteMapaMedidaPlantilla;

        medidaTexto.textContent = plantilla
            .replace(':dibujadas', formatearHectareas(hectareas))
            .replace(':declaradas', declaradas ? formatearHectareas(declaradas) : '');

        medida.removeAttribute('hidden');
        medida.dataset.agLoteHectareas = hectareas.toFixed(2);
    };

    return { mostrarMedida, leerHectareasDeclaradas };
}

/**
 * Deshacer, igual para los dos proveedores: guarda el STRING previo del
 * input oculto (no una estructura del proveedor) y le pide a `aplicar` que
 * lo vuelva a dibujar. `aplicar` es lo único que sabe de Leaflet o de
 * Google Maps.
 */
function crearHistorial({ boton, aplicar }) {
    const pila = [];

    const actualizarBoton = () => {
        if (boton) {
            boton.disabled = pila.length === 0;
        }
    };

    /** Guarda el valor ANTERIOR a un cambio. Se llama antes de aplicarlo. */
    const registrar = (valorAnterior) => {
        pila.push(valorAnterior);

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
    const {
        marco, lienzo, mapaDiv, input, medida, medidaTexto, botonUsar,
        botonDibujar, iconoDibujar, botonBorrar, botonDeshacer, botonCentrar, botonCapa,
        botonPantallaCompleta, iconoPantallaCompleta,
    } = refs;

    // El lápiz cambia de "Dibujar" a "Terminar" mientras hay un trazo en
    // curso (16/9/2026, mismo patrón que `propiedades/mapa.blade.php`): el
    // propio ícono anticipa que volver a presionarlo confirma el
    // perímetro, no solo cancela.
    const actualizarBotonDibujar = (activo) => {
        if (!botonDibujar) {
            return;
        }

        const etiqueta = activo ? botonDibujar.dataset.agLoteMapaDibujarTerminar : botonDibujar.dataset.agLoteMapaDibujarIniciar;
        botonDibujar.dataset.agLoteMapaTooltip = etiqueta;
        botonDibujar.setAttribute('aria-label', etiqueta);
        botonDibujar.setAttribute('aria-pressed', String(activo));

        if (iconoDibujar) {
            iconoDibujar.textContent = activo ? 'account_tree' : 'draw';
        }
    };

    const mapa = L.map(mapaDiv, { zoomControl: false, minZoom: ZOOM_MINIMO }).setView([CENTRO_POR_DEFECTO.lat, CENTRO_POR_DEFECTO.lng], ZOOM_SIN_GEOMETRIA);
    L.control.zoom({ position: 'bottomleft' }).addTo(mapa);

    let esSatelital = true;
    let capaBase = L.tileLayer(URL_CAPA_SATELITE, { attribution: ATRIBUCION_ESRI, maxZoom: 19 }).addTo(mapa);

    const capa = L.featureGroup().addTo(mapa);
    const { mostrarMedida } = crearMedidor({ contenedor, medida, medidaTexto });

    // ---------- Capas de referencia (límite de propiedad + lotes hermanos) ----------
    const referencia = leerReferencia(contenedor);
    // Capa APARTE de la máscara (16/9/2026): si viviera en `capaReferencia`,
    // `getBounds()` (más abajo, para encuadrar) devolvería el rectángulo
    // que cubre el mundo entero en vez del límite real de la propiedad.
    // Se agrega ANTES que `capaReferencia` para quedar DEBAJO — Leaflet
    // apila las capas por orden de alta, y el contorno punteado tiene que
    // verse por encima del oscurecido, no tapado por él.
    const capaMascara = L.layerGroup().addTo(mapa);
    const capaReferencia = L.layerGroup().addTo(mapa);

    const redibujarReferencia = () => {
        capaReferencia.clearLayers();
        capaMascara.clearLayers();

        if (!referencia) {
            return;
        }

        const propiedadId = referencia.selectPropiedad.value;
        const limite = propiedadId ? referencia.propiedades[propiedadId] : null;

        if (limite) {
            const agujeros = anillosExterioresLatLng(limite);

            if (agujeros.length > 0) {
                L.polygon([ANILLO_MUNDO_LATLNG, ...agujeros], {
                    fillColor: colorMascaraFueraDePropiedad(),
                    fillOpacity: OPACIDAD_MASCARA_FUERA_DE_PROPIEDAD,
                    stroke: false,
                    interactive: false,
                }).addTo(capaMascara);
            }

            L.geoJSON(limite, { style: estiloReferenciaPropiedad, interactive: false }).addTo(capaReferencia);
        }

        (propiedadId ? referencia.lotesPorPropiedad[propiedadId] ?? [] : []).forEach((loteHermano) => {
            L.geoJSON(loteHermano.geometria, { style: estiloReferenciaLote, interactive: false }).addTo(capaReferencia);
        });

        // Sin nada dibujado todavía en ESTE lote: encuadra sobre el límite
        // de la propiedad en vez del centro genérico — "no dibujar a
        // ciegas" también vale para saber adónde mirar en el mapa.
        if (capa.getLayers().length === 0 && capaReferencia.getLayers().length > 0) {
            mapa.fitBounds(capaReferencia.getBounds(), { padding: [24, 24], maxZoom: ZOOM_MAXIMO_AL_ENCUADRAR });
        }
    };

    if (referencia) {
        redibujarReferencia();
        referencia.selectPropiedad.addEventListener('change', redibujarReferencia);
    }

    /** Vuelca la capa dibujada al input oculto y refresca la superficie. */
    const sincronizar = () => {
        const capas = capa.getLayers();

        if (capas.length === 0) {
            fijarGeometria(input, '');
            mostrarMedida(null);

            return;
        }

        // Un lote es UN polígono: si se dibuja otro, el anterior se descarta.
        // `toGeoJSON()` de Leaflet ya emite [lng, lat], el mismo orden que
        // guarda `com_lotes.geometria`.
        const geometria = capas[capas.length - 1].toGeoJSON().geometry;

        fijarGeometria(input, JSON.stringify(geometria));
        mostrarMedida(hectareasDe(geometria));
    };

    const aplicarGeometria = (valorJson) => {
        capa.clearLayers();
        fijarGeometria(input, valorJson);

        const geometria = leerGeometria(input);

        if (geometria) {
            L.geoJSON(geometria, { style: estiloPoligono }).eachLayer((capaGuardada) => capa.addLayer(capaGuardada));
        }

        mostrarMedida(geometria ? hectareasDe(geometria) : null);
    };

    const historial = crearHistorial({ boton: botonDeshacer, aplicar: aplicarGeometria });
    const confirmarEdicion = () => {
        historial.registrar(input.value);
        sincronizar();
    };

    // Registrado ANTES de cargar el perímetro guardado: así el polígono ya
    // existente también queda con sus vértices editables/arrastrables, no
    // solo los que se dibujan en esta misma carga de página.
    capa.on('layeradd', ({ layer }) => {
        layer.on?.('pm:edit', confirmarEdicion);
        layer.on?.('pm:dragend', confirmarEdicion);
    });

    // Perímetro ya guardado: se dibuja y el mapa encuadra sobre él (sin
    // pasar por el historial — no hay nada previo a lo que "deshacer" acá).
    // `.pm.enable()` (16/9/2026, mismo criterio que Propiedad): los vértices
    // quedan arrastrables de entrada, sin un botón "editar" aparte.
    const guardada = leerGeometria(input);

    if (guardada) {
        L.geoJSON(guardada, { style: estiloPoligono }).eachLayer((capaGuardada) => {
            capaGuardada.pm.enable({ allowSelfIntersection: false });
            capa.addLayer(capaGuardada);
        });
        mapa.fitBounds(capa.getBounds(), { padding: [16, 16], maxZoom: ZOOM_MAXIMO_AL_ENCUADRAR });
        sincronizar();
    }

    // El español que ya trae Geoman, sin diccionario propio: los mensajes de
    // ayuda que Geoman muestra junto al cursor durante el dibujo (no el
    // chrome de botones, que ya no se usa) siguen en español.
    mapa.pm.setLang('es');
    mapa.pm.setPathOptions(estiloPoligono());

    mapa.on('pm:create', ({ layer }) => {
        historial.registrar(input.value);

        // Uno solo: el nuevo reemplaza al anterior. Vértices editables de
        // entrada, mismo criterio que arriba.
        capa.clearLayers();
        mapa.removeLayer(layer);
        layer.pm.enable({ allowSelfIntersection: false });
        capa.addLayer(layer);

        sincronizar();
        actualizarBotonDibujar(false);
    });

    // Superficie EN VIVO mientras se dibuja, antes de cerrar el polígono —
    // la ayuda de precisión que pide la tarea 79: se compara contra lo
    // declarado sin esperar a terminar el trazo. Geoman emite `pm:vertexadded`
    // sobre su capa interna de trabajo, no sobre el mapa (no hay forma pública
    // de escucharlo desde afuera) — se junta el trazo en curso escuchando el
    // `click` nativo del mapa mientras el modo dibujar está activo, que es la
    // misma fuente de la que Geoman toma cada vértice.
    let puntosEnCurso = [];

    mapa.on('click', ({ latlng }) => {
        if (!mapa.pm.globalDrawModeEnabled()) {
            return;
        }

        puntosEnCurso.push([latlng.lng, latlng.lat]);

        if (puntosEnCurso.length >= 3) {
            mostrarMedida(hectareasDeAnillo(puntosEnCurso));
        }
    });

    botonUsar?.addEventListener('click', () => {
        const hectareas = medida?.dataset.agLoteHectareas;
        const campoHectareas = contenedor
            .closest('[data-ag-lote-ficha]')
            ?.querySelector('input[name$="[hectareas]"]');

        if (hectareas && campoHectareas) {
            campoHectareas.value = hectareas;
            campoHectareas.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });

    // ---------- Barra de acciones ----------
    // Un solo modo (dibujar) — vértices editables SIEMPRE una vez trazado
    // el perímetro, no un modo "editar"/"mover" aparte (16/9/2026, mismo
    // criterio que `propiedades/mapa.blade.php`: "un componente
    // compartido", incluida la forma de interactuar).

    mapa.on('pm:globaldrawmodetoggled', ({ enabled }) => {
        actualizarBotonDibujar(enabled);

        if (enabled) {
            puntosEnCurso = [];
        } else {
            // Se salió del modo dibujar sin terminar el trazo (Escape,
            // volver a clickear "Dibujar"): la medida en vivo vuelve a
            // reflejar lo que hay REALMENTE guardado, no el trazo cancelado.
            sincronizar();
        }
    });

    botonDibujar?.addEventListener('click', () => {
        if (mapa.pm.globalDrawModeEnabled()) {
            // Volver a presionar el lápiz CONFIRMA el perímetro en curso (si
            // ya tiene 3+ vértices) en vez de solo cancelar — mismo criterio
            // que Propiedad. `_finishShape` es el método que usa la propia UI
            // de Geoman para su botón "Finish"; si en algún update deja de
            // existir, el `typeof` cae al comportamiento anterior (cancela).
            const manejador = mapa.pm.Draw?.Polygon;

            if (manejador && typeof manejador._finishShape === 'function') {
                manejador._finishShape();
            } else {
                mapa.pm.disableDraw();
            }

            return;
        }

        mapa.pm.enableDraw('Polygon');
    });

    botonBorrar?.addEventListener('click', () => {
        if (capa.getLayers().length === 0) {
            return;
        }

        historial.registrar(input.value);
        capa.clearLayers();
        sincronizar();
    });

    botonCentrar?.addEventListener('click', () => {
        // Sin lote todavía: centra sobre el límite de la PROPIEDAD, no un
        // punto fijo genérico (16/9/2026, bug real) — con la máscara
        // encima, un centro que cae fuera de la propiedad mostraba todo
        // oscurecido, como si el polígono hubiera desaparecido.
        if (capa.getLayers().length > 0) {
            mapa.fitBounds(capa.getBounds(), { padding: [16, 16], maxZoom: ZOOM_MAXIMO_AL_ENCUADRAR });
        } else if (capaReferencia.getLayers().length > 0) {
            mapa.fitBounds(capaReferencia.getBounds(), { padding: [24, 24], maxZoom: ZOOM_MAXIMO_AL_ENCUADRAR });
        } else {
            mapa.setView([CENTRO_POR_DEFECTO.lat, CENTRO_POR_DEFECTO.lng], ZOOM_SIN_GEOMETRIA);
        }
    });

    if (botonCapa) {
        const actualizarBotonCapa = () => {
            const etiqueta = esSatelital ? botonCapa.dataset.agLoteMapaCapaCalles : botonCapa.dataset.agLoteMapaCapaSatelite;

            botonCapa.dataset.agLoteMapaTooltip = etiqueta;
            botonCapa.setAttribute('aria-label', etiqueta);
            botonCapa.setAttribute('aria-pressed', String(!esSatelital));
        };

        botonCapa.addEventListener('click', () => {
            esSatelital = !esSatelital;
            mapa.removeLayer(capaBase);
            capaBase = L.tileLayer(esSatelital ? URL_CAPA_SATELITE : URL_CAPA_CALLES, {
                attribution: ATRIBUCION_ESRI,
                maxZoom: 19,
            }).addTo(mapa);
            capaBase.bringToBack();

            actualizarBotonCapa();
        });

        actualizarBotonCapa();
    }

    // ---------- Pantalla completa ----------

    if (botonPantallaCompleta) {
        activarPantallaCompleta({
            contenedor: marco,
            alRedimensionar: () => mapa.invalidateSize(),
            boton: botonPantallaCompleta,
            iconoBoton: iconoPantallaCompleta,
            etiquetaEntrar: botonPantallaCompleta.dataset.agLoteMapaEntrar,
            etiquetaSalir: botonPantallaCompleta.dataset.agLoteMapaSalir,
        });
    }

    // El repintado al cambiar de tema: los tokens de color se resuelven al
    // inicializar, así que hay que releerlos. Las capas de referencia se
    // redibujan enteras (mismo costo que recalcular sus dos estilos a
    // mano, mucho más simple).
    window.addEventListener('agrocom:theme-changed', () => {
        capa.eachLayer((capaDibujada) => capaDibujada.setStyle?.(estiloPoligono()));
        mapa.pm.setPathOptions(estiloPoligono());
        redibujarReferencia();
    });

    // Leaflet en un contenedor que todavía no tiene tamaño (fila recién
    // clonada, o formulario dentro de una sección plegada) renderiza mal.
    requestAnimationFrame(() => mapa.invalidateSize());
}

/**
 * Mismo editor, sobre Google Maps JS API — se usa solo cuando hay llave
 * configurada y el SDK cargó bien. La barra de acciones es la MISMA de
 * `inicializarLeaflet`; acá solo cambia qué API de mapas responde a cada
 * botón.
 *
 * El trazo de "dibujar" se arma a mano sobre un `Polygon` propio (click
 * agrega un vértice, doble click cierra el trazo) — NO con
 * `google.maps.drawing.DrawingManager`: Google la retiró de la API en la
 * v3.65 (confirmado en vivo, 15/9/2026 — tira
 * "The DrawingManager functionality... is no longer available", ver
 * https://developers.google.com/maps/deprecations). El resto de la API
 * (`Map`, `Polygon`, `LatLngBounds`) sigue como siempre.
 *
 * Sin undo/redo propio en la librería (igual que Geoman free) — mismo
 * mecanismo de {@see crearHistorial} que Leaflet, sobre el mismo string
 * GeoJSON.
 */
function inicializarGoogle(contenedor, refs, googleMapsNs) {
    const {
        marco, lienzo, mapaDiv, input, medida, medidaTexto, botonUsar,
        botonDibujar, iconoDibujar, botonBorrar, botonDeshacer, botonCentrar, botonCapa,
        botonPantallaCompleta, iconoPantallaCompleta,
    } = refs;

    // El lápiz cambia de "Dibujar" a "Terminar" mientras hay un trazo en
    // curso (16/9/2026, mismo patrón que `propiedades/mapa.blade.php`).
    const actualizarBotonDibujar = (activo) => {
        if (!botonDibujar) {
            return;
        }

        const etiqueta = activo ? botonDibujar.dataset.agLoteMapaDibujarTerminar : botonDibujar.dataset.agLoteMapaDibujarIniciar;
        botonDibujar.dataset.agLoteMapaTooltip = etiqueta;
        botonDibujar.setAttribute('aria-label', etiqueta);
        botonDibujar.setAttribute('aria-pressed', String(activo));

        if (iconoDibujar) {
            iconoDibujar.textContent = activo ? 'account_tree' : 'draw';
        }
    };

    const mapa = new googleMapsNs.Map(mapaDiv, {
        center: CENTRO_POR_DEFECTO,
        zoom: ZOOM_SIN_GEOMETRIA,
        minZoom: ZOOM_MINIMO,
        // HYBRID (satelital + nombres/caminos), no SATELLITE a secas: sin
        // etiquetas, un lote se pierde en un mar de verde indistinguible del
        // vecino — es lo mismo que muestra el Google Maps normal cuando pasás
        // a vista satelital.
        mapTypeId: googleMapsNs.MapTypeId.HYBRID,
        // Sin esto, la rueda/trackpad zoomea de a niveles enteros por cada
        // evento de scroll — con un trackpad, que dispara muchos eventos
        // chicos seguidos, se siente como que salta varios niveles de golpe.
        // Con zoom fraccionario el nivel avanza proporcional al gesto, igual
        // de suave que en maps.google.com.
        isFractionalZoomEnabled: true,
        streetViewControl: false,
        mapTypeControl: false,
        fullscreenControl: false, // el botón propio de pantalla completa lo reemplaza
        // Zoom nativo abajo a la izquierda: la columna de acciones propia
        // vive arriba a la derecha (16/9/2026, mismo acomodo que Propiedad).
        zoomControlOptions: { position: googleMapsNs.ControlPosition.LEFT_BOTTOM },
    });

    let esSatelital = true;
    let poligono = null;

    const { mostrarMedida } = crearMedidor({ contenedor, medida, medidaTexto });

    // ---------- Capas de referencia (límite de propiedad + lotes hermanos) ----------
    // Mismo criterio que inicializarLeaflet: trazo punteado, sin clic ni
    // edición — google.maps.Polygon no tiene un layerGroup nativo, así que
    // se lleva un array propio aparte de `poligono` (el editable).
    const referencia = leerReferencia(contenedor);
    let poligonosReferencia = [];
    // Aparte de `poligonosReferencia` (16/9/2026 — bug real, no solo
    // cautela): si la máscara entrara en el mismo array que se usa para
    // `fitBounds` más abajo, sus vértices en las puntas del mundo
    // (`ANILLO_MUNDO_LATLNG`) hacen que el mapa encuadre el planeta entero
    // en vez de la propiedad — confirmado en vivo, el mapa quedaba a zoom
    // mundial con Google Maps.
    let poligonosMascara = [];

    /** Un GeoJSON `Polygon` o `MultiPolygon` → uno o más `google.maps.Polygon`,
     *  con las mismas opciones (nunca editables/clickeables). */
    const poligonosDesdeGeoJSON = (geometria, opciones) => {
        if (!geometria) {
            return [];
        }

        const poligonos = geometria.type === 'MultiPolygon' ? geometria.coordinates : [geometria.coordinates];

        return poligonos.map((anillos) => new googleMapsNs.Polygon({
            paths: anillos[0].map(([lng, lat]) => ({ lat, lng })),
            map: mapa,
            zIndex: 1,
            ...opciones,
        }));
    };

    /** Máscara fuera del límite de la propiedad ({@see colorMascaraFueraDePropiedad}):
     *  UN solo polígono, mundo entero como anillo exterior y un agujero
     *  por cada terreno de la propiedad — `zIndex: 0`, siempre por debajo
     *  del contorno punteado. */
    const poligonoMascaraDesdeGeoJSON = (geometria) => {
        const agujeros = anillosExterioresLatLng(geometria)
            .map((anillo) => conSentidoOpuestoA(ANILLO_MUNDO_LATLNG, anillo))
            .map((anillo) => anillo.map(([lat, lng]) => ({ lat, lng })));

        if (agujeros.length === 0) {
            return [];
        }

        return [new googleMapsNs.Polygon({
            paths: [ANILLO_MUNDO_LATLNG.map(([lat, lng]) => ({ lat, lng })), ...agujeros],
            map: mapa,
            fillColor: colorMascaraFueraDePropiedad(),
            fillOpacity: OPACIDAD_MASCARA_FUERA_DE_PROPIEDAD,
            strokeWeight: 0,
            clickable: false,
            editable: false,
            zIndex: 0,
        })];
    };

    const redibujarReferencia = () => {
        poligonosReferencia.forEach((poligonoReferencia) => poligonoReferencia.setMap(null));
        poligonosReferencia = [];
        poligonosMascara.forEach((poligonoMascara) => poligonoMascara.setMap(null));
        poligonosMascara = [];

        if (!referencia) {
            return;
        }

        const propiedadId = referencia.selectPropiedad.value;
        const limite = propiedadId ? referencia.propiedades[propiedadId] : null;

        if (limite) {
            poligonosMascara.push(...poligonoMascaraDesdeGeoJSON(limite));
            poligonosReferencia.push(...poligonosDesdeGeoJSON(limite, estiloReferenciaPropiedadGoogle()));
        }

        (propiedadId ? referencia.lotesPorPropiedad[propiedadId] ?? [] : []).forEach((loteHermano) => {
            poligonosReferencia.push(...poligonosDesdeGeoJSON(loteHermano.geometria, estiloReferenciaLoteGoogle()));
        });

        if (!poligono && poligonosReferencia.length > 0) {
            const limites = new googleMapsNs.LatLngBounds();
            poligonosReferencia.forEach((poligonoReferencia) => poligonoReferencia.getPath().forEach((punto) => limites.extend(punto)));
            mapa.fitBounds(limites);
        }
    };

    if (referencia) {
        redibujarReferencia();
        referencia.selectPropiedad.addEventListener('change', redibujarReferencia);
    }

    /** GeoJSON `Polygon` a partir del `Path` actual de `poligono`, con el
     *  mismo anillo CERRADO que emite Leaflet (`toGeoJSON()`), para que el
     *  input oculto sea el mismo formato venga de donde venga. */
    const geometriaDePoligono = () => {
        if (!poligono) {
            return null;
        }

        const anillo = poligono.getPath().getArray().map((punto) => [punto.lng(), punto.lat()]);

        if (anillo.length < 3) {
            return null;
        }

        return { type: 'Polygon', coordinates: [[...anillo, anillo[0]]] };
    };

    const sincronizar = () => {
        const geometria = geometriaDePoligono();

        fijarGeometria(input, geometria ? JSON.stringify(geometria) : '');
        mostrarMedida(geometria ? hectareasDe(geometria) : null);
    };

    const quitarPoligono = () => {
        poligono?.setMap(null);
        poligono = null;
    };

    /** Conecta los eventos de edición/arrastre del polígono actual a
     *  `confirmarEdicion` — se llama cada vez que se crea o se restaura un
     *  polígono nuevo (dibujado, deshecho, o cargado desde el servidor). */
    const conectarPoligono = (confirmarEdicion) => {
        poligono.addListener('dragend', confirmarEdicion);

        const ruta = poligono.getPath();
        ruta.addListener('set_at', confirmarEdicion);
        ruta.addListener('insert_at', confirmarEdicion);
        ruta.addListener('remove_at', confirmarEdicion);
    };

    // `editable: true` (16/9/2026, mismo criterio que Propiedad): los
    // vértices quedan arrastrables de entrada, sin un botón "editar" aparte
    // — "Borrar" ahora es una acción directa (ver más abajo), no un modo de
    // click-sobre-el-polígono.
    const dibujarPoligono = (geometria, confirmarEdicion) => {
        quitarPoligono();

        if (!geometria) {
            return;
        }

        const ruta = geometria.coordinates[0].slice(0, -1).map(([lng, lat]) => ({ lat, lng }));

        poligono = new googleMapsNs.Polygon({ paths: ruta, editable: true, ...estiloPoligonoGoogle() });
        poligono.setMap(mapa);
        conectarPoligono(confirmarEdicion);
    };

    const aplicarGeometria = (valorJson) => {
        fijarGeometria(input, valorJson);

        const geometria = leerGeometria(input);
        dibujarPoligono(geometria, confirmarEdicion);
        mostrarMedida(geometria ? hectareasDe(geometria) : null);
    };

    const historial = crearHistorial({ boton: botonDeshacer, aplicar: aplicarGeometria });
    const confirmarEdicion = () => {
        historial.registrar(input.value);
        sincronizar();
    };

    // Perímetro ya guardado: se dibuja y el mapa encuadra sobre él (sin
    // pasar por el historial — no hay nada previo a lo que "deshacer" acá).
    const guardada = leerGeometria(input);

    if (guardada) {
        dibujarPoligono(guardada, confirmarEdicion);

        const limites = new googleMapsNs.LatLngBounds();
        guardada.coordinates[0].forEach(([lng, lat]) => limites.extend({ lat, lng }));
        mapa.fitBounds(limites);

        sincronizar();
    }

    // ---------- Barra de acciones ----------

    // Trazo a mano sobre un `Polygon` propio: click agrega un vértice, doble
    // click cierra el trazo — reemplaza a `DrawingManager` (ver docblock de
    // esta función). `clickPendiente` distingue un click suelto (agrega
    // vértice) de la primera mitad de un doble click (Google dispara
    // click+click+dblclick: sin este delay, el doble click para CERRAR el
    // trazo le agregaría además un vértice de más pegado al último real).
    // Un solo modo (dibujar) — vértices editables SIEMPRE una vez trazado
    // el perímetro (`editable: true` en `dibujarPoligono`/`terminarTrazo`),
    // no un modo "editar"/"mover" aparte; "Borrar" es una acción directa,
    // no un modo de click-sobre-el-polígono (16/9/2026, mismo criterio que
    // Propiedad — "un componente compartido", incluida la forma de
    // interactuar).
    let modoDibujo = false;
    let poligonoEnCurso = null;
    let clickPendiente = null;

    const salirDeModoDibujo = () => {
        modoDibujo = false;
        window.clearTimeout(clickPendiente);
        poligonoEnCurso?.setMap(null);
        poligonoEnCurso = null;
        mapa.setOptions({ draggableCursor: null, disableDoubleClickZoom: false });
        actualizarBotonDibujar(false);
    };

    const agregarVertice = (latLng) => {
        if (!poligonoEnCurso) {
            poligonoEnCurso = new googleMapsNs.Polygon({ paths: [latLng], ...estiloPoligonoGoogle() });
            poligonoEnCurso.setMap(mapa);
        } else {
            poligonoEnCurso.getPath().push(latLng);
        }

        const ruta = poligonoEnCurso.getPath().getArray();

        if (ruta.length >= 3) {
            mostrarMedida(hectareasDeAnillo(ruta.map((punto) => [punto.lng(), punto.lat()])));
        }
    };

    const terminarTrazo = () => {
        const ruta = poligonoEnCurso?.getPath().getArray() ?? [];

        salirDeModoDibujo();

        if (ruta.length < 3) {
            sincronizar();

            return;
        }

        historial.registrar(input.value);

        // Uno solo: el nuevo reemplaza al anterior. Editable de entrada,
        // mismo criterio que `dibujarPoligono`.
        quitarPoligono();
        poligono = new googleMapsNs.Polygon({ paths: ruta, editable: true, ...estiloPoligonoGoogle() });
        poligono.setMap(mapa);
        conectarPoligono(confirmarEdicion);

        sincronizar();
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
            // Volver a presionar el lápiz CONFIRMA el perímetro en curso (si
            // ya tiene 3+ vértices) en vez de solo cancelar — mismo criterio
            // que Propiedad. `terminarTrazo()` ya sale del modo y descarta
            // el trazo si tiene menos de 3 puntos.
            terminarTrazo();

            return;
        }

        modoDibujo = true;
        mapa.setOptions({ draggableCursor: 'crosshair', disableDoubleClickZoom: true });
        actualizarBotonDibujar(true);
    });

    botonBorrar?.addEventListener('click', () => {
        if (!poligono) {
            return;
        }

        historial.registrar(input.value);
        quitarPoligono();
        sincronizar();
    });

    botonCentrar?.addEventListener('click', () => {
        // Sin lote todavía: centra sobre el límite de la PROPIEDAD, no un
        // punto fijo genérico (16/9/2026, bug real) — con la máscara
        // encima, un centro que cae fuera de la propiedad mostraba todo
        // oscurecido, como si el polígono hubiera desaparecido.
        if (poligono) {
            const limites = new googleMapsNs.LatLngBounds();
            poligono.getPath().forEach((punto) => limites.extend(punto));
            mapa.fitBounds(limites);
        } else if (poligonosReferencia.length > 0) {
            const limites = new googleMapsNs.LatLngBounds();
            poligonosReferencia.forEach((poligonoReferencia) => poligonoReferencia.getPath().forEach((punto) => limites.extend(punto)));
            mapa.fitBounds(limites);
        } else {
            mapa.setCenter(CENTRO_POR_DEFECTO);
            mapa.setZoom(ZOOM_SIN_GEOMETRIA);
        }
    });

    if (botonCapa) {
        const actualizarBotonCapa = () => {
            const etiqueta = esSatelital ? botonCapa.dataset.agLoteMapaCapaCalles : botonCapa.dataset.agLoteMapaCapaSatelite;

            botonCapa.dataset.agLoteMapaTooltip = etiqueta;
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

    botonUsar?.addEventListener('click', () => {
        const hectareas = medida?.dataset.agLoteHectareas;
        const campoHectareas = contenedor
            .closest('[data-ag-lote-ficha]')
            ?.querySelector('input[name$="[hectareas]"]');

        if (hectareas && campoHectareas) {
            campoHectareas.value = hectareas;
            campoHectareas.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });

    // ---------- Pantalla completa ----------

    if (botonPantallaCompleta) {
        activarPantallaCompleta({
            contenedor: marco,
            alRedimensionar: () => googleMapsNs.event.trigger(mapa, 'resize'),
            boton: botonPantallaCompleta,
            iconoBoton: iconoPantallaCompleta,
            etiquetaEntrar: botonPantallaCompleta.dataset.agLoteMapaEntrar,
            etiquetaSalir: botonPantallaCompleta.dataset.agLoteMapaSalir,
        });
    }

    // El repintado al cambiar de tema: los tokens de color se resuelven al
    // inicializar, así que hay que releerlos.
    window.addEventListener('agrocom:theme-changed', () => {
        poligono?.setOptions(estiloPoligonoGoogle());
        poligonoEnCurso?.setOptions(estiloPoligonoGoogle());
        redibujarReferencia();
    });
}

function inicializar(contenedor) {
    if (contenedor.dataset.agLoteMapaListo === '1') {
        return;
    }

    const refs = referenciasDom(contenedor);

    if (!refs.marco || !refs.lienzo || !refs.mapaDiv || !refs.input) {
        return;
    }

    // Marca de inicialización: las filas se clonan desde un <template> y este
    // módulo se llama de nuevo por cada fila agregada.
    contenedor.dataset.agLoteMapaListo = '1';

    const llave = contenedor.dataset.agLoteMapaGoogleKey;

    if (contenedor.dataset.agLoteMapaProveedor === 'google' && llave) {
        cargarGoogleMaps(llave)
            .then((googleMapsNs) => inicializarGoogle(contenedor, refs, googleMapsNs))
            .catch((error) => {
                // Antes caía a Leaflet sin dejar rastro — imposible saber SI
                // Google falló y POR QUÉ sin esto. El fallback en sí queda
                // igual: el formulario nunca se queda sin mapa.
                console.warn('No se pudo inicializar Google Maps, se usa Leaflet como respaldo.', error);
                inicializarLeaflet(contenedor, refs);
            });

        return;
    }

    inicializarLeaflet(contenedor, refs);
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
