// Comportamiento de la molécula `timezone-selector` (tarea 63)
// Selector de zona horaria en el topbar: lee el cambio del select nativo
// (disparado por el combobox mejorado del átomo `select`), persiste via
// POST a la URL de `<meta name="ag-preferencias-zona-horaria-url">` con
// payload JSON `{zona_horaria: valor}`, fire-and-forget con .catch()
// silencioso (mismo patrón que theme-toggle.js).

document.addEventListener('change', (event) => {
    const selectElement = event.target.closest('[data-ag-timezone-selector] select');

    if (!selectElement) {
        return;
    }

    const zonaHoraria = selectElement.value;
    const url = document.querySelector('meta[name="ag-preferencias-zona-horaria-url"]')?.content;

    if (!url) {
        return; // página sin usuario autenticado (login) — nada que persistir
    }

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content,
            Accept: 'application/json',
        },
        body: JSON.stringify({ zona_horaria: zonaHoraria }),
    }).catch(() => {
        // Fire-and-forget: el select ya cambió en la UI, que sea suficiente
        // mientras el servidor procesa la persistencia en background.
    });
});
