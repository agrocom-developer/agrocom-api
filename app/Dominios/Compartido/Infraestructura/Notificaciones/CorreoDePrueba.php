<?php

namespace App\Dominios\Compartido\Infraestructura\Notificaciones;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Correo de prueba de `/panel/configuracion` (pestaña Correo): confirma que el servidor configurado envía. */
final class CorreoDePrueba extends Notification
{
    /** @return list<string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('configuracion.correo_prueba.asunto'))
            ->line(__('configuracion.correo_prueba.cuerpo'))
            ->line(__('configuracion.correo_prueba.nota'))
            ->salutation(__('configuracion.correo_prueba.despedida'));
    }
}
