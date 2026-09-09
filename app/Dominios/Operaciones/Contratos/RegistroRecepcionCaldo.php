<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * DTO primitivo de entrada del contrato de escritura de `Operaciones` (ADR
 * 0003, regla 2; HU-10 redefinida por CR-01, tarea 18): la forma de un
 * registro `recepcion_caldo` en el lote de `POST /api/sync`.
 *
 * Mismo criterio que `RegistroCondiciones`: `$trabajoUuidCliente` referencia
 * el trabajo por el `uuid_cliente` de su apertura, nunca por id de servidor
 * — puede haber llegado en el mismo lote. Sin `$operarioPersonaId` en
 * `EscrituraSincronizacion::registrarRecepcionCaldo()`: la espec (§7.2) no
 * define una noción de "dueño" de este registro — cualquier operario legítimo
 * puede dejar constancia de que el cliente entregó caldo.
 *
 * `$litros` es OBLIGATORIO (a diferencia de `litros_consumidos`/
 * `litros_sobrante` de `CierreSesion`/`CierreTrabajo`, que son opcionales):
 * un evento de recepción sin cantidad no es un evento. `$entregadoPor`
 * también es obligatorio — la espec lo lista explícitamente junto con
 * cuánto y cuándo ("quién lo entregó").
 */
final readonly class RegistroRecepcionCaldo
{
    private function __construct(
        public string $uuidCliente,
        public string $trabajoUuidCliente,
        public string $litros,
        public string $entregadoPor,
        public string $hora,
    ) {}

    /** @param  array<string, mixed>  $datos */
    public static function intentarDesdeArreglo(array $datos): ?self
    {
        if (! self::esStringNoVacio($datos['uuid_cliente'] ?? null)
            || ! self::esStringNoVacio($datos['trabajo_uuid_cliente'] ?? null)
            || ! self::esNumeroNoNegativo($datos['litros'] ?? null)
            || ! self::esStringNoVacio($datos['entregado_por'] ?? null)
            || ! self::esStringNoVacio($datos['hora'] ?? null)
        ) {
            return null;
        }

        return new self(
            uuidCliente: (string) $datos['uuid_cliente'],
            trabajoUuidCliente: (string) $datos['trabajo_uuid_cliente'],
            litros: (string) $datos['litros'],
            entregadoPor: (string) $datos['entregado_por'],
            hora: (string) $datos['hora'],
        );
    }

    private static function esStringNoVacio(mixed $valor): bool
    {
        return is_string($valor) && $valor !== '';
    }

    /** Forma de un `DECIMAL` no negativo (invariante 6 de CLAUDE.md). */
    private static function esNumeroNoNegativo(mixed $valor): bool
    {
        if (is_int($valor) || is_float($valor)) {
            return $valor >= 0;
        }

        return is_string($valor) && $valor !== '' && is_numeric($valor) && (float) $valor >= 0;
    }
}
