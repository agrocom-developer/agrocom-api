/**
 * Dinámicas del formulario de contrato (HU-23, tarea 34; ampliado tarea
 * "contratos-lotes"): ventanas horarias + sección maestro-detalle de
 * propiedades y lotes. JS vanilla, sin frameworks nuevos.
 *
 * Ventanas (HU-23, tarea 34): agregar y quitar filas de `ventanas[]` sin
 * recargar. Mismo patrón que `resources/js/pages/clientes-form.js` (tarea
 * 33) — clona `<template>` con placeholder `__INDICE__`, reemplazado por
 * próximo índice libre.
 *
 * El interruptor "Día completo" (HU-47, tarea 70) es puro DOM, sin campo
 * propio que viaje al servidor (ADR 0015 punto 5): encenderlo oculta la
 * sección Y VACÍA la lista; apagarlo la muestra y agrega una fila si está
 * vacía.
 *
 * Lotes (tarea "contratos-lotes", estrategia 'a'): datos de propiedades y
 * lotes embebidos en JSON en un <script> — sin AJAX. Select de propiedad
 * dependiente de cliente (lee JSON embebido), checkboxes de lotes (con
 * "seleccionar todos"), botón "Agregar" que apila los lotes en una lista
 * visible (agrupada por propiedad), con inputs `<hidden name="lotes[]">`
 * generados por JS.
 *
 * SessionStorage (tarea "contratos-lotes", punto 5): guardar/restaurar el
 * estado del formulario al navegar a "Crear cliente/propiedad/lote" y
 * volver, para no perder lo ya tipeado (clave `ag_contrato_borrador`).
 *
 * Guard de presencia en el DOM (mismo criterio que `login.js`): en cualquier
 * página sin `[data-ag-contratos-form]` este módulo no hace nada.
 */
document.addEventListener('DOMContentLoaded', () => {
    const formulario = document.querySelector('[data-ag-contratos-form]');
    if (!formulario) return;

    // ===== VENTANAS HORARIAS (HU-23, tarea 34) =====
    const contenedorVentanas = formulario.querySelector('[data-ag-ventanas]');
    const listaVentanas = formulario.querySelector('[data-ag-ventanas-lista]');
    const plantillaVentana = formulario.querySelector('[data-ag-ventana-template]');
    const botonAgregarVentana = formulario.querySelector('[data-ag-ventanas-agregar]');
    const interruptorDiaCompleto = formulario.querySelector('[data-ag-dia-completo]');

    if (contenedorVentanas && listaVentanas && plantillaVentana && botonAgregarVentana) {
        let proximoIndiceVentana = listaVentanas.querySelectorAll('[data-ag-ventana-fila]').length;

        const agregarFilaVentana = () => {
            const html = plantillaVentana.innerHTML.replaceAll('__INDICE__', String(proximoIndiceVentana));
            proximoIndiceVentana += 1;

            const envoltorio = document.createElement('div');
            envoltorio.innerHTML = html.trim();

            const fila = envoltorio.firstElementChild;
            if (fila) {
                listaVentanas.appendChild(fila);
            }
        };

        botonAgregarVentana.addEventListener('click', agregarFilaVentana);

        contenedorVentanas.addEventListener('click', (evento) => {
            const botonQuitar = evento.target.closest('[data-ag-ventana-quitar]');
            if (!botonQuitar) return;

            botonQuitar.closest('[data-ag-ventana-fila]')?.remove();
        });

        if (interruptorDiaCompleto) {
            interruptorDiaCompleto.addEventListener('change', () => {
                if (interruptorDiaCompleto.checked) {
                    listaVentanas.replaceChildren();
                    contenedorVentanas.hidden = true;
                } else {
                    contenedorVentanas.hidden = false;
                    if (listaVentanas.querySelectorAll('[data-ag-ventana-fila]').length === 0) {
                        agregarFilaVentana();
                    }
                }
            });
        }
    }

    // ===== PROPIEDADES Y LOTES (tarea "contratos-lotes") =====
    const selectCliente = formulario.querySelector('[name="cliente_id"]');
    const selectPropiedad = formulario.querySelector('[data-ag-propiedad-select]');
    const contenedorCheckboxes = formulario.querySelector('[data-ag-lotes-checkboxes]');
    const listaCheckboxes = formulario.querySelector('[data-ag-lotes-lista-checkboxes]');
    const checkboxSeleccionarTodos = formulario.querySelector('[data-ag-lotes-seleccionar-todos]');
    const contenedorSinDatos = formulario.querySelector('[data-ag-lotes-sin-datos]');
    const botonAgregarLotes = formulario.querySelector('[data-ag-lotes-agregar]');
    const listaApilada = formulario.querySelector('[data-ag-lotes-agrupados]');
    const scriptDatos = formulario.querySelector('[data-ag-propiedades-lotes]');
    const linkCrearPropiedad = formulario.querySelector('#link-crear-propiedad');
    const linkCrearLote = formulario.querySelector('#link-crear-lote');

    if (selectCliente && selectPropiedad && contenedorCheckboxes && scriptDatos) {
        let propiedadesYLotes = {};
        try {
            propiedadesYLotes = JSON.parse(scriptDatos.textContent) || {};
        } catch (e) {
            console.error('Error al parsear propiedades/lotes JSON:', e);
        }

        // Actualizar select de propiedad cuando cambia el cliente
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

            // Limpiar todo cuando cambia el cliente
            limpiarLotes();
        };

        selectCliente.addEventListener('change', actualizarSelectPropiedad);

        // Mostrar/ocultar checkboxes cuando cambia la propiedad
        const actualizarLotes = () => {
            const clienteId = selectCliente.value;
            const propiedadId = selectPropiedad.value;

            if (!clienteId || !propiedadId) {
                limpiarLotes();
                return;
            }

            const propiedades = propiedadesYLotes[clienteId] || {};
            const propiedad = propiedades[propiedadId];

            if (!propiedad) {
                limpiarLotes();
                return;
            }

            const lotes = propiedad.lotes || [];

            if (lotes.length === 0) {
                // Sin lotes en esta propiedad
                contenedorCheckboxes.setAttribute('hidden', '');
                contenedorSinDatos.removeAttribute('hidden');
                botonAgregarLotes.setAttribute('hidden', '');

                // Link "Crear lote" apunta a la propiedad elegida
                if (linkCrearLote) {
                    const urlCrear = linkCrearLote.href.split('?')[0];
                    linkCrearLote.href = `${urlCrear}?propiedad_id=${propiedadId}&volver_a=${encodeURIComponent(window.location.href)}`;
                    linkCrearLote.removeAttribute('hidden');
                }
            } else {
                // Con lotes, mostrar checkboxes
                contenedorCheckboxes.removeAttribute('hidden');
                contenedorSinDatos.setAttribute('hidden', '');
                botonAgregarLotes.removeAttribute('hidden');

                // Llenar checkboxes
                listaCheckboxes.innerHTML = '';
                lotes.forEach((lote) => {
                    const label = document.createElement('label');

                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.value = lote.id;
                    checkbox.setAttribute('data-ag-lote-checkbox', '');

                    const span = document.createElement('span');
                    span.textContent = `${lote.codigo} (${Number(lote.hectareas).toFixed(2)} ha)`;

                    label.appendChild(checkbox);
                    label.appendChild(span);
                    listaCheckboxes.appendChild(label);

                    // Sincronizar "seleccionar todos"
                    checkbox.addEventListener('change', sincronizarSeleccionarTodos);
                });

                sincronizarSeleccionarTodos();
            }
        };

        selectPropiedad.addEventListener('change', actualizarLotes);

        // "Seleccionar todos" checkbox
        if (checkboxSeleccionarTodos) {
            checkboxSeleccionarTodos.addEventListener('change', () => {
                const checkboxes = listaCheckboxes.querySelectorAll('[data-ag-lote-checkbox]');
                checkboxes.forEach((cb) => {
                    cb.checked = checkboxSeleccionarTodos.checked;
                });
            });
        }

        // Sincronizar "seleccionar todos" con checkboxes individuales
        const sincronizarSeleccionarTodos = () => {
            const checkboxes = listaCheckboxes.querySelectorAll('[data-ag-lote-checkbox]');
            const todosMarcados = Array.from(checkboxes).every((cb) => cb.checked);
            const algunosMarcados = Array.from(checkboxes).some((cb) => cb.checked);

            if (checkboxSeleccionarTodos) {
                checkboxSeleccionarTodos.checked = todosMarcados && checkboxes.length > 0;
                checkboxSeleccionarTodos.indeterminate = algunosMarcados && !todosMarcados;
            }
        };

        // Agregar lotes seleccionados a la lista apilada
        botonAgregarLotes.addEventListener('click', () => {
            const clienteId = selectCliente.value;
            const propiedadId = selectPropiedad.value;
            const propiedades = propiedadesYLotes[clienteId] || {};
            const propiedad = propiedades[propiedadId];
            const propiedadNombre = propiedad.nombre;

            const checkboxesMarcados = listaCheckboxes.querySelectorAll('[data-ag-lote-checkbox]:checked');

            if (checkboxesMarcados.length === 0) {
                return; // No hacer nada si no hay lotes seleccionados
            }

            // Verificar si ya existe un grupo para esta propiedad
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

            // Agregar cada lote marcado a la lista
            checkboxesMarcados.forEach((checkbox) => {
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

                const inputOculto = document.createElement('input');
                inputOculto.type = 'hidden';
                inputOculto.name = 'lotes[]';
                inputOculto.value = loteId;

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
                    sincronizarSeleccionarTodos();
                });

                fila.appendChild(info);
                fila.appendChild(inputOculto);
                fila.appendChild(botonQuitar);

                contenedorLotes.appendChild(fila);
            });

            // Desmarcar checkboxes después de agregar
            checkboxesMarcados.forEach((cb) => {
                cb.checked = false;
            });
            sincronizarSeleccionarTodos();
        });

        // Limpiar UI de lotes
        const limpiarLotes = () => {
            contenedorCheckboxes.setAttribute('hidden', '');
            contenedorCheckboxes.style.display = 'none';
            contenedorSinDatos.setAttribute('hidden', '');
            contenedorSinDatos.style.display = 'none';
            botonAgregarLotes.setAttribute('hidden', '');
            botonAgregarLotes.style.display = 'none';
            listaCheckboxes.innerHTML = '';

            if (linkCrearLote) {
                linkCrearLote.setAttribute('hidden', '');
            }
        };

        // Remover lotes desde la lista apilada
        formulario.addEventListener('click', (evento) => {
            const botonQuitar = evento.target.closest('[data-ag-lote-quitar]');
            if (!botonQuitar) return;

            const loteId = botonQuitar.getAttribute('data-lote-id');
            if (!loteId) return;

            // Buscar el div padre (fila) que contiene este lote
            const fila = botonQuitar.closest('div[style*="justify-content"]') || botonQuitar.parentElement;
            if (fila) {
                // Desmarcar el checkbox si existe en la UI de selección
                const checkbox = listaCheckboxes.querySelector(`[value="${loteId}"]`);
                if (checkbox) {
                    checkbox.checked = false;
                    sincronizarSeleccionarTodos();
                }
                fila.remove();

                // Si el grupo quedó vacío, quitarlo
                const grupo = botonQuitar.closest('[data-ag-lote-grupo]');
                if (grupo) {
                    const filasRestantes = grupo.querySelectorAll('[data-ag-lote-quitar]');
                    if (filasRestantes.length === 0) {
                        grupo.remove();
                    }
                }
            }
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
            if (key.startsWith('lotes[') || key.startsWith('ventanas[')) {
                if (!bor[key]) bor[key] = [];
                bor[key].push(value);
            } else if (!bor[key]) {
                bor[key] = value;
            }
        });

        sessionStorage.setItem('ag_contrato_borrador', JSON.stringify(bor));
    }

    function restaurarBorrador() {
        const bor = sessionStorage.getItem('ag_contrato_borrador');
        if (!bor) return;

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

            // Disparar cambios para actualizar dependencias
            if (selectCliente) {
                selectCliente.dispatchEvent(new Event('change'));
            }

            sessionStorage.removeItem('ag_contrato_borrador');
        } catch (e) {
            console.error('Error al restaurar borrador:', e);
        }
    }

    // Restaurar al cargar
    restaurarBorrador();

    // Guardar al enviar (limpiar después)
    formulario.addEventListener('submit', () => {
        sessionStorage.removeItem('ag_contrato_borrador');
    });
});
