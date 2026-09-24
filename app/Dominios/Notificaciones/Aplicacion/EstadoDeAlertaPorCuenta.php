<?php

namespace App\Dominios\Notificaciones\Aplicacion;

use App\Dominios\Notificaciones\Infraestructura\Eloquent\AlertaVista;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Escribe lo que UNA cuenta hizo con una alerta técnica en la campana: abrirla
 * (leída) o limpiarla. Es el único lugar que toca `ntf_alertas_vistas`, para
 * que los tres casos de uso que lo necesitan ({@see AbrirAlerta},
 * {@see MarcarTodasLeidas}, {@see LimpiarNotificaciones}) no repitan la
 * carrera de «buscar o crear».
 *
 * Idempotente: repetir la acción sobre lo ya hecho no cambia nada ni escribe.
 * Se guarda con `save()` fila por fila, no con un `update()` de query builder,
 * para que cada cambio pase por la autoría y la bitácora (invariantes 8 y 9).
 */
final class EstadoDeAlertaPorCuenta
{
    /** @return bool si la alerta estaba sin leer para esta cuenta */
    public function marcarLeida(int $usuarioId, int $alertaId): bool
    {
        return $this->aplicar($usuarioId, $alertaId, static function (AlertaVista $vista): bool {
            if ($vista->leida_en !== null) {
                return false;
            }

            $vista->leida_en = CarbonImmutable::now();

            return true;
        });
    }

    /**
     * Sale de la campana de esta cuenta (sigue en su pantalla). Limpiar es
     * también haberla visto: queda leída.
     *
     * @return bool si había algo que limpiar
     */
    public function limpiar(int $usuarioId, int $alertaId): bool
    {
        return $this->aplicar($usuarioId, $alertaId, static function (AlertaVista $vista): bool {
            if ($vista->limpiada_en !== null) {
                return false;
            }

            $ahora = CarbonImmutable::now();
            $vista->leida_en ??= $ahora;
            $vista->limpiada_en = $ahora;

            return true;
        });
    }

    /**
     * @param  Closure(AlertaVista): bool  $cambio  modifica la fila y dice si cambió algo.
     */
    private function aplicar(int $usuarioId, int $alertaId, Closure $cambio): bool
    {
        // Dos pedidos de la misma cuenta pueden cruzarse al crear la fila: el segundo choca con el
        // índice único y reintenta sobre la fila que dejó el primero.
        for ($intento = 1; ; $intento++) {
            $vista = AlertaVista::query()->deUsuario($usuarioId)->where('alerta_id', $alertaId)->first()
                ?? new AlertaVista(['usuario_id' => $usuarioId, 'alerta_id' => $alertaId]);

            if (! $cambio($vista)) {
                return false;
            }

            try {
                $vista->save();

                return true;
            } catch (UniqueConstraintViolationException $excepcion) {
                if ($intento >= 2) {
                    throw $excepcion;
                }
            }
        }
    }
}
