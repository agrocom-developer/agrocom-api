<?php

namespace App\Dominios\Operaciones\Dominio\MaquinaEstados;

use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;

/**
 * Tabla de transiciones permitidas para `ope_ordenes_aplicacion.estado`
 * (invariante 7 de CLAUDE.md; espec §5, HU-25, tarea 38). Reglas puras, sin
 * Eloquent ni `Illuminate\Database` (verificado por
 * `tests/Unit/ArquitecturaModulosTest.php`).
 *
 * `emitida` es el único estado de alta — lo fija
 * `Aplicacion/MaquinaEstados/MaquinaEstadosOrden::crear()` directamente,
 * nunca es destino de una transición (mismo criterio que `TransicionesContrato`
 * con `borrador`). Desde `emitida` la única salida es `vigente`; una orden
 * `emitida` que ya no se quiere se ELIMINA (baja lógica), no se cancela.
 *
 * Reforma 19/9/2026 (ADR 0022): una vez `vigente`, la aplicación puede
 * `pausarse` a la espera de resolver un problema y volver a `vigente`, cerrarse
 * (`consumida`: cumplida) o `cancelarse` (con causa y motivo, desde `vigente` o
 * `pausada`). `consumida` solo sale de `vigente`: para cerrar hay que estar
 * en ejecución, no detenida. `consumida`, `cancelada` y `vencida` no tienen
 * salida. `vencida` sigue sin ningún disparador de negocio: no es destino de
 * ninguna transición.
 */
final class TransicionesOrden
{
    /** @var array<string, list<string>> */
    private const array PERMITIDAS = [
        'emitida' => ['vigente'],
        'vigente' => ['pausada', 'consumida', 'cancelada'],
        'pausada' => ['vigente', 'cancelada'],
        'consumida' => [],
        'cancelada' => [],
        'vencida' => [],
    ];

    public static function permitida(EstadoOrdenAplicacion $desde, EstadoOrdenAplicacion $hacia): bool
    {
        return in_array($hacia->value, self::PERMITIDAS[$desde->value], true);
    }
}
