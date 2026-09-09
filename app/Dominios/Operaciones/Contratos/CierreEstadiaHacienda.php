<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * DTO primitivo de entrada del contrato de escritura de `Operaciones` (ADR
 * 0003, regla 2; HU-51, tarea 74): la forma de un registro `estadia_salida`
 * en el lote de `POST /api/sync`.
 *
 * Mismo criterio que `CierreSesion`/`CierreTrabajo`: `$uuidCliente`
 * identifica el EVENTO de salida, `$estadiaUuidCliente` referencia la
 * estadía a cerrar por el `uuid_cliente` de su entrada (espec §2.1, punto 5)
 * — nunca por id de servidor.
 */
final readonly class CierreEstadiaHacienda
{
    private function __construct(
        public string $uuidCliente,
        public string $estadiaUuidCliente,
        public string $salida,
    ) {}

    /** @param  array<string, mixed>  $datos */
    public static function intentarDesdeArreglo(array $datos): ?self
    {
        if (! self::esStringNoVacio($datos['uuid_cliente'] ?? null)
            || ! self::esStringNoVacio($datos['estadia_uuid_cliente'] ?? null)
            || ! self::esStringNoVacio($datos['salida'] ?? null)
        ) {
            return null;
        }

        return new self(
            uuidCliente: (string) $datos['uuid_cliente'],
            estadiaUuidCliente: (string) $datos['estadia_uuid_cliente'],
            salida: (string) $datos['salida'],
        );
    }

    private static function esStringNoVacio(mixed $valor): bool
    {
        return is_string($valor) && $valor !== '';
    }
}
