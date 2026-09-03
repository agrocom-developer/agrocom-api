<?php

namespace App\Dominios\Comercial\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Factura emitida desde un acta de conformidad ya firmada (espec Sprint 9
 * §205; HU-31, tarea 45): "como encargado, quiero emitir la factura de un
 * trabajo desde su acta conformada, para cobrar sobre hectáreas ya
 * firmadas". La crea únicamente `Comercial/Aplicacion/EmitirFactura.php`,
 * contra el acta resuelta por
 * `Operaciones\Contratos\LecturaActaConformada` (ADR 0003, regla 2 — este
 * módulo nunca importa `Acta`, `Trabajo` ni `OrdenAplicacion` de
 * Operaciones).
 *
 * `acta_id` referencia `ope_actas` solo por FK + entero plano — sin
 * `belongsTo` cross-módulo, mismo criterio que `fin_devengos_personal.sesion_id`
 * y `Finanzas\Infraestructura\Eloquent\Anticipo.persona_id`.
 *
 * `hectareas_facturadas`/`precio_ha`/`monto` son una copia congelada al
 * emitir, nunca recalculada contra el contrato o el acta actuales
 * (invariante 6 de CLAUDE.md).
 *
 * `RegistraBitacora` (invariante 9): mismo criterio que `Contrato` — es
 * dinero.
 *
 * @property int $id
 * @property int $contrato_id
 * @property int $acta_id
 * @property string $hectareas_facturadas
 * @property string $precio_ha
 * @property string $monto
 * @property CarbonImmutable $fecha_emision
 */
class Factura extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'com_facturas';

    /** @var list<string> */
    protected $fillable = [
        'contrato_id',
        'acta_id',
        'hectareas_facturadas',
        'precio_ha',
        'monto',
        'fecha_emision',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'contrato_id' => 'integer',
            'acta_id' => 'integer',
            'hectareas_facturadas' => 'decimal:2',
            'precio_ha' => 'decimal:2',
            'monto' => 'decimal:2',
            'fecha_emision' => 'immutable_date',
        ];
    }

    /** @return BelongsTo<Contrato, $this> */
    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }
}
