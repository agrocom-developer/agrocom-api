<?php

namespace App\Dominios\Operaciones\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use Carbon\CarbonImmutable;

/**
 * Recepción de caldo (espec §7.2, tabla ope_recepciones_caldo; HU-10
 * redefinida por CR-01, tarea 18). Nace en la app de campo con su
 * `uuid_cliente` (invariante 1), escrita por
 * `EscrituraSincronizacionEloquent::registrarRecepcionCaldo()` — nunca por un
 * caso de uso ajeno a `Operaciones` (ADR 0003, regla 2).
 *
 * Mismo criterio que `Condiciones` (tarea 17): registra un HECHO puntual (una
 * entrega de caldo), no una entidad con máquina de estados propia — sin
 * `Aplicacion/MaquinaEstados/` para este modelo. Ningún dato de composición
 * del caldo (producto, dosis, fórmula): §7.1 lo excluye explícitamente del
 * alcance.
 *
 * @property int $id
 * @property string $uuid_cliente
 * @property int $trabajo_id
 * @property string $litros
 * @property string $entregado_por
 * @property CarbonImmutable $hora
 */
class RecepcionCaldo extends ModeloDominio
{
    /** Prefijo de módulo en el nombre físico (ADR 0011); el global lo pone la conexión. */
    protected $table = 'ope_recepciones_caldo';

    /** @var list<string> */
    protected $fillable = [
        'uuid_cliente',
        'trabajo_id',
        'litros',
        'entregado_por',
        'hora',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'litros' => 'decimal:2',
            'hora' => 'immutable_datetime',
        ];
    }
}
