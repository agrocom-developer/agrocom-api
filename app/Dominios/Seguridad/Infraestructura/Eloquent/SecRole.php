<?php

namespace App\Dominios\Seguridad\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rol de seguridad (RBAC), catálogo del sistema (ADR 0004; ADR 0046 punto 4).
 * Los roles del panel son `administrador` y `operador` (ámbito `panel`); los
 * de ámbito `app` (`cliente`, `proveedor`, `piloto`) son transitorios hasta que
 * el token deje de llevar rol (fase A5) y no se ven en la pantalla de roles.
 *
 * Lleva {@see RegistraBitacora} (ADR 0007, invariante 9 de CLAUDE.md): es
 * catálogo de control de acceso, con la columna `state` que activa/desactiva
 * el rol para todo el sistema.
 *
 * @property int $id
 * @property string $name
 * @property string $description
 * @property string $ambito `panel` (administrador, operador) o `app` (roles de app, transitorios hasta A5)
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
