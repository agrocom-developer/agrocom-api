<?php

namespace App\Livewire\Panel;

use App\Dominios\Seguridad\Aplicacion\ElegirRolActivo;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

/**
 * RoleSwitcher: componente Livewire pequeño que renderiza el popover de
 * cambio de rol activo en sidebar-nav y topbar. Recibe los roles disponibles
 * y el rol activo, y expone la acción `cambiarRol()` que Livewire dispara
 * sin recarga completa.
 *
 * Usado dentro de sidebar-nav/topbar, NO como envoltura de página completa.
 *
 * @property Collection<int, SecRole> $roles
 */
class RoleSwitcher extends Component
{
    /**
     * Propiedades públicas: roles disponibles y rol activo.
     * Se pasan desde sidebar-nav/topbar cuando se instancia el componente.
     *
     * @var Collection<int, SecRole>
     */
    public Collection $roles;

    public ?int $rolActivoId = null;

    /**
     * @param  Collection<int, SecRole>|null  $roles
     */
    public function mount(?Collection $roles = null, ?int $rolActivoId = null): void
    {
        $this->roles = $roles ?? collect();
        $this->rolActivoId = $rolActivoId;
    }

    public function render(): View
    {
        return view('livewire.panel.role-switcher');
    }

    /**
     * Acción: cambiar el rol activo. Se invoca desde wire:click en los botones
     * del popover. Tras cambiar el rol, redirige a la URL actual para que el
     * controlador refresque el menú y todos los datos sin recargar completamente.
     */
    public function cambiarRol(int $idRol): void
    {
        /** @var SecUser $usuario */
        $usuario = auth()->guard('interno')->user();

        try {
            $elegirRolActivo = app(ElegirRolActivo::class);
            $elegirRolActivo->ejecutar($usuario, $idRol);

            // Redirect a la URL actual para que el controlador refresque
            // el menú con los permisos del nuevo rol activo.
            $this->redirect(request()->url(), navigate: true);
        } catch (\Throwable $e) {
            throw $e;
        }
    }
}
