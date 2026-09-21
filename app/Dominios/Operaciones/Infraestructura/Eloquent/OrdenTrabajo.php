<?php

namespace App\Dominios\Operaciones\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Orden de Trabajo (cabecera de tanda, reforma 18/9/2026, migración
 * `create_ope_ordenes_trabajo_table`): una Orden de Aplicación se ejecuta en
 * una o más tandas — cada una agrupa los equipos que trabajan juntos ese
 * período bajo los MISMOS límites climáticos/parámetros de vuelo/Ph/calda.
 * No es 1-a-1 con un equipo: una tanda puede cubrir varios equipos a la vez
 * (y una misma orden puede tener varias tandas sucesivas, cada una con
 * distinta cantidad de equipos).
 *
 * `equipo_trabajo_id` NO vive acá — cada `Trabajo` (equipo×lote) se queda
 * con el suyo, porque una tanda cubre varios equipos (ver docblock de la
 * migración). Lo que sí es compartido por toda la tanda son los 7 campos de
 * clima/vuelo y los 2 de Ph — se leen una sola vez por tanda, nunca
 * repetidos por fila de `Trabajo` como antes de esta reforma.
 *
 * `orden_id` es relación Eloquent real (`orden()`): `OrdenAplicacion` es del
 * mismo módulo (ADR 0003, regla 1). `trabajos()` — sus detalles — también.
 *
 * @property int $id
 * @property int $orden_id
 * @property int $nro_aplicacion
 * @property string|null $humedad_min_pct
 * @property string|null $viento_max_kmh
 * @property string|null $temperatura_max_c
 * @property string|null $humedad_max_pct
 * @property string|null $altura_vuelo_m
 * @property string|null $velocidad_vuelo_kmh
 * @property string|null $ancho_pasada_m
 * @property string|null $ph_agua
 * @property string|null $ph_calda
 * @property string|null $litros_ha
 * @property string|null $kilos_ha
 * @property list<string>|null $calda_productos
 */
class OrdenTrabajo extends ModeloDominio
{
    use RegistraBitacora;

    /** Prefijo de módulo en el nombre físico (ADR 0011); el global lo pone la conexión. */
    protected $table = 'ope_ordenes_trabajo';

    /** @var list<string> */
    protected $fillable = [
        'orden_id',
        'nro_aplicacion',
        'humedad_min_pct',
        'viento_max_kmh',
        'temperatura_max_c',
        'humedad_max_pct',
        'altura_vuelo_m',
        'velocidad_vuelo_kmh',
        'ancho_pasada_m',
        'ph_agua',
        'ph_calda',
        'litros_ha',
        'kilos_ha',
        'calda_productos',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'nro_aplicacion' => 'integer',
            'humedad_min_pct' => 'decimal:2',
            'viento_max_kmh' => 'decimal:2',
            'temperatura_max_c' => 'decimal:2',
            'humedad_max_pct' => 'decimal:2',
            'altura_vuelo_m' => 'decimal:2',
            'velocidad_vuelo_kmh' => 'decimal:2',
            'ancho_pasada_m' => 'decimal:2',
            'ph_agua' => 'decimal:2',
            'ph_calda' => 'decimal:2',
            'litros_ha' => 'decimal:2',
            'kilos_ha' => 'decimal:2',
            'calda_productos' => 'array',
        ];
    }

    /** @return BelongsTo<OrdenAplicacion, $this> */
    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenAplicacion::class, 'orden_id');
    }

    /** @return HasMany<Trabajo, $this> */
    public function trabajos(): HasMany
    {
        return $this->hasMany(Trabajo::class, 'orden_trabajo_id');
    }
}
