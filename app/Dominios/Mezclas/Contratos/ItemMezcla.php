<?php

namespace App\Dominios\Mezclas\Contratos;

/**
 * Un renglón de producto cargado en el caldo dentro de un registro `mezcla`
 * del lote de `POST /api/sync` (espec §7, HU-78, tarea 94, revierte CR-01).
 *
 * `$producto` viaja como NOMBRE de texto libre, no id de catálogo: a
 * diferencia de `orden_id`/`lote_id`/`piloto_id` (que resuelven contra el
 * pull de `GET /api/sync/catalogo`), no hay un catálogo de productos que la
 * app de campo baje antes — el piloto transcribe lo que lee en el envase, y
 * el servidor lo normaliza contra `mez_productos` al aplicar (ver
 * `Infraestructura\EscrituraMezclasEloquent`). Mismo deslinde que el resto de
 * HU-78: Agrocom registra qué se cargó, nunca valida ni calcula (§7.1 sigue
 * vigente).
 */
final readonly class ItemMezcla
{
    /** Catálogo cerrado de unidades — ver docblock de `create_mez_mezcla_detalles_table`. */
    private const array UNIDADES = ['l', 'ml', 'kg', 'g'];

    private function __construct(
        public string $producto,
        public string $cantidad,
        public string $unidad,
    ) {}

    /** @param  array<string, mixed>  $datos */
    public static function intentarDesdeArreglo(array $datos): ?self
    {
        if (! self::esStringNoVacio($datos['producto'] ?? null)
            || ! self::esNumeroPositivo($datos['cantidad'] ?? null)
            || ! self::esUnidadValida($datos['unidad'] ?? null)
        ) {
            return null;
        }

        return new self(
            producto: trim((string) $datos['producto']),
            cantidad: (string) $datos['cantidad'],
            unidad: (string) $datos['unidad'],
        );
    }

    private static function esStringNoVacio(mixed $valor): bool
    {
        return is_string($valor) && trim($valor) !== '';
    }

    /** Forma de un `DECIMAL` positivo (invariante 6 de CLAUDE.md): cero no es "se cargó algo". */
    private static function esNumeroPositivo(mixed $valor): bool
    {
        if (is_int($valor) || is_float($valor)) {
            return $valor > 0;
        }

        return is_string($valor) && $valor !== '' && is_numeric($valor) && (float) $valor > 0;
    }

    private static function esUnidadValida(mixed $valor): bool
    {
        return is_string($valor) && in_array($valor, self::UNIDADES, true);
    }
}
