/**
 * Tab "Mapas" de /panel/configuracion: mientras "Forzar Leaflet" está
 * activado no hace falta tocar la llave de Google (el sistema ni la va a
 * usar), así que el campo arranca deshabilitado y solo se habilita cuando el
 * dueño apaga el forzado — evita cargar una llave a medio escribir sin
 * querer mientras el interruptor sigue en Leaflet.
 *
 * Progresivo: sin este archivo el campo de la llave queda editable siempre
 * (como antes de este cambio) — el formulario sigue guardando igual.
 */
document.addEventListener('DOMContentLoaded', () => {
    const interruptor = document.querySelector('[data-ag-config-switch-forzar-leaflet]');
    const campoLlave = document.querySelector('[data-ag-config-llave-google]');

    // `interruptor.disabled` = sin permiso de edición (`$puedeEditar` en
    // false): el switch ya viene deshabilitado del lado del servidor y el
    // usuario no puede tocarlo, así que no hay que enganchar nada — de lo
    // contrario `sincronizar()` podría REHABILITAR el campo de la llave (si
    // el switch quedó "apagado") pisando el disabled que puso el servidor
    // por falta de permiso, no por el estado del switch.
    if (! interruptor || ! campoLlave || interruptor.disabled) {
        return;
    }

    const sincronizar = () => {
        campoLlave.disabled = interruptor.checked;
    };

    interruptor.addEventListener('change', sincronizar);
    sincronizar();
});
