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
 *
 * `$hashDispositivo` (ADR 0009: "hash SHA-256 calculado en el dispositivo y
 * verificado al subir") es OPCIONAL: la app de campo puede declarar el hash
 * que calculó al capturar, y `RegistrarEvidencia` lo compara contra el que
 * el servidor recalcula sobre el contenido recibido — detecta corrupción en
 * tránsito. El hash que queda persistido en `ope_evidencias.hash` es
 * SIEMPRE el que calcula el servidor (nunca se confía ciegamente en el
 * valor declarado); este campo solo sirve para la comparación, no para
 * sustituir ese cálculo.
 *
 * `$uuidCliente` acá se valida sin `/` a diferencia del resto de DTOs de
 * `Operaciones/Contratos/*` (que solo exigen "string no vacío"): en el resto
 * del motor de sync este valor solo se usa como columna parametrizada, pero
 * `RegistrarEvidencia` lo interpola como segmento de una clave de storage S3
 * — un `/` produciría una ruta con más niveles de los previstos. No es una
 * validación de formato UUID completa (mismo criterio laxo que el resto del
 * módulo), solo bloquea el único caracter que rompería esa ruta.
 */
final readonly class RegistroEvidencia
{
    private function __construct(
        public string $uuidCliente,
        public TipoEvidencia $tipo,
        public string $fecha,
        public ?string $hashDispositivo,
    ) {}

    /** @param  array<string, mixed>  $datos */
    public static function intentarDesdeArreglo(array $datos): ?self
    {
        if (! self::esStringNoVacio($datos['uuid_cliente'] ?? null)
            || str_contains($datos['uuid_cliente'], '/')
            || ! is_string($datos['tipo'] ?? null)
            || TipoEvidencia::tryFrom($datos['tipo']) === null
            || ! self::esStringNoVacio($datos['fecha'] ?? null)
            || ! self::esStringOAusente($datos['hash_dispositivo'] ?? null)
        ) {
            return null;
        }

        return new self(
            uuidCliente: (string) $datos['uuid_cliente'],
            tipo: TipoEvidencia::from($datos['tipo']),
            fecha: (string) $datos['fecha'],
            hashDispositivo: self::esStringNoVacio($datos['hash_dispositivo'] ?? null) ? (string) $datos['hash_dispositivo'] : null,
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
}
