<?php

namespace App\Dominios\Comercial\Infraestructura\Eloquent;

use App\Dominios\Comercial\Dominio\EtapaCultivo;
use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Qué se sembró en un lote, en una campaña (HU-48, tarea 71, etapa 2; ADR
 * 0015 punto 4; tabla `com_lote_campania`). No es un atributo del lote: el
 * mismo lote tiene una fila por cada campaña en la que se sembró algo.
 *
 * `campania_id` es FK real + entero plano, SIN relación `belongsTo`: mismo
 * criterio que `Contrato::$campania_id` (ADR 0003 regla 3) — `Campania` es
 * de otro módulo, y este modelo no la importa.
 *
 * `RegistraBitacora`: mismo criterio que `Lote`/`Cultivo` — el alta, edición
 * y baja de una siembra es una mutación de negocio con autor y momento
 * auditables.
 *
 * @property int $id
 * @property int $lote_id
 * @property int $campania_id
 * @property int $cultivo_id
 * @property EtapaCultivo|null $etapa_cultivo
 * @property string $hectareas_sembradas
 * @property Carbon|null $fecha_siembra
 * @property Carbon|null $fecha_cosecha_estimada
 */
class LoteCampania extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'com_lote_campania';

    /** @var list<string> */
    protected $fillable = [
        'lote_id',
        'campania_id',
        'cultivo_id',
        'etapa_cultivo',
        'hectareas_sembradas',
        'fecha_siembra',
        'fecha_cosecha_estimada',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'etapa_cultivo' => EtapaCultivo::class,
            'hectareas_sembradas' => 'decimal:2',
            'fecha_siembra' => 'date',
            'fecha_cosecha_estimada' => 'date',
        ];
    }

    /** @return BelongsTo<Lote, $this> */
    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class, 'lote_id');
    }

    /** @return BelongsTo<Cultivo, $this> */
    public function cultivo(): BelongsTo
    {
        return $this->belongsTo(Cultivo::class, 'cultivo_id');
    }
}
