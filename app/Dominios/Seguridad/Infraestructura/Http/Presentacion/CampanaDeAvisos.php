<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Presentacion;

use Illuminate\Support\Carbon;

/**
 * Qué avisos entran en la campana del header y en qué orden (tarea 141, ADR
 * 0025 punto 7). Pura: recibe candidatas ya armadas —las del motor de
 * `Notificaciones` y las alertas técnicas de `Operaciones`— y devuelve la
 * lista final, sin tocar la base.
 *
 * Regla: entran PRIMERO todos los no leídos (hasta el tope) y se completa con
 * los leídos más recientes; el resultado va del más nuevo al más viejo. Sin
 * esto, un aviso sin leer viejo quedaría fuera de la lista por culpa de
 * leídos más nuevos, y el badge de la campana —que cuenta lo no leído de la
 * lista que recibe— mentiría hacia abajo. Con la prioridad, el badge es exacto
 * hasta «9+».
 */
final class CampanaDeAvisos
{
    /**
     * @param  list<array{id: int|null, alerta_id?: int|null, icon: string, title: string, momento: Carbon, unread: bool, href: string}>  $candidatas
     * @return list<array{id: int|null, alerta_id: int|null, icon: string, title: string, time: string, unread: bool, href: string}>
     */
    public static function elegir(array $candidatas, int $maximo): array
    {
        $sinLeer = array_values(array_filter($candidatas, static fn (array $aviso): bool => $aviso['unread']));
        $leidas = array_values(array_filter($candidatas, static fn (array $aviso): bool => ! $aviso['unread']));

        usort($sinLeer, self::masNuevoPrimero(...));
        usort($leidas, self::masNuevoPrimero(...));

        $elegidas = [
            ...array_slice($sinLeer, 0, $maximo),
            ...array_slice($leidas, 0, max(0, $maximo - count($sinLeer))),
        ];

        usort($elegidas, self::masNuevoPrimero(...));

        return array_map(static fn (array $aviso): array => [
            'id' => $aviso['id'],
            'alerta_id' => $aviso['alerta_id'] ?? null,
            'icon' => $aviso['icon'],
            'title' => $aviso['title'],
            'time' => $aviso['momento']->diffForHumans(),
            'unread' => $aviso['unread'],
            'href' => $aviso['href'],
        ], $elegidas);
    }

    /**
     * @param  array{momento: Carbon}  $a
     * @param  array{momento: Carbon}  $b
     */
    private static function masNuevoPrimero(array $a, array $b): int
    {
        return $b['momento'] <=> $a['momento'];
    }
}
