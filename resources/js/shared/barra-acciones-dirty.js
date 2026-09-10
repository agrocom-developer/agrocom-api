/**
 * shared/barra-acciones-dirty.js — oculta `.ag-form-actions-bar` en un
 * formulario de EDICIÓN hasta que algo cambie.
 *
 * El motivo: la ficha de edición de este panel dobla de pantalla "ver" — no
 * existe una vista de solo lectura separada — así que si alguien entra solo
 * a mirar los datos, la barra flotante de "Cancelar"/"Guardar" no debería
 * aparecer. En alta (`crear`) no hay nada "ya guardado" que se esté
 * mirando, así que ahí la barra queda visible siempre, como antes.
 *
 * "Formulario de edición" se detecta por el `_method=PUT|PATCH` que emite
 * `@method(...)` — no hace falta que cada Blade declare un data-attribute
 * nuevo, el spoofing de Laravel ya es la señal.
 *
 * Mejora progresiva: sin este archivo la barra queda siempre visible y el
 * formulario sigue guardando igual — son inputs nativos dentro de un
 * <form>. Mismo criterio que `pages/roles-permisos.js`, con el que convive
 * sin pisarse: ese archivo sigue pintando su propio texto/color de estado
 * sobre la MISMA barra, este solo decide si se ve o no.
 */

function esFormularioDeEdicion(formulario) {
    const metodo = formulario.querySelector('input[name="_method"]');

    return !!metodo && ['PUT', 'PATCH'].includes(metodo.value.toUpperCase());
}

/** Comparación aproximada por serialización: alcanza para saber SI algo
 *  cambió, no hace falta un diff campo por campo. */
function serializar(formulario) {
    return new URLSearchParams(new FormData(formulario)).toString();
}

function vigilar(formulario, barra) {
    const inicial = serializar(formulario);

    const revisar = () => {
        barra.hidden = serializar(formulario) === inicial;
    };

    formulario.addEventListener('input', revisar);
    formulario.addEventListener('change', revisar);

    // `reset` restaura los valores por defecto DESPUÉS de despachar el
    // evento, así que revisar en el siguiente tick — si no, comparar contra
    // el estado previo al reset.
    formulario.addEventListener('reset', () => window.setTimeout(revisar, 0));

    revisar();
}

function activarTodas(raiz = document) {
    raiz.querySelectorAll('.ag-form-actions-bar').forEach((barra) => {
        const formulario = barra.closest('form');

        if (formulario && esFormularioDeEdicion(formulario)) {
            vigilar(formulario, barra);
        }
    });
}

document.addEventListener('DOMContentLoaded', () => activarTodas());
