/**
 * Ventanas horarias dinámicas del formulario de contrato (HU-23, tarea 34):
 * agregar y quitar filas de `ventanas[]` sin recargar la página. Mismo patrón
 * que `resources/js/pages/clientes-form.js` (tarea 33) — JS vanilla, clona el
 * `<template>` que ya trae el partial `_ventana-fila.blade.php` con el
 * placeholder `__INDICE__` en cada `name`, y lo reemplaza por el próximo
 * índice libre. No hay reindexado al quitar una fila: PHP arma igual el
 * array de `ventanas` aunque los índices numéricos queden con huecos.
 *
 * Filtra el `<select>` de campaña según el cliente elegido (ADR 0015 punto
 * 1, tarea 69): el contrato es con un cliente y para una campaña SUYA, mismo
 * patrón que rubro/subrubro en `gastos-form.js`. Es presentación, no
 * validación — el servidor (`Aplicacion/CrearContrato`) rechaza igual una
 * campaña de otro cliente ante un POST manual.
 *
 * Guard de presencia en el DOM (mismo criterio que `login.js`): en cualquier
 * página sin `[data-ag-contratos-form]` este módulo no hace nada.
 */
document.addEventListener('DOMContentLoaded', () => {
    const formulario = document.querySelector('[data-ag-contratos-form]');
    if (!formulario) return;

    const selectCliente = formulario.querySelector('[data-ag-contrato-cliente]');
    const selectCampania = formulario.querySelector('[data-ag-contrato-campania]');

    if (selectCliente && selectCampania) {
        // El mapa campaña→cliente viaja en el propio <select> (tarea 76,
        // `x-atoms.select`), no por <option> como antes: el combobox que arma
        // atoms/select.js reemplaza al nativo visualmente y no soporta
        // atributos por opción.
        const mapaClienteCampania = JSON.parse(selectCampania.dataset.mapaClienteCampania || '{}');
        const opciones = Array.from(selectCampania.querySelectorAll('option')).filter((opcion) => opcion.value !== '');

        const aplicarFiltro = () => {
            const clienteId = selectCliente.value;
            let valorSigueVisible = false;

            opciones.forEach((opcion) => {
                const visible = String(mapaClienteCampania[opcion.value]) === clienteId;
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
    }

    const contenedor = formulario.querySelector('[data-ag-ventanas]');
    const lista = formulario.querySelector('[data-ag-ventanas-lista]');
    const plantilla = formulario.querySelector('[data-ag-ventana-template]');
    const botonAgregar = formulario.querySelector('[data-ag-ventanas-agregar]');

    if (!contenedor || !lista || !plantilla || !botonAgregar) return;

    let proximoIndice = lista.querySelectorAll('[data-ag-ventana-fila]').length;

    botonAgregar.addEventListener('click', () => {
        const html = plantilla.innerHTML.replaceAll('__INDICE__', String(proximoIndice));
        proximoIndice += 1;

        const envoltorio = document.createElement('div');
        envoltorio.innerHTML = html.trim();

        const fila = envoltorio.firstElementChild;
        if (fila) {
            lista.appendChild(fila);
        }
    });

    contenedor.addEventListener('click', (evento) => {
        const botonQuitar = evento.target.closest('[data-ag-ventana-quitar]');
        if (!botonQuitar) return;

        botonQuitar.closest('[data-ag-ventana-fila]')?.remove();
    });
});
