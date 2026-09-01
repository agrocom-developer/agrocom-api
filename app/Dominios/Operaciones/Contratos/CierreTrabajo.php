<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * DTO primitivo de entrada del contrato de escritura de `Operaciones` (ADR
 * 0003, regla 2; HU-05, tarea 13): la forma de un registro `cierre_trabajo`
 * en el lote de `POST /api/sync`.
 *
 * `$uuidCliente` identifica el EVENTO de cierre — distinto de
 * `$trabajoUuidCliente`, que referencia el trabajo a cerrar por el
 * `uuid_cliente` que ya trajo su apertura. Son dos UUID porque el cierre es
 * una mutación sobre una fila existente, no una fila nueva: la idempotencia
 * de un reintento se apoya en `$uuidCliente` (ver
 * `EscrituraSincronizacionEloquent::cerrarTrabajo()` y runs/13.md), no en el
 * `UNIQUE` de apertura, que protege una fila distinta.
 *
 * Mismo criterio que `AperturaTrabajo`: constructor privado, solo
 * `intentarDesdeArreglo()` construye, devuelve `null` ante un dato faltante
 * o mal tipado — el registro se rechaza sin frenar el resto del lote.
 */
final readonly class CierreTrabajo
{
    private function __construct(
        public string $uuidCliente,
        public string $trabajoUuidCliente,
        public string $fin,
    ) {}

    /** @param  array<string, mixed>  $datos */
    public static function intentarDesdeArreglo(array $datos): ?self
    {
        if (! self::esStringNoVacio($datos['uuid_cliente'] ?? null)
            || ! self::esStringNoVacio($datos['trabajo_uuid_cliente'] ?? null)
            || ! self::esStringNoVacio($datos['fin'] ?? null)
        ) {
            return null;
        }

        return new self(
            uuidCliente: (string) $datos['uuid_cliente'],
            trabajoUuidCliente: (string) $datos['trabajo_uuid_cliente'],
            fin: (string) $datos['fin'],
        );
    }

    private static function esStringNoVacio(mixed $valor): bool
    {
        return is_string($valor) && $valor !== '';
    }
}
