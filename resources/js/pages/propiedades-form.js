/**
 * Cascada Departamento → Provincia → Municipio del formulario de propiedad
 * (adenda 16/9/2026 a ADR 0018 punto 1). Mismo patrón "estrategia embebida"
 * que ya usa `contratos-form.js` para Propiedad → Lote: los 3 niveles del
 * catálogo (~460 filas, liviano) viajan en un
 * `<script type="application/json" data-ag-geografia">`, sin AJAX — este JS
 * solo filtra en el cliente.
 *
 * Manipula el `<select>` NATIVO (innerHTML + .disabled): el combobox
 * mejorado de `atoms/select.js` reacciona solo, vía su propio
 * `MutationObserver` sobre el nativo (ver su docblock) — no hace falta
 * reinicializar nada acá, mismo criterio que el select de Propiedad en
 * `contratos-form.js`.
 *
 * El valor inicial de provincia/municipio (edición, o `old()` tras un error
 * de validación) no puede venir como `<option selected>` porque el servidor
 * no arma esas listas — viaja en `data-valor-inicial` y este JS lo
 * pre-selecciona después de poblar, una sola vez al cargar.
 *
 * Guard de presencia en el DOM (mismo criterio que `contratos-form.js`): en
 * cualquier página sin `[data-ag-propiedades-form]` este módulo no hace nada.
 */
document.addEventListener('DOMContentLoaded', () => {
    const formulario = document.querySelector('[data-ag-propiedades-form]');
    if (!formulario) return;

    const selectDepartamento = formulario.querySelector('[name="departamento_id"]');
    const selectProvincia = formulario.querySelector('[name="provincia_id"]');
    const selectMunicipio = formulario.querySelector('[name="municipio_id"]');
    const scriptDatos = formulario.querySelector('[data-ag-geografia]');

    if (!selectDepartamento || !selectProvincia || !selectMunicipio || !scriptDatos) return;

    /** @type {{departamentos: {id: number, nombre: string}[], provincias: {id: number, departamento_id: number, nombre: string}[], municipios: {id: number, provincia_id: number, nombre: string}[]}} */
    const geografia = JSON.parse(scriptDatos.textContent);

    const valorInicialProvincia = selectProvincia.dataset.valorInicial || '';
    const valorInicialMunicipio = selectMunicipio.dataset.valorInicial || '';

    const poblarSelect = (select, opciones, placeholder, valorAPreseleccionar) => {
        select.innerHTML = '';

        const opcionPlaceholder = document.createElement('option');
        opcionPlaceholder.value = '';
        opcionPlaceholder.textContent = placeholder;
        opcionPlaceholder.disabled = true;
        opcionPlaceholder.hidden = true;
        select.appendChild(opcionPlaceholder);

        opciones.forEach((opcion) => {
            const elemento = document.createElement('option');
            elemento.value = String(opcion.id);
            elemento.textContent = opcion.nombre;
            select.appendChild(elemento);
        });

        const valorValido = valorAPreseleccionar && opciones.some((o) => String(o.id) === String(valorAPreseleccionar));
        select.value = valorValido ? String(valorAPreseleccionar) : '';
        opcionPlaceholder.selected = !valorValido;
    };

    const actualizarProvincias = (departamentoId, provinciaAPreseleccionar) => {
        const disponibles = departamentoId
            ? geografia.provincias.filter((p) => String(p.departamento_id) === String(departamentoId))
            : [];

        poblarSelect(selectProvincia, disponibles, selectProvincia.dataset.placeholder || '', provinciaAPreseleccionar);
        selectProvincia.disabled = disponibles.length === 0;
    };

    const actualizarMunicipios = (provinciaId, municipioAPreseleccionar) => {
        const disponibles = provinciaId
            ? geografia.municipios.filter((m) => String(m.provincia_id) === String(provinciaId))
            : [];

        poblarSelect(selectMunicipio, disponibles, selectMunicipio.dataset.placeholder || '', municipioAPreseleccionar);
        selectMunicipio.disabled = disponibles.length === 0;
    };

    selectDepartamento.addEventListener('change', () => {
        actualizarProvincias(selectDepartamento.value, null);
        actualizarMunicipios(null, null);
    });

    selectProvincia.addEventListener('change', () => {
        actualizarMunicipios(selectProvincia.value, null);
    });

    // Carga inicial: si ya hay departamento (edición, o `old()` tras un
    // error de validación), reconstruye la cadena completa una sola vez.
    if (selectDepartamento.value) {
        actualizarProvincias(selectDepartamento.value, valorInicialProvincia);

        if (selectProvincia.value) {
            actualizarMunicipios(selectProvincia.value, valorInicialMunicipio);
        }
    }
});
