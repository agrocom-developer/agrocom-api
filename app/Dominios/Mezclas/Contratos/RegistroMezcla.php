<?php

namespace App\Dominios\Mezclas\Contratos;

/**
 * DTO primitivo de entrada del contrato de escritura de `Mezclas` (ADR 0003,
 * regla 2; espec §7, HU-78, tarea 94, revierte CR-01): la forma de un
 * registro `mezcla` en el lote de `POST /api/sync`.
 *
 * Referencia el TRABAJO por `uuid_cliente` (mismo criterio que
 * `Operaciones\Contratos\RegistroRecepcionCaldo`, no la sesión): "al crear
 * una aplicación" (nota del dueño, 13/9/2026) es cuando se abre el trabajo,
 * todavía puede no existir ninguna sesión.
 *
 * El constructor es privado a propósito, mismo motivo que `AperturaTrabajo`
 * (`Operaciones\Contratos`): la única forma de obtener una instancia es
 * `intentarDesdeArreglo()`, que devuelve `null` ante un dato faltante o mal
 * formado en vez de lanzar — un rechazo no frena el resto del lote.
 *
 * `$productos` no puede venir vacío: una `mezcla` sin ningún producto no es
 * un hecho que registrar (el piloto no abre este evento si no cargó nada). Es
 * el primer registro del motor de sync que trae un arreglo anidado en vez de
 * campos planos — cada elemento se valida con `ItemMezcla::intentarDesdeArreglo()`;
 * si cualquiera es inválido, el registro completo se rechaza (no se aplican
 * "los productos válidos" de una mezcla parcialmente mal formada).
 */
final readonly class RegistroMezcla
{
    /** @param  list<ItemMezcla>  $productos */
    private function __construct(
        public string $uuidCliente,
        public string $trabajoUuidCliente,
        public string $hora,
        public array $productos,
    ) {}

    /** @param  array<string, mixed>  $datos */
    public static function intentarDesdeArreglo(array $datos): ?self
    {
        if (! self::esStringNoVacio($datos['uuid_cliente'] ?? null)
            || ! self::esStringNoVacio($datos['trabajo_uuid_cliente'] ?? null)
            || ! self::esStringNoVacio($datos['hora'] ?? null)
            || ! is_array($datos['productos'] ?? null)
            || $datos['productos'] === []
        ) {
            return null;
        }

        $productos = [];

        foreach ($datos['productos'] as $item) {
            if (! is_array($item)) {
                return null;
            }

            $itemValido = ItemMezcla::intentarDesdeArreglo($item);

            if ($itemValido === null) {
                return null;
            }

            $productos[] = $itemValido;
        }

        return new self(
            uuidCliente: (string) $datos['uuid_cliente'],
            trabajoUuidCliente: (string) $datos['trabajo_uuid_cliente'],
            hora: (string) $datos['hora'],
            productos: $productos,
        );
    }

    private static function esStringNoVacio(mixed $valor): bool
    {
        return is_string($valor) && $valor !== '';
    }
}
