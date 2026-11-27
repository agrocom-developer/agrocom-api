<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Presentacion;

use App\Dominios\Seguridad\Aplicacion\CatalogoDePermisos;

/**
 * Resumen de lo que un rol tiene, agrupado por módulo del menú y con el color de
 * cada módulo ({@see ColorDeModulo}): lo que pinta la ficha del rol. Parte del
 * árbol que arma {@see CatalogoDePermisos::arbolDeConcesiones()}
 * y de los ids que el rol tiene; no consulta nada.
 */
final class ResumenDePermisosDeRol
{
    /**
     * @param  array{modulos: list<array<string, mixed>>, sueltos: list<array<string, mixed>>}  $arbol
     * @param  list<int>  $otorgados
     * @return list<array{
     *     clave: string, icono: string, tono: string, activos: int, total: int,
     *     pantallas: list<array{clave: string, activa: bool, accionesActivas: int, accionesTotal: int}>,
     *     sueltos: list<string>
     * }>
     */
    public static function de(array $arbol, array $otorgados): array
    {
        $claves = array_column($arbol['modulos'], 'clave');

        if ($arbol['sueltos'] !== []) {
            $claves[] = ColorDeModulo::CLAVE_SUELTOS;
        }

        $tonos = ColorDeModulo::asignar($claves);
        $tiene = static fn (int $id): bool => in_array($id, $otorgados, true);
        $resumen = [];

        foreach ($arbol['modulos'] as $modulo) {
            $activos = 0;
            $total = 0;
            $pantallas = [];

            foreach ($modulo['pantallas'] as $pantalla) {
                $accionesActivas = count(array_filter($pantalla['acciones'], static fn (array $accion): bool => $tiene($accion['id'])));
                $activa = $tiene($pantalla['id']);

                $activos += ($activa ? 1 : 0) + $accionesActivas;
                $total += 1 + count($pantalla['acciones']);
                $pantallas[] = [
                    'clave' => $pantalla['clave'],
                    'activa' => $activa,
                    'accionesActivas' => $accionesActivas,
                    'accionesTotal' => count($pantalla['acciones']),
                ];
            }

            $resumen[] = [
                'clave' => $modulo['clave'],
                'icono' => $modulo['icono'],
                'tono' => $tonos[$modulo['clave']],
                'activos' => $activos,
                'total' => $total,
                'pantallas' => $pantallas,
                'sueltos' => [],
            ];
        }

        if ($arbol['sueltos'] !== []) {
            $activos = array_values(array_filter($arbol['sueltos'], static fn (array $suelto): bool => $tiene($suelto['id'])));

            $resumen[] = [
                'clave' => ColorDeModulo::CLAVE_SUELTOS,
                'icono' => 'phone_android',
                'tono' => $tonos[ColorDeModulo::CLAVE_SUELTOS],
                'activos' => count($activos),
                'total' => count($arbol['sueltos']),
                'pantallas' => [],
                'sueltos' => array_map(static fn (array $suelto): string => $suelto['codigo'], $activos),
            ];
        }

        return $resumen;
    }
}
