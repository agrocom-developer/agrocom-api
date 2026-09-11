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

document.querySelectorAll('[data-ag-datetime]').forEach((input) => {
    flatpickr(input, {
        locale: Spanish,
        enableTime: true,
        time_24hr: true,
        dateFormat: 'Y-m-d H:i',
    });
});
