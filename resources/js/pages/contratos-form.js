/**
 * Dinámicas del formulario de contrato (HU-23, tarea 34; ampliado tarea
 * "contratos-lotes", 16/9/2026): multi-select de propiedades con pills +
 * horario por lote + sección maestro-detalle de propiedades y lotes.
 * JS vanilla, sin frameworks nuevos.
 *
 * Propiedades (cambio del 16/9/2026, pedido del dueño): en lugar de elegir
 * UNA propiedad a la vez, ahora es un multi-select mostrado como pills. Para
 * cada propiedad seleccionada (cada pill), aparecen sus checkboxes de lotes
 * todos visibles a la vez (no "confirmar y repetir"). Los lotes tildados de
 * todos los paneles arman la lista apilada final.
 *
 * Horario por lote (cambio del 16/9/2026, reemplazo completo de
 * com_contrato_ventanas): cada lote tiene su propio rango horario (nullable,
 * string `H:i`). Cada lote en la lista apilada arranca como "día completo"
 * (sin inputs visibles) con un link "Personalizar horario" que despliega dos
 * campos de entrada. Tiempo y hora se guardan en `lotes[N][hora_inicio]` y
 * `lotes[N][hora_fin]`.
 *
 * SessionStorage (punto 5 de tarea "contratos-lotes"): guardar/restaurar el
 * estado del formulario al navegar a "Crear cliente/propiedad/lote" y volver,
 * para no perder lo ya tipeado (clave `ag_contrato_borrador`).
 *
 * Guard de presencia en el DOM (mismo criterio que `login.js`): en cualquier
 * página sin `[data-ag-contratos-form]` este módulo no hace nada.
 */
document.addEventListener('DOMContentLoaded', () => {
    const formulario = document.querySelector('[data-ag-contratos-form]');
    if (!formulario) return;

    // ===== PROPIEDADES Y LOTES (tarea "contratos-lotes", estrategia 'a': datos embebidos) =====
    const selectCliente = formulario.querySelector('[name="cliente_id"]');
    const selectPropiedad = formulario.querySelector('[data-ag-propiedades-select]');
    const contenedorPropiedadesPills = formulario.querySelector('[data-ag-propiedades-pills]');
    const contenedorPaneles = formulario.querySelector('[data-ag-lotes-panels]');
    const listaApilada = formulario.querySelector('[data-ag-lotes-agrupados]');
    const scriptDatos = formulario.querySelector('[data-ag-propiedades-lotes]');
    const linkCrearPropiedad = formulario.querySelector('#link-crear-propiedad');
    const urlCrearLote = contenedorPaneles?.dataset.urlCrearLote || '';

    // Estado global accesible para restaurarBorrador
    let propiedadesSeleccionadas = new Set();
    let propiedadesYLotes = {};

    // Funciones de renderizado (accesibles desde restaurarBorrador)
    const renderizarPills = () => {
        if (!contenedorPropiedadesPills) return;

        contenedorPropiedadesPills.innerHTML = '';

        if (propiedadesSeleccionadas.size === 0) {
            return; // Sin pills, nada que renderizar
        }

        const clienteId = selectCliente?.value;
        if (!clienteId) return;

        const propiedades = propiedadesYLotes[clienteId] || {};

        Array.from(propiedadesSeleccionadas).forEach((propiedadId) => {
            const propiedad = propiedades[propiedadId];
            if (!propiedad) return;

            const pill = document.createElement('span');
            pill.className = 'ag-contratos-form__propiedad-pill';
            pill.textContent = propiedad.nombre;

            const botonQuitar = document.createElement('button');
            botonQuitar.type = 'button';
            botonQuitar.className = 'ag-contratos-form__propiedad-pill-close';
            botonQuitar.innerHTML = '<span class="material-icons">close</span>';
            botonQuitar.addEventListener('click', (e) => {
                e.preventDefault();
                propiedadesSeleccionadas.delete(propiedadId);
                renderizarPills();
                renderizarPaneles();
            });

            pill.appendChild(botonQuitar);
            contenedorPropiedadesPills.appendChild(pill);
        });
    };

    const renderizarPaneles = () => {
        if (!contenedorPaneles) return;

        contenedorPaneles.innerHTML = '';

        if (propiedadesSeleccionadas.size === 0) {
            return; // Sin propiedades seleccionadas, no hay paneles
        }

        const clienteId = selectCliente?.value;
        if (!clienteId) return;

        const propiedades = propiedadesYLotes[clienteId] || {};

        Array.from(propiedadesSeleccionadas).forEach((propiedadId) => {
            const propiedad = propiedades[propiedadId];
            if (!propiedad) return;

            const lotes = propiedad.lotes || [];

            // Contenedor del panel para esta propiedad
            const panel = document.createElement('div');
            panel.className = 'ag-contratos-form__lotes-panel';
            panel.setAttribute('data-ag-propiedad-panel', propiedadId);

            // Título del panel
            const titulo = document.createElement('h4');
            titulo.className = 'ag-contratos-form__lotes-panel-title';
            titulo.textContent = propiedad.nombre;
            panel.appendChild(titulo);

            // "Seleccionar todos" para este panel
            const labelSeleccionarTodos = document.createElement('label');
            labelSeleccionarTodos.className = 'ag-contratos-form__lotes-select-all';

            const checkboxSeleccionarTodos = document.createElement('input');
            checkboxSeleccionarTodos.type = 'checkbox';
            checkboxSeleccionarTodos.setAttribute('data-ag-lotes-seleccionar-todos-panel', propiedadId);

            const spanLabel = document.createElement('span');
            spanLabel.className = 'ag-contratos-form__lotes-select-all-label';
            spanLabel.textContent = formulario.querySelector('[data-ag-lotes-seleccionar-todos]')?.textContent || 'Seleccionar todos';

            labelSeleccionarTodos.appendChild(checkboxSeleccionarTodos);
            labelSeleccionarTodos.appendChild(spanLabel);
            panel.appendChild(labelSeleccionarTodos);

            // Checkboxes de lotes
            const listaCheckboxes = document.createElement('div');
            listaCheckboxes.className = 'ag-contratos-form__lotes-checkbox-group';
            listaCheckboxes.setAttribute('data-ag-lotes-lista-checkboxes-panel', propiedadId);

            if (lotes.length === 0) {
                const sinDatos = document.createElement('p');
                sinDatos.className = 'ag-contratos-form__lotes-empty-text';
                sinDatos.textContent = contenedorPaneles?.dataset.textoSinLotes || 'Sin lotes';

                const linkCrearLotePropiedadPanel = document.createElement('a');
                linkCrearLotePropiedadPanel.href = '#';
                linkCrearLotePropiedadPanel.className = 'ag-contratos-form__lotes-empty-link';
                linkCrearLotePropiedadPanel.setAttribute('data-ag-link-accent', '');
                linkCrearLotePropiedadPanel.textContent = contenedorPaneles?.dataset.textoCrearLote || 'Crear lote';
                linkCrearLotePropiedadPanel.addEventListener('click', (e) => {
                    e.preventDefault();
                    guardarBorrador();
                    window.location.href = `${urlCrearLote}?propiedad_id=${propiedadId}&volver_a=${encodeURIComponent(window.location.href)}`;
                });

                listaCheckboxes.appendChild(sinDatos);
                listaCheckboxes.appendChild(linkCrearLotePropiedadPanel);
            } else {
                // Agregar checkboxes para cada lote
                lotes.forEach((lote) => {
                    const label = document.createElement('label');

                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.value = lote.id;
                    checkbox.setAttribute('data-ag-lote-checkbox', '');
                    checkbox.setAttribute('data-ag-lote-checkbox-panel', propiedadId);

                    const span = document.createElement('span');
                    span.textContent = `${lote.codigo} (${Number(lote.hectareas).toFixed(2)} ha)`;

                    label.appendChild(checkbox);
                    label.appendChild(span);
                    listaCheckboxes.appendChild(label);

                    // Sincronizar "seleccionar todos" cuando cambia este checkbox
                    checkbox.addEventListener('change', () => sincronizarSeleccionarTodos(propiedadId));
                });
            }

            panel.appendChild(listaCheckboxes);

            // Botón "Agregar lotes" para este panel
            const botonAgregarLotes = document.createElement('button');
            botonAgregarLotes.type = 'button';
            botonAgregarLotes.className = 'ag-btn ag-btn--outline';
            botonAgregarLotes.innerHTML = '<span class="material-icons">add</span> Agregar';
            botonAgregarLotes.setAttribute('data-ag-lotes-agregar-panel', propiedadId);
            botonAgregarLotes.addEventListener('click', () => agregarLotesDesdePanel(propiedadId));

            panel.appendChild(botonAgregarLotes);

            contenedorPaneles.appendChild(panel);

            // Sincronizar "seleccionar todos" al renderizar
            if (lotes.length > 0) {
                sincronizarSeleccionarTodos(propiedadId);
            }
        });
    };

    const sincronizarSeleccionarTodos = (propiedadId) => {
        if (!contenedorPaneles) return;

        const checkboxes = contenedorPaneles.querySelectorAll(`[data-ag-lote-checkbox-panel="${propiedadId}"]`);
        const checkboxSeleccionarTodos = contenedorPaneles.querySelector(`[data-ag-lotes-seleccionar-todos-panel="${propiedadId}"]`);

        if (!checkboxSeleccionarTodos) return;

        const todosMarcados = Array.from(checkboxes).every((cb) => cb.checked);
        const algunosMarcados = Array.from(checkboxes).some((cb) => cb.checked);

        checkboxSeleccionarTodos.checked = todosMarcados && checkboxes.length > 0;
        checkboxSeleccionarTodos.indeterminate = algunosMarcados && !todosMarcados;
    };

    const desplegarHorarioLote = (loteId, inputHoraInicio, inputHoraFin, botonPersonalizar, contenedorHorario) => {
        // Verificar si ya existen los inputs de tiempo (despliegue ya activo)
        let inputTiempoInicio = contenedorHorario.querySelector('[data-ag-hora-inicio-tiempo]');

        if (inputTiempoInicio) {
            // Ya desplegado, contraer
            inputTiempoInicio.remove();
            contenedorHorario.querySelector('[data-ag-hora-fin-tiempo]')?.remove();
            botonPersonalizar.style.display = '';
            return;
        }

        // No desplegado, crear inputs de tiempo
        inputTiempoInicio = document.createElement('input');
        inputTiempoInicio.type = 'time';
        inputTiempoInicio.className = 'ag-input__field';
        inputTiempoInicio.value = inputHoraInicio.value;
        inputTiempoInicio.setAttribute('data-ag-hora-inicio-tiempo', '');
        inputTiempoInicio.setAttribute('placeholder', 'Hora inicio');
        inputTiempoInicio.addEventListener('change', () => {
            inputHoraInicio.value = inputTiempoInicio.value;
        });

        const inputTiempoFin = document.createElement('input');
        inputTiempoFin.type = 'time';
        inputTiempoFin.className = 'ag-input__field';
        inputTiempoFin.value = inputHoraFin.value;
        inputTiempoFin.setAttribute('data-ag-hora-fin-tiempo', '');
        inputTiempoFin.setAttribute('placeholder', 'Hora fin');
        inputTiempoFin.addEventListener('change', () => {
            inputHoraFin.value = inputTiempoFin.value;
        });

        // Insertar antes del botón de personalizar
        botonPersonalizar.parentNode.insertBefore(inputTiempoInicio, botonPersonalizar);
        botonPersonalizar.parentNode.insertBefore(inputTiempoFin, botonPersonalizar);
        botonPersonalizar.style.display = 'none';
    };

    const agregarLotesDesdePanel = (propiedadId) => {
        if (!contenedorPaneles || !listaApilada) return;

        const clienteId = selectCliente?.value;
        if (!clienteId) return;

        const propiedades = propiedadesYLotes[clienteId] || {};
        const propiedad = propiedades[propiedadId];

        if (!propiedad) return;

        const propiedadNombre = propiedad.nombre;
        const checkboxesMarcados = contenedorPaneles.querySelectorAll(`[data-ag-lote-checkbox-panel="${propiedadId}"]:checked`);

        if (checkboxesMarcados.length === 0) {
            return; // No hacer nada si no hay lotes seleccionados
        }

        // Verificar si ya existe un grupo para esta propiedad en la lista apilada
        let grupoExistente = listaApilada.querySelector(`[data-ag-lote-grupo="propiedad-${propiedadId}"]`);

        if (!grupoExistente) {
            grupoExistente = document.createElement('div');
            grupoExistente.setAttribute('data-ag-lote-grupo', `propiedad-${propiedadId}`);
            grupoExistente.className = 'ag-contratos-form__lote-group';

            const titulo = document.createElement('h4');
            titulo.className = 'ag-contratos-form__lote-group-title';
            titulo.textContent = propiedadNombre;

            const contenedorLotes = document.createElement('div');
            contenedorLotes.className = 'ag-contratos-form__lote-group-items';
            contenedorLotes.setAttribute('data-ag-lote-contenedor', '');

            grupoExistente.appendChild(titulo);
            grupoExistente.appendChild(contenedorLotes);
            listaApilada.appendChild(grupoExistente);
        }

        const contenedorLotes = grupoExistente.querySelector('[data-ag-lote-contenedor]');
        const proximoIndice = listaApilada.querySelectorAll('[data-lote-id]').length;

        // Agregar cada lote marcado a la lista
        checkboxesMarcados.forEach((checkbox, indiceLocal) => {
            const loteId = checkbox.value;

            // Evitar duplicados
            if (contenedorLotes.querySelector(`[data-lote-id="${loteId}"]`)) {
                return;
            }

            const propiedades = propiedadesYLotes[clienteId];
            const propiedad = propiedades[propiedadId];
            const loteData = propiedad.lotes.find((l) => String(l.id) === loteId);

            const fila = document.createElement('div');
            fila.className = 'ag-contratos-form__lote-row';
            fila.setAttribute('data-lote-id', loteId);

            const info = document.createElement('div');
            info.className = 'ag-contratos-form__lote-info';
            const codigo = document.createElement('strong');
            codigo.className = 'ag-contratos-form__lote-code';
            codigo.textContent = loteData.codigo;
            const hectareas = document.createElement('span');
            hectareas.className = 'ag-contratos-form__lote-hectareas';
            hectareas.textContent = `${Number(loteData.hectareas).toFixed(2)} ha`;

            info.appendChild(codigo);
            info.appendChild(hectareas);

            // Contenedor de horario
            const contenedorHorario = document.createElement('div');
            contenedorHorario.className = 'ag-contratos-form__lote-horario';
            contenedorHorario.setAttribute('data-ag-lote-horario', loteId);

            // Inputs ocultos para hora_inicio y hora_fin
            const indiceGlobal = proximoIndice + indiceLocal;
            const inputLoteId = document.createElement('input');
            inputLoteId.type = 'hidden';
            inputLoteId.name = `lotes[${indiceGlobal}][lote_id]`;
            inputLoteId.value = loteId;

            const inputHoraInicio = document.createElement('input');
            inputHoraInicio.type = 'hidden';
            inputHoraInicio.name = `lotes[${indiceGlobal}][hora_inicio]`;
            inputHoraInicio.value = '';
            inputHoraInicio.setAttribute('data-ag-hora-inicio', '');

            const inputHoraFin = document.createElement('input');
            inputHoraFin.type = 'hidden';
            inputHoraFin.name = `lotes[${indiceGlobal}][hora_fin]`;
            inputHoraFin.value = '';
            inputHoraFin.setAttribute('data-ag-hora-fin', '');

            // Botón para personalizar horario (inicialmente oculto)
            const botonPersonalizarHorario = document.createElement('button');
            botonPersonalizarHorario.type = 'button';
            botonPersonalizarHorario.className = 'ag-btn ag-btn--text ag-btn--sm';
            botonPersonalizarHorario.innerHTML = '<span class="material-icons">schedule</span> Personalizar horario';
            botonPersonalizarHorario.setAttribute('data-ag-lote-personalizar-horario', loteId);
            botonPersonalizarHorario.addEventListener('click', (e) => {
                e.preventDefault();
                desplegarHorarioLote(loteId, inputHoraInicio, inputHoraFin, botonPersonalizarHorario, contenedorHorario);
            });

            contenedorHorario.appendChild(inputLoteId);
            contenedorHorario.appendChild(inputHoraInicio);
            contenedorHorario.appendChild(inputHoraFin);
            contenedorHorario.appendChild(botonPersonalizarHorario);

            const botonQuitar = document.createElement('button');
            botonQuitar.type = 'button';
            botonQuitar.className = 'ag-btn ag-btn--text ag-btn--sm';
            botonQuitar.innerHTML = '<span class="material-icons">delete</span>';
            botonQuitar.textContent += ' Quitar';
            botonQuitar.addEventListener('click', (e) => {
                e.preventDefault();
                fila.remove();

                // Si el grupo quedó vacío, quitarlo
                if (!contenedorLotes.querySelector('[data-lote-id]')) {
                    grupoExistente.remove();
                }

                // Desmarcar el checkbox
                checkbox.checked = false;
                sincronizarSeleccionarTodos(propiedadId);
            });

            fila.appendChild(info);
            fila.appendChild(contenedorHorario);
            fila.appendChild(botonQuitar);

            contenedorLotes.appendChild(fila);
        });

        // Desmarcar checkboxes después de agregar
        checkboxesMarcados.forEach((cb) => {
            cb.checked = false;
        });
        sincronizarSeleccionarTodos(propiedadId);
    };

    // Configurar eventos solo si tenemos los elementos necesarios
    if (selectCliente && selectPropiedad && contenedorPropiedadesPills && scriptDatos) {
        try {
            propiedadesYLotes = JSON.parse(scriptDatos.textContent) || {};
        } catch (e) {
            console.error('Error al parsear propiedades/lotes JSON:', e);
        }

        /**
         * Actualizar select de propiedad cuando cambia el cliente.
         * Llena el <select> oculto con las propiedades del cliente actual.
         */
        const actualizarSelectPropiedad = () => {
            const clienteId = selectCliente.value;
            const propiedades = propiedadesYLotes[clienteId] || {};

            // Limpiar select
            selectPropiedad.innerHTML = `<option value="">${selectPropiedad.getAttribute('placeholder') || 'Seleccioná'}</option>`;
            selectPropiedad.disabled = !clienteId;

            // Llenar con propiedades del cliente
            Object.entries(propiedades).forEach(([propiedadId, propiedad]) => {
                const option = document.createElement('option');
                option.value = propiedadId;
                option.textContent = propiedad.nombre;
                selectPropiedad.appendChild(option);
            });

            // Actualizar visibilidad del link "Crear propiedad"
            if (linkCrearPropiedad) {
                if (clienteId) {
                    const urlCrear = linkCrearPropiedad.href.split('?')[0];
                    linkCrearPropiedad.href = `${urlCrear}?cliente_id=${clienteId}&volver_a=${encodeURIComponent(window.location.href)}`;
                    linkCrearPropiedad.removeAttribute('hidden');
                } else {
                    linkCrearPropiedad.setAttribute('hidden', '');
                }
            }

            // Limpiar propiedades y paneles al cambiar cliente
            propiedadesSeleccionadas.clear();
            renderizarPills();
            renderizarPaneles();
        };

        selectCliente.addEventListener('change', actualizarSelectPropiedad);

        // Manejar "Seleccionar todos" para un panel específico
        contenedorPaneles.addEventListener('change', (evento) => {
            const checkbox = evento.target;
            const propiedadId = checkbox.getAttribute('data-ag-lotes-seleccionar-todos-panel');

            if (propiedadId) {
                const checkboxes = contenedorPaneles.querySelectorAll(`[data-ag-lote-checkbox-panel="${propiedadId}"]`);
                checkboxes.forEach((cb) => {
                    cb.checked = checkbox.checked;
                });
            }
        });

        /**
         * Manejar click en el select de propiedades para agregar nuevas propiedades.
         */
        selectPropiedad.addEventListener('change', () => {
            const propiedadId = selectPropiedad.value;

            if (!propiedadId) {
                return;
            }

            // Agregar propiedad al conjunto de seleccionadas
            propiedadesSeleccionadas.add(propiedadId);
            selectPropiedad.value = ''; // Limpiar select
            renderizarPills();
            renderizarPaneles();
        });

        // Inicializar en carga si hay cliente preseleccionado
        if (selectCliente.value) {
            actualizarSelectPropiedad();
        }
    }

    // ===== SESSIONSTORT (guardad/restaura estado formulario) =====
    // Guardar borrador en sessionStorage antes de navegar (tarea "contratos-lotes", punto 5)
    const linksAltaRapida = formulario.querySelectorAll('[data-ag-link-accent]');
    linksAltaRapida.forEach((link) => {
        link.addEventListener('click', () => {
            guardarBorrador();
        });
    });

    function guardarBorrador() {
        const datos = new FormData(formulario);
        const bor = {};

        // Guardar campos clave
        datos.forEach((value, key) => {
            if (key.startsWith('lotes[')) {
                if (!bor[key]) bor[key] = [];
                bor[key].push(value);
            } else if (!bor[key]) {
                bor[key] = value;
            }
        });

        sessionStorage.setItem('ag_contrato_borrador', JSON.stringify(bor));
    }

    function restaurarBorrador() {
        // Leer URL primero para permitir que los query params ganen
        const urlParams = new URLSearchParams(window.location.search);
        const clienteIdUrl = urlParams.get('cliente_id');
        const propiedadIdUrl = urlParams.get('propiedad_id');
        const loteIdUrl = urlParams.get('lote_id');

        const bor = sessionStorage.getItem('ag_contrato_borrador');
        if (bor) {
            try {
                const datos = JSON.parse(bor);
                Object.entries(datos).forEach(([key, value]) => {
                    if (Array.isArray(value)) {
                        value.forEach((v) => {
                            const input = formulario.querySelector(`[name="${key}"]`);
                            if (input) input.value = v;
                        });
                    } else {
                        const input = formulario.querySelector(`[name="${key}"]`);
                        if (input) {
                            if (input.type === 'checkbox') {
                                input.checked = value === 'on' || value === '1';
                            } else {
                                input.value = value;
                            }
                        }
                    }
                });

                sessionStorage.removeItem('ag_contrato_borrador');
            } catch (e) {
                console.error('Error al restaurar borrador:', e);
            }
        }

        // Aplicar valores de URL (ganan sobre el borrador)
        if (clienteIdUrl && !urlParams.has('_limpiar_cliente')) {
            if (selectCliente) {
                selectCliente.value = clienteIdUrl;
                selectCliente.dispatchEvent(new Event('change'));
            }
        }

        // Agregar propiedad desde URL si viene con ella
        if (propiedadIdUrl && selectCliente && selectCliente.value) {
            propiedadesSeleccionadas.add(propiedadIdUrl);
            renderizarPills();
            renderizarPaneles();

            // Si además hay loteIdUrl, tildar ese lote en el panel
            if (loteIdUrl) {
                setTimeout(() => {
                    const checkbox = contenedorPaneles.querySelector(`[data-ag-lote-checkbox][value="${loteIdUrl}"]`);
                    if (checkbox) {
                        checkbox.checked = true;
                        sincronizarSeleccionarTodos(propiedadIdUrl);
                    }
                }, 0);
            }
        }
    }

    // Restaurar al cargar
    restaurarBorrador();

    // Guardar al enviar (limpiar después)
    formulario.addEventListener('submit', () => {
        sessionStorage.removeItem('ag_contrato_borrador');
    });
});
