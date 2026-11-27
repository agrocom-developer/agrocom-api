<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Aplicacion\ConfiguracionDeCorreo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Throwable;

/**
 * `POST /recuperar` (tarea 66; ADR 0004, ampliación 9/9/2026): pide el
 * enlace de restablecimiento. Responde SIEMPRE el mismo mensaje, exista o no
 * la cuenta, esté bloqueada o no — nunca revela qué correos existen en el
 * sistema (invariante de negocio de esta tarea).
 *
 * Solo para cuentas de panel (broker `interno`, ADR 0046): una cuenta de app
 * con ese correo recibe la misma respuesta uniforme y ningún enlace. Las cuentas
 * de app recuperan su acceso desde la app (fase A10).
 *
 * `state: true` en las credenciales del broker: `EloquentUserProvider`
 * agrega un `WHERE` por cada clave que no sea `password`, así que una
 * cuenta bloqueada (`seguridad.usuario.bloquear`) queda fuera de la
 * búsqueda igual que un correo inexistente — mismo mecanismo que ya usa
 * `SesionController` para el login.
 *
 * Si el sistema todavía no tiene correo configurado (ni en la base ni en el `.env`),
 * lo dice igual para todos —no depende de la cuenta— y sin culpar a quien pregunta.
 * Un fallo al enviar (servidor caído, contraseña vencida) también responde el mensaje
 * genérico y queda en el log solo con la clase de la excepción: contar el fallo solo
 * cuando la cuenta existe revelaría qué correos existen.
 *
 * El enlace sale del broker `interno`: un solo uso, vence a los 60 minutos
 * (`config('auth.passwords.interno.expire')`) y pedir otro invalida el anterior.
 *
 * Rate limit por email: `config('auth.passwords.*.throttle')` (60 s, lo
 * aplica el propio broker). Por IP: `throttle:6,1` en la ruta.
 */
final class RecuperarContrasenaController
{
    public function store(Request $request, ConfiguracionDeCorreo $correo): RedirectResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        if (! $correo->estaConfigurado()) {
            return redirect()
                ->route('login.form')
                ->withErrors(['email' => __('seguridad.recuperar.correo_no_configurado')]);
        }

        try {
            Password::broker('interno')->sendResetLink([
                'email' => $datos['email'],
                'state' => true,
            ]);
        } catch (Throwable $e) {
            Log::warning('Falló el envío del enlace para restablecer la contraseña del panel.', ['excepcion' => $e::class]);
        }

        return redirect()
            ->route('login.form')
            ->with('estado', __('seguridad.recuperar.estado_generico'));
    }
}
