<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * DTO primitivo de entrada del contrato de escritura de `Operaciones` (ADR
 * 0003, regla 2; HU-51, tarea 74): la forma de un registro `estadia_entrada`
 * en el lote de `POST /api/sync`. `equipo_trabajo_id`, `propiedad_id` y
 * `vehiculo_id` ya vienen resueltos a id de servidor (el cliente los trae del
 * pull de catálogo), mismo criterio que `AperturaTrabajo::$ordenId`/`$loteId`.
 *
 * El constructor es privado a propósito, mismo motivo que `AperturaTrabajo`:
 * la única forma de obtener una instancia es `intentarDesdeArreglo()`, que
 * devuelve `null` ante un dato faltante o mal formado en vez de lanzar — un
 * rechazo no frena el resto del lote.
 *
 * Sin `campania_id` (ver docblock de la migración de `ope_estadias_hacienda`):
 * la estadía es de la propiedad, no de una campaña.
 *
 * `propiedad_id` (ADR 0020): contrato externo con `agrocom-field`, renombrado
 * de `campo_id` en conjunto con esa app (rama `feature/sync-propiedad`) — sin
 * APK distribuido todavía, sin ventana de compatibilidad que cuidar.
 */
final readonly class AperturaEstadiaHacienda
{
    private function __construct(
        public string $uuidCliente,
        public int $equipoTrabajoId,
        public int $propiedadId,
        public string $entrada,
        public ?int $vehiculoId,
        public ?string $observacion,
    ) {}

    /** @param  array<string, mixed>  $datos */
    public static function intentarDesdeArreglo(array $datos): ?self
    {
        if (! self::esStringNoVacio($datos['uuid_cliente'] ?? null)
            || ! self::esEntero($datos['equipo_trabajo_id'] ?? null)
            || ! self::esEntero($datos['propiedad_id'] ?? null)
            || ! self::esStringNoVacio($datos['entrada'] ?? null)
            || ! self::esEnteroOAusente($datos['vehiculo_id'] ?? null)
            || ! self::esStringOAusente($datos['observacion'] ?? null)
        ) {
            return null;
        }

        return new self(
            uuidCliente: (string) $datos['uuid_cliente'],
            equipoTrabajoId: (int) $datos['equipo_trabajo_id'],
            propiedadId: (int) $datos['propiedad_id'],
            entrada: (string) $datos['entrada'],
            vehiculoId: isset($datos['vehiculo_id']) ? (int) $datos['vehiculo_id'] : null,
            observacion: isset($datos['observacion']) ? (string) $datos['observacion'] : null,
        );
    }

    private static function esStringNoVacio(mixed $valor): bool
    {
        return is_string($valor) && $valor !== '';
    }

    private static function esStringOAusente(mixed $valor): bool
    {
        return $valor === null || is_string($valor);
    }

    private static function esEntero(mixed $valor): bool
    {
        return is_int($valor) || (is_string($valor) && ctype_digit($valor));
    }

    private static function esEnteroOAusente(mixed $valor): bool
    {
        return $valor === null || self::esEntero($valor);
    }
}
