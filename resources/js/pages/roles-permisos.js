/**
 * Matriz de permisos de un rol (panel.roles.permisos.edit) — la parte que
 * tiene que responder mientras se togglea, sin ida y vuelta al servidor:
 *
 * 1. La VISTA PREVIA del sidebar: qué ítems vería alguien operando con este
 *    rol, y qué módulos desaparecen enteros. Es lo que convierte la pantalla
 *    en una respuesta a "¿qué le cambio a esta persona?" en vez de una lista
 *    de códigos.
 * 2. El aviso de ACCIÓN HUÉRFANA: una acción encendida sobre una pantalla que
 *    el rol no ve. Es una combinación válida en la base que no hace nada.
 *    Se AVISA, no se bloquea — puede ser deliberado dejarla lista para cuando
 *    se encienda la pantalla; y el servidor tampoco la rechaza.
 * 3. El contador de cambios sin guardar de la barra de acciones y el estado
 *    «Guardando…» al enviar.
 * 4. Por MÓDULO: el contador de permisos activos y el interruptor «Todo el
 *    módulo» (enciende o apaga de una vez lo que el actor puede tocar; queda a
 *    medias si solo algunos están encendidos).
 *
 * Mejora progresiva: sin este archivo el formulario sigue guardando bien —
 * son checkboxes nativos dentro de un <form>. Lo que se pierde es saber qué
 * estás haciendo antes de hacerlo.
 *
 * Los textos NO se arman acá: viajan en `data-*` desde el Blade, ya
 * traducidos y ya pluralizados por Laravel (ADR 0013 — este archivo no sabe
 * de idiomas).
 */

const RAIZ = '[data-ag-permisos]';

/** Estado inicial del formulario, para contar cambios contra él. */
function estadoInicial(formulario) {
    const inicial = new Set();

    formulario.querySelectorAll('input[type="checkbox"][name="permisos[]"]').forEach((casilla) => {
        if (casilla.defaultChecked) {
            inicial.add(casilla.value);
        }
    });

    return inicial;
}

function marcados(formulario) {
    const set = new Set();

    formulario.querySelectorAll('input[type="checkbox"][name="permisos[]"]').forEach((casilla) => {
        if (casilla.checked) {
            set.add(casilla.value);
        }
    });

    return set;
}

/**
 * Vista previa: prende/apaga cada ítem y recalcula el conteo del módulo.
 * El módulo entero se marca oculto cuando ninguna de sus pantallas está
 * encendida — es exactamente lo que hace el riel del panel real.
 */
function pintarPreview(formulario, activos) {
    formulario.querySelectorAll('[data-ag-preview-modulo]').forEach((modulo) => {
        const items = modulo.querySelectorAll('[data-ag-preview-item]');
        let visibles = 0;

        items.forEach((item) => {
            const visible = activos.has(item.dataset.agPreviewItem);
            item.classList.toggle('is-visible', visible);

            if (visible) {
                visibles += 1;
            }
        });

        modulo.classList.toggle('is-oculto', visibles === 0);

        const conteo = modulo.querySelector('[data-ag-preview-conteo]');

        if (conteo) {
            conteo.textContent = visibles === 0
                ? conteo.dataset.agTextoOculto
                : `${visibles}/${items.length}`;
        }
    });
}

/**
 * Resumen por módulo del editor (contador de permisos activos e interruptor
 * «Todo el módulo») y, por pantalla, el conteo de acciones más el aviso de
 * huérfana.
 */
function pintarEditor(formulario, activos) {
    formulario.querySelectorAll('[data-ag-modulo]').forEach((modulo) => {
        const pantallas = modulo.querySelectorAll('[data-ag-pantalla]');

        pantallas.forEach((pantalla) => {
            const activa = activos.has(pantalla.dataset.agPantalla);
            pantalla.classList.toggle('is-activa', activa);

            const acciones = pantalla.querySelectorAll('[data-ag-chip-accion]');
            const activasDeLaPantalla = [...acciones].filter((chip) => chip.checked).length;

            const conteo = pantalla.querySelector('[data-ag-pantalla-conteo]');

            if (conteo && acciones.length > 0) {
                conteo.textContent = conteo.dataset.agPlantilla
                    .replace(':activas', String(activasDeLaPantalla))
                    .replace(':total', String(acciones.length));
            }

            const aviso = pantalla.querySelector('[data-ag-huerfana]');

            if (aviso) {
                const huerfanas = ! activa && activasDeLaPantalla > 0;
                aviso.hidden = ! huerfanas;

                if (huerfanas) {
                    const texto = aviso.querySelector('[data-ag-huerfana-texto]');
                    // Laravel manda las dos formas separadas por "|" ya
                    // traducidas; elegir cuál es lo único que queda acá.
                    const formas = aviso.dataset.agPlantilla.split('|');
                    const forma = formas[activasDeLaPantalla === 1 ? 0 : formas.length - 1];

                    texto.textContent = forma
                        .replace(':cantidad', String(activasDeLaPantalla))
                        .replace(':codigo', aviso.dataset.agCodigo);
                }
            }
        });

        const casillas = [...modulo.querySelectorAll('input[type="checkbox"][name="permisos[]"]')];
        const editables = casillas.filter((casilla) => ! casilla.disabled);
        const encendidas = casillas.filter((casilla) => casilla.checked).length;
        const resumen = modulo.querySelector('[data-ag-modulo-resumen]');

        if (resumen) {
            resumen.textContent = resumen.dataset.agPlantilla
                .replace(':activos', String(encendidas))
                .replace(':total', String(casillas.length));
        }

        const todo = modulo.querySelector('[data-ag-modulo-todo]');

        if (todo) {
            const editablesEncendidas = editables.filter((casilla) => casilla.checked).length;

            todo.checked = editables.length > 0 && editablesEncendidas === editables.length;
            todo.indeterminate = editablesEncendidas > 0 && editablesEncendidas < editables.length;
        }
    });
}

function pintarBarra(formulario, inicial, activos) {
    const barra = formulario.querySelector('[data-ag-permisos-barra]');

    if (! barra) {
        return;
    }

    const estado = barra.querySelector('.ag-form-actions-bar__status');

    if (! estado) {
        return;
    }

    // El estado de guardado se anuncia a lectores de pantalla sin mover el foco.
    estado.setAttribute('role', 'status');

    let cambios = 0;

    inicial.forEach((id) => {
        if (! activos.has(id)) {
            cambios += 1;
        }
    });
    activos.forEach((id) => {
        if (! inicial.has(id)) {
            cambios += 1;
        }
    });

    barra.classList.toggle('is-sucio', cambios > 0);

    if (cambios === 0) {
        estado.textContent = barra.dataset.agSinCambios;

        return;
    }

    const formas = barra.dataset.agConCambios.split('|');
    const forma = formas[cambios === 1 ? 0 : formas.length - 1];
    estado.textContent = forma.replace(':cantidad', String(cambios));
}

document.addEventListener('DOMContentLoaded', () => {
    const formulario = document.querySelector(RAIZ);

    if (! formulario) {
        return;
    }

    const inicial = estadoInicial(formulario);

    const repintar = () => {
        const activos = marcados(formulario);
        pintarPreview(formulario, activos);
        pintarEditor(formulario, activos);
        pintarBarra(formulario, inicial, activos);
    };

    formulario.addEventListener('change', (evento) => {
        const todo = evento.target.closest('[data-ag-modulo-todo]');

        // «Todo el módulo»: lo que se puede tocar toma el estado del interruptor.
        // Lo bloqueado o no concedible queda como está (el servidor lo revalida).
        if (todo) {
            const modulo = todo.closest('[data-ag-modulo]');

            modulo.querySelectorAll('input[type="checkbox"][name="permisos[]"]:not(:disabled)').forEach((casilla) => {
                casilla.checked = todo.checked;
            });

            repintar();

            return;
        }

        if (evento.target.matches('input[type="checkbox"][name="permisos[]"]')) {
            repintar();
        }
    });

    // Estado de guardado: al enviar, la barra dice «Guardando…» y el botón se
    // apaga para que un segundo clic no mande el formulario dos veces.
    formulario.addEventListener('submit', (evento) => {
        if (formulario.dataset.agEnviando === '1') {
            evento.preventDefault();

            return;
        }

        formulario.dataset.agEnviando = '1';

        const barra = formulario.querySelector('[data-ag-permisos-barra]');
        const estado = barra?.querySelector('.ag-form-actions-bar__status');
        const guardar = formulario.querySelector('[data-ag-permisos-guardar]');

        barra?.classList.add('is-guardando');

        if (estado && barra.dataset.agGuardando) {
            estado.textContent = barra.dataset.agGuardando;
        }

        if (guardar) {
            guardar.setAttribute('aria-disabled', 'true');
            guardar.classList.add('is-guardando');
        }
    });

    // `reset` restaura los `defaultChecked` DESPUÉS de que el evento se
    // despacha, así que el repintado va en el siguiente tick — si no,
    // pintaría el estado anterior al reset.
    formulario.addEventListener('reset', () => {
        window.setTimeout(repintar, 0);
    });

    repintar();
});
