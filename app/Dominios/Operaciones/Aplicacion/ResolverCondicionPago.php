<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Finanzas\Contratos\CondicionPago;
use App\Dominios\Finanzas\Contratos\LecturaTarifasPago;
use App\Dominios\Finanzas\Contratos\ModalidadPago;
use App\Dominios\Operaciones\Dominio\Excepciones\TarifaNoDisponible;

/**
 * Condición de pago de un equipo (ADR 0023): copia una tarifa del catálogo
 * de Finanzas, o arma una negociada con los valores del formulario. Extraída
 * de `CrearOrdenTrabajo::resolverCondicion()` (tarea 127) para que
 * `ActualizarOrdenTrabajo` la reutilice sin duplicar la regla — misma
 * resolución para el alta y para la edición de la condición de un equipo.
 */
final class ResolverCondicionPago
{
    public function __construct(private readonly LecturaTarifasPago $tarifas) {}

    /**
     * @param  array{tarifa_id: int|null, negociado: bool, modalidad: string|null, monto_piloto: string|null, monto_auxiliar: string|null, motivo: string|null}  $pago
     *
     * Con `negociado` falso se COPIAN modalidad y montos de la tarifa
     * elegida; con `negociado` verdadero valen los del formulario y la
     * tarifa queda solo como referencia de dónde se partió. En ambos casos
     * lo devuelto es una copia congelada: cambiar la tarifa después no toca
     * lo ya resuelto.
     *
     * @throws TarifaNoDisponible
     */
    public function ejecutar(array $pago): CondicionPago
    {
        $tarifa = $pago['tarifa_id'] !== null ? $this->tarifas->porId($pago['tarifa_id']) : null;

        if ($pago['tarifa_id'] !== null && $tarifa === null) {
            throw TarifaNoDisponible::porId($pago['tarifa_id']);
        }

        if (! $pago['negociado']) {
            if ($tarifa === null) {
                throw TarifaNoDisponible::porId((int) $pago['tarifa_id']);
            }

            return $tarifa->comoCondicion();
        }

        return new CondicionPago(
            modalidad: ModalidadPago::from((string) $pago['modalidad']),
            montoPiloto: (string) $pago['monto_piloto'],
            montoAuxiliar: (string) $pago['monto_auxiliar'],
            tarifaId: $tarifa?->id,
            negociada: true,
        );
    }
}
