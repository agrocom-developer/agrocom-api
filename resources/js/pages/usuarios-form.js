/**
 * Formulario de usuario (tarea 65, HU-41): dos comportamientos, ambos
 * presentación pura — el servidor (`CrearUsuarioRequest`/
 * `ActualizarUsuarioRequest`) valida igual sin importar qué mande un POST
 * armado a mano.
 *
 * 1) Alta: el `<select>` de tipo alterna qué sección se ve (interna vs.
 *    portal) y deshabilita los campos de la sección oculta — un campo
 *    `disabled` no viaja en el POST, así que cambiar de tipo no arrastra
 *    `persona_id`/`roles[]` de sobra hacia una cuenta de portal, ni
 *    `contrato_id` hacia una interna.
 * 2) Filtra el `<select>` de contrato según el cliente elegido, mismo
 *    patrón que propiedad→cliente en `lotes-form.js`: el cliente es solo un
 *    FILTRO, no viaja como columna propia de `sec_user`.
 *
 * Guard de presencia en el DOM (mismo criterio que `login.js`): en cualquier
 * página sin `[data-ag-usuarios-form]` este módulo no hace nada.
 */
document.addEventListener('DOMContentLoaded', () => {
    const formulario = document.querySelector('[data-ag-usuarios-form]');
    if (!formulario) return;

    const seccionInterno = formulario.querySelector('[data-ag-usuario-seccion-interno]');
    const seccionCliente = formulario.querySelector('[data-ag-usuario-seccion-cliente]');
    const selectTipo = formulario.querySelector('[data-ag-usuario-tipo]');

    const alternarSeccion = (seccion, visible) => {
        if (!seccion) return;
        seccion.hidden = !visible;
        seccion.querySelectorAll('input, select').forEach((campo) => {
            campo.disabled = !visible;
        });
    };

    if (selectTipo && seccionInterno && seccionCliente) {
        const aplicarTipo = () => {
            const esCliente = selectTipo.value === 'cliente';
            alternarSeccion(seccionInterno, !esCliente);
            alternarSeccion(seccionCliente, esCliente);
        };

        selectTipo.addEventListener('change', aplicarTipo);
        aplicarTipo();
    }

    const selectCliente = formulario.querySelector('[data-ag-usuario-cliente]');
    const selectContrato = formulario.querySelector('[data-ag-usuario-contrato]');

    if (!selectCliente || !selectContrato) return;

    // El mapa contrato→cliente viaja en el propio <select> (tarea 76,
    // `x-atoms.select`), no por <option> — el combobox que arma
    // atoms/select.js reemplaza al nativo visualmente y no soporta
    // atributos por opción.
    const mapaClienteContrato = JSON.parse(selectContrato.dataset.mapaClienteContrato || '{}');
    const opciones = Array.from(selectContrato.querySelectorAll('option')).filter((opcion) => opcion.value !== '');

    const aplicarFiltroContrato = () => {
        const clienteId = selectCliente.value;
        let valorSigueVisible = false;

        opciones.forEach((opcion) => {
            const visible = clienteId === '' || String(mapaClienteContrato[opcion.value]) === clienteId;
            opcion.hidden = !visible;
            opcion.disabled = !visible;
            if (visible && opcion.value === selectContrato.value) {
                valorSigueVisible = true;
            }
        });

        if (!valorSigueVisible) {
            selectContrato.value = '';
        }
    };

    selectCliente.addEventListener('change', aplicarFiltroContrato);
    aplicarFiltroContrato();
});
