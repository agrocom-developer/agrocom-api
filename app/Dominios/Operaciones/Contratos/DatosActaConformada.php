<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Forma de dato primitiva de un acta para quien necesita facturarla sin
 * importar el modelo Eloquent `Acta` (ADR 0003, regla 2): hoy, `Comercial`
 * (HU-31, tarea 45) para emitir la factura de un trabajo desde su acta
 * conformada. `firmada` viaja explícito en vez de que el consumidor infiera
 * el estado por ausencia de campos: así el caso de uso distingue "no existe"
 * (`obtenerPorActaId` devuelve `null`) de "existe pero no está firmada
 * todavía" con un solo booleano, sin depender del enum `EstadoActa` de este
 * módulo (mismo criterio "solo primitivos" que `DatosSesionValidada`).
 */
final readonly class DatosActaConformada
{
    public function __construct(
        public int $actaId,
        public int $contratoId,
        public string $hectareasConformadas,
        public bool $firmada,
    ) {}
}
