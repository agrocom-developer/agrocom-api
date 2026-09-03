<?php

namespace App\Dominios\Finanzas\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Finanzas\Dominio\EstadoPlanilla;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Planilla del período, generada desde devengos y anticipos (espec Sprint 8
 * §193, tabla `fin_planillas`; HU-30, tarea 44): "como dueño, quiero generar
 * la planilla del período desde los devengos y aprobarla, para pagar con un
 * respaldo que cuadre". La crea únicamente
 * `Finanzas/Aplicacion/GenerarPlanilla.php`; sus transiciones de `estado`
 * (borrador → aprobada) pasan por
 * `Aplicacion/MaquinaEstados/MaquinaEstadosPlanilla.php` (invariante 7 de
 * CLAUDE.md) — este modelo no ofrece atajos para mutarlas.
 *
 * `total` es la suma persistida de los `neto` de sus `detalles` al momento
 * de generar (invariante 6: DECIMAL, recalculable a centavo exacto contra
 * `fin_devengos_personal`/`fin_anticipos` del período), nunca recalculada en
 * vivo.
 *
 * `RegistraBitacora` (invariante 9): es dinero, mismo criterio que
 * `DevengoPersonal`/`Anticipo` — se declara acá explícitamente.
 *
 * @property int $id
 * @property string $periodo
 * @property EstadoPlanilla $estado
 * @property string $total
 * @property int|null $aprobada_por
 * @property CarbonImmutable|null $aprobada_en
 */
class Planilla extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'fin_planillas';

    /** @var list<string> */
    protected $fillable = [
        'periodo',
        'estado',
        'total',
        'aprobada_por',
        'aprobada_en',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'estado' => EstadoPlanilla::class,
            'total' => 'decimal:2',
            'aprobada_por' => 'integer',
            'aprobada_en' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<PlanillaDetalle, $this> */
    public function detalles(): HasMany
    {
        return $this->hasMany(PlanillaDetalle::class, 'planilla_id');
    }
}
