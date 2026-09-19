<?php

namespace App\Dominios\Operaciones\Dominio;

use Brick\Math\BigDecimal;

/**
 * Regla de edición de una orden de aplicación (ADR 0022, adenda del 19/9/2026).
 * Hasta entonces solo se editaba una orden `emitida`; pero el dato se puede
 * cargar mal (tipo de insumo, categoría, dosis, fecha…) y la única salida era
 * cancelar — que consume una aplicación del contrato o falsea la causa. Ahora:
 * - Se corrige una orden abierta: `emitida`, `vigente` o `pausada`. Una cerrada
 *   (`consumida`, `cancelada`, `vencida`) ya es historia y no se toca.
 * - Sobre una orden ya publicada (no `emitida`) la corrección exige un motivo,
 *   que queda en la orden y en la bitácora con los valores antes y después.
 * - El insumo —categoría, y con ella su tipo, y la dosis— deja de poder
 *   cambiarse en cuanto la orden tiene trabajos: ya hay equipos operando (y, en
 *   líquidos, Ph y calda cargados) sobre esos datos. Si hay un error ahí, se
 *   cancela la orden y se emite otra.
 *
 * Reglas puras, sin Eloquent (verificado por `tests/Unit/ArquitecturaModulosTest`).
 * Las lee quien aplica la edición (`Aplicacion/ActualizarOrden`) y quien la
 * ofrece (el detalle, el listado y la ficha), para que lo que la pantalla
 * anuncia y lo que el servidor exige nunca se contradigan. El estado no se toca
 * acá: editar no es una transición (invariante 7).
 */
final class PoliticaEdicionOrden
{
    /** Las tres claves que forman «el insumo»: categoría (con su tipo) y las dos dosis posibles. */
    private const array CLAVES_INSUMO = ['categoria_insumo_id', 'kilos_por_vuelo', 'litros_ha'];

    /** ¿La orden admite corregir sus datos en este estado? Las abiertas, sí. */
    public static function admiteEdicion(EstadoOrdenAplicacion $estado): bool
    {
        return $estado->estaAbierta();
    }

    /** ¿La corrección pide motivo? Sí en una orden ya publicada; no mientras sigue `emitida`. */
    public static function exigeMotivo(EstadoOrdenAplicacion $estado): bool
    {
        return self::admiteEdicion($estado) && $estado !== EstadoOrdenAplicacion::Emitida;
    }

    /**
     * ¿El insumo queda bloqueado? Cuando la orden ya tiene al menos un trabajo.
     */
    public static function bloqueaInsumo(bool $tieneTrabajos): bool
    {
        return $tieneTrabajos;
    }

    /**
     * ¿Los datos nuevos cambian el insumo respecto de los actuales? Solo mira las
     * claves de insumo que vienen en `$nuevos` (las que no vienen, no se tocan).
     * Las dosis se comparan como decimales — «10» y «10.00» son lo mismo — y una
     * dosis nula solo es igual a otra nula.
     *
     * @param  array<string, mixed>  $actual  categoria_insumo_id, kilos_por_vuelo y litros_ha vigentes.
     * @param  array<string, mixed>  $nuevos  los datos que se quieren guardar.
     */
    public static function cambiaInsumo(array $actual, array $nuevos): bool
    {
        foreach (self::CLAVES_INSUMO as $clave) {
            if (! array_key_exists($clave, $nuevos)) {
                continue;
            }

            if (! self::iguales($clave, $actual[$clave] ?? null, $nuevos[$clave])) {
                return true;
            }
        }

        return false;
    }

    private static function iguales(string $clave, mixed $actual, mixed $nuevo): bool
    {
        if ($actual === null || $actual === '' || $nuevo === null || $nuevo === '') {
            return ($actual === null || $actual === '') && ($nuevo === null || $nuevo === '');
        }

        return $clave === 'categoria_insumo_id'
            ? (int) $actual === (int) $nuevo
            : BigDecimal::of((string) $actual)->isEqualTo(BigDecimal::of((string) $nuevo));
    }
}
