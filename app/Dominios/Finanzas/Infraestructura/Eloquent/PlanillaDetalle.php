<?php

namespace App\Dominios\Finanzas\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Renglón por persona de una `fin_planillas` (espec Sprint 8 §193, tabla
 * `fin_planilla_detalles`; HU-30, tarea 44). `devengado`/`anticipos`/`neto`
 * son un SNAPSHOT calculado y persistido al generar la planilla
 * (`Finanzas/Aplicacion/GenerarPlanilla.php`), nunca recalculado después —
 * mismo criterio que `fin_devengos_personal.monto`: el registro de lo que
 * se pagó, no una vista sobre el estado actual de sus tablas de origen.
 *
 * `pdf_path` se completa recién al aprobar la planilla
 * (`Finanzas/Aplicacion/AprobarPlanilla.php`, que genera el recibo dentro de
 * la misma transacción de aprobación) — mismo patrón que `ope_actas.pdf_path`.
 *
 * `persona_id` referencia `per_personas` solo por FK + entero plano (ADR
 * 0003, regla 3) — sin `belongsTo` cross-módulo, mismo criterio que
 * `DevengoPersonal`/`Anticipo`.
 *
 * `RegistraBitacora` (invariante 9): es dinero, mismo criterio que
 * `Planilla`/`DevengoPersonal`/`Anticipo`.
 *
 * @property int $id
 * @property int $planilla_id
 * @property int $persona_id
 * @property string $devengado
 * @property string $anticipos
 * @property string $neto
 * @property string|null $pdf_path
 */
class PlanillaDetalle extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'fin_planilla_detalles';

    /** @var list<string> */
    protected $fillable = [
        'planilla_id',
        'persona_id',
        'devengado',
        'anticipos',
        'neto',
        'pdf_path',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'devengado' => 'decimal:2',
            'anticipos' => 'decimal:2',
            'neto' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Planilla, $this> */
    public function planilla(): BelongsTo
    {
        return $this->belongsTo(Planilla::class, 'planilla_id');
    }
}
