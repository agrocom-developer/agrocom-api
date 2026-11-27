<?php

namespace App\Dominios\Seguridad\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Seguridad\Dominio\PermisosReservados;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rol de seguridad (RBAC), catálogo del sistema (ADR 0004; ADR 0046 punto 4).
 * Los roles del panel (ámbito `panel`) son `administrador` (id 1, del sistema),
 * `operador` y los que el administrador crea; los de ámbito `app` (`cliente`, `proveedor`, `piloto`) son transitorios hasta que
 * el token deje de llevar rol (fase A5) y no se ven en la pantalla de roles.
 *
 * Lleva {@see RegistraBitacora} (ADR 0007, invariante 9 de CLAUDE.md): es
 * catálogo de control de acceso, con la columna `state` que activa/desactiva
 * el rol para todo el sistema.
 *
 * @property int $id
 * @property string $name
 * @property string $description
 * @property string $ambito `panel` (administrador, operador y los creados) o `app` (roles de app, transitorios hasta A5)
 * @property bool $state
 */
class SecRole extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'sec_role';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'description',
        'ambito',
        'state',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'state' => 'boolean',
        ];
    }

    /**
     * Solo los roles del panel: los de ámbito `app` no se administran desde acá.
     *
     * @param  Builder<SecRole>  $consulta
     * @return Builder<SecRole>
     */
    public function scopeDelPanel(Builder $consulta): Builder
    {
        return $consulta->where('ambito', 'panel');
    }

    /** Si es un rol del panel (los de app no se administran desde el panel). */
    public function esDelPanel(): bool
    {
        return $this->ambito === 'panel';
    }

    /** Si es el administrador del sistema: no se elimina, renombra ni desactiva. */
    public function esDelSistema(): bool
    {
        return PermisosReservados::esRolDelSistema((int) $this->id, (string) $this->name);
    }

    /**
     * Asignaciones de permiso vivas del rol (filas de `sec_role_permission`,
     * no `SecPermission` directo). `HasMany`, no `belongsToMany()->using()`:
     * ver la nota de {@see SecUser::asignacionesDeRol()} — mismo motivo
     * (el pivote audita quién otorgó/quitó el permiso, así que extiende
     * `ModeloDominio` y no la clase `Pivot` de Laravel).
     *
     * @return HasMany<SecRolePermission, $this>
     */
    public function asignacionesDePermiso(): HasMany
    {
        return $this->hasMany(SecRolePermission::class, 'id_role');
    }
}
