<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * DTO primitivo de entrada del contrato de escritura de `Operaciones` (ADR
 * 0003, regla 2; HU-80, tarea 86): la forma de un registro
 * `evidencia_equipo` en el lote de `POST /api/sync` — el "Reporte de
 * Equipos" del dueño (13/9/2026).
 *
 * Mismo criterio que `RegistroRecepcionCaldo`: `$trabajoUuidCliente`
 * referencia el trabajo por el `uuid_cliente` de su apertura, nunca por id
 * de servidor — puede haber llegado en el mismo lote. Sin
 * `$operarioPersonaId` en `EscrituraSincronizacion::registrarEvidenciaEquipo()`:
 * la espec no define un dueño individual para este registro, mismo criterio
 * que `condiciones`/`recepcion_caldo`.
 *
 * Los tres `*UuidCliente` de foto son OBLIGATORIOS, mismo criterio que
 * `RegistroIncidencia::$evidenciaFotoUuidCliente`: el propósito del registro
 * ("Reporte de Equipos" con las tres fotos de chequeo) es la condición del
 * registro, no un dato opcional. Cada uno referencia, por `uuid_cliente`, una
 * evidencia ya subida vía `POST /api/evidencias` — este DTO solo valida
 * FORMA ("string no vacío"); que exista, sea del tipo correcto y no esté ya
 * usada por otro registro lo valida
 * `EscrituraSincronizacionEloquent::registrarEvidenciaEquipo()`, que sí puede
 * leer la base.
 */
final readonly class RegistroEvidenciaEquipo
{
    private function __construct(
        public string $uuidCliente,
        public string $trabajoUuidCliente,
        public string $horasVueloDron,
        public string $fotoControlUuidCliente,
        public string $fotoCicloBateriaBalanceoUuidCliente,
        public string $fotoDronLimpioUuidCliente,
    ) {}

    /** @param  array<string, mixed>  $datos */
    public static function intentarDesdeArreglo(array $datos): ?self
    {
        if (! self::esStringNoVacio($datos['uuid_cliente'] ?? null)
            || ! self::esStringNoVacio($datos['trabajo_uuid_cliente'] ?? null)
            || ! self::esNumeroNoNegativo($datos['horas_vuelo_dron'] ?? null)
            || ! self::esStringNoVacio($datos['foto_control_uuid_cliente'] ?? null)
            || ! self::esStringNoVacio($datos['foto_ciclo_bateria_balanceo_uuid_cliente'] ?? null)
            || ! self::esStringNoVacio($datos['foto_dron_limpio_uuid_cliente'] ?? null)
        ) {
            return null;
        }

        return new self(
            uuidCliente: (string) $datos['uuid_cliente'],
            trabajoUuidCliente: (string) $datos['trabajo_uuid_cliente'],
            horasVueloDron: (string) $datos['horas_vuelo_dron'],
            fotoControlUuidCliente: (string) $datos['foto_control_uuid_cliente'],
            fotoCicloBateriaBalanceoUuidCliente: (string) $datos['foto_ciclo_bateria_balanceo_uuid_cliente'],
            fotoDronLimpioUuidCliente: (string) $datos['foto_dron_limpio_uuid_cliente'],
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
