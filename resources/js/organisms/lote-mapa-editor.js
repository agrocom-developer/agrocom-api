/**
 * organisms/lote-mapa-editor.js — editor del perímetro de un lote sobre mapa
 * satelital (Leaflet + Esri World Imagery + Leaflet-Geoman), en el formulario
 * de campos.
 *
 * Contrato con el servidor SIN CAMBIOS: el valor sigue viajando como el mismo
 * string JSON (`{type: 'Polygon', coordinates: [[[lng, lat], ...]]}`) en el
 * input oculto que el Form Request ya validaba. Este módulo solo cambia cómo
 * se produce ese string.
 *
 * Cargado por `import()` dinámico desde app.js, solo cuando la página tiene un
 * `[data-ag-lote-mapa]` — Leaflet pesa, y no entra en el resto del panel.
 *
 * Tarea 79 (HU-56): reemplaza el chrome nativo de Leaflet-Geoman (íconos
 * ajenos al panel, en inglés) por una barra de acciones propia con Material
 * Symbols y texto en español, agrega pantalla completa de verdad
 * (`shared/mapa-pantalla-completa.js`) y superficie en vivo mientras se
 * dibuja. Geoman se sigue usando por su motor de edición de vértices
 * (`enableGlobalEditMode`, `enableDraw`, etc.) — lo que cambia es quién
 * dibuja los botones, no quién dibuja el polígono.
 *
 * Colores desde tokens (shared/color-tokens.js), nunca hex acá: CLAUDE.md
 * invariante 11, y el editor se abre en ambos temas.
 */

import 'leaflet/dist/leaflet.css';
import '@geoman-io/leaflet-geoman-free/dist/leaflet-geoman.css';
import L from 'leaflet';
import '@geoman-io/leaflet-geoman-free';
import { leerColorToken } from '../shared/color-tokens.js';
import { activarPantallaCompleta } from '../shared/mapa-pantalla-completa.js';

/** Centro por defecto: la zona donde opera Agrocom (Santa Cruz). Solo se usa
 *  cuando el lote todavía no tiene perímetro y no hay ninguno cerca. */
const CENTRO_POR_DEFECTO = { lat: -17.34, lng: -62.85 };
const ZOOM_SIN_GEOMETRIA = 13;
const ZOOM_MAXIMO_AL_ENCUADRAR = 17;

/** Historial de deshacer: alcanza con unos pocos pasos — no es un editor de
 *  vectores, es el perímetro de UN lote. */
const LIMITE_HISTORIAL = 20;

const ATRIBUCION_ESRI = 'Tiles &copy; Esri — Source: Esri, Maxar, Earthstar Geographics';
const URL_CAPA_SATELITE = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
const URL_CAPA_CALLES = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}';

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
 *  suyo propio. */
function hectareasDe(geometria) {
    const anillo = geometria?.coordinates?.[0];

    return Array.isArray(anillo) ? hectareasDeAnillo(anillo.slice(0, -1)) : null;
}

function formatearHectareas(valor) {
    return valor.toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
    const marco = contenedor.querySelector('[data-ag-lote-mapa-marco]');
    const lienzo = contenedor.querySelector('[data-ag-lote-mapa-lienzo]');
    const input = contenedor.querySelector('[data-ag-lote-geometria]');
    const medida = contenedor.querySelector('[data-ag-lote-medida]');
    const medidaTexto = contenedor.querySelector('[data-ag-lote-medida-texto]');
    const botonUsar = contenedor.querySelector('[data-ag-lote-usar-superficie]');

    if (!marco || !lienzo || !input || contenedor.dataset.agLoteMapaListo === '1') {
        return;
    }

    // Marca de inicialización: las filas se clonan desde un <template> y este
    // módulo se llama de nuevo por cada fila agregada.
    contenedor.dataset.agLoteMapaListo = '1';

    const mapa = L.map(lienzo).setView([CENTRO_POR_DEFECTO.lat, CENTRO_POR_DEFECTO.lng], ZOOM_SIN_GEOMETRIA);

    let esSatelital = true;
    let capaBase = L.tileLayer(URL_CAPA_SATELITE, { attribution: ATRIBUCION_ESRI, maxZoom: 19 }).addTo(mapa);

    const capa = L.featureGroup().addTo(mapa);

    /** Hectáreas declaradas del lote (input hermano), para comparar contra
     *  lo dibujado — nunca se copian solas (invariante 6: lo contratado no
     *  lo pisa un polígono). */
    const leerHectareasDeclaradas = () => {
        const campo = contenedor.closest('[data-ag-lote-fila]')?.querySelector('input[name$="[hectareas]"]');
        const valor = campo ? Number.parseFloat(campo.value) : NaN;

        return Number.isFinite(valor) && valor > 0 ? valor : null;
    };

    /** Muestra (o esconde) la superficie. Compara contra lo declarado cuando
     *  ese campo ya tiene un valor. */
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

    /** Vuelca la capa dibujada al input oculto y refresca la superficie. */
    const sincronizar = () => {
        const capas = capa.getLayers();

        if (capas.length === 0) {
            input.value = '';
            mostrarMedida(null);

            return;
        }

        // Un lote es UN polígono: si se dibuja otro, el anterior se descarta.
        // `toGeoJSON()` de Leaflet ya emite [lng, lat], el mismo orden que
        // guarda `com_lotes.geometria`.
        const geometria = capas[capas.length - 1].toGeoJSON().geometry;

        input.value = JSON.stringify(geometria);
        mostrarMedida(hectareasDe(geometria));
    };

    // ---------- Deshacer ----------

    const historial = [];
    const botonDeshacer = contenedor.querySelector('[data-ag-lote-accion="deshacer"]');

    const actualizarBotonDeshacer = () => {
        if (botonDeshacer) {
            botonDeshacer.disabled = historial.length === 0;
        }
    };

    /** Guarda el valor ANTERIOR a un cambio, para poder volver a él. Se llama
     *  antes de aplicar el cambio, nunca después. */
    const registrarHistorial = () => {
        historial.push(input.value);

        if (historial.length > LIMITE_HISTORIAL) {
            historial.shift();
        }

        actualizarBotonDeshacer();
    };

    const aplicarGeometria = (valorJson) => {
        capa.clearLayers();
        input.value = valorJson ?? '';

        const geometria = leerGeometria(input);

        if (geometria) {
            L.geoJSON(geometria, { style: estiloPoligono }).eachLayer((capaGuardada) => capa.addLayer(capaGuardada));
        }

        mostrarMedida(geometria ? hectareasDe(geometria) : null);
    };

    botonDeshacer?.addEventListener('click', () => {
        if (historial.length === 0) {
            return;
        }

        aplicarGeometria(historial.pop());
        actualizarBotonDeshacer();
    });

    const confirmarEdicion = () => {
        registrarHistorial();
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
    const guardada = leerGeometria(input);

    if (guardada) {
        L.geoJSON(guardada, { style: estiloPoligono }).eachLayer((capaGuardada) => capa.addLayer(capaGuardada));
        mapa.fitBounds(capa.getBounds(), { padding: [16, 16], maxZoom: ZOOM_MAXIMO_AL_ENCUADRAR });
        sincronizar();
    }

    // El español que ya trae Geoman, sin diccionario propio: los mensajes de
    // ayuda que Geoman muestra junto al cursor durante el dibujo (no el
    // chrome de botones, que ya no se usa) siguen en español.
    mapa.pm.setLang('es');
    mapa.pm.setPathOptions(estiloPoligono());

    mapa.on('pm:create', ({ layer }) => {
        registrarHistorial();

        // Uno solo: el nuevo reemplaza al anterior.
        capa.clearLayers();
        mapa.removeLayer(layer);
        capa.addLayer(layer);

        sincronizar();
    });

    mapa.on('pm:remove', () => {
        registrarHistorial();
        capa.clearLayers();
        sincronizar();
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
            .closest('[data-ag-lote-fila]')
            ?.querySelector('input[name$="[hectareas]"]');

        if (hectareas && campoHectareas) {
            campoHectareas.value = hectareas;
            campoHectareas.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });

    // ---------- Barra de acciones ----------

    const botonDibujar = contenedor.querySelector('[data-ag-lote-accion="dibujar"]');
    const botonEditar = contenedor.querySelector('[data-ag-lote-accion="editar"]');
    const botonMover = contenedor.querySelector('[data-ag-lote-accion="mover"]');
    const botonBorrar = contenedor.querySelector('[data-ag-lote-accion="borrar"]');
    const botonCentrar = contenedor.querySelector('[data-ag-lote-accion="centrar"]');
    const botonCapa = contenedor.querySelector('[data-ag-lote-accion="capa"]');

    /** Apaga los cuatro modos de Geoman antes de prender uno — son
     *  mutuamente excluyentes desde la barra, aunque la librería en sí
     *  permitiría combinarlos. */
    const apagarModos = () => {
        if (mapa.pm.globalDrawModeEnabled()) {
            mapa.pm.disableDraw();
        }

        if (mapa.pm.globalEditModeEnabled()) {
            mapa.pm.disableGlobalEditMode();
        }

        if (mapa.pm.globalDragModeEnabled()) {
            mapa.pm.toggleGlobalDragMode();
        }

        if (mapa.pm.globalRemovalModeEnabled()) {
            mapa.pm.toggleGlobalRemovalMode();
        }
    };

    const sincronizarEstadoBarra = () => {
        botonDibujar?.setAttribute('aria-pressed', String(mapa.pm.globalDrawModeEnabled()));
        botonEditar?.setAttribute('aria-pressed', String(mapa.pm.globalEditModeEnabled()));
        botonMover?.setAttribute('aria-pressed', String(mapa.pm.globalDragModeEnabled()));
        botonBorrar?.setAttribute('aria-pressed', String(mapa.pm.globalRemovalModeEnabled()));
    };

    ['pm:globaleditmodetoggled', 'pm:globaldragmodetoggled', 'pm:globalremovalmodetoggled']
        .forEach((evento) => mapa.on(evento, sincronizarEstadoBarra));

    mapa.on('pm:globaldrawmodetoggled', ({ enabled }) => {
        sincronizarEstadoBarra();

        if (enabled) {
            puntosEnCurso = [];
        } else {
            // Se salió del modo dibujar sin terminar el trazo (Escape,
            // volver a clickear "Dibujar"): la medida en vivo vuelve a
            // reflejar lo que hay REALMENTE guardado, no el trazo cancelado.
            sincronizar();
        }
    });

    sincronizarEstadoBarra();

    botonDibujar?.addEventListener('click', () => {
        if (mapa.pm.globalDrawModeEnabled()) {
            mapa.pm.disableDraw();

            return;
        }

        apagarModos();
        mapa.pm.enableDraw('Polygon');
    });

    botonEditar?.addEventListener('click', () => {
        const activar = !mapa.pm.globalEditModeEnabled();
        apagarModos();

        if (activar) {
            mapa.pm.enableGlobalEditMode();
        }
    });

    botonMover?.addEventListener('click', () => {
        const activo = mapa.pm.globalDragModeEnabled();
        apagarModos();

        if (!activo) {
            mapa.pm.toggleGlobalDragMode();
        }
    });

    botonBorrar?.addEventListener('click', () => {
        const activo = mapa.pm.globalRemovalModeEnabled();
        apagarModos();

        if (!activo) {
            mapa.pm.toggleGlobalRemovalMode();
        }
    });

    botonCentrar?.addEventListener('click', () => {
        const capas = capa.getLayers();

        if (capas.length > 0) {
            mapa.fitBounds(capa.getBounds(), { padding: [16, 16], maxZoom: ZOOM_MAXIMO_AL_ENCUADRAR });
        } else {
            mapa.setView([CENTRO_POR_DEFECTO.lat, CENTRO_POR_DEFECTO.lng], ZOOM_SIN_GEOMETRIA);
        }
    });

    if (botonCapa) {
        const etiquetaVerCalles = botonCapa.dataset.agLoteMapaCapaCalles;
        const etiquetaVerSatelite = botonCapa.dataset.agLoteMapaCapaSatelite;

        const actualizarBotonCapa = () => {
            const etiqueta = esSatelital ? etiquetaVerCalles : etiquetaVerSatelite;

            botonCapa.title = etiqueta;
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

    const botonPantallaCompleta = contenedor.querySelector('[data-ag-lote-mapa-boton-pantalla-completa]');
    const iconoPantallaCompleta = contenedor.querySelector('[data-ag-lote-mapa-icono-pantalla-completa]');

    if (botonPantallaCompleta) {
        activarPantallaCompleta({
            contenedor: marco,
            mapa,
            boton: botonPantallaCompleta,
            iconoBoton: iconoPantallaCompleta,
            etiquetaEntrar: botonPantallaCompleta.dataset.agLoteMapaEntrar,
            etiquetaSalir: botonPantallaCompleta.dataset.agLoteMapaSalir,
        });
    }

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
