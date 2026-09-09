<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Dominio\ZonaHoraria;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserPreferencia;

/**
 * Fija la zona horaria IANA del usuario la PRIMERA vez, en el login (tarea
 * 63): el formulario manda `Intl.DateTimeFormat().resolvedOptions().timeZone`
 * del navegador en un campo oculto y esto la guarda SOLO si la preferencia
 * todavía está vacía. Un login posterior nunca pisa lo que el usuario ya
 * fijó (a mano desde el selector, o en un login anterior) — para eso está
 * {@see ActualizarPreferenciaUsuario}, que sí pisa siempre porque ahí la
 * acción es un cambio explícito.
 *
 * Silencioso ante un valor ausente o que no es un identificador IANA (el
 * navegador podría no soportar `Intl`, o la request venir de un cliente sin
 * ese campo): el login nunca falla por esto, a diferencia del selector
 * manual, que si rechaza con 422 (ver `PreferenciasController`).
 */
final class FijarZonaHorariaUsuario
{
    public function ejecutarSiVacia(SecUser $usuario, ?string $zonaHoraria): void
    {
        if ($zonaHoraria === null || $zonaHoraria === '' || ! ZonaHoraria::esValida($zonaHoraria)) {
            return;
        }

        $preferencia = SecUserPreferencia::query()->where('user_id', $usuario->id)->first();

        if ($preferencia !== null && $preferencia->zona_horaria !== null) {
            return;
        }

        if ($preferencia === null) {
            $preferencia = new SecUserPreferencia(['user_id' => $usuario->id]);
            $preferencia->created_by = $usuario->id;
        }

        $preferencia->zona_horaria = $zonaHoraria;
        $preferencia->updated_by = $usuario->id;
        $preferencia->save();
    }
}
