<?php

namespace App\Dominios\Seguridad\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Seguridad\Aplicacion\ActualizarPreferenciaUsuario;
use App\Dominios\Seguridad\Aplicacion\ElegirRolActivo;
use App\Dominios\Seguridad\Dominio\TemaPreferencia;

/**
 * Preferencia de panel por usuario: tema de color e idioma (HU-02; ADR 0011,
 * extensión 27/8/2026, punto 8; ADR 0002 punto 4; ADR 0013 punto 2). Tabla
 * satélite de `sec_user`, dentro del mismo módulo `Seguridad` — no es un
 * módulo `Identidad` (descartado explícitamente, ver el ADR de arriba).
 *
 * Español único idioma habilitado en v1 (ADR 0013): esa regla se aplica en
 * el caso de uso {@see ActualizarPreferenciaUsuario},
 * no acá ni en la migración (sin CHECK de `idioma` a propósito).
 *
 * Preferencias de rol (quinta vuelta, maqueta 5c — ver la migración
 * `add_preferencias_rol_to_sec_user_preferencia_table`): `rol_preferido_id`
 * ("Entrar siempre con este rol", saltea el selector) y `ultimo_rol_id`
 * (badge "ÚLTIMO USADO", lo registra
 * {@see ElegirRolActivo}). Ninguno
 * gobierna permisos: el rol activo sigue siendo estado de sesión (ADR 0004)
 * y ambos ids se revalidan como "rol vivo" antes de usarse.
 *
 * @property int $id
 * @property int $user_id
 * @property TemaPreferencia $tema
 * @property string $idioma
 * @property int|null $rol_preferido_id
 * @property int|null $ultimo_rol_id
 */
class SecUserPreferencia extends ModeloDominio
{
    protected $table = 'sec_user_preferencia';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'tema',
        'idioma',
        'rol_preferido_id',
        'ultimo_rol_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'tema' => TemaPreferencia::class,
            'rol_preferido_id' => 'integer',
            'ultimo_rol_id' => 'integer',
        ];
    }
}
