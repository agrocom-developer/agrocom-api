// Comportamiento de la molécula `timezone-badge` (tarea 63; reescrito el
// 9/9/2026 al caer el selector del topbar).
//
// Antes esto escuchaba el `change` de un `<select>` de zonas IANA en el
// header. Ya no hay tal select: la zona horaria se CAPTURA, no se elige
// (pedido del dueño). Este script hace esa captura en el panel y el portal —
// el login ya la mandaba en su propio POST, pero eso solo cubre a quien
// acaba de loguearse: una sesión vieja, o una que empezó sin JS, se quedaba
// sin zona y ahora ya no tiene dónde ponerla a mano.
//
// Al cargar: compara la zona del navegador contra la que el badge trae del
// servidor (`data-ag-timezone`, vacío = ninguna guardada). Si no coinciden,
// pinta la nueva y la persiste con un POST a la URL de
// `<meta name="ag-preferencias-zona-horaria-url">` — fire-and-forget con
// .catch() silencioso, mismo patrón que theme-toggle.js.
//
// Que se persista también cuando ya había una guardada y CAMBIÓ es
// deliberado: sin selector, el navegador es la única fuente. Quien viaja o
// entra desde otra máquina ve las horas en la zona desde la que está
// mirando, sin tener que tocar nada.

function sincronizarZonaHoraria() {
    const badge = document.querySelector('[data-ag-timezone-badge]');

    if (!badge) {
        return; // página sin pie de panel/portal (login, error)
    }

    let zonaDelNavegador;

    try {
        zonaDelNavegador = Intl.DateTimeFormat().resolvedOptions().timeZone;
    } catch {
        return; // navegador sin soporte de Intl — se queda con lo que haya
    }

    if (!zonaDelNavegador || zonaDelNavegador === badge.dataset.agTimezone) {
        return;
    }

    badge.dataset.agTimezone = zonaDelNavegador;

    const valor = badge.querySelector('[data-ag-timezone-value]');

    if (valor) {
        valor.textContent = zonaDelNavegador.replace(/_/g, ' ');
    }

    const url = document.querySelector('meta[name="ag-preferencias-zona-horaria-url"]')?.content;

    if (!url) {
        return; // página sin usuario autenticado — nada que persistir
    }

    // Vista "como otro usuario" (tarea 140): solo lectura. La zona del
    // navegador se pinta en el badge, pero no se guarda en la cuenta observada.
    if (document.documentElement.hasAttribute('data-ag-vista-como')) {
        return;
    }

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content,
            Accept: 'application/json',
        },
        body: JSON.stringify({ zona_horaria: zonaDelNavegador }),
    }).catch(() => {
        // Fire-and-forget: el badge ya muestra la zona correcta, que el
        // servidor se entere en el próximo intento si este falló.
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', sincronizarZonaHoraria);
} else {
    sincronizarZonaHoraria();
}
