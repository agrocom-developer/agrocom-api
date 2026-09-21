<?php

namespace App\Dominios\Operaciones\Contratos\Eventos;

/**
 * Evento de dominio (ADR 0003, regla 2: entre módulos se viaja por
 * `Contratos/` o por eventos). Se dispara cuando una aplicación se CIERRA
 * (`vigente → consumida`, acción manual del encargado; ADR 0022). DTO
 * primitivo, nunca un modelo Eloquent, para que quien lo escuche no dependa
 * de `Operaciones` más allá de estos tres números.
 *
 * Lo dispara `Aplicacion/MaquinaEstados/MaquinaEstadosOrden::cerrar()`
 * DESPUÉS de persistir la transición — nunca antes, para no anunciar un
 * cierre que todavía podría fallar (mismo criterio que {@see SesionValidada}).
 *
 * Oyente real: `Comercial\Infraestructura\ComercialServiceProvider::boot()`
 * registra el que finaliza el contrato cuando esta era su última aplicación
 * (`Comercial\Aplicacion\FinalizarContratoPorUltimaAplicacion`), liberando así
 * sus lotes. Una aplicación CANCELADA no dispara este evento: cancelar es una
 * decisión del operador y del dueño, no un cierre por cumplimiento.
 */
final readonly class AplicacionCerrada
{
    public function __construct(
        public int $ordenId,
        public int $contratoId,
        public int $nroAplicacion,
    ) {}
}
