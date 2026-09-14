<?php

namespace App\Dominios\Operaciones\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Reporte de Equipos" de un trabajo (HU-80, tarea 86): horas de vuelo
 * declaradas del dron y las tres fotos de chequeo (control, ciclo de
 * batería y balanceo, dron limpio). Nace con su `uuid_cliente` (invariante
 * 1), escrita por `RegistrarEvidenciaEquipo` (vía el motor de sync) — nunca
 * por un caso de uso ajeno a `Operaciones` (ADR 0003, regla 2).
 *
 * Mismo criterio que `Condiciones`/`Recarga`/`Evidencia`: registra un HECHO
 * puntual, no una entidad con máquina de estados propia — sin
 * `Aplicacion/MaquinaEstados/` para este modelo.
 *
 * @property int $id
 * @property string $uuid_cliente
 * @property int $trabajo_id
 * @property string $horas_vuelo_dron
 * @property int $foto_control_id
 * @property int $foto_ciclo_bateria_balanceo_id
 * @property int $foto_dron_limpio_id
 */
class EvidenciaEquipo extends ModeloDominio
{
    /** Prefijo de módulo en el nombre físico (ADR 0011); el global lo pone la conexión. */
    protected $table = 'ope_evidencias_equipo';

    /** @var list<string> */
    protected $fillable = [
        'uuid_cliente',
        'trabajo_id',
        'horas_vuelo_dron',
        'foto_control_id',
        'foto_ciclo_bateria_balanceo_id',
        'foto_dron_limpio_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'horas_vuelo_dron' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Trabajo, $this> */
    public function trabajo(): BelongsTo
    {
        return $this->belongsTo(Trabajo::class, 'trabajo_id');
    }

    /** @return BelongsTo<Evidencia, $this> */
    public function fotoControl(): BelongsTo
    {
        return $this->belongsTo(Evidencia::class, 'foto_control_id');
    }

    /** @return BelongsTo<Evidencia, $this> */
    public function fotoCicloBateriaBalanceo(): BelongsTo
    {
        return $this->belongsTo(Evidencia::class, 'foto_ciclo_bateria_balanceo_id');
    }

    /** @return BelongsTo<Evidencia, $this> */
    public function fotoDronLimpio(): BelongsTo
    {
        return $this->belongsTo(Evidencia::class, 'foto_dron_limpio_id');
    }
}
