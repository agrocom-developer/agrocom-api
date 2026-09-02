<?php

namespace App\Dominios\Operaciones\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Operaciones\Dominio\TipoIncidencia;
use Carbon\CarbonImmutable;

/**
 * Incidencia de sesión (espec §4.3, tabla `incidencias`; HU-08, tarea 22).
 * Nace en la app de campo con su `uuid_cliente` (invariante 1), escrita por
 * `EscrituraSincronizacionEloquent::registrarIncidencia()` — nunca por un
 * caso de uso ajeno a `Operaciones` (ADR 0003, regla 2).
 *
 * No transiciona: mismo criterio que `Condiciones`/`RecepcionCaldo`, registra
 * un HECHO puntual, no una entidad con máquina de estados propia.
 *
 * @property int $id
 * @property string $uuid_cliente
 * @property int $sesion_id
 * @property TipoIncidencia $tipo
 * @property string|null $descripcion
 * @property CarbonImmutable $hora
 * @property int $evidencia_foto_id
 */
class Incidencia extends ModeloDominio
{
    /** Prefijo de módulo en el nombre físico (ADR 0011); el global lo pone la conexión. */
    protected $table = 'ope_incidencias';

    /** @var list<string> */
    protected $fillable = [
        'uuid_cliente',
        'sesion_id',
        'tipo',
        'descripcion',
        'hora',
        'evidencia_foto_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo' => TipoIncidencia::class,
            'hora' => 'immutable_datetime',
        ];
    }
}
