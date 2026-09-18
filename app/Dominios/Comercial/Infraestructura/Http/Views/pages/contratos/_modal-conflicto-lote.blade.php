{{--
    Partial: modal informativo del OTRO contrato que también tiene un lote en
    conflicto (tarea "contrato-lotes-conflicto", 18/9/2026) — mismo criterio
    que `_modal-lotes.blade.php`: un solo modal en el DOM, sin props, todo
    texto/URL por `__()`/`route()` acá adentro, repoblado por JS desde el
    JSON embebido `data-ag-conflictos-lotes` de `_formulario.blade.php`
    (`conflictosPorLote`, indexado por `lote_id`).

    Lenguaje visual (rediseño 18/9/2026, pedido explícito del usuario: "se
    supone que tenemos estilos ya consolidados" en `ordenes/show.blade.php` y
    en la tarjeta "Datos del contrato" de `ordenes/_formulario.blade.php`) —
    mismo patrón campo-label/campo-valor en grid de 2 columnas
    (`.ag-form-section__body`, componente compartido, no propio de
    Operaciones) + `x-molecules.index-table` para los lotes, en vez del
    `dl`/`dt`/`dd` a mano de la primera versión. Clases propias
    `.ag-contratos-form__conflicto-*` (no las `ag-ordenes-detalle__*` de
    Operaciones): mismo TRATAMIENTO visual, cada módulo dueño de su propio
    CSS — ver `resources/css/pages/contratos.css`.

    Se abre desde el botón "Ver contrato" que agrega `_lotes-tabla.blade.php`
    en la columna Acciones de la fila en conflicto
    (`data-ag-lote-conflicto-ver`). El botón "Editar contrato" navega al OTRO
    contrato con el memento de navegación ya resuelto server-side
    (`ContratosController::formatearConflictos()`) — `warning-outline`,
    mismo color que todo botón "Editar" del panel (ver docblock de
    `atoms/button`), no el verde de un `primary` genérico.
--}}
<div
    class="modal fade"
    id="ag-modal-conflicto-lote"
    tabindex="-1"
    aria-hidden="true"
    aria-labelledby="ag-modal-conflicto-lote-titulo"
    data-ag-modal-conflicto-lote
>
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title ag-page-header__title" id="ag-modal-conflicto-lote-titulo">
                    {{ __('comercial.contratos.conflicto_modal_titulo') }}
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.action.close') }}"></button>
            </div>
            <div class="modal-body">
                <div class="ag-form-section__body">
                    <div class="ag-contratos-form__conflicto-campo">
                        <p class="ag-contratos-form__conflicto-campo-label">{{ __('comercial.contratos.conflicto_modal_cliente') }}</p>
                        <p class="ag-contratos-form__conflicto-campo-valor" data-ag-conflicto-cliente></p>
                    </div>
                    <div class="ag-contratos-form__conflicto-campo">
                        <p class="ag-contratos-form__conflicto-campo-label">{{ __('comercial.contratos.conflicto_modal_propiedades') }}</p>
                        <p class="ag-contratos-form__conflicto-campo-valor" data-ag-conflicto-propiedades></p>
                    </div>
                    <div class="ag-contratos-form__conflicto-campo">
                        <p class="ag-contratos-form__conflicto-campo-label">{{ __('comercial.contratos.conflicto_modal_vigencia') }}</p>
                        <p class="ag-contratos-form__conflicto-campo-valor" data-ag-conflicto-vigencia></p>
                    </div>
                    <div class="ag-contratos-form__conflicto-campo">
                        <p class="ag-contratos-form__conflicto-campo-label">{{ __('comercial.contratos.conflicto_modal_estado') }}</p>
                        <p class="ag-contratos-form__conflicto-campo-valor">
                            <x-atoms.badge data-ag-conflicto-estado variant="neutral"></x-atoms.badge>
                        </p>
                    </div>
                    <div class="ag-contratos-form__conflicto-campo">
                        <p class="ag-contratos-form__conflicto-campo-label">{{ __('comercial.contratos.conflicto_modal_monto') }}</p>
                        <p class="ag-contratos-form__conflicto-campo-valor ag-contratos-form__conflicto-mono" data-ag-conflicto-monto></p>
                    </div>
                </div>

                {{-- Lotes compartidos entre este contrato y el de arriba
                     (pedido explícito: "no me sirve de mucho solo los datos
                     del contrato" — hace falta ver QUÉ choca, no solo con
                     quién). Filas armadas por JS desde
                     `conflicto.lotes_en_conflicto`. --}}
                <p class="ag-contratos-form__conflicto-lotes-titulo">
                    {{ __('comercial.contratos.conflicto_modal_lotes_titulo') }}
                </p>
                <x-molecules.index-table columns="1fr 1fr 8rem">
                    <x-slot:head>
                        <span role="columnheader">{{ __('comercial.lotes.lote_codigo') }}</span>
                        <span role="columnheader">{{ __('comercial.contratos.conflicto_modal_propiedades') }}</span>
                        <span role="columnheader">{{ __('comercial.lotes.lote_hectareas') }}</span>
                    </x-slot:head>

                    <div data-ag-conflicto-lotes></div>
                </x-molecules.index-table>
            </div>
            <div class="modal-footer">
                <x-atoms.button type="button" variant="outline" data-bs-dismiss="modal">
                    {{ __('ui.action.close') }}
                </x-atoms.button>
                <x-atoms.button variant="warning-outline" icon="edit" href="#" data-ag-conflicto-editar>
                    {{ __('comercial.contratos.conflicto_modal_editar') }}
                </x-atoms.button>
            </div>
        </div>
    </div>
</div>
