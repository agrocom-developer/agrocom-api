<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * DTO primitivo de entrada del contrato de escritura de `Operaciones` (ADR
 * 0003, regla 2; TE-05): la forma de un registro `trabajo` en el lote de
 * `POST /api/sync` (espec §2.1, punto 3), ya con `orden_id`/`lote_id`
 * resueltos a id de servidor — el cliente los trae del pull de catálogo
 * (`OrdenAplicacionCatalogo`), no hace falta resolverlos por UUID como pasa
 * con la referencia de `sesion` a su `trabajo`.
 *
 * El constructor es privado a propósito: la única forma de obtener una
 * instancia es `intentarDesdeArreglo()`, que valida la forma del dato antes
 * de comprometerse a construirla. Sin esto, un campo faltante o con el tipo
 * equivocado en el payload del cliente terminaría en un `TypeError` de PHP al
 * construir el DTO — una excepción no capturada que tumbaría el request
 * entero, exactamente lo que la espec prohíbe ("un rechazo no frena el resto
 * del lote"). `intentarDesdeArreglo()` devuelve `null` en vez de lanzar, y
 * quien la invoca (`Sincronizacion\Aplicacion\SincronizarLote`) traduce ese
 * `null` en un resultado `rechazado` para ese registro puntual.
 */
final readonly class AperturaTrabajo
{
    private function __construct(
        public string $uuidCliente,
        public int $ordenId,
        public int $loteId,
        public int $nroAplicacion,
        public string $hectareasDeclaradas,
        public string $inicio,
        public ?string $fin,
    ) {}

    /** @param  array<string, mixed>  $datos */
    public static function intentarDesdeArreglo(array $datos): ?self
    {
        if (! self::esStringNoVacio($datos['uuid_cliente'] ?? null)
            || ! self::esEntero($datos['orden_id'] ?? null)
            || ! self::esEntero($datos['lote_id'] ?? null)
            || ! self::esEntero($datos['nro_aplicacion'] ?? null)
            || ! self::esStringNoVacio($datos['inicio'] ?? null)
            || ! self::esStringOAusente($datos['fin'] ?? null)
        ) {
            return null;
        }

        return new self(
            uuidCliente: (string) $datos['uuid_cliente'],
            ordenId: (int) $datos['orden_id'],
            loteId: (int) $datos['lote_id'],
            nroAplicacion: (int) $datos['nro_aplicacion'],
            hectareasDeclaradas: isset($datos['hectareas_declaradas']) ? (string) $datos['hectareas_declaradas'] : '0',
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
}
