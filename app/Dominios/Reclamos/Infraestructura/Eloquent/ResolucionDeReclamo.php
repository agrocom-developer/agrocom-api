<?php

namespace App\Dominios\Reclamos\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Reclamos\Dominio\ResultadoReclamo;
use Illuminate\Support\Carbon;

/**
 * Resolución de un reclamo (`rcl_resoluciones`): un registro nuevo con autor y
 * motivo. La apelación agrega una segunda y la última rige. Importes en decimal
 * exacto (invariante 6 de CLAUDE.md).
 *
 * @property int $id
 * @property int $reclamo_id
 * @property int $ronda
 * @property ResultadoReclamo $resultado
 * @property string $monto_reembolso
 * @property string $comision_ajuste
 * @property bool $derivada_a_seguro
 * @property bool $mala_fe_cliente
 * @property string $motivo
 * @property int $resuelta_por
 * @property Carbon $resuelta_en
 */
class ResolucionDeReclamo extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'rcl_resoluciones';

    /** @var list<string> */
    protected $fillable = [
        'reclamo_id',
        'ronda',
        'resultado',
        'monto_reembolso',
        'comision_ajuste',
        'derivada_a_seguro',
        'mala_fe_cliente',
        'motivo',
        'resuelta_por',
        'resuelta_en',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'reclamo_id' => 'integer',
            'ronda' => 'integer',
            'resultado' => ResultadoReclamo::class,
            'monto_reembolso' => 'decimal:2',
            'comision_ajuste' => 'decimal:2',
            'derivada_a_seguro' => 'boolean',
            'mala_fe_cliente' => 'boolean',
            'resuelta_por' => 'integer',
            'resuelta_en' => 'datetime',
        ];
    }
}
