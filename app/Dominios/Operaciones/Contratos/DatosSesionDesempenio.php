<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Una sesión VIGENTE de la persona consultada (tarea 81, HU-58), con solicitud,
 * terreno y cliente ya resueltos vía `Trabajo → solicitud_id` y
 * `Solicitudes\Contratos\LecturaContextoSolicitudes` — el cruce no lo hace el
 * consumidor (`Personal`).
 *
 * `rol`: `'piloto'` o `'auxiliar'`, según cuál columna de `ope_sesiones`
 * coincide con la persona consultada — nunca ambas a la vez.
 *
 * `clienteId` en `0`/`clienteNombre` en `'—'` cuando la solicitud no pudo
 * resolverse (un trabajo anterior a las solicitudes, sin `solicitud_id`) —
 * mismo criterio de relleno que `ListarReportesTecnicos`.
 */
final readonly class DatosSesionDesempenio
{
    public function __construct(
        public int $sesionId,
        public string $fecha,
        public string $rol,
        public int $trabajoId,
        public int $solicitudId,
        public string $solicitudEtiqueta,
        public int $clienteId,
        public string $clienteNombre,
        public ?int $dronId,
        public string $hectareasDeclaradas,
        public string $estado,
    ) {}
}
