<?php

namespace App\Dominios\Seguridad\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Seguridad\Aplicacion\ActualizarPreferenciaUsuario;
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
 * @property int $id
 * @property int $user_id
 * @property TemaPreferencia $tema
 * @property string $idioma
 */
class SecUserPreferencia extends ModeloDominio
{
    protected $table = 'sec_user_preferencia';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'tema',
        'idioma',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'tema' => TemaPreferencia::class,
        ];
    }
}
