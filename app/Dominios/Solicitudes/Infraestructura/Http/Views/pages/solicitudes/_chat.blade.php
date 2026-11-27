{{--
    Parcial: el chat entre el cliente y el proveedor adjudicado, en solo lectura.
    El administrador lo lee para atender un reclamo o una intervención; no
    escribe en él (un solo rol escritor por registro).

    Espera: $mensajes (ver LecturaNegociacionParaPanel) y $fecha (Closure).
--}}
@if ($mensajes === [])
    <p class="ag-solicitudes__vacio">{{ __('solicitudes.panel.chat_vacio') }}</p>
@else
    <ol class="ag-solicitudes__chat">
        @foreach ($mensajes as $mensaje)
            <li class="ag-solicitudes__mensaje ag-solicitudes__mensaje--{{ $mensaje['autor_rol'] }}">
                <span class="ag-solicitudes__mensaje-meta">{{ __('solicitudes.panel.chat_autor.'.$mensaje['autor_rol']) }} · {{ $fecha($mensaje['creado_en']) }}</span>
                <p class="ag-solicitudes__mensaje-texto">{{ $mensaje['texto'] }}</p>
            </li>
        @endforeach
    </ol>
@endif
