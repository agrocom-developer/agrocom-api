/**
 * Filtra el `<select>` de subrubro según el rubro elegido (HU-33, tarea 47):
 * el catálogo trae subrubros de todos los rubros en una sola lista (`fin_subrubros`,
 * evita un endpoint aparte para 9 rubros), y este script solo muestra los que
 * pertenecen al rubro seleccionado. Es presentación, no validación: el
 * servidor no exige que `subrubro_id` pertenezca a `rubro_id` (ver docblock
 * de `CrearGastoRequest`) — un POST manual podría descalzarlos igual.
 *
 * Guard de presencia en el DOM (mismo criterio que `campos-form.js`): en
 * cualquier página sin `[data-ag-gastos-form]` este módulo no hace nada.
 */
document.addEventListener('DOMContentLoaded', () => {
    const formulario = document.querySelector('[data-ag-gastos-form]');
    if (!formulario) return;

    const selectRubro = formulario.querySelector('[data-ag-gasto-rubro]');
    const selectSubrubro = formulario.querySelector('[data-ag-gasto-subrubro]');
    if (!selectRubro || !selectSubrubro) return;

    const opciones = Array.from(selectSubrubro.querySelectorAll('option[data-rubro-id]'));

    const aplicarFiltro = () => {
        const rubroId = selectRubro.value;
        let valorSigueVisible = false;

        opciones.forEach((opcion) => {
            const visible = opcion.dataset.rubroId === rubroId;
            opcion.hidden = !visible;
            opcion.disabled = !visible;
            if (visible && opcion.value === selectSubrubro.value) {
                valorSigueVisible = true;
            }
        });

        if (!valorSigueVisible) {
            selectSubrubro.value = '';
        }
    };

    selectRubro.addEventListener('change', aplicarFiltro);
    aplicarFiltro();
});
