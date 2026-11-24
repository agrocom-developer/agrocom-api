<?php

namespace App\Dominios\Disputas\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Disputas\Dominio\ResultadoDisputa;
use Illuminate\Support\Carbon;

/**
 * Resolución de una disputa (`dsp_resoluciones`): un registro nuevo con autor y
 * motivo. La apelación agrega una segunda y la última rige. Importes en decimal
 * exacto (invariante 6 de CLAUDE.md).
 *
 * @property int $id
 * @property int $disputa_id
 * @property int $ronda
 * @property ResultadoDisputa $resultado
 * @property string $monto_reembolso
 * @property string $comision_ajuste
 * @property bool $derivada_a_seguro
 * @property bool $mala_fe_cliente
 * @property string $motivo
 * @property int $resuelta_por
 * @property Carbon $resuelta_en
 */
class ResolucionDeDisputa extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'dsp_resoluciones';

    /** @var list<string> */
    protected $fillable = [
        'disputa_id',
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
            'disputa_id' => 'integer',
            'ronda' => 'integer',
            'resultado' => ResultadoDisputa::class,
            'monto_reembolso' => 'decimal:2',
            'comision_ajuste' => 'decimal:2',
            'derivada_a_seguro' => 'boolean',
            'mala_fe_cliente' => 'boolean',
            'resuelta_por' => 'integer',
            'resuelta_en' => 'datetime',
        ];
    }
}
