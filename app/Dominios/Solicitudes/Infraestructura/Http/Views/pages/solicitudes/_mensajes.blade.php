{{--
    Parcial: el chat entre el cliente y el proveedor adjudicado, solo lectura
    (`docs/especificacion/negociacion-contrato.md` §5). El administrador lo ve
    para mediar en una disputa o una incidencia; no escribe en él.

    Espera: $mensajes (list<array{autor, autor_rol, texto, creado_en}>).
--}}
@if (count($mensajes) === 0)
    <p class="ag-solicitudes__sub">{{ __('solicitudes.panel.mensajes_vacio') }}</p>
@else
    <ol class="ag-solicitudes__mensajes">
        @foreach ($mensajes as $mensaje)
            <li class="ag-solicitudes__mensaje ag-solicitudes__mensaje--{{ $mensaje['autor_rol'] }}">
                <span class="ag-solicitudes__sub">{{ $mensaje['autor'] }} · {{ __('solicitudes.panel.autor.'.$mensaje['autor_rol']) }} · {{ $mensaje['creado_en'] }}</span>
                <span>{{ $mensaje['texto'] }}</span>
            </li>
        @endforeach
    </ol>
@endif
