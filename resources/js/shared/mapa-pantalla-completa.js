/**
 * shared/mapa-pantalla-completa.js — alterna un contenedor de mapa Leaflet a
 * pantalla completa de verdad (Fullscreen API), con respaldo a un
 * contenedor fijo al 100% de la ventana cuando el navegador la niega (sin
 * gesto suficiente, política de permisos, iframe restringido, etc.). Salida
 * por el mismo botón, por `Escape`, o por el control nativo del navegador.
 *
 * Compartido entre `organisms/lote-mapa-editor.js` y `organisms/dashboard-map.js`
 * (tarea 79): la mecánica de expandir/salir es la misma en los dos mapas —
 * solo cambia qué hay adentro del contenedor.
 *
 * El llamador conserva el polígono/zoom en curso porque este módulo nunca
 * recrea el mapa: solo cambia el tamaño de su contenedor y llama a
 * `invalidateSize()`, que Leaflet resuelve manteniendo el centro geográfico.
 */

const CLASE_RESPALDO = 'ag-mapa--pantalla-completa-respaldo';

/**
 * @param {object} opciones
 * @param {HTMLElement} opciones.contenedor - elemento que pasa a pantalla completa.
 * @param {import('leaflet').Map} opciones.mapa - instancia Leaflet dentro del contenedor.
 * @param {HTMLElement} opciones.boton - botón que alterna el estado.
 * @param {HTMLElement|null} [opciones.iconoBoton] - ícono Material Symbols dentro de `boton`, si se quiere alternar el glifo.
 * @param {string} opciones.etiquetaEntrar - texto de `title`/`aria-label` cuando la acción ES entrar.
 * @param {string} opciones.etiquetaSalir - texto de `title`/`aria-label` cuando la acción ES salir.
 * @param {(activo: boolean) => void} [opciones.alCambiar] - se llama tras cada cambio de estado.
 */
export function activarPantallaCompleta({
    contenedor,
    mapa,
    boton,
    iconoBoton = null,
    etiquetaEntrar,
    etiquetaSalir,
    alCambiar,
}) {
    let estabaFullscreen = false;

    const actualizar = (activo) => {
        boton.setAttribute('aria-pressed', String(activo));

        const etiqueta = activo ? etiquetaSalir : etiquetaEntrar;
        boton.title = etiqueta;
        boton.setAttribute('aria-label', etiqueta);

        if (iconoBoton) {
            iconoBoton.textContent = activo ? 'fullscreen_exit' : 'fullscreen';
        }

        // El contenedor recién cambió de tamaño (CSS de pantalla completa o
        // fullscreenchange del navegador): Leaflet no lo nota solo.
        requestAnimationFrame(() => mapa.invalidateSize());

        alCambiar?.(activo);
    };

    boton.addEventListener('click', async () => {
        if (contenedor.classList.contains(CLASE_RESPALDO)) {
            contenedor.classList.remove(CLASE_RESPALDO);
            actualizar(false);

            return;
        }

        if (document.fullscreenElement === contenedor) {
            await document.exitFullscreen?.();

            return; // 'fullscreenchange' actualiza el botón.
        }

        if (contenedor.requestFullscreen) {
            try {
                await contenedor.requestFullscreen();

                return; // 'fullscreenchange' actualiza el botón.
            } catch {
                // El navegador negó el pedido: sigue al respaldo en vez de
                // dejar el botón sin efecto.
            }
        }

        contenedor.classList.add(CLASE_RESPALDO);
        actualizar(true);
    });

    document.addEventListener('fullscreenchange', () => {
        const activo = document.fullscreenElement === contenedor;

        // Sin esto, el fullscreenchange de OTRO mapa de la misma página
        // (dashboard con más de un mapa, o cualquier otro elemento) también
        // actualizaría este botón.
        if (!activo && !estabaFullscreen) {
            return;
        }

        estabaFullscreen = activo;
        actualizar(activo);
    });

    contenedor.addEventListener('keydown', (evento) => {
        if (evento.key === 'Escape' && contenedor.classList.contains(CLASE_RESPALDO)) {
            contenedor.classList.remove(CLASE_RESPALDO);
            actualizar(false);
        }
    });
}
