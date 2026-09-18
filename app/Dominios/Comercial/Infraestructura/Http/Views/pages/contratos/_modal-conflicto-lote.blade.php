{{--
    Partial: modal informativo del OTRO contrato que también tiene un lote en
    conflicto (tarea "contrato-lotes-conflicto", 18/9/2026) — mismo criterio
    que `_modal-lotes.blade.php`: un solo modal en el DOM, sin props, todo
    texto/URL por `__()`/`route()` acá adentro, repoblado por JS desde el
    JSON embebido `data-ag-conflictos-lotes` de `_formulario.blade.php`
    (`conflictosPorLote`, indexado por `lote_id`).

    El cuerpo reusa las clases de `molecules/summary-card` a mano (no el
    componente en sí: ese arma sus filas desde un array ya resuelto
    server-side, y acá el valor de cada fila lo pinta el JS después de abrir
    el modal) — mismo look, filas con destino propio (`data-ag-conflicto-*`)
    para que `contratos-form.js` las complete sin tener que reconstruir el
    marcado.

    Se abre desde el botón "Ver contrato" que agrega `_lotes-tabla.blade.php`
    junto al badge de alerta de un lote en conflicto
    (`data-ag-lote-conflicto-ver`). El botón "Editar" navega al OTRO contrato
    con el memento de navegación ya resuelto server-side
    (`ContratosController::formatearConflictos()`), así que solo necesita el
    `href` — sin lógica de vuelta adicional en JS.
--}}
<div
    class="modal fade"
    id="ag-modal-conflicto-lote"
    tabindex="-1"
    aria-hidden="true"
    aria-labelledby="ag-modal-conflicto-lote-titulo"
    data-ag-modal-conflicto-lote
>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title ag-page-header__title" id="ag-modal-conflicto-lote-titulo">
                    {{ __('comercial.contratos.conflicto_modal_titulo') }}
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.action.close') }}"></button>
            </div>
            <div class="modal-body">
                <dl class="ag-summary-card__list">
                    <div class="ag-summary-card__row">
                        <dt class="ag-summary-card__label">{{ __('comercial.contratos.conflicto_modal_cliente') }}</dt>
                        <dd class="ag-summary-card__value"><span data-ag-conflicto-cliente></span></dd>
                    </div>
                    <div class="ag-summary-card__row">
                        <dt class="ag-summary-card__label">{{ __('comercial.contratos.conflicto_modal_propiedades') }}</dt>
                        <dd class="ag-summary-card__value"><span data-ag-conflicto-propiedades></span></dd>
                    </div>
                    <div class="ag-summary-card__row">
                        <dt class="ag-summary-card__label">{{ __('comercial.contratos.conflicto_modal_vigencia') }}</dt>
                        <dd class="ag-summary-card__value"><span data-ag-conflicto-vigencia></span></dd>
                    </div>
                    <div class="ag-summary-card__row">
                        <dt class="ag-summary-card__label">{{ __('comercial.contratos.conflicto_modal_estado') }}</dt>
                        <dd class="ag-summary-card__value">
                            <x-atoms.badge data-ag-conflicto-estado variant="neutral"></x-atoms.badge>
                        </dd>
                    </div>
                    <div class="ag-summary-card__row">
                        <dt class="ag-summary-card__label">{{ __('comercial.contratos.conflicto_modal_monto') }}</dt>
                        <dd class="ag-summary-card__value"><span class="ag-summary-card__value--mono" data-ag-conflicto-monto></span></dd>
                    </div>
                </dl>
            </div>
            <div class="modal-footer">
                <x-atoms.button type="button" variant="outline" data-bs-dismiss="modal">
                    {{ __('ui.action.close') }}
                </x-atoms.button>
                <x-atoms.button variant="primary" icon="edit" href="#" data-ag-conflicto-editar>
                    {{ __('comercial.contratos.conflicto_modal_editar') }}
                </x-atoms.button>
            </div>
        </div>
    </div>
</div>
