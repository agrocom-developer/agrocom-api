{{--
    Parcial: una fila del selector de «Cambiar vista» (dashboard). Extraído
    de `_modal-cambiar-vista.blade.php` porque se repite dos veces (grupos
    por rol y grupo de portal) con el mismo marcado.

    Espera: $idUsuario (int), $candidato ({nombre, username, esPortal, roles}
    de ListarCandidatosVerComo::ejecutar()).
--}}
<li
    class="ag-cambiar-vista__opcion-item"
    data-ag-cambiar-vista-opcion
    data-ag-cambiar-vista-texto="{{ \Illuminate\Support\Str::lower($candidato['nombre'].' '.$candidato['username']) }}"
>
    {{-- Sin data-bs-toggle/target a propósito: Bootstrap abriría el modal de
         confirmación al toque, encimado con este. El JS de la lista hace el
         encadenamiento (cierra este, y recién en su evento "hidden" abre el
         de confirmación) — ver data-ag-cambiar-vista-destino. --}}
    <button
        type="button"
        class="ag-cambiar-vista__opcion"
        data-ag-cambiar-vista-elegir
        data-ag-cambiar-vista-destino="usuario-ver-como-modal-dash-{{ $idUsuario }}"
    >
        <span class="ag-cambiar-vista__opcion-nombre">{{ $candidato['nombre'] }}</span>
        <span class="ag-cambiar-vista__opcion-username">{{ $candidato['username'] }}</span>
    </button>
</li>
