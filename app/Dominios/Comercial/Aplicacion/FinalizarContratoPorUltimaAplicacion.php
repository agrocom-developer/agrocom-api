<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Aplicacion\MaquinaEstados\MaquinaEstadosContrato;
use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;

/**
 * Finaliza el contrato cuando se cierra su ÚLTIMA aplicación (pedido del dueño,
 * 18/9/2026, ADR 0022): con eso se libera el bloqueo de sus lotes (ADR 0021).
 * Lo invoca el oyente de `AplicacionCerrada` (`ComercialServiceProvider::boot()`).
 *
 * Solo actúa sobre un contrato `vigente` y cuando el número de la aplicación
 * cerrada alcanza las `aplicaciones_previstas`. Si el contrato está pausado (o
 * ya terminó), no hace nada: no es un error, el encargado lo resuelve a mano. La
 * transición pasa por `MaquinaEstadosContrato::finalizar()` (invariante 7).
 */
final class FinalizarContratoPorUltimaAplicacion
{
    public function __construct(private readonly MaquinaEstadosContrato $maquinaEstados) {}

    public function ejecutar(int $contratoId, int $nroAplicacion): void
    {
        $contrato = Contrato::query()->find($contratoId);

        if ($contrato === null || $contrato->estado !== EstadoContrato::Vigente) {
            return;
        }

        if ($nroAplicacion < (int) $contrato->aplicaciones_previstas) {
            return;
        }

        $this->maquinaEstados->finalizar($contrato);
    }
}
