// Comportamiento del átomo `datetime` (resources/views/components/atoms/datetime.blade.php).
// A diferencia de atoms/date.js (calendario propio hecho a mano), acá se usa
// flatpickr: construir un selector de hora accesible desde cero (grid de
// hora/minuto, roving tabindex) es mucho más trabajo que el calendario de
// días, y este campo solo tiene dos usos hoy (pausas) — no justifica
// replicar el patrón "date picker dialog" de atoms/date. Decisión explícita
// del usuario de instalar una librería de terceros para este caso puntual.
//
// El input real es texto plano (ver docblock del átomo): flatpickr escribe
// directo su valor formateado ahí, sin altInput ni input oculto — no hace
// falta ese mecanismo porque no hay un `type="date"` nativo cuyo formato
// haya que sortear.
//
// Se importa dinámicamente desde app.js solo si `[data-ag-datetime]` existe
// en el DOM (mismo criterio que dashboard-charts.js/dashboard-map.js), así
// que para cuando este módulo corre el DOM ya está listo.

import flatpickr from 'flatpickr';
import { Spanish } from 'flatpickr/dist/l10n/es.js';

// Footer "Hoy"/"Limpiar", mismo texto y comportamiento que
// `.ag-date__dialog-footer`/`.ag-date__today` de atoms/date — flatpickr no
// trae uno propio, así que se arma a mano una sola vez por instancia
// (onReady) y se estiliza en resources/css/components/datetime.css.
function agregarFooter(instancia, input) {
    const footer = document.createElement('div');
    footer.className = 'ag-datetime-footer';

    const limpiar = document.createElement('button');
    limpiar.type = 'button';
    limpiar.className = 'ag-datetime-footer__limpiar';
    limpiar.textContent = input.dataset.labelLimpiar;
    limpiar.addEventListener('click', () => instancia.clear());

    const hoy = document.createElement('button');
    hoy.type = 'button';
    hoy.className = 'ag-datetime-footer__hoy';
    hoy.textContent = input.dataset.labelHoy;
    hoy.addEventListener('click', () => instancia.setDate(new Date(), true));

    footer.append(limpiar, hoy);
    instancia.calendarContainer.appendChild(footer);
}

document.querySelectorAll('[data-ag-datetime]').forEach((input) => {
    flatpickr(input, {
        locale: Spanish,
        enableTime: true,
        time_24hr: true,
        dateFormat: 'Y-m-d H:i',
        onReady: (fechasSeleccionadas, valorTexto, instancia) => agregarFooter(instancia, input),
    });
});
