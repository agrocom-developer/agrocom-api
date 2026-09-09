<?php

namespace App\Dominios\Comercial\Contratos;

/**
 * Forma de dato primitiva de un contrato para quien solo necesita ubicar su
 * cliente, sin importar los modelos Eloquent `Contrato`/`Cliente` (ADR 0003,
 * regla 2): hoy, `Operaciones\Aplicacion\ListarReportesTecnicos` (HU-43,
 * tarea 57), que resuelve el nombre de cliente de cada reporte técnico y
 * filtra el listado por `cliente_id`; y `Operaciones\Infraestructura\LecturaDesempenioPersonaEloquent`
 * (tarea 81), que además necesita la campaña — es la ruta exacta que pide ADR
 * 0015 punto 1 (`orden → contrato → campania_id`), nunca deducida por fecha.
 *
 * `campaniaId`/`campaniaCodigo` en `null` cuando el contrato todavía no tiene
 * campaña asignada (dato histórico previo a la migración del ADR 0015, o fila
 * de test que no la fija — la columna es `NOT NULL` solo en `pgsql`, ver
 * `2026_09_08_100002_add_campania_id_a_com_contratos_table.php`).
 */
final readonly class DatosResumenContrato
{
    public function __construct(
        public int $contratoId,
        public int $clienteId,
        public string $clienteNombre,
        public ?int $campaniaId,
        public ?string $campaniaCodigo,
    ) {}
}
