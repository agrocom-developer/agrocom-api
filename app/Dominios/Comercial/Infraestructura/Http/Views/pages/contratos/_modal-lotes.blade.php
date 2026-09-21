{{--
    Partial: modal único de selección de lotes por propiedad (tarea
    "contratos-lotes", rediseño sept/2026) — extraído de
    `_formulario.blade.php` el 16/9/2026, sin cambiar nada: reemplaza al
    panel lateral con checkboxes siempre visibles. Un solo modal en el DOM,
    repoblado por JS con los lotes de la propiedad que se abrió
    (`abrirModalLotes` en `resources/js/pages/contratos-form.js`) — igual que
    `atoms/image-modal` usa el modal nativo de Bootstrap
    (`data-bs-toggle`/`data-bs-target`, ya cargado en app.js), pero éste no es
    un átomo genérico: es específico de esta pantalla, arma su lista de
    casillas 100% por JS a partir del JSON embebido en la sección de lotes
    (estrategia 'a', sin pedirle nada al servidor al abrir).

    No espera props: todo el texto sale de `__()` y las URLs de `route()`,
    directo en este archivo — nada del scope de la página llega adentro. Los
    data-attributes son los que antes vivían en `[data-ag-lotes-panels]`.

    Elegir una propiedad del select — o clickear una pill ya agregada — abre
    este modal con TODOS los lotes de esa propiedad, tildados los que ya
    están en el contrato (leído de la lista apilada actual, no de
    `$lotesIniciales` directo — así también sirve para una propiedad recién
    agregada en esta misma sesión de edición). Nada cambia en el contrato
    hasta apretar "Guardar selección" — cerrar el modal por la X, Cancelar o
    el fondo descarta los cambios. Ver `abrirModalLotes`/`guardarSeleccionModal`
    en `resources/js/pages/contratos-form.js`.
--}}
<div
    class="modal fade"
    id="ag-modal-lotes-propiedad"
    tabindex="-1"
    aria-hidden="true"
    aria-labelledby="ag-modal-lotes-propiedad-titulo"
    data-ag-modal-lotes
    data-url-crear-lote="{{ route('panel.lotes.create') }}"
    data-texto-crear-lote="{{ __('comercial.contratos.crear_lote') }}"
    {{-- «Registrar siembra» (21/9/2026): el paso del flujo donde la siembra
         importa es este, al elegir qué lotes van juntos. Sale a la siembra de
         la propiedad con la campaña del formulario y vuelve acá con lo
         cargado intacto, como «Crear lote». Solo con permiso de editar la
         propiedad; sin la URL, el JS no dibuja el botón. --}}
    @puede('comercial.propiedad.editar')
        data-url-siembra="{{ route('panel.propiedades.siembra', '__PROPIEDAD__') }}"
        data-texto-siembra="{{ __('comercial.contratos.registrar_siembra') }}"
    @endpuede
    data-texto-sin-lotes="{{ __('comercial.contratos.lotes_sin_datos') }}"
    data-texto-seleccionar-todos="{{ __('comercial.contratos.lote_seleccionar_todos') }}"
    data-texto-col-codigo="{{ __('comercial.lotes.lote_codigo') }}"
    data-texto-col-hectareas="{{ __('comercial.lotes.lote_hectareas') }}"
    data-texto-col-desnivel="{{ __('comercial.lotes.lote_desnivel') }}"
    data-texto-col-limpieza="{{ __('comercial.lotes.lote_limpieza') }}"
    data-texto-col-cultivo="{{ __('comercial.contratos.lotes_modal_col_cultivo') }}"
    data-texto-sin-etapa="{{ __('comercial.contratos.lotes_modal_sin_etapa') }}"
    data-texto-seleccionados="{{ __('comercial.contratos.lotes_modal_seleccionados') }}"
>
    {{-- `modal-dialog-scrollable`: si el modal no entra en la pantalla, el
         cuerpo se desplaza y el pie con «Guardar selección» queda siempre a la
         vista, en vez de tener que bajar hasta el final de la página. --}}
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title ag-page-header__title" id="ag-modal-lotes-propiedad-titulo" data-ag-modal-lotes-titulo></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.action.close') }}"></button>
            </div>
            <div class="modal-body">
                <div class="ag-contratos-form__modal-header-row">
                    <p class="ag-contratos-form__modal-ayuda">{{ __('comercial.contratos.lotes_modal_ayuda') }}</p>
                    <div data-ag-modal-crear-lote-slot></div>
                </div>
                <div data-ag-modal-lotes-lista></div>

                {{-- Paginación de 20 lotes por página (`paginador-cliente.js`): las
                     filas de otras páginas quedan ocultas, no quitadas, así que lo
                     marcado en ellas se conserva al guardar. --}}
                <div
                    class="ag-paginador"
                    data-ag-modal-paginador
                    hidden
                    data-label-aria="{{ __('comercial.contratos.lotes_modal_paginacion_aria') }}"
                    data-label-anterior="{{ __('ui.paginador.anterior') }}"
                    data-label-siguiente="{{ __('ui.paginador.siguiente') }}"
                    data-label-pagina="{{ __('ui.paginador.pagina') }}"
                    data-label-resumen="{{ __('comercial.contratos.lotes_paginacion_resumen') }}"
                ></div>

                {{-- Todos los lotes de la propiedad ya están comprometidos
                     en otro contrato vigente de la campaña elegida (tarea
                     "contrato-lotes-conflicto", 18/9/2026) — distinto del
                     caso "la propiedad no tiene ningún lote" de arriba
                     (`data-texto-sin-lotes`, texto plano ya existente):
                     acá SÍ hay lotes, pero ninguno queda disponible para
                     elegir en esta campaña. Oculto por defecto, lo muestra
                     `abrirModalLotes` en vez de la tabla. --}}
                <div data-ag-modal-lotes-agotado hidden>
                    <x-molecules.empty-state
                        icon="block"
                        :title="__('comercial.contratos.lotes_todos_ocupados_titulo')"
                        :detail="__('comercial.contratos.lotes_todos_ocupados_detalle')"
                    />
                </div>
            </div>
            <div class="modal-footer">
                <span class="ag-contratos-form__modal-seleccionados me-auto" data-ag-modal-lotes-seleccionados aria-live="polite"></span>
                <x-atoms.button type="button" variant="outline" data-bs-dismiss="modal">
                    {{ __('ui.action.cancel') }}
                </x-atoms.button>
                <x-atoms.button type="button" variant="primary" data-ag-modal-lotes-guardar>
                    {{ __('comercial.contratos.lotes_modal_guardar') }}
                </x-atoms.button>
            </div>
        </div>
    </div>
</div>
