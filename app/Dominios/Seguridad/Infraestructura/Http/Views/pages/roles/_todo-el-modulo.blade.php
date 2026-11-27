{{--
    Partial: interruptor «Todo el módulo» de la matriz de permisos (roles/permisos).
    Un <input type="checkbox"> real, sin `name` (no se envía): enciende o apaga de una
    vez todos los permisos del módulo que el actor puede tocar, y resumen el estado:
    encendido si todos los habilitados lo están, a medias (`indeterminate`) si solo algunos.
    Lo calcula resources/js/pages/roles-permisos.js; sin JS el módulo se maneja interruptor
    por interruptor. Teclado: Tab llega, Espacio alterna.

    Espera: $nombre (string, ya traducido), $encendido (bool), $deshabilitado (bool: no hay
    nada que el actor pueda tocar en el módulo).
--}}
<label class="ag-permisos__todo">
    <input
        type="checkbox"
        class="ag-permisos__switch-input"
        data-ag-modulo-todo
        aria-label="{{ __('seguridad.roles.modulo_todo_aria', ['modulo' => $nombre]) }}"
        @checked($encendido)
        @disabled($deshabilitado)
    >
    <span class="ag-permisos__switch-pista" aria-hidden="true">
        <span class="ag-permisos__switch-perilla"></span>
    </span>
    <span class="ag-permisos__todo-texto">{{ __('seguridad.roles.modulo_todo') }}</span>
</label>
