/**
 * Filtra el `<select>` de propiedad según el cliente elegido, en la ficha de
 * un lote suelto (tarea 77, HU-54, etapa 2) — mismo patrón que cliente→
 * campaña en `contratos-form.js`. Es presentación, no validación: el
 * servidor (`CrearLoteRequest`/`ActualizarLoteRequest`) valida `campo_id`
 * contra `com_campos` sin importar qué cliente esté seleccionado ante un
 * POST manual — el cliente ni siquiera viaja como columna del lote, lo
 * hereda de su propiedad.
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
    const opciones = Array.from(selectPropiedad.querySelectorAll('option')).filter((opcion) => opcion.value !== '');

    const aplicarFiltro = () => {
        const clienteId = selectCliente.value;
        let valorSigueVisible = false;

        opciones.forEach((opcion) => {
            const visible = clienteId === '' || String(mapaClientePropiedad[opcion.value]) === clienteId;
            opcion.hidden = !visible;
            opcion.disabled = !visible;
            if (visible && opcion.value === selectPropiedad.value) {
                valorSigueVisible = true;
            }
        });

        if (!valorSigueVisible) {
            selectPropiedad.value = '';
        }
    };

    selectCliente.addEventListener('change', aplicarFiltro);
    aplicarFiltro();
});
