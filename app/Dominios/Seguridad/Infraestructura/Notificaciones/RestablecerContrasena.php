<?php

namespace App\Dominios\Seguridad\Infraestructura\Notificaciones;

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Correo de "recuperar acceso" (tarea 66; ADR 0004, ampliación 9/9/2026),
 * en español — nunca `Mail::raw` desde un controlador, para que el texto
 * viva en un solo lugar y se pueda testear con `Notification::fake()`.
 *
 * Recibe la URL YA armada (`SecUsuarioInterno`/`SecUsuarioCliente` deciden
 * cuál según su guard) — esta clase es agnóstica de guard a propósito, así
 * no necesita saber si el destinatario es una cuenta interna o de portal.
 */
final class RestablecerContrasena extends Notification
{
    use Queueable;

    public function __construct(private readonly string $url) {}

    /** @return list<string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(SecUser $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('seguridad.recuperar.correo_asunto'))
            ->greeting(__('seguridad.recuperar.correo_saludo', ['nombre' => $notifiable->name]))
            ->line(__('seguridad.recuperar.correo_cuerpo'))
            ->action(__('seguridad.recuperar.correo_boton'), $this->url)
            ->line(__('seguridad.recuperar.correo_expiracion'))
            ->line(__('seguridad.recuperar.correo_ignorar'))
            ->salutation(__('seguridad.recuperar.correo_despedida'));
    }
}
