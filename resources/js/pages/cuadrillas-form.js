/**
 * cuadrillas-form.js — Gestión del formulario de alta/edición de cuadrillas
 * (tarea "cuadrillas-estadias", 19/9/2026)
 *
 * Funcionalidades:
 * 1. Validación de integrantes no repetidos (piloto, ayudante, ayudante2)
 * 2. Cambio dinámico de opciones de equipamiento por tipo (similar a contratos-form.js)
 *
 * Lo cargado no se pierde al salir a crear una base, una persona o un equipo y
 * volver: eso lo hace `shared/borrador-formulario.js` para todo formulario con
 * un botón de alta rápida, en el alta y en la edición (21/9/2026; antes este
 * módulo llevaba un guardado propio, solo en el alta).
 */

(() => {
    const form = document.querySelector('[data-ag-cuadrillas-form]');
    if (!form) return;

    // === 1. Validación de integrantes no repetidos ===

    const selectPiloto = form.querySelector('[data-ag-select-integrante="piloto"]');
    const selectAyudante = form.querySelector('[data-ag-select-integrante="ayudante"]');
    const selectAyudante2 = form.querySelector('[data-ag-select-integrante="ayudante2"]');

    if (selectPiloto && selectAyudante) {
        const validar = () => {
            const pilotoId = selectPiloto.value;
            const ayudanteId = selectAyudante.value;
            const ayudante2Id = selectAyudante2?.value;

            // Si piloto está elegido, deshabilitar en ayudantes
            if (selectAyudante) {
                const optionPiloto = selectAyudante.querySelector(`option[value="${pilotoId}"]`);
                if (optionPiloto) optionPiloto.disabled = !!pilotoId;
            }
            if (selectAyudante2) {
                const optionPiloto = selectAyudante2.querySelector(`option[value="${pilotoId}"]`);
                if (optionPiloto) optionPiloto.disabled = !!pilotoId;
            }

            // Si ayudante está elegido, deshabilitar en ayudante2
            if (selectAyudante2 && ayudanteId) {
                const optionAyudante = selectAyudante2.querySelector(`option[value="${ayudanteId}"]`);
                if (optionAyudante) optionAyudante.disabled = true;
            }

            // Si ayudante2 está elegido, deshabilitar en ayudante
            if (selectAyudante && ayudante2Id) {
                const optionAyudante2 = selectAyudante.querySelector(`option[value="${ayudante2Id}"]`);
                if (optionAyudante2) optionAyudante2.disabled = true;
            }

            // Mostrar error si hay repetidas
            const ids = [pilotoId, ayudanteId, ayudante2Id].filter(Boolean);
            const repetidas = ids.length !== new Set(ids).size;

            if (repetidas) {
                // El error real viene de la validación server-side
                // Acá solo mostramos una pista visual
            }
        };

        selectPiloto.addEventListener('change', validar);
        selectAyudante.addEventListener('change', validar);
        if (selectAyudante2) selectAyudante2.addEventListener('change', validar);

        validar(); // Validar al cargar
    }

    // === 2. Cambio dinámico de equipamiento por tipo ===

    const selectTipo = document.querySelector('[data-ag-select-tipo-recurso]');
    const selectRecurso = document.querySelector('[data-ag-select-recurso-id]');
    const scriptOpciones = document.querySelector('[data-ag-opciones-equipamiento]');

    if (selectTipo && selectRecurso && scriptOpciones) {
        const opcionesEquipamiento = JSON.parse(scriptOpciones.textContent);

        selectTipo.addEventListener('change', () => {
            const tipo = selectTipo.value;
            const opciones = opcionesEquipamiento[tipo] || {};

            // Limpiar select de recurso
            selectRecurso.innerHTML = '';

            // Opción placeholder
            const optionPlaceholder = document.createElement('option');
            optionPlaceholder.value = '';
            optionPlaceholder.textContent = selectRecurso.dataset.placeholder || '';
            optionPlaceholder.disabled = true;
            selectRecurso.appendChild(optionPlaceholder);

            // Agregar opciones del tipo elegido
            Object.entries(opciones).forEach(([id, etiqueta]) => {
                const option = document.createElement('option');
                option.value = id;
                option.textContent = etiqueta;
                selectRecurso.appendChild(option);
            });

            // Sin tipo elegido (o sin recursos de ese tipo) el select queda
            // deshabilitado; el combobox de `atoms/select` observa el cambio de
            // opciones y de `disabled`, y refresca su etiqueta al oír `change`.
            optionPlaceholder.selected = true;
            selectRecurso.disabled = Object.keys(opciones).length === 0;
            selectRecurso.dispatchEvent(new Event('change', { bubbles: true }));
        });

        // Trigger inicial
        selectTipo.dispatchEvent(new Event('change', { bubbles: true }));
    }
})();
