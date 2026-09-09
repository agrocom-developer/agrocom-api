/**
 * Page: reportes-comerciales (HU-52, tarea 75)
 * Validación cliente: deshabilita los botones "Filtros" y "Generar" mientras
 * falte al menos uno de los dos selectores obligatorios (cliente, cultivo).
 * Progressive enhancement: sin JS, los botones quedan habilitados y la
 * validación la hace el servidor (que devuelve error bajo el selector que
 * falta).
 *
 * Patrón: delegación de eventos sobre el contenedor de la entrada (event
 * bubbling), no listeners por cada checkbox (ver atoms/checkbox-group.js).
 */

document.addEventListener('DOMContentLoaded', function () {
    const formEntrada = document.querySelector('.ag-reportes-comerciales__entrada');
    if (!formEntrada) return; // Sin formulario = estamos en pantalla de resultados

    const clienteCheckboxes = formEntrada.querySelectorAll('input[name="cliente_ids[]"]');
    const cultivoCheckboxes = formEntrada.querySelectorAll('input[name="cultivo_ids[]"]');
    const botones = formEntrada.querySelectorAll('button[type="button"], button[type="submit"]');

    /**
     * Verifica si al menos una casilla está marcada en cada grupo.
     */
    function validar() {
        const hayCliente = Array.from(clienteCheckboxes).some(cb => cb.checked);
        const hayCultivo = Array.from(cultivoCheckboxes).some(cb => cb.checked);
        const habilitado = hayCliente && hayCultivo;

        botones.forEach(boton => {
            boton.disabled = !habilitado;
        });
    }

    // Escucha cambios en ambos grupos (delegación de eventos)
    formEntrada.addEventListener('change', function (event) {
        if (event.target.matches('input[name="cliente_ids[]"], input[name="cultivo_ids[]"]')) {
            validar();
        }
    });

    // Validación inicial (ya puede haber valores premarcados por error de entrada)
    validar();
});
