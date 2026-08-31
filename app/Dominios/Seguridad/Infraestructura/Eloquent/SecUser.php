<?php

namespace App\Dominios\Seguridad\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Seguridad\Aplicacion\AsignarRolesUsuario;
use App\Dominios\Seguridad\Aplicacion\EmitirTokenDispositivo;
use App\Dominios\Seguridad\Dominio\Excepciones\EmisionDirectaDeTokenNoPermitida;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use Database\Factories\SecUserFactory;
use DateTimeInterface;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Sanctum\HasApiTokens;

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

    /**
     * Trait de Sanctum, obligatorio y no decorativo: `Guard::supportsTokens()`
     * comprueba literalmente que el modelo autenticable lo use
     * (`in_array(HasApiTokens::class, class_uses_recursive(...))`) — sin él
     * ningún token de dispositivo autentica. De acá se usan `withAccessToken()`
     * y `currentAccessToken()`; `tokens()` se sobrescribe abajo y
     * `createToken()` queda sellado.
     */
    use HasApiTokens;

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

    /**
     * @return list<int> IDs de los roles asignados y vivos del usuario
     *                   (`sec_user_role.deleted_at IS NULL`), sin filtrar
     *                   por `sec_role.state` — úsalo para gestionar
     *                   asignaciones (p. ej. `AsignarRolesUsuario`), no para
     *                   decidir rol activo. Para eso, ver
     *                   {@see self::idsDeRolesActivos()}.
     */
    public function idsDeRoles(): array
    {
        return $this->asignacionesDeRol()
            ->pluck('id_role')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();
    }

    /**
     * @return list<int> IDs de los roles del usuario que además de vivos
     *                   (asignación no revocada) siguen activos en el
     *                   catálogo (`sec_role.state = true`). Es la lista que
     *                   gobierna el rol activo de sesión (ADR 0004,
     *                   extensión 27/8/2026): un rol desactivado en el
     *                   catálogo nunca cuenta como "el único rol vivo" para
     *                   auto-activarlo, ni aparece como opción en el
     *                   selector — evita que {@see ElegirRolActivo} reciba
     *                   un id que después rechaza por catálogo, cosa que
     *                   convertiría un login válido en un error 500.
     */
    public function idsDeRolesActivos(): array
    {
        return SecRole::query()
            ->join('sec_user_role', 'sec_user_role.id_role', '=', 'sec_role.id')
            ->where('sec_user_role.id_user', $this->id)
            ->whereNull('sec_user_role.deleted_at')
            ->where('sec_role.state', true)
            ->pluck('sec_role.id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();
    }

    /**
     * Tokens de dispositivo vivos del usuario (HU-03). Sobrescribe el
     * `morphMany` del trait de Sanctum por un `HasMany` sobre `user_id`:
     * `sec_token_dispositivo` no lleva las columnas polimórficas
     * `tokenable_type`/`tokenable_id` porque el único portador posible de un
     * token en este sistema es una cuenta `sec_user` — con morph se perdería
     * la FK real a cambio de una flexibilidad que nadie va a usar.
     *
     * Relación dentro del mismo módulo `Seguridad`, así que no aplica la
     * restricción de relaciones cruzadas de ADR 0011 (extensión 26/8/2026,
     * punto 5).
     *
     * @return HasMany<SecTokenDispositivo, $this>
     */
    public function tokens(): HasMany
    {
        return $this->hasMany(SecTokenDispositivo::class, 'user_id');
    }

    /**
     * Sellado. `createToken()` no conoce el dispositivo ni el rol activo, que
     * son obligatorios en `sec_token_dispositivo` (invariantes 1 y 10 de
     * CLAUDE.md), y además construye un `NewAccessToken` que exige una
     * instancia del modelo de token de Sanctum — que este proyecto no usa
     * (ver {@see SecTokenDispositivo}). Se sobrescribe para que el error
     * salga con nombre en vez de como un `TypeError` del paquete.
     *
     * @param  list<string>  $abilities
     *
     * @throws EmisionDirectaDeTokenNoPermitida siempre
     */
    public function createToken(string $name, array $abilities = ['*'], ?DateTimeInterface $expiresAt = null): never
    {
        throw EmisionDirectaDeTokenNoPermitida::usar(EmitirTokenDispositivo::class);
    }

    /**
     * Preferencia de panel del usuario (tema/idioma, HU-02). Relación
     * `HasOne` dentro del mismo módulo `Seguridad` — no cruza a otro módulo,
     * así que no aplica la restricción de `belongsTo`/`hasOne` cross-módulo
     * de ADR 0011 (extensión 26/8/2026, punto 5); esa restricción rige
     * relaciones hacia modelos de *otro* módulo (ADR 0011, extensión
     * 27/8/2026, punto 8).
     *
     * @return HasOne<SecUserPreferencia, $this>
     */
    public function preferencia(): HasOne
    {
        return $this->hasOne(SecUserPreferencia::class, 'user_id');
    }

    /**
     * Unión de permisos de TODOS los roles vivos del usuario, sin importar
     * cuál esté activo en la sesión actual. Correcto únicamente para
     * llamadores sin contexto de sesión de panel — hoy, exclusivamente
     * {@see AsignarRolesUsuario}, que su
     * propio docblock declara "no depende de `Auth::id()`" (ADR 0004,
     * extensión 27/8/2026, punto 5).
     *
     * Cualquier llamador con una sesión de panel autenticada (controlador,
     * middleware, componente Livewire) NUNCA debe usar este método para
     * decidir permisos — usa {@see self::tienePermisoEnRol()} pasando el rol
     * activo de la sesión de forma explícita. Se optó por un método
     * separado sin parámetro opcional (alternativa (b) de la nota técnica
     * del ADR) en vez de agregar un parámetro `?int $idRolActivo = null` a
     * este mismo método: un default `null` que cae en unión es un
     * "fail-open" fácil de heredar por olvido en un call site nuevo con
     * sesión — invariante 10 de `CLAUDE.md` es no negociable.
     *
     * Un rol o permiso desactivado a nivel de catálogo (`state = false`) no
     * cuenta. Join explícito en vez de relaciones anidadas: cada tabla
     * intermedia (`sec_user_role`, `sec_role_permission`) es soft-deleteable
     * y un JOIN de query builder no hereda el scope de borrado lógico del
     * modelo, así que se filtra a mano igual que se filtraría con cualquier
     * otra consulta cruda sobre esas tablas.
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

    /**
     * Variante consciente del rol activo (ADR 0004, extensión 27/8/2026,
     * punto 5, alternativa (b)): evalúa el permiso SOLO dentro de `$idRol`,
     * nunca la unión de todos los roles del usuario. Sin parámetro por
     * default a propósito — todo call site con sesión de panel (login, menú,
     * cambio de rol activo, y la re-exposición de `AsignarRolesUsuario`
     * detrás de un controlador) está obligado a resolver y pasar el rol
     * activo explícitamente, nunca a heredar la unión por omisión.
     *
     * No exige que `$idRol` sea uno de los roles vivos de este usuario: esa
     * verificación es responsabilidad de quien resuelve el rol activo de la
     * sesión (middleware `ResolverRolActivo`, caso de uso `ElegirRolActivo`)
     * — acá solo se evalúa el permiso puro rol→permiso, igual que
     * {@see self::tienePermiso()} lo hace para la unión.
     */
    public function tienePermisoEnRol(string $codigo, int $idRol): bool
    {
        return SecRole::query()
            ->join('sec_role_permission', 'sec_role_permission.id_role', '=', 'sec_role.id')
            ->join('sec_permission', 'sec_permission.id', '=', 'sec_role_permission.id_permission')
            ->where('sec_role.id', $idRol)
            ->whereNull('sec_role_permission.deleted_at')
            ->whereNull('sec_permission.deleted_at')
            ->where('sec_role.state', true)
            ->where('sec_permission.code', $codigo)
            ->where('sec_permission.state', true)
            ->exists();
    }
}
