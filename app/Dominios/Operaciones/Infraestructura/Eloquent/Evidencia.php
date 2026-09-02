<?php

namespace App\Dominios\Operaciones\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Operaciones\Dominio\TipoEvidencia;
use Carbon\CarbonImmutable;

/**
 * Evidencia subida por la app de campo (espec §4.3, tabla `evidencias`; TE-07
 * parte servidor, tarea 19). Nace con su `uuid_cliente` (invariante 1),
 * escrita por `RegistrarEvidencia` — nunca por un caso de uso ajeno a
 * `Operaciones` (ADR 0003, regla 2).
 *
 * Mismo criterio que `Condiciones`/`RecepcionCaldo`: registra un HECHO
 * puntual (un archivo subido y verificado), no una entidad con máquina de
 * estados propia — sin `Aplicacion/MaquinaEstados/` para este modelo.
 *
 * @property int $id
 * @property string $uuid_cliente
 * @property TipoEvidencia $tipo
 * @property string $archivo_url
 * @property string $hash
 * @property int|null $subido_por
 * @property CarbonImmutable $fecha
 */
class Evidencia extends ModeloDominio
{
    /** Prefijo de módulo en el nombre físico (ADR 0011); el global lo pone la conexión. */
    protected $table = 'ope_evidencias';

    /** @var list<string> */
    protected $fillable = [
        'uuid_cliente',
        'tipo',
        'archivo_url',
        'hash',
        'subido_por',
        'fecha',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo' => TipoEvidencia::class,
            'fecha' => 'immutable_datetime',
        ];
    }
}
