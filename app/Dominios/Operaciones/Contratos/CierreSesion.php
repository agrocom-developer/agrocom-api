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
 *
 * `$hectareasDeclaradas` (hallazgo del veredicto de la tarea 13): la espec
 * (§5, tabla de transiciones) fija "hectáreas de la sesión" como condición
 * de `sesión → cerrada`, no de la apertura — un piloto no sabe cuánto va a
 * cubrir antes de volar, pero sí sabe cuánto cubrió al cerrar. Por eso es
 * OBLIGATORIO acá (a diferencia de `AperturaSesion::$hectareasDeclaradas`,
 * que acepta ausencia con default `'0'`): un registro de cierre sin
 * hectáreas se rechaza completo, mismo criterio que `motivo_cierre` fuera de
 * catálogo — no queda una sesión "cerrada" con el dato central de la
 * transición sin declarar.
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
        public string $hectareasDeclaradas,
    ) {}

    /** @param  array<string, mixed>  $datos */
    public static function intentarDesdeArreglo(array $datos): ?self
    {
        if (! self::esStringNoVacio($datos['uuid_cliente'] ?? null)
            || ! self::esStringNoVacio($datos['sesion_uuid_cliente'] ?? null)
            || ! self::esStringNoVacio($datos['fin'] ?? null)
            || ! is_string($datos['motivo_cierre'] ?? null)
            || ! in_array($datos['motivo_cierre'], self::MOTIVOS, true)
            || ! self::esNumeroNoNegativo($datos['hectareas_declaradas'] ?? null)
        ) {
            return null;
        }

        return new self(
            uuidCliente: (string) $datos['uuid_cliente'],
            sesionUuidCliente: (string) $datos['sesion_uuid_cliente'],
            fin: (string) $datos['fin'],
            motivoCierre: (string) $datos['motivo_cierre'],
            hectareasDeclaradas: (string) $datos['hectareas_declaradas'],
        );
    }

    private static function esStringNoVacio(mixed $valor): bool
    {
        return is_string($valor) && $valor !== '';
    }

    /**
     * Forma de un `DECIMAL` no negativo (invariante 6 de CLAUDE.md): entero,
     * float o string numérica, nunca menor que cero. A diferencia del
     * homónimo de `AperturaSesion`, acá el valor es obligatorio — `null`
     * rechaza el registro, no se completa con `'0'`.
     */
    private static function esNumeroNoNegativo(mixed $valor): bool
    {
        if (is_int($valor) || is_float($valor)) {
            return $valor >= 0;
        }

        return is_string($valor) && $valor !== '' && is_numeric($valor) && (float) $valor >= 0;
    }
}
