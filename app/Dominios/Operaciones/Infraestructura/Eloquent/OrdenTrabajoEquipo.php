<?php

namespace App\Dominios\Operaciones\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Finanzas\Contratos\CondicionPago;
use App\Dominios\Finanzas\Contratos\ModalidadPago;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Condición de pago de un equipo dentro de una Orden de Trabajo
 * (`ope_orden_trabajo_equipos`, ADR 0023): tarifa elegida del catálogo de
 * Finanzas (referencia plana, sin relación Eloquent: es tabla ajena), la
 * modalidad y los montos copiados o negociados para ese trabajo puntual.
 * Los `Trabajo` del mismo equipo en la misma orden la comparten.
 *
 * @property int $id
 * @property int $orden_trabajo_id
 * @property int $equipo_trabajo_id
 * @property int|null $tarifa_id
 * @property ModalidadPago $modalidad_pago
 * @property string $monto_piloto
 * @property string $monto_auxiliar
 * @property bool $negociado
 * @property string|null $motivo_negociacion
 */
class OrdenTrabajoEquipo extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'ope_orden_trabajo_equipos';

    protected $fillable = [
        'orden_trabajo_id',
        'equipo_trabajo_id',
        'tarifa_id',
        'modalidad_pago',
        'monto_piloto',
        'monto_auxiliar',
        'negociado',
        'motivo_negociacion',
    ];

    protected function casts(): array
    {
        return [
            'orden_trabajo_id' => 'integer',
            'equipo_trabajo_id' => 'integer',
            'tarifa_id' => 'integer',
            'modalidad_pago' => ModalidadPago::class,
            'monto_piloto' => 'decimal:2',
            'monto_auxiliar' => 'decimal:2',
            'negociado' => 'boolean',
        ];
    }

    /** @return BelongsTo<OrdenTrabajo, $this> */
    public function ordenTrabajo(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class, 'orden_trabajo_id');
    }

    public function comoCondicion(): CondicionPago
    {
        return new CondicionPago(
            modalidad: $this->modalidad_pago,
            montoPiloto: (string) $this->monto_piloto,
            montoAuxiliar: (string) $this->monto_auxiliar,
            tarifaId: $this->tarifa_id,
            negociada: $this->negociado,
        );
    }
}
