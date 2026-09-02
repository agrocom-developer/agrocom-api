<?php

namespace App\Dominios\Operaciones\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reporte técnico por lote (espec §9, "Técnico — por lote"; HU-18, tarea
 * 25). Registra un HECHO generado por el servidor al firmar el acta de
 * conformidad (`Aplicacion/GenerarReporteTecnico.php`) — no una entidad con
 * máquina de estados propia (mismo criterio que `Condiciones`/`Recarga`/
 * `Evidencia`: sin `Aplicacion/MaquinaEstados/` para este modelo, sin
 * `RegistraBitacora` — ver runs/25.md, esa categoría de ADR 0007 hoy solo
 * tiene gate automático para roles/permisos).
 *
 * `hora_inicio`/`hora_fin`: snapshot tomado al generar el reporte, nunca
 * recalculado (ver docblock de la migración).
 *
 * @property int $id
 * @property int $trabajo_id
 * @property CarbonImmutable|null $hora_inicio
 * @property CarbonImmutable|null $hora_fin
 * @property string|null $pdf_path
 * @property CarbonImmutable $generado_en
 */
class ReporteTecnico extends ModeloDominio
{
    /** Prefijo de módulo en el nombre físico (ADR 0011); el global lo pone la conexión. */
    protected $table = 'ope_reportes_tecnicos';

    /** @var list<string> */
    protected $fillable = [
        'trabajo_id',
        'hora_inicio',
        'hora_fin',
        'pdf_path',
        'generado_en',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'trabajo_id' => 'integer',
            'hora_inicio' => 'immutable_datetime',
            'hora_fin' => 'immutable_datetime',
            'generado_en' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Trabajo, $this> */
    public function trabajo(): BelongsTo
    {
        return $this->belongsTo(Trabajo::class, 'trabajo_id');
    }
}
