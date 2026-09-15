/**
 * Cascade de 2 niveles en la ficha de un lote suelto (tarea 77, HU-54, etapa 2;
 * actualizado ADR 0020): cliente → propiedad. Mismo patrón que cliente→
 * campaña en `contratos-form.js`. Es presentación, no validación: el servidor
 * (`CrearLoteRequest`/`ActualizarLoteRequest`) valida `propiedad_id` contra
 * `com_propiedades` sin importar qué cliente esté seleccionado ante un POST manual
 * — el cliente ni siquiera viaja como columna del lote, lo hereda de su
 * propiedad.
 *
 * Guard de presencia en el DOM (mismo criterio que `login.js`): en cualquier
 * página sin `[data-ag-lotes-form]` este módulo no hace nada.
 */
document.addEventListener('DOMContentLoaded', () => {
    const formulario = document.querySelector('[data-ag-lotes-form]');
    if (!formulario) return;

    const selectCliente = formulario.querySelector('[data-ag-lote-cliente]');
    const selectPropiedad = formulario.querySelector('[data-ag-lote-propiedad]');

    if (!selectCliente || !selectPropiedad) return;

    // El mapa propiedad→cliente viaja en el propio <select> (tarea 76,
    // `x-atoms.select`), no por <option>: el combobox que arma
    // atoms/select.js reemplaza al nativo visualmente y no soporta
    // atributos por opción.
    const mapaClientePropiedad = JSON.parse(selectPropiedad.dataset.mapaClientePropiedad || '{}');

    const opcionesPropiedad = Array.from(selectPropiedad.querySelectorAll('option')).filter((opcion) => opcion.value !== '');

    const aplicarFiltros = () => {
        const clienteId = selectCliente.value;
        const propiedadId = selectPropiedad.value;

        // Filtrar propiedades por cliente
        let propiedadSigueVisible = false;
        opcionesPropiedad.forEach((opcion) => {
            const visible = clienteId === '' || String(mapaClientePropiedad[opcion.value]) === clienteId;
            opcion.hidden = !visible;
            opcion.disabled = !visible;
            if (visible && opcion.value === propiedadId) {
                propiedadSigueVisible = true;
            }
        });
        if (!propiedadSigueVisible) {
            selectPropiedad.value = '';
        }
    };

    selectCliente.addEventListener('change', aplicarFiltros);
    aplicarFiltros();
});
