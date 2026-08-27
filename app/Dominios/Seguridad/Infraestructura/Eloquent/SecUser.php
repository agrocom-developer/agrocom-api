<?php

namespace App\Dominios\Seguridad\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use Database\Factories\SecUserFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cuenta de acceso (ADR 0004; HU-01 diseño `modulos-roles` §5): un usuario,
 * un login, múltiples roles vía `sec_user_role`. Autenticación por
 * `username` + password, nunca correo (memoria del proyecto).
 *
 * `persona_id` (interno) y `contrato_id` (portal) son atributos planos con
 * FK real en la base pero **sin** `belongsTo` Eloquent: cruzar hacia
 * `Personal`/`Comercial` con una relación violaría el ADR 0003 (un módulo no
 * importa modelos Eloquent ajenos). Quien necesite la persona u contrato
 * completo los resuelve por el Contrato/evento del módulo dueño, no por acá
 * (ADR 0011, extensión 26/8/2026, punto 5).
 *
 * Implementa `Authenticatable` directo (no extiende
 * `Illuminate\Foundation\Auth\User`) porque ya extiende `ModeloDominio` —
 * PHP no permite herencia múltiple de clases. `sec_user` no tiene columna
 * `remember_token`; `getRememberTokenName()` devuelve cadena vacía para que
 * el guard de sesión nunca intente leerla/escribirla (soporte de "recordar
 * sesión" fuera de alcance).
 *
 * Los roles se navegan vía `HasMany` hacia `SecUserRole` (no `belongsToMany`
 * con `->using()`): el pivote es un modelo Eloquent propio auditado
 * (extiende `ModeloDominio`, no la clase `Pivot` de Laravel — diseño
 * `modulos-roles` §5), y los stubs de tipos de `BelongsToMany::using()`
 * exigen que el pivote sea subtipo de `Pivot`, cosa que rompería el
 * requisito de auditoría/soft-delete si se cumpliera. `HasMany` no tiene esa
 * restricción y, de paso, aplica el soft-delete scope de `SecUserRole`
 * automáticamente (con el pivote implícito había que repetirlo a mano vía
 * `wherePivotNull`).
 *
 * @property int $id
 * @property string $name
 * @property string $username
 * @property string $password
 * @property TipoUsuario $type
 * @property int|null $persona_id
 * @property int|null $contrato_id
 * @property bool $state
 */
class SecUser extends ModeloDominio implements AuthenticatableContract
{
    use Authenticatable;

    /** @use HasFactory<SecUserFactory> */
    use HasFactory;

    protected $table = 'sec_user';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'username',
        'password',
        'type',
        'persona_id',
        'contrato_id',
        'state',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => TipoUsuario::class,
            'persona_id' => 'integer',
            'contrato_id' => 'integer',
            'state' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function getRememberTokenName(): string
    {
        return '';
    }

    /**
     * Override explícito: la resolución por convención de `HasFactory`
     * asume que los modelos viven bajo `App\Models\`, y acá viven bajo
     * `App\Dominios\...\Infraestructura\Eloquent` (ADR 0003) — sin esto,
     * Laravel buscaría la factory en un namespace que no existe.
     */
    protected static function newFactory(): SecUserFactory
    {
        return SecUserFactory::new();
    }

    /**
     * Asignaciones de rol vivas del usuario (filas de `sec_user_role`, no
     * `SecRole` directo — ver {@see idsDeRoles()} y {@see tienePermiso()}
     * para las consultas útiles).
     *
     * @return HasMany<SecUserRole, $this>
     */
    public function asignacionesDeRol(): HasMany
    {
        return $this->hasMany(SecUserRole::class, 'id_user');
    }

    /** @return list<int> IDs de los roles vivos del usuario. */
    public function idsDeRoles(): array
    {
        return $this->asignacionesDeRol()
            ->pluck('id_role')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();
    }

    /**
     * Unión de permisos de todos los roles vivos del usuario (invariante 10:
     * "los permisos se evalúan por unión de roles"). Un rol o permiso
     * desactivado a nivel de catálogo (`state = false`) no cuenta. Join
     * explícito en vez de relaciones anidadas: cada tabla intermedia
     * (`sec_user_role`, `sec_role_permission`) es soft-deleteable y un JOIN
     * de query builder no hereda el scope de borrado lógico del modelo, así
     * que se filtra a mano igual que se filtraría con cualquier otra
     * consulta cruda sobre esas tablas.
     */
    public function tienePermiso(string $codigo): bool
    {
        return SecRole::query()
            ->join('sec_user_role', 'sec_user_role.id_role', '=', 'sec_role.id')
            ->join('sec_role_permission', 'sec_role_permission.id_role', '=', 'sec_role.id')
            ->join('sec_permission', 'sec_permission.id', '=', 'sec_role_permission.id_permission')
            ->where('sec_user_role.id_user', $this->id)
            ->whereNull('sec_user_role.deleted_at')
            ->whereNull('sec_role_permission.deleted_at')
            ->whereNull('sec_permission.deleted_at')
            ->where('sec_role.state', true)
            ->where('sec_permission.code', $codigo)
            ->where('sec_permission.state', true)
            ->exists();
    }
}
