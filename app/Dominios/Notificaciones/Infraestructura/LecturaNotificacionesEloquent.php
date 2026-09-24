<?php

namespace App\Dominios\Notificaciones\Infraestructura;

use App\Dominios\Notificaciones\Contratos\AlertasDeCuenta;
use App\Dominios\Notificaciones\Contratos\LecturaNotificaciones;
use App\Dominios\Notificaciones\Contratos\NotificacionPanel;
use App\Dominios\Notificaciones\Infraestructura\Eloquent\AlertaVista;
use App\Dominios\Notificaciones\Infraestructura\Eloquent\Notificacion;

/**
 * Implementación Eloquent de {@see LecturaNotificaciones}. Vive fuera de
 * `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * `LecturaUsuarioDePersonaEloquent`: esa subcarpeta es solo para modelos.
 *
 * Toda consulta parte de `Notificacion::deUsuario($usuarioId)`: no existe
 * ninguna que mire avisos de otra cuenta.
 */
final class LecturaNotificacionesEloquent implements LecturaNotificaciones
{
    public function recientesDe(int $usuarioId, int $limite): array
    {
        if ($limite <= 0) {
            return [];
        }

        $sinLeer = Notificacion::query()
            ->deUsuario($usuarioId)
            ->sinLeer()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limite)
            ->get();

        $cupoRestante = $limite - $sinLeer->count();

        $leidas = $cupoRestante > 0
            ? Notificacion::query()
                ->deUsuario($usuarioId)
                ->whereNotNull('leida_en')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit($cupoRestante)
                ->get()
            : collect();

        return $sinLeer
            ->concat($leidas)
            ->sort(static fn (Notificacion $a, Notificacion $b): int => [$b->created_at, $b->id] <=> [$a->created_at, $a->id])
            ->map(static fn (Notificacion $notificacion): NotificacionPanel => new NotificacionPanel(
                id: $notificacion->id,
                icono: $notificacion->tipo->icono(),
                titulo: __($notificacion->tipo->claveDeTitulo(), $notificacion->parametros ?? []),
                creadaEn: $notificacion->created_at?->toIso8601String() ?? '',
                leida: $notificacion->leida_en !== null,
            ))
            ->values()
            ->all();
    }

    public function alertasDeCuenta(int $usuarioId): AlertasDeCuenta
    {
        $filas = AlertaVista::query()->deUsuario($usuarioId)->get(['alerta_id', 'leida_en', 'limpiada_en']);

        return new AlertasDeCuenta(
            leidas: $filas->whereNotNull('leida_en')->pluck('alerta_id')->map(intval(...))->values()->all(),
            limpiadas: $filas->whereNotNull('limpiada_en')->pluck('alerta_id')->map(intval(...))->values()->all(),
        );
    }
}
