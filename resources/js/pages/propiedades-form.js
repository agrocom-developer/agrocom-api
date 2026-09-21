/**
 * Ubicación Departamento / Provincia / Municipio del formulario de propiedad
 * (adenda 16/9/2026 a ADR 0018 punto 1, ajustada el 19/9/2026). Mismo patrón
 * "estrategia embebida" que ya usa `contratos-form.js` para Propiedad → Lote:
 * los 3 niveles del catálogo (~460 filas, liviano) viajan en un
 * `<script type="application/json" data-ag-geografia">`, sin AJAX — este JS
 * solo filtra y sincroniza en el cliente.
 *
 * Reglas:
 * - Departamento filtra las provincias (como antes).
 * - Municipio YA NO depende de la provincia: lista TODOS los municipios, con la
 *   etiqueta "Municipio - Departamento" (hay nombres repetidos entre
 *   departamentos), y es un select con búsqueda (`atoms/select`, `searchable`).
 *   Sirve cuando solo se sabe el municipio, o se eligió mal el departamento.
 * - Al elegir un municipio se completan solos el departamento y la provincia
 *   que le corresponden, pisando lo que hubiera elegido antes.
 * - Lo que se elige después no puede contradecir al municipio: cambiar el
 *   departamento o la provincia a uno que no lo contiene lo limpia (el servidor
 *   igual lo exige, `ValidadorUbicacionGeografica`).
 *
 * Manipula el `<select>` NATIVO (innerHTML + .value): el combobox mejorado de
 * `atoms/select.js` reacciona solo, vía su `MutationObserver` y su listener de
 * `change` sobre el nativo — no hace falta reinicializar nada acá.
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

    const porId = (filas) => new Map(filas.map((fila) => [String(fila.id), fila]));
    const departamentos = porId(geografia.departamentos);
    const provincias = porId(geografia.provincias);
    const municipios = porId(geografia.municipios);

    const valorInicialProvincia = selectProvincia.dataset.valorInicial || '';
    const valorInicialMunicipio = selectMunicipio.dataset.valorInicial || '';

    // Mientras este script mueve los tres selects a la vez, sus propios
    // `change` no deben volver a disparar la lógica de cada uno.
    let sincronizando = false;

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

    const departamentoDeProvincia = (provincia) => (provincia ? departamentos.get(String(provincia.departamento_id)) : undefined);

    // Todos los municipios, ordenados por nombre, con su departamento al lado.
    const opcionesMunicipio = geografia.municipios
        .map((municipio) => {
            const departamento = departamentoDeProvincia(provincias.get(String(municipio.provincia_id)));

            return { id: municipio.id, nombre: departamento ? `${municipio.nombre} - ${departamento.nombre}` : municipio.nombre };
        })
        .sort((a, b) => a.nombre.localeCompare(b.nombre, 'es', { sensitivity: 'base' }));

    const actualizarProvincias = (departamentoId, provinciaAPreseleccionar) => {
        const disponibles = departamentoId
            ? geografia.provincias.filter((p) => String(p.departamento_id) === String(departamentoId))
            : [];

        poblarSelect(selectProvincia, disponibles, selectProvincia.dataset.placeholder || '', provinciaAPreseleccionar);
        selectProvincia.disabled = disponibles.length === 0;
    };

    // El municipio elegido y la provincia/departamento a los que pertenece.
    const municipioElegido = () => {
        const municipio = municipios.get(selectMunicipio.value);
        const provincia = municipio ? provincias.get(String(municipio.provincia_id)) : undefined;

        return { municipio, provincia, departamento: departamentoDeProvincia(provincia) };
    };

    const limpiarMunicipio = () => {
        if (!selectMunicipio.value) return;

        selectMunicipio.value = '';
        // El combobox de `atoms/select` refresca su etiqueta al oír este `change`.
        selectMunicipio.dispatchEvent(new Event('change', { bubbles: true }));
    };

    // Elegir un municipio completa el departamento y la provincia.
    selectMunicipio.addEventListener('change', () => {
        if (sincronizando) return;

        const { provincia, departamento } = municipioElegido();
        if (!provincia || !departamento) return;

        sincronizando = true;
        selectDepartamento.value = String(departamento.id);
        selectDepartamento.dispatchEvent(new Event('change', { bubbles: true }));
        actualizarProvincias(departamento.id, provincia.id);
        sincronizando = false;
    });

    selectDepartamento.addEventListener('change', () => {
        if (sincronizando) return;

        const { provincia, departamento } = municipioElegido();
        const municipioSigueValiendo = departamento && String(departamento.id) === selectDepartamento.value;

        if (municipioSigueValiendo) {
            // Se volvió a elegir el mismo departamento: la provincia del municipio se conserva.
            actualizarProvincias(departamento.id, provincia.id);
            return;
        }

        actualizarProvincias(selectDepartamento.value, null);
        limpiarMunicipio();
    });

    selectProvincia.addEventListener('change', () => {
        if (sincronizando) return;

        const { provincia } = municipioElegido();

        if (provincia && String(provincia.id) !== selectProvincia.value) {
            limpiarMunicipio();
        }
    });

    // Carga inicial: el municipio ya no espera a la provincia — se pueblan todos
    // de una vez; si ya hay departamento (edición, o `old()` tras un error de
    // validación) se reconstruye también su lista de provincias.
    poblarSelect(selectMunicipio, opcionesMunicipio, selectMunicipio.dataset.placeholder || '', valorInicialMunicipio);
    selectMunicipio.disabled = false;

    if (selectDepartamento.value) {
        actualizarProvincias(selectDepartamento.value, valorInicialProvincia);
    }
});
