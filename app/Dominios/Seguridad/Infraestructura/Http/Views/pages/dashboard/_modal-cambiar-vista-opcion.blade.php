{{--
    Parcial: una fila del selector de «Cambiar vista» (dashboard). Extraído
    de `_modal-cambiar-vista.blade.php` porque se repite dos veces (grupos
    por rol y grupo de portal) con el mismo marcado.

    Espera: $idUsuario (int), $candidato ({nombre, username, esPortal, roles,
    detalle} de ListarCandidatosVerComo::ejecutar()), $rolId (int|null) — el
    rol del GRUPO en el que aparece esta fila (null en el grupo de portal),
    $claveRol (string) — la clave técnica del rol (ej. 'piloto', 'jefe_campo',
    'portal') para calcular la clase CSS del avatar.

    Avatar de iniciales: mismo cálculo que `molecules/user-menu` (2 letras,
    una por palabra del nombre) — acá no se reusa ese componente porque es un
    disparador de dropdown con su propio markup, no un átomo aparte; el
    cálculo es la única parte que vale la pena repetir.
--}}
@php
    $iniciales = collect(preg_split('/\s+/', trim((string) $candidato['nombre'])))
        ->filter()
        ->map(fn ($palabra) => mb_strtoupper(mb_substr($palabra, 0, 1)))
        ->take(2)
        ->implode('');

    $claveRolCss = str_replace('_', '-', (string) ($claveRol ?? ''));
@endphp
<li
    class="ag-cambiar-vista__opcion-item"
    data-ag-cambiar-vista-opcion
    data-ag-cambiar-vista-texto="{{ \Illuminate\Support\Str::lower($candidato['nombre'].' '.$candidato['username']) }}"
>
    {{-- Sin data-bs-toggle/target a propósito: Bootstrap abriría el modal de
         confirmación al toque, encimado con este. El JS de la lista hace el
         encadenamiento (cierra este, y recién en su evento "hidden" abre el
         de confirmación) — ver data-ag-cambiar-vista-destino. Con más de un
         rol vivo, data-ag-cambiar-vista-rol-id lleva el rol de ESTE grupo:
         el JS lo copia al campo oculto del modal de confirmación antes de
         abrirlo, así no hace falta volver a preguntarlo. --}}
    <button
        type="button"
        class="ag-cambiar-vista__opcion"
        data-ag-cambiar-vista-elegir
        data-ag-cambiar-vista-destino="usuario-ver-como-modal-dash-{{ $idUsuario }}"
        @if ($rolId !== null && count($candidato['roles']) > 1)
            data-ag-cambiar-vista-rol-id="{{ $rolId }}"
        @endif
    >
        <span class="ag-cambiar-vista__opcion-avatar{{ $claveRolCss ? " ag-cambiar-vista__opcion-avatar--{$claveRolCss}" : '' }}" aria-hidden="true">{{ $iniciales ?: '?' }}</span>

        <span class="ag-cambiar-vista__opcion-texto">
            <span class="ag-cambiar-vista__opcion-nombre">{{ $candidato['nombre'] }}</span>
            <span class="ag-cambiar-vista__opcion-username">{{ $candidato['username'] }}</span>
            @if ($candidato['detalle'])
                <span class="ag-cambiar-vista__opcion-detalle">{{ $candidato['detalle'] }}</span>
            @endif
        </span>
    </button>
</li>
