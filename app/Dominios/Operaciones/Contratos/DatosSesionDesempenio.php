<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Una sesión VIGENTE de la persona consultada (tarea 81, HU-58), con cliente
 * y campaña ya resueltos vía `Trabajo → OrdenAplicacion → LecturaContrato`
 * (ADR 0015 punto 1) y lote/campo vía `Comercial\Contratos\LecturaPanelComercial`
 * — ninguno de los dos cruces lo hace el consumidor (`Personal`).
 *
 * `rol`: `'piloto'` o `'auxiliar'`, según cuál columna de `ope_sesiones`
 * coincide con la persona consultada — nunca ambas a la vez.
 *
 * `campaniaId`/`campaniaCodigo` en `null` cuando el contrato del trabajo
 * todavía no tiene campaña asignada (ver docblock de `DatosResumenContrato`).
 * `clienteId` en `0`/`clienteNombre` en `'—'` cuando el contrato no pudo
 * resolverse (dato inconsistente, no debería ocurrir con las FK vigentes) —
 * mismo criterio de relleno que `ListarReportesTecnicos`.
 */
final readonly class DatosSesionDesempenio
{
    public function __construct(
        public int $sesionId,
        public string $fecha,
        public string $rol,
        public int $trabajoId,
        public int $loteId,
        public string $loteCodigo,
        public string $campoNombre,
        public int $clienteId,
        public string $clienteNombre,
        public ?int $campaniaId,
        public ?string $campaniaCodigo,
        public ?int $dronId,
        public string $hectareasDeclaradas,
        public string $estado,
    ) {}
}
