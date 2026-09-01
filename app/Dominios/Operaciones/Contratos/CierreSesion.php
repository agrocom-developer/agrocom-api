<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * DTO primitivo de entrada del contrato de escritura de `Operaciones` (ADR
 * 0003, regla 2; HU-05, tarea 13): la forma de un registro `cierre_sesion` en
 * el lote de `POST /api/sync`.
 *
 * Mismo criterio que `CierreTrabajo`: `$uuidCliente` identifica el EVENTO de
 * cierre, `$sesionUuidCliente` referencia la sesión a cerrar por el
 * `uuid_cliente` de su apertura. `$motivoCierre` es el catálogo cerrado de la
 * espec §4.3 — se valida acá, contra la lista, no solo contra "no vacío":
 * un valor fuera del catálogo rechaza el registro en el borde, antes de
 * llegar al `CHECK` de la base (que además no existe en SQLite).
 */
final readonly class CierreSesion
{
    /** Catálogo de la espec §4.3. Sin la lógica de relevo de HU-07. */
    private const array MOTIVOS = [
        'completado', 'relevo_piloto', 'cambio_dron',
        'falla_equipo', 'clima', 'fin_jornada', 'otro',
    ];

    private function __construct(
        public string $uuidCliente,
        public string $sesionUuidCliente,
        public string $fin,
        public string $motivoCierre,
    ) {}

    /** @param  array<string, mixed>  $datos */
    public static function intentarDesdeArreglo(array $datos): ?self
    {
        if (! self::esStringNoVacio($datos['uuid_cliente'] ?? null)
            || ! self::esStringNoVacio($datos['sesion_uuid_cliente'] ?? null)
            || ! self::esStringNoVacio($datos['fin'] ?? null)
            || ! is_string($datos['motivo_cierre'] ?? null)
            || ! in_array($datos['motivo_cierre'], self::MOTIVOS, true)
        ) {
            return null;
        }

        return new self(
            uuidCliente: (string) $datos['uuid_cliente'],
            sesionUuidCliente: (string) $datos['sesion_uuid_cliente'],
            fin: (string) $datos['fin'],
            motivoCierre: (string) $datos['motivo_cierre'],
        );
    }

    private static function esStringNoVacio(mixed $valor): bool
    {
        return is_string($valor) && $valor !== '';
    }
}
