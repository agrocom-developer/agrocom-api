<?php

namespace App\Dominios\Compartido\Dominio;

use App\Dominios\Compartido\Dominio\Excepciones\MotivoRequerido;
use App\Dominios\Compartido\Dominio\Excepciones\TransicionDeAprobacionInvalida;

/**
 * Valida una decisión de aprobación y devuelve el estado destino: la guarda de
 * la máquina de estados que comparten `Proveedores` y `Flota`. Pura. No decide
 * si el actor tiene permiso ni si al sujeto le faltan documentos: eso es de
 * cada caso de uso.
 */
final class DecisionDeAprobacion
{
    /**
     * @throws TransicionDeAprobacionInvalida si la acción no corresponde al estado actual.
     * @throws MotivoRequerido si rechazar, suspender o dar de baja llega sin motivo.
     */
    public static function validar(EstadoAprobacion $actual, AccionAprobacion $accion, ?string $motivo): EstadoAprobacion
    {
        if (! TransicionesAprobacion::aplicable($actual, $accion)) {
            throw TransicionDeAprobacionInvalida::desde($actual, $accion);
        }

        if ($accion->requiereMotivo() && trim((string) $motivo) === '') {
            throw MotivoRequerido::para($accion);
        }

        return $accion->estadoDestino();
    }

    /** Motivo a guardar en la fila del sujeto: solo las decisiones con motivo lo dejan; aprobar lo limpia. */
    public static function motivoAGuardar(AccionAprobacion $accion, ?string $motivo): ?string
    {
        return $accion->requiereMotivo() ? trim((string) $motivo) : null;
    }
}
