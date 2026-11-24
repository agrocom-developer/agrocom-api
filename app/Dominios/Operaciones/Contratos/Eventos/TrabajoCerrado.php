<?php

namespace App\Dominios\Operaciones\Contratos\Eventos;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Evento de dominio (ADR 0003, regla 2; tarea 141, ADR 0025), mismo molde que
 * {@see SesionValidada}: se anuncia que un trabajo
 * pasó de `abierto` a `cerrado` — el piloto lo cerró en campo (el «trabajo
 * realizado» del pedido del dueño). DTO de primitivos.
 *
 * Lo dispara `Aplicacion/MaquinaEstados/MaquinaEstadosTrabajo::cerrar()`
 * DESPUÉS de persistir la transición, sin cambiar sus guardas ni su tabla de
 * transiciones (invariante 7). Como esa transición ocurre dentro de la
 * transacción de sincronización de la app de campo, el evento implementa
 * {@see ShouldDispatchAfterCommit}: el aviso sale recién al confirmarla, y un
 * cierre que se revierte no anuncia nada. Un reintento del mismo cierre no
 * llega a `cerrar()` (el motor de sync compara `cierre_uuid_cliente` antes), y
 * si llegara, el aviso es idempotente por `trabajo_cerrado:{trabajoId}`.
 *
 * `$solicitudId` es la solicitud de servicio de la que nació el trabajo.
 * `$hectareas` es el decimal exacto de `hectareas_declaradas` (invariante 6).
 *
 * Oyente real: `Notificaciones\Infraestructura\NotificacionesServiceProvider`
 * (regla `ReglaTrabajoCerrado`: avisa a jefe de campo y encargado de
 * operaciones).
 */
final readonly class TrabajoCerrado implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $trabajoId,
        public int $solicitudId,
        public string $hectareas,
    ) {}
}
