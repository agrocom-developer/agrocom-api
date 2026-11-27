<?php

namespace App\Dominios\Seguridad\Infraestructura\Notificaciones;

use App\Dominios\Seguridad\Contratos\PropositoDeCodigo;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Correo con el código de un solo uso (ADR 0046, punto 27), en español y por `Notification` (nunca `Mail::raw`).
 * El código viaja en el cuerpo del correo y en ningún otro lado: ni logs ni bitácora.
 */
final class CodigoDeVerificacion extends Notification
{
    public function __construct(
        private readonly string $nombre,
        private readonly string $codigo,
        private readonly PropositoDeCodigo $proposito,
        private readonly int $vigenciaMinutos,
    ) {}

    /** @return list<string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('seguridad.codigos.correo_asunto'))
            ->greeting(__('seguridad.codigos.correo_saludo', ['nombre' => $this->nombre]))
            ->line(__('seguridad.codigos.correo_motivo.'.$this->proposito->value))
            ->line(__('seguridad.codigos.correo_codigo', ['codigo' => $this->codigo]))
            ->line(__('seguridad.codigos.correo_vigencia', ['minutos' => $this->vigenciaMinutos]))
            ->line(__('seguridad.codigos.correo_ignorar'))
            ->salutation(__('seguridad.codigos.correo_despedida'));
    }
}
