<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Finanzas\Dominio\Excepciones\AnticipoExcedeTope;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Anticipo;
use Brick\Math\BigDecimal;

/**
 * Alta de un anticipo con validación de tope (HU-29, tarea 41). El tope
 * (3.000 Bs/mes y 70% del devengado del mes, el que sea menor, menos lo ya
 * adelantado) lo calcula `CalcularDisponibleAnticipo` — este caso de uso
 * solo compara el monto pedido contra ese disponible y decide.
 *
 * Si el monto excede el disponible, lanza `AnticipoExcedeTope` ANTES de
 * tocar la base: el criterio de aceptación pide que el rechazo diga cuánto
 * es el máximo disponible, así que ese valor tiene que estar calculado antes
 * de decidir si se persiste o no.
 */
final class RegistrarAnticipo
{
    public function __construct(private readonly CalcularDisponibleAnticipo $calcularDisponible) {}

    /**
     * @throws AnticipoExcedeTope si `$monto` supera el disponible del mes de `$fecha`.
     */
    public function ejecutar(int $personaId, string $monto, string $fecha, ?string $motivo): Anticipo
    {
        $disponible = $this->calcularDisponible->ejecutar($personaId, $fecha);

        if (BigDecimal::of($monto)->isGreaterThan($disponible)) {
            throw AnticipoExcedeTope::paraDisponible($disponible);
        }

        $anticipo = new Anticipo([
            'persona_id' => $personaId,
            'monto' => $monto,
            'fecha' => $fecha,
            'motivo' => $motivo,
        ]);

        $anticipo->save();

        return $anticipo->refresh();
    }
}
