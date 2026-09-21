{{--
    Partial: modal «Elegir lotes» de un sector de siembra (21/9/2026). Uno solo
    por página: `siembra-form.js` lo abre para el sector que se pulsó y lo
    llena con los lotes que ese sector PUEDE elegir — los que siguen libres más
    los que ya tiene; los de otros sectores no aparecen.

    Pensado para propiedades grandes (mil lotes): los lotes van como casillas
    en una grilla compacta (código + hectáreas) y no en una tabla, de a 40 por
    página en cuatro columnas que se leen DE ARRIBA ABAJO —la primera columna
    trae los primeros lotes, la segunda los siguientes—, como una planilla.
    «Marcar todos» actúa sobre todos los lotes que el sector puede elegir
    (todas las páginas, no solo la actual), y con Mayús + clic se marca el
    tramo entre dos lotes. Nada se aplica al sector hasta «Guardar selección».

    Va FUERA del `<form>` de la página: sus casillas y su buscador no son datos
    a enviar. La ficha de cada lote se clona del `<template>` de abajo, que
    trae el mismo marcado del átomo `checkbox`.
--}}
<div
    class="modal fade"
    id="ag-siembra-modal-lotes"
    tabindex="-1"
    aria-hidden="true"
    aria-labelledby="ag-siembra-modal-lotes-titulo"
    data-ag-siembra-modal
    data-texto-titulo="{{ __('comercial.siembra.modal_titulo') }}"
    data-texto-seleccionados="{{ __('comercial.siembra.modal_seleccionados') }}"
    data-texto-hectareas="{{ __('comercial.siembra.lote_hectareas_valor') }}"
>
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title ag-page-header__title" id="ag-siembra-modal-lotes-titulo" data-ag-siembra-modal-titulo></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.action.close') }}"></button>
            </div>

            <div class="modal-body">
                <p class="ag-siembra__ayuda">{{ __('comercial.siembra.modal_ayuda') }}</p>

                <div class="ag-siembra-modal__herramientas">
                    <x-atoms.checkbox
                        name="siembra_modal_todos"
                        id="siembra-modal-todos"
                        :label="__('comercial.siembra.modal_marcar_todos')"
                        data-ag-siembra-modal-todos
                    />
                </div>

                <div class="ag-siembra-modal__grilla" role="group" aria-labelledby="ag-siembra-modal-lotes-titulo" data-ag-siembra-modal-grilla></div>

                <div data-ag-siembra-modal-agotado hidden>
                    <x-molecules.empty-state
                        icon="block"
                        :title="__('comercial.siembra.modal_agotado_titulo')"
                        :detail="__('comercial.siembra.modal_agotado_detalle')"
                    />
                </div>

                <div
                    class="ag-paginador"
                    data-ag-siembra-modal-paginador
                    hidden
                    data-label-aria="{{ __('comercial.siembra.modal_paginacion_aria') }}"
                    data-label-anterior="{{ __('ui.paginador.anterior') }}"
                    data-label-siguiente="{{ __('ui.paginador.siguiente') }}"
                    data-label-pagina="{{ __('ui.paginador.pagina') }}"
                    data-label-resumen="{{ __('comercial.siembra.modal_paginacion_resumen') }}"
                ></div>
            </div>

            <div class="modal-footer">
                <span class="ag-siembra-modal__seleccionados me-auto" data-ag-siembra-modal-seleccionados aria-live="polite"></span>
                <x-atoms.button type="button" variant="outline" data-bs-dismiss="modal">
                    {{ __('ui.action.cancel') }}
                </x-atoms.button>
                <x-atoms.button type="button" variant="primary" data-ag-siembra-modal-guardar>
                    {{ __('comercial.siembra.modal_guardar') }}
                </x-atoms.button>
            </div>
        </div>
    </div>

    <template data-ag-siembra-modal-molde>
        <x-atoms.checkbox name="siembra_modal_lote" id="siembra-modal-lote-__ID__" label="__CODIGO__" class="ag-siembra-modal__lote" data-ag-siembra-modal-lote />
    </template>
</div>
