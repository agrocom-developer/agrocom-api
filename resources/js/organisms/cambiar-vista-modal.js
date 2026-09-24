/**
 * organisms/cambiar-vista-modal.js — comportamiento del selector «Cambiar
 * vista» del dashboard (resources/views/components/../pages/dashboard/
 * _modal-cambiar-vista.blade.php). No abre la vista por sí mismo: eso lo
 * hace el form+confirm-modal por cuenta que ya existe, POST a
 * `panel.usuarios.ver-como` — este archivo solo filtra la lista y encadena
 * el cierre de este modal con la apertura del de confirmación elegido.
 *
 * Dos modales de Bootstrap abiertos a la vez duplican backdrop y foco, así
 * que el encadenamiento espera a que este termine de cerrar
 * («hidden.bs.modal») antes de abrir el siguiente — nunca los dos a la vez.
 */

const ID_MODAL_SELECTOR = 'ag-cambiar-vista-modal';

function normalizar(texto) {
    return texto.trim().toLowerCase();
}

function filtrar(modal, termino) {
    const terminoNormalizado = normalizar(termino);
    const grupos = modal.querySelectorAll('[data-ag-cambiar-vista-grupo]');
    let algunaVisible = false;

    grupos.forEach((grupo) => {
        let visiblesEnGrupo = 0;

        grupo.querySelectorAll('[data-ag-cambiar-vista-opcion]').forEach((opcion) => {
            const coincide = terminoNormalizado === '' || (opcion.dataset.agCambiarVistaTexto ?? '').includes(terminoNormalizado);
            opcion.hidden = !coincide;
            visiblesEnGrupo += coincide ? 1 : 0;
        });

        // El contador del section-head nace con el total del grupo (lo pintó
        // Blade); mientras hay búsqueda, cuenta lo que realmente se ve —
        // "PILOTO DE DRON 3" con una sola fila visible confundiría más de lo
        // que ayuda.
        const contador = grupo.querySelector('.ag-section-head__count');

        if (contador) {
            contador.dataset.total ??= contador.textContent;
            contador.textContent = terminoNormalizado === '' ? contador.dataset.total : String(visiblesEnGrupo);
        }

        grupo.hidden = visiblesEnGrupo === 0;
        algunaVisible ||= visiblesEnGrupo > 0;
    });

    const sinResultados = modal.querySelector('[data-ag-cambiar-vista-sin-resultados]');

    if (sinResultados) {
        sinResultados.hidden = algunaVisible || grupos.length === 0;
    }
}

document.addEventListener('input', (evento) => {
    const campo = evento.target.closest('[data-ag-cambiar-vista-buscador]');
    const modal = campo?.closest('[data-ag-cambiar-vista]');

    if (campo && modal) {
        filtrar(modal, campo.value);
    }
});

// Reabrir con la búsqueda anterior seguiría mostrando la lista filtrada de
// la última vez — se limpia siempre al abrir.
document.addEventListener('show.bs.modal', (evento) => {
    if (evento.target.id !== ID_MODAL_SELECTOR) {
        return;
    }

    const campo = evento.target.querySelector('[data-ag-cambiar-vista-buscador]');

    if (campo) {
        campo.value = '';
    }

    filtrar(evento.target, '');
});

document.addEventListener('click', (evento) => {
    const disparador = evento.target.closest('[data-ag-cambiar-vista-elegir]');

    if (!disparador) {
        return;
    }

    const idDestino = disparador.dataset.agCambiarVistaDestino;
    const elementoDestino = idDestino ? document.getElementById(idDestino) : null;
    const modalSelector = document.getElementById(ID_MODAL_SELECTOR);
    const Modal = window.bootstrap?.Modal;

    if (!elementoDestino || !modalSelector || !Modal) {
        return;
    }

    modalSelector.addEventListener(
        'hidden.bs.modal',
        () => Modal.getOrCreateInstance(elementoDestino).show(),
        { once: true },
    );

    Modal.getOrCreateInstance(modalSelector).hide();
});
