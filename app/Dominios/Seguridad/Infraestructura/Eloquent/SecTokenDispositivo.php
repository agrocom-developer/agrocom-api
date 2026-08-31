<?php

namespace App\Dominios\Seguridad\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Seguridad\Aplicacion\EmitirTokenDispositivo;
use App\Dominios\Seguridad\Aplicacion\RevocarTokenDispositivo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Contracts\HasAbilities;

/**
 * Token de acceso de un dispositivo de campo (HU-03). Es la credencial con la
 * que `agrocom-field` opera sin volver a loguearse: la app la guarda una vez
 * y la reusa mientras no se la revoquen.
 *
 * Implementa el contrato de token de Sanctum (`HasAbilities` + `findToken()`
 * estático + relación `tokenable`) en vez de extender
 * `Laravel\Sanctum\PersonalAccessToken`: PHP no tiene herencia múltiple y
 * `ModeloDominio` no es negociable — es la clase que garantiza soft delete,
 * bloqueo de borrado físico y autoría por fila (ADR 0007), y
 * `tests/Unit/ArquitecturaModulosTest.php` la exige a todo modelo de un
 * módulo. Se registra como modelo de token en `SeguridadServiceProvider` vía
 * `Sanctum::usePersonalAccessTokenModel()`.
 *
 * **Revocar es `delete()`** (borrado lógico). No hace falta una columna
 * `revocado_at` aparte: `deleted_at` ya dice cuándo y `updated_by` quién, y
 * el global scope de `SoftDeletes` hace que {@see self::findToken()} deje de
 * encontrarlo — el token muere en el request siguiente a la revocación, sin
 * esperar a que caduque. La emisión y la revocación pasan siempre por
 * {@see EmitirTokenDispositivo} y {@see RevocarTokenDispositivo}.
 *
 * @property int $id
 * @property int $user_id
 * @property int $role_id
 * @property string $uuid_dispositivo
 * @property string|null $nombre_dispositivo
 * @property string $token
 * @property list<string>|null $abilities
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 */
class SecTokenDispositivo extends ModeloDominio implements HasAbilities
{
    protected $table = 'sec_token_dispositivo';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'role_id',
        'uuid_dispositivo',
        'nombre_dispositivo',
        'token',
        'abilities',
        'expires_at',
    ];

    /**
     * El hash nunca sale serializado: es el equivalente de la contraseña para
     * la app de campo.
     *
     * @var list<string>
     */
    protected $hidden = [
        'token',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'role_id' => 'integer',
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Cuenta dueña del token. El nombre `tokenable` es contrato de Sanctum
     * (`Guard` accede a `$accessToken->tokenable`), no vocabulario del
     * dominio — por eso queda en inglés, como el resto de la infraestructura.
     *
     * Apunta a `SecUsuarioInterno`, no a `SecUser` a secas: es el modelo del
     * provider `usuarios_internos` que usa el guard `sanctum`
     * (`config/auth.php`), y `Guard::hasValidProvider()` compara la instancia
     * contra esa clase — con `SecUser` la comparación fallaría y ningún token
     * autenticaría. De paso hereda el global scope `type = 'interno'`: una
     * cuenta de portal jamás autentica en la API de campo.
     *
     * @return BelongsTo<SecUsuarioInterno, $this>
     */
    public function tokenable(): BelongsTo
    {
        return $this->belongsTo(SecUsuarioInterno::class, 'user_id');
    }

    /**
     * Rol activo del dispositivo (invariante 10 de CLAUDE.md): el token se
     * emite para un rol concreto y los permisos efectivos son los de ese rol,
     * nunca la unión de los roles del usuario.
     *
     * Relación dentro del mismo módulo `Seguridad` — no aplica la restricción
     * de relaciones cruzadas de ADR 0011 (extensión 26/8/2026, punto 5).
     *
     * @return BelongsTo<SecRole, $this>
     */
    public function rol(): BelongsTo
    {
        return $this->belongsTo(SecRole::class, 'role_id');
    }

    /**
     * Busca el token por su valor en claro, en el formato `{id}|{secreto}`
     * que emite Sanctum. Misma implementación que
     * `PersonalAccessToken::findToken()`, con dos diferencias que importan:
     * hereda el global scope de `SoftDeletes` (un token revocado no se
     * encuentra) y compara con `hash_equals` incluso en la rama sin `|`.
     */
    public static function findToken(string $token): ?static
    {
        if (! str_contains($token, '|')) {
            /** @var static|null */
            return static::query()->where('token', hash('sha256', $token))->first();
        }

        [$id, $secreto] = explode('|', $token, 2);

        /** @var static|null $instancia */
        $instancia = static::query()->find($id);

        if ($instancia === null) {
            return null;
        }

        return hash_equals($instancia->token, hash('sha256', $secreto)) ? $instancia : null;
    }

    /**
     * Contrato `HasAbilities` de Sanctum. Sin tipo nativo en el parámetro
     * porque la interfaz tampoco lo declara y PHP no permite estrecharlo.
     *
     * Las `abilities` NO son el sistema de permisos de Agrocom: los permisos
     * efectivos salen de `sec_permission` evaluados contra el rol activo del
     * token ({@see SecUser::tienePermisoEnRol()}), que se revalidan contra la
     * base en cada request. Un ability grabado en la fila sería una copia
     * congelada de un permiso que puede haberse revocado — exactamente el
     * fail-open que la invariante 10 evita.
     *
     * @param  string  $ability
     */
    public function can($ability): bool
    {
        $abilities = $this->abilities ?? [];

        return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
    }

    /**
     * @param  string  $ability
     */
    public function cant($ability): bool
    {
        return ! $this->can($ability);
    }
}
