/**
 * organisms/vista-como-banner.js — comportamiento de la franja de "viendo como"
 * (tarea 140, resources/views/components/organisms/vista-como-banner.blade.php).
 *
 * El servidor ya rechaza con 403 todo lo que escriba mientras dura la vista:
 * esto no es la defensa, es cortesía. Sin ella, tocar «Guardar» o confirmar un
 * modal terminaría en una página de error; con ella el formulario no sale y un
 * aviso breve explica por qué. No hay nada que este archivo pueda "permitir":
 * si se lo salteara (JS apagado, un `fetch` a mano), el 403 sigue ahí.
 *
 * Solo actúa si el `<html>` lleva `data-ag-vista-como` (lo pone panel-shell
 * cuando AplicarVistaComo compartió la vista). Deja pasar los formularios GET
 * (buscadores y filtros) y el propio formulario «Volver a mi vista», que vive
 * dentro de la franja y es la única escritura que el servidor acepta.
 */

const DURACION_AVISO_MS = 4500;

function mensajeDeSoloLectura() {
    return document.querySelector('[data-ag-vista-como-banner]')?.dataset.agVistaComoMensaje ?? '';
}

function avisarSoloLectura() {
    const texto = mensajeDeSoloLectura();

    if (!texto || document.querySelector('.ag-vista-como-aviso')) {
        return;
    }

    const aviso = document.createElement('div');
    aviso.className = 'ag-vista-como-aviso';
    aviso.setAttribute('role', 'alert');
    aviso.textContent = texto;
    document.body.appendChild(aviso);

    window.setTimeout(() => aviso.remove(), DURACION_AVISO_MS);
}

if (document.documentElement.hasAttribute('data-ag-vista-como')) {
    // En captura: se decide antes que cualquier otro listener de submit de la
    // página (validaciones, borradores) y se corta ahí mismo.
    document.addEventListener(
        'submit',
        (evento) => {
            const formulario = evento.target;

            if (!(formulario instanceof HTMLFormElement) || formulario.closest('[data-ag-vista-como-banner]')) {
                return;
            }

            if ((formulario.getAttribute('method') || 'get').toLowerCase() === 'get') {
                return;
            }

            evento.preventDefault();
            evento.stopImmediatePropagation();
            avisarSoloLectura();
        },
        true,
    );
}
