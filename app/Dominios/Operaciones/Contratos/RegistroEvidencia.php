<?php

namespace App\Dominios\Operaciones\Contratos;

use App\Dominios\Operaciones\Dominio\TipoEvidencia;

/**
 * DTO primitivo de entrada de `POST /api/evidencias` (TE-07 parte servidor,
 * tarea 19). Mismo patrón que `CierreTrabajo`/`CierreSesion`: constructor
 * privado, solo `intentarDesdeArreglo()` construye, devuelve `null` ante un
 * dato faltante o mal tipado.
 *
 * A diferencia de esos DTO, este NO viaja dentro del lote de `POST
 * /api/sync` (ver el docblock de la migración de `ope_evidencias` para el
 * porqué) y por eso no lleva el archivo en sí: el archivo es un
 * `UploadedFile` de multipart, no un dato primitivo serializable en un
 * arreglo — lo maneja `RegistrarEvidencia` por separado, como parámetro
 * propio.
 */
final readonly class RegistroEvidencia
{
    private function __construct(
        public string $uuidCliente,
        public TipoEvidencia $tipo,
        public string $fecha,
    ) {}

    /** @param  array<string, mixed>  $datos */
    public static function intentarDesdeArreglo(array $datos): ?self
    {
        if (! self::esStringNoVacio($datos['uuid_cliente'] ?? null)
            || ! is_string($datos['tipo'] ?? null)
            || TipoEvidencia::tryFrom($datos['tipo']) === null
            || ! self::esStringNoVacio($datos['fecha'] ?? null)
        ) {
            return null;
        }

        return new self(
            uuidCliente: (string) $datos['uuid_cliente'],
            tipo: TipoEvidencia::from($datos['tipo']),
            fecha: (string) $datos['fecha'],
        );
    }

    private static function esStringNoVacio(mixed $valor): bool
    {
        return is_string($valor) && $valor !== '';
    }
}
