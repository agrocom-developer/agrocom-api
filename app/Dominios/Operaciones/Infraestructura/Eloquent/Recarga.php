<?php

namespace App\Dominios\Operaciones\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use Carbon\CarbonImmutable;

/**
 * Recarga del dron: batería, temperatura, litros de caldo cargados y motivo
 * de retraso por caldo si lo hubo (espec §4.3, tabla `recargas`, reencuadrada
 * por CR-01 — sin `mezcla_id`; HU-13, tarea 23). Nace en la app de campo con
 * su `uuid_cliente` (invariante 1), escrita por
 * `EscrituraSincronizacionEloquent::registrarRecarga()` — nunca por un caso
 * de uso ajeno a `Operaciones` (ADR 0003, regla 2).
 *
 * Mismo criterio que `Condiciones`/`RecepcionCaldo`: registra un HECHO
 * puntual (una recarga durante el ciclo de vuelo), no una entidad con
 * máquina de estados propia — sin `Aplicacion/MaquinaEstados/` para este
 * modelo.
 *
 * `alerta_temperatura`: calculada UNA VEZ al insertar
 * (`RegistroRecarga::alertaTemperatura()`), no se recalcula después — mismo
 * criterio de "valor derivado congelado en el momento del hecho" que
 * `ope_condiciones.autorizado`.
 *
 * @property int $id
 * @property string $uuid_cliente
 * @property int $sesion_id
 * @property int $secuencia
 * @property string $litros_caldo
 * @property string $bateria_saliente_id
 * @property string $temperatura_bateria_c
 * @property bool $alerta_temperatura
 * @property string|null $motivo_retraso_caldo
 * @property CarbonImmutable|null $hora_retraso
 * @property string|null $litros_combustible_generador
 * @property CarbonImmutable $hora
 */
class Recarga extends ModeloDominio
{
    /** Prefijo de módulo en el nombre físico (ADR 0011); el global lo pone la conexión. */
    protected $table = 'ope_recargas';

    /** @var list<string> */
    protected $fillable = [
        'uuid_cliente',
        'sesion_id',
        'secuencia',
        'litros_caldo',
        'bateria_saliente_id',
        'temperatura_bateria_c',
        'alerta_temperatura',
        'motivo_retraso_caldo',
        'hora_retraso',
        'litros_combustible_generador',
        'hora',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'secuencia' => 'integer',
            'litros_caldo' => 'decimal:2',
            'temperatura_bateria_c' => 'decimal:2',
            'alerta_temperatura' => 'boolean',
            'hora_retraso' => 'immutable_datetime',
            'litros_combustible_generador' => 'decimal:2',
            'hora' => 'immutable_datetime',
        ];
    }
}
