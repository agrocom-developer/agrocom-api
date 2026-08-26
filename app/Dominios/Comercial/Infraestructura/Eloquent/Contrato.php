<?php

namespace App\Dominios\Comercial\Infraestructura\Eloquent;

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Contrato comercial (espec §4.1, tabla com_contratos) con sus parámetros
 * operativos (RF-60): los límites en NULL significan que rige el valor por
 * defecto del sistema; el contrato solo los modula.
 *
 * Dinero y hectáreas en DECIMAL — el cast `decimal:2` entrega string, nunca
 * float (invariante 6). Las transiciones de `estado` pasarán por el servicio
 * de dominio de la máquina de estados cuando exista (invariante 7).
 *
 * @property int $id
 * @property int $cliente_id
 * @property string $hectareas_contratadas
 * @property int $aplicaciones_previstas
 * @property string $precio_ha
 * @property string $monto_total
 * @property string|null $adelanto_monto
 * @property string|null $adelanto_pct
 * @property CarbonImmutable $fecha_inicio
 * @property CarbonImmutable|null $fecha_fin
 * @property EstadoContrato $estado
 * @property string|null $viento_max_kmh
 * @property string|null $temperatura_max_c
 * @property string|null $humedad_min_pct
 * @property string|null $humedad_max_pct
 * @property string|null $velocidad_max_kmh
 * @property string|null $umbral_reporte_avance_ha
 */
class Contrato extends ModeloDominio
{
    protected $table = 'com_contratos';

    /** @var list<string> */
    protected $fillable = [
        'cliente_id',
        'hectareas_contratadas',
        'aplicaciones_previstas',
        'precio_ha',
        'monto_total',
        'adelanto_monto',
        'adelanto_pct',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'viento_max_kmh',
        'temperatura_max_c',
        'humedad_min_pct',
        'humedad_max_pct',
        'velocidad_max_kmh',
        'umbral_reporte_avance_ha',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'hectareas_contratadas' => 'decimal:2',
            'aplicaciones_previstas' => 'integer',
            'precio_ha' => 'decimal:2',
            'monto_total' => 'decimal:2',
            'adelanto_monto' => 'decimal:2',
            'adelanto_pct' => 'decimal:2',
            'fecha_inicio' => 'immutable_date',
            'fecha_fin' => 'immutable_date',
            'estado' => EstadoContrato::class,
            'viento_max_kmh' => 'decimal:2',
            'temperatura_max_c' => 'decimal:2',
            'humedad_min_pct' => 'decimal:2',
            'humedad_max_pct' => 'decimal:2',
            'velocidad_max_kmh' => 'decimal:2',
            'umbral_reporte_avance_ha' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Cliente, $this> */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /** @return HasMany<ContratoVentana, $this> */
    public function ventanas(): HasMany
    {
        return $this->hasMany(ContratoVentana::class, 'contrato_id');
    }
}
