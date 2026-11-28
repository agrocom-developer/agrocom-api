{{--
    Parcial: negociaciones de la solicitud — una tarjeta por proveedor con sus
    rondas (precio por hectárea, autor, estado) y, mientras la solicitud sigue
    abierta a ofertas, la intervención del administrador: cerrar la negociación
    de ese proveedor con motivo.

    Espera: $negociaciones (ver LecturaNegociacionParaPanel::deLaSolicitud()),
    $puedeIntervenir (bool: permiso + solicitud abierta a ofertas), $solicitud y
    $fecha (Closure que formatea un instante en la zona horaria de quien mira).
--}}
@php
    $dinero = fn (string $valor): string => number_format((float) $valor, 2, ',', '.');
@endphp

@if ($negociaciones === [])
    <p class="ag-solicitudes__vacio">{{ __('solicitudes.panel.neg_vacio') }}</p>
@else
    <div class="ag-solicitudes__negociaciones">
        @foreach ($negociaciones as $negociacion)
            <article class="ag-solicitudes__negociacion">
                <header class="ag-solicitudes__negociacion-cabecera">
                    <h3 class="ag-solicitudes__negociacion-titulo">
                        <a class="ag-dash__link" href="{{ route('panel.proveedores.show', $negociacion['proveedor_id']) }}">{{ $negociacion['proveedor'] }}</a>
                    </h3>
                    <span class="ag-solicitudes__sub">{{ trans_choice('solicitudes.panel.neg_rondas', count($negociacion['ofertas']), ['cantidad' => count($negociacion['ofertas'])]) }}</span>

                    @if ($puedeIntervenir && $negociacion['vigente'])
                        <x-atoms.button
                            type="button"
                            size="sm"
                            variant="alert-outline"
                            icon="block"
                            data-bs-toggle="modal"
                            data-bs-target="#negociacion-cerrar-modal-{{ $negociacion['proveedor_id'] }}"
                        >
                            {{ __('solicitudes.panel.neg_cerrar_accion') }}
                        </x-atoms.button>
                    @endif
                </header>

                <div class="ag-tablero__scroll">
                    <table class="ag-tablero__tabla">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('solicitudes.panel.neg_col_ronda') }}</th>
                                <th scope="col">{{ __('solicitudes.panel.neg_col_autor') }}</th>
                                <th scope="col" class="ag-tablero__num">{{ __('solicitudes.panel.neg_col_precio') }}</th>
                                <th scope="col" class="ag-tablero__num">{{ __('solicitudes.panel.neg_col_monto') }}</th>
                                <th scope="col">{{ __('solicitudes.panel.neg_col_estado') }}</th>
                                <th scope="col">{{ __('solicitudes.panel.neg_col_vence') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($negociacion['ofertas'] as $oferta)
                                <tr>
                                    <th scope="row">{{ $oferta['numero'] }}@if ($oferta['es_definitiva']) · {{ __('solicitudes.panel.neg_definitiva') }}@endif</th>
                                    <td>{{ __('solicitudes.panel.neg_autor.'.$oferta['autor']) }}</td>
                                    <td class="ag-tablero__num">{{ $dinero($oferta['precio_ha']) }}</td>
                                    <td class="ag-tablero__num">{{ $dinero($oferta['monto_estimado']) }}</td>
                                    <td>
                                        <x-atoms.badge :variant="match ($oferta['estado']) {
                                            'vigente' => 'info',
                                            'aceptada' => 'success',
                                            'rechazada' => 'danger',
                                            default => 'neutral',
                                        }">{{ __('solicitudes.panel.neg_estado.'.$oferta['estado']) }}</x-atoms.badge>
                                    </td>
                                    <td>{{ $fecha($oferta['respondida_en'] ?? $oferta['vence_en']) }}</td>
                                </tr>
                                @if ($oferta['nota'] || $oferta['motivo'])
                                    <tr class="ag-solicitudes__fila-nota">
                                        <td></td>
                                        <td colspan="5">
                                            @if ($oferta['nota'])<span class="ag-solicitudes__sub">{{ __('solicitudes.panel.neg_nota', ['nota' => $oferta['nota']]) }}</span>@endif
                                            @if ($oferta['motivo'])<span class="ag-solicitudes__sub">{{ __('solicitudes.panel.neg_motivo_cierre', ['motivo' => $oferta['motivo']]) }}</span>@endif
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </article>

            @if ($puedeIntervenir && $negociacion['vigente'])
                <form id="negociacion-cerrar-form-{{ $negociacion['proveedor_id'] }}" method="POST" action="{{ route('panel.solicitudes.cerrar-negociacion', [$solicitud, $negociacion['proveedor_id']]) }}" hidden>
                    @csrf
                </form>

                <x-molecules.confirm-modal
                    :id="'negociacion-cerrar-modal-'.$negociacion['proveedor_id']"
                    :form-id="'negociacion-cerrar-form-'.$negociacion['proveedor_id']"
                    :title="__('solicitudes.panel.neg_cerrar_titulo', ['proveedor' => $negociacion['proveedor']])"
                    :message="__('solicitudes.panel.neg_cerrar_mensaje')"
                    :confirm-label="__('solicitudes.panel.neg_cerrar_accion')"
                    tone="danger"
                >
                    <x-atoms.textarea
                        name="motivo"
                        :id="'negociacion-cerrar-motivo-'.$negociacion['proveedor_id']"
                        :form="'negociacion-cerrar-form-'.$negociacion['proveedor_id']"
                        :label="__('solicitudes.panel.motivo_etiqueta')"
                        :help="__('solicitudes.panel.neg_cerrar_ayuda')"
                        :rows="3"
                        required
                    />
                </x-molecules.confirm-modal>
            @endif
        @endforeach
    </div>
@endif
