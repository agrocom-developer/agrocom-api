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

    // Limpieza del lote (16/9/2026): el switch "¿está limpio?" oculta o
    // muestra el grado de obstáculos — `lotes/_lote-fila.blade.php` ya
    // resuelve el estado inicial correcto server-side (atributo HTML
    // `hidden`), esto solo reacciona al cambio. Por cada fila de lote en el
    // formulario (hoy una sola, reusable si el partial vuelve a incluirse
    // en un array).
    formulario.querySelectorAll('[data-ag-lote-fila]').forEach((bloque) => {
        const switchLimpio = bloque.querySelector('[data-ag-lote-limpio]');
        const envoltorioGrado = bloque.querySelector('[data-ag-lote-grado-obstaculos-wrap]');
        if (!switchLimpio || !envoltorioGrado) return;

        // El color del track (primario/gris) no alcanza para leer el
        // estado del switch a simple vista — el label inline responde la
        // pregunta en palabras ("Sí"/"No", ya traducidas en los `data-*`).
        const textoLabel = switchLimpio.closest('.ag-switch__control')?.querySelector('.ag-switch__label');
        const textoSi = switchLimpio.dataset.agLoteLimpioTextoSi;
        const textoNo = switchLimpio.dataset.agLoteLimpioTextoNo;

        switchLimpio.addEventListener('change', () => {
            envoltorioGrado.hidden = switchLimpio.checked;
            if (textoLabel) {
                textoLabel.textContent = switchLimpio.checked ? textoSi : textoNo;
            }
        });
    });
});
