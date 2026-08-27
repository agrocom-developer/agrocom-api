{{--
    Componente Livewire: RoleSwitcher
    Renderiza la lista de roles dentro del popover de cambio de rol.
    Se instancia desde sidebar-nav/topbar como un componente independiente.

    Props:
    - roles: Collection<SecRole>
    - rolActivoId: int (id del rol activo actual)
--}}
<div class="ag-role-list">
    @forelse ($this->roles as $rol)
        <x-molecules.role-selector-item
            variant="switch"
            :id="$rol->id"
            :label="$rol->name"
            :selected="(int) $rol->id === (int) $this->rolActivoId"
            wire:click="cambiarRol({{ $rol->id }})"
        />
    @empty
        <p>{{ __('seguridad.rol.seleccion_vacia') }}</p>
    @endforelse
</div>
