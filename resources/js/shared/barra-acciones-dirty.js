/**
 * shared/barra-acciones-dirty.js — oculta `.ag-form-actions-bar` hasta la
 * PRIMERA interacción real del usuario con el formulario (15/9/2026,
 * pedido directo sobre el formulario de cliente, generalizado a todo
 * formulario con esta barra).
 *
 * El motivo: mostrar "Cancelar"/"Guardar" flotando antes de que el usuario
 * haya tocado algo no aporta nada — en un formulario de edición además
 * dobla de pantalla "ver" (no hay vista de solo lectura separada), así que
 * alguien que entra solo a mirar no debería ver botones de guardado.
 *
 * Revelado de una sola vía: la barra aparece en la primera de estas señales
 * — `input`/`change` sobre cualquier control (escribir, tildar un check,
 * elegir una opción) o un `click` en cualquier parte del formulario (un
 * botón "Reemplazar"/"Agregar fila", un link) — y una vez visible se queda
 * así por el resto de la carga de página: los listeners se dan de baja al
 * primer disparo, esta barra no vuelve a esconderse aunque el usuario
 * deshaga el cambio. A propósito no compara valores (ya no es "¿cambió
 * respecto al original?", es "¿el usuario hizo algo?") — evitaba el
 * parpadeo de mostrarse y esconderse de nuevo si el valor volvía a coincidir
 * con el inicial.
 *
 * Mejora progresiva: sin este archivo la barra queda siempre visible y el
 * formulario sigue guardando igual — son inputs nativos dentro de un
 * <form>. Convive con `pages/roles-permisos.js` sin pisarse: ese archivo
 * sigue pintando su propio texto/color de estado sobre la MISMA barra, este
 * solo decide si se ve o no.
 */

function vigilar(formulario, barra) {
    const revelar = () => {
        barra.hidden = false;
        formulario.removeEventListener('input', revelar);
        formulario.removeEventListener('change', revelar);
        formulario.removeEventListener('click', revelar);
    };

    formulario.addEventListener('input', revelar);
    formulario.addEventListener('change', revelar);
    formulario.addEventListener('click', revelar);
}

function activarTodas(raiz = document) {
    raiz.querySelectorAll('.ag-form-actions-bar').forEach((barra) => {
        const formulario = barra.closest('form');

        if (formulario) {
            barra.hidden = true;
            vigilar(formulario, barra);
        }
    });
}

document.addEventListener('DOMContentLoaded', () => activarTodas());
