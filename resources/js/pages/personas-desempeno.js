/**
 * Filtra el `<select>` de campaña según el cliente elegido en la ficha de
 * desempeño de una persona (HU-58, tarea 81): mismo patrón que el select
 * dependiente de `contratos-form.js` — el mapa campaña→cliente viaja en un
 * data-attribute JSON del propio `<select>` (`atoms/select.js` reemplaza al
 * nativo visualmente y no soporta atributos por `<option>`).
 *
 * A diferencia de `contratos-form.js` (cliente siempre elegido, es un
 * formulario de alta), acá el cliente es un filtro OPCIONAL: sin cliente
 * elegido se muestran las campañas de TODos los clientes, no ninguna.
 *
 * Es presentación, no el filtro real: `ObtenerDesempenioPersona` ya filtra
 * en PHP sin importar qué combinación haya mostrado el navegador.
 *
 * Guard de presencia en el DOM (mismo criterio que `login.js`): en cualquier
 * página sin `[data-ag-desempenio-filtros]` este módulo no hace nada.
 */
document.addEventListener('DOMContentLoaded', () => {
    const formulario = document.querySelector('[data-ag-desempenio-filtros]');
    if (!formulario) return;

    const selectCliente = formulario.querySelector('[data-ag-desempenio-cliente]');
    const selectCampania = formulario.querySelector('[data-ag-desempenio-campania]');

    if (!selectCliente || !selectCampania) return;

    const mapaClienteCampania = JSON.parse(selectCampania.dataset.mapaClienteCampania || '{}');
    const opciones = Array.from(selectCampania.querySelectorAll('option')).filter((opcion) => opcion.value !== '');

    const aplicarFiltro = () => {
        const clienteId = selectCliente.value;
        let valorSigueVisible = clienteId === '';

        opciones.forEach((opcion) => {
            const visible = clienteId === '' || String(mapaClienteCampania[opcion.value]) === clienteId;
            opcion.hidden = !visible;
            opcion.disabled = !visible;
            if (visible && opcion.value === selectCampania.value) {
                valorSigueVisible = true;
            }
        });

        if (!valorSigueVisible) {
            selectCampania.value = '';
        }
    };

    selectCliente.addEventListener('change', aplicarFiltro);
    aplicarFiltro();
});
