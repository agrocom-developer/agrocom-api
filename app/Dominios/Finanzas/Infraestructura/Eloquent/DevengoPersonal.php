<?php

namespace App\Dominios\Finanzas\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Finanzas\Contratos\ModalidadPago;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Devengo de una persona por una sesión validada (`fin_devengos_personal`,
 * espec §4.4; ADR 0023 desde el 22/9/2026). `hectareas`, `tarifa` y `monto`
 * son copia congelada al validar: el registro de lo que se pagó y con qué
 * condición, no una referencia recalculable.
 *
 * `modalidad`: `por_ha` (monto = hectáreas × tarifa) o `por_dia` (jornal:
 * monto = tarifa, uno por persona y fecha). `absorbido_por_id`: un devengo
 * por hectárea que ese mismo día quedó cubierto por un jornal de la misma
 * persona (decisión del dueño: el día por jornal absorbe todo lo del día).
 * No se paga, pero se conserva; {@see self::scopePagables()} es lo que
 * suman planilla, anticipos y el panel.
 *
 * @property int $id
 * @property int $sesion_id
 * @property int|null $trabajo_id
 * @property int $persona_id
 * @property ModalidadPago $modalidad
 * @property string $hectareas
 * @property string $tarifa
 * @property string $monto
 * @property Carbon $fecha
 * @property int|null $absorbido_por_id
 */
class DevengoPersonal extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'fin_devengos_personal';

    protected $fillable = [
        'sesion_id',
        'trabajo_id',
        'persona_id',
        'modalidad',
        'hectareas',
        'tarifa',
        'monto',
        'fecha',
        'absorbido_por_id',
    ];

    protected function casts(): array
    {
        return [
            'modalidad' => ModalidadPago::class,
            'hectareas' => 'decimal:2',
            'tarifa' => 'decimal:2',
            'monto' => 'decimal:2',
            'fecha' => 'date',
        ];
    }

    /**
     * Los que efectivamente se pagan: excluye los absorbidos por un jornal.
     *
     * @param  Builder<DevengoPersonal>  $consulta
     * @return Builder<DevengoPersonal>
     */
    public function scopePagables(Builder $consulta): Builder
    {
        return $consulta->whereNull('absorbido_por_id');
    }

    public function estaAbsorbido(): bool
    {
        return $this->absorbido_por_id !== null;
    }
}
