<?php

namespace App\Dominios\Finanzas\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Finanzas\Dominio\EstadoRendicion;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Rendición de campo (espec §4.4; HU-34, tarea 48): "como jefe de campo,
 * quiero rendir los gastos que hice en campo; el encargado los aprueba para
 * reponer el fondo". La crea únicamente
 * `Finanzas/Aplicacion/MaquinaEstados/MaquinaEstadosRendicion::generar()`;
 * sus transiciones de `estado` (abierta → presentada → aprobada) pasan por
 * esa misma clase (invariante 7 de CLAUDE.md) — este modelo no ofrece
 * atajos para mutarlas.
 *
 * `base_id`/`jefe_campo_id`/`aprobado_por` referencian `per_bases`/
 * `per_personas` solo por FK + entero plano (ADR 0003 regla 3, mismo
 * criterio que `Gasto::base_id`) — sin `belongsTo` cross-módulo.
 *
 * `gastos()` sí es una relación Eloquent legítima: `Gasto` es del mismo
 * módulo (Finanzas), a diferencia de `base_id`/`jefe_campo_id` de arriba.
 *
 * `monto` es la suma persistida de los `fin_gastos.monto` asociados al
 * momento de presentar/aprobar (invariante 6: DECIMAL, recalculable a
 * centavo exacto), nunca recalculada en vivo — mismo criterio que
 * `Planilla::total`.
 *
 * `RegistraBitacora` (invariante 9): es dinero, mismo criterio que
 * `Gasto`/`Anticipo`/`Planilla`.
 *
 * @property int $id
 * @property int $base_id
 * @property int $jefe_campo_id
 * @property Carbon $fecha
 * @property string|null $descripcion
 * @property string $monto
 * @property EstadoRendicion $estado
 * @property int|null $aprobado_por
 * @property int|null $gastos_count cargado por `withCount('gastos')` (listado)
 */
final class Rendicion extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'fin_rendiciones';

    /** @var list<string> */
    protected $fillable = [
        'base_id',
        'jefe_campo_id',
        'fecha',
        'descripcion',
        'monto',
        'estado',
        'aprobado_por',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'monto' => 'decimal:2',
            'estado' => EstadoRendicion::class,
        ];
    }

    /** @return HasMany<Gasto, $this> */
    public function gastos(): HasMany
    {
        return $this->hasMany(Gasto::class, 'rendicion_id');
    }
}
