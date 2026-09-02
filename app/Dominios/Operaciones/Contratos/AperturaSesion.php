<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * DTO primitivo de entrada del contrato de escritura de `Operaciones` (ADR
 * 0003, regla 2; TE-05): la forma de un registro `sesion` en el lote de
 * `POST /api/sync` (espec §2.1, punto 3). `$trabajoUuidCliente` — no
 * `trabajoId` — porque el trabajo referenciado puede haber llegado en el
 * mismo lote y todavía no tener id de servidor cuando el cliente arma el
 * payload (espec §2.1, punto 5); resolver esa referencia es responsabilidad
 * de la implementación del contrato, no de este DTO.
 *
 * Mismo criterio que `AperturaTrabajo`: constructor privado, solo
 * `intentarDesdeArreglo()` construye, devuelve `null` en vez de lanzar ante
 * un dato faltante o mal tipado — el registro se rechaza sin frenar el resto
 * del lote.
 *
 * `$dronId`/`$hectareaInicialAcumulada` (HU-07, tarea 20): ambos OPCIONALES,
 * mismo patrón que `$auxiliarId`/`$hectareasDeclaradas` — un `null`/ausente
 * no rechaza el registro. Ver docblock de la migración
 * `2026_09_01_100014_add_dron_y_hectarea_inicial_a_ope_sesiones_table.php`
 * y runs/20.md para el porqué de dejarlos opcionales en vez de obligatorios
 * como `$pilotoId`.
 */
final readonly class AperturaSesion
{
    private function __construct(
        public string $uuidCliente,
        public string $trabajoUuidCliente,
        public int $secuencia,
        public int $pilotoId,
        public ?int $auxiliarId,
        public ?int $dronId,
        public string $hectareasDeclaradas,
        public ?string $hectareaInicialAcumulada,
        public string $inicio,
        public ?string $fin,
    ) {}

    /** @param  array<string, mixed>  $datos */
    public static function intentarDesdeArreglo(array $datos): ?self
    {
        if (! self::esStringNoVacio($datos['uuid_cliente'] ?? null)
            || ! self::esStringNoVacio($datos['trabajo_uuid_cliente'] ?? null)
            || ! self::esEntero($datos['secuencia'] ?? null)
            || ! self::esEntero($datos['piloto_id'] ?? null)
            || ! self::esEnteroOAusente($datos['auxiliar_id'] ?? null)
            || ! self::esEnteroOAusente($datos['dron_id'] ?? null)
            || ! self::esNumeroNoNegativoOAusente($datos['hectareas_declaradas'] ?? null)
            || ! self::esNumeroNoNegativoOAusente($datos['hectarea_inicial_acumulada'] ?? null)
            || ! self::esStringNoVacio($datos['inicio'] ?? null)
            || ! self::esStringOAusente($datos['fin'] ?? null)
        ) {
            return null;
        }

        return new self(
            uuidCliente: (string) $datos['uuid_cliente'],
            trabajoUuidCliente: (string) $datos['trabajo_uuid_cliente'],
            secuencia: (int) $datos['secuencia'],
            pilotoId: (int) $datos['piloto_id'],
            auxiliarId: isset($datos['auxiliar_id']) ? (int) $datos['auxiliar_id'] : null,
            dronId: isset($datos['dron_id']) ? (int) $datos['dron_id'] : null,
            hectareasDeclaradas: isset($datos['hectareas_declaradas']) ? (string) $datos['hectareas_declaradas'] : '0',
            hectareaInicialAcumulada: isset($datos['hectarea_inicial_acumulada']) ? (string) $datos['hectarea_inicial_acumulada'] : null,
            inicio: (string) $datos['inicio'],
            fin: isset($datos['fin']) ? (string) $datos['fin'] : null,
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

    /**
     * Forma de un `DECIMAL` no negativo (invariante 6 de CLAUDE.md): entero,
     * float o string numérica, nunca menor que cero. Ausente cuenta como
     * válido — `intentarDesdeArreglo()` lo completa con `'0'`.
     */
    private static function esNumeroNoNegativoOAusente(mixed $valor): bool
    {
        if ($valor === null) {
            return true;
        }

        if (is_int($valor) || is_float($valor)) {
            return $valor >= 0;
        }

        return is_string($valor) && is_numeric($valor) && (float) $valor >= 0;
    }
}
