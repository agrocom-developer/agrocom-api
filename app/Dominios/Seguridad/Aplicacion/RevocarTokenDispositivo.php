<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecTokenDispositivo;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;

/**
 * Revoca el token de un dispositivo (HU-03, CA "revocable desde el panel").
 *
 * Revocar es un borrado lógico: `deleted_at` registra cuándo y `updated_by`
 * quién, y {@see SecTokenDispositivo::findToken()} deja de encontrarlo por el
 * global scope de `SoftDeletes` — el dispositivo pierde el acceso en el
 * request siguiente, sin esperar a ninguna caducidad. La fila queda para la
 * auditoría (ADR 0007); nunca se borra físico.
 *
 * **La autorización no vive acá, y es a propósito.** Los dos llamadores no
 * comparten la regla: el panel exige el permiso
 * `seguridad.dispositivo.revocar` evaluado contra el ROL ACTIVO de la sesión
 * (estado de sesión que este caso de uso no conoce, mismo criterio que el
 * `UsuariosController` del panel), mientras que la app de campo solo puede
 * cerrar la sesión del dispositivo desde el que está llamando — nunca la de
 * otro. Cada adaptador resuelve su guarda; lo que se comparte, y por eso está
 * acá, es el efecto.
 */
final class RevocarTokenDispositivo
{
    /**
     * @param  SecUser  $actor  Quién revoca (queda en `updated_by`): el usuario
     *                          del panel, o el propio dueño del dispositivo
     *                          cuando cierra sesión desde la app.
     */
    public function ejecutar(SecTokenDispositivo $token, SecUser $actor): void
    {
        if ($token->trashed()) {
            // Revocar dos veces no es un error (dos operadores del panel
            // pueden apretar el botón sobre la misma fila), pero tampoco
            // debe reescribir quién lo revocó primero.
            return;
        }

        $token->updated_by = $actor->id;
        $token->save();
        $token->delete();
    }
}
