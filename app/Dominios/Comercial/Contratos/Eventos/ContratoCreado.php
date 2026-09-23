<?php

namespace App\Dominios\Comercial\Contratos\Eventos;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Evento de dominio (ADR 0003, regla 2; tarea 141, ADR 0025): se anuncia que
 * nació un contrato. DTO de primitivos, nunca un modelo Eloquent, para que
 * quien lo escuche no dependa de `Comercial` más allá de estos cuatro datos —
 * el módulo `Notificaciones` arma el texto del aviso solo con ellos, sin
 * consultar `com_contratos` ni `com_clientes`.
 *
 * Lo dispara `Aplicacion/CrearContrato::ejecutar()` recién DESPUÉS de que la
 * transacción del alta (contrato y lotes) se confirmó — nunca antes, para no
 * anunciar un contrato que todavía podría deshacerse. Implementa
 * {@see ShouldDispatchAfterCommit} por si algún día se invoca dentro de una
 * transacción mayor: el aviso sale al confirmarla y un rollback no anuncia
 * nada.
 *
 * `$hectareas` es el decimal exacto de `hectareas_contratadas` (invariante 6:
 * nunca float).
 *
 * Oyente real: `Notificaciones\Infraestructura\NotificacionesServiceProvider`
 * (regla `ReglaContratoCreado`: avisa a los usuarios con rol
 * `encargado_operaciones`).
 */
final readonly class ContratoCreado implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $contratoId,
        public int $clienteId,
        public string $cliente,
        public string $hectareas,
    ) {}
}
