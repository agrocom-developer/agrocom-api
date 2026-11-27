<?php

namespace App\Dominios\Compartido\Aplicacion;

use App\Dominios\Compartido\Contratos\LecturaConfiguracion;
use App\Dominios\Compartido\Infraestructura\Eloquent\Configuracion;
use Throwable;

/**
 * El correo saliente efectivo del sistema: lo que el administrador cargó en
 * `/panel/configuracion` (pestaña Correo) manda; sin eso, el `.env` y
 * `config/mail.php` (Mailpit en desarrollo, `array` en pruebas) siguen igual.
 *
 * "Cargado en la base" significa una fila viva de `correo.host` con valor. Mientras
 * no exista, {@see aplicar()} no toca nada: ni el mailer por defecto ni el remitente.
 * Con ella, el SMTP de la base pasa a ser el mailer por defecto; lo que falte en la
 * base (usuario, remitente) cae al respaldo del `.env` por la cascada de
 * {@see LecturaConfiguracion}.
 *
 * La contraseña de aplicación solo pasa de la base (cifrada con `APP_KEY`) a `config()`
 * en memoria del proceso para armar el transporte: nunca se escribe en logs, respuestas
 * ni bitácora.
 */
final class ConfiguracionDeCorreo
{
    public function __construct(private readonly LecturaConfiguracion $lectura) {}

    /** ¿Hay un servidor de correo cargado en la base? */
    public function definidaEnBase(): bool
    {
        try {
            $fila = Configuracion::query()->where('clave', 'correo.host')->first();
        } catch (Throwable) {
            // Antes de migrar no existe la tabla: el sistema sigue con el `.env`.
            return false;
        }

        return $fila !== null && trim((string) $fila->valor) !== '';
    }

    /** ¿Puede el sistema enviar correo de verdad (no solo al log)? */
    public function estaConfigurado(): bool
    {
        if ($this->definidaEnBase()) {
            return true;
        }

        $mailer = (string) config('mail.default');

        if ($mailer === 'log' || $mailer === '') {
            return false;
        }

        return $mailer !== 'smtp' || trim((string) config('mail.mailers.smtp.host')) !== '';
    }

    /** Vuelca la configuración de la base sobre `config('mail.*')` si existe; si no, no hace nada. */
    public function aplicar(): void
    {
        if (! $this->definidaEnBase()) {
            return;
        }

        $cifrado = $this->lectura->valor('correo.cifrado') ?? 'tls';
        $contrasena = (string) $this->lectura->valor('correo.password');

        if ($this->lectura->valor('correo.proveedor') === 'gmail') {
            // Google muestra la contraseña de aplicación en cuatro grupos separados por espacios.
            $contrasena = (string) preg_replace('/\s+/', '', $contrasena);
        }

        $puerto = (int) ($this->lectura->valor('correo.puerto') ?? 587);

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.url' => null,
            'mail.mailers.smtp.host' => trim((string) $this->lectura->valor('correo.host')),
            'mail.mailers.smtp.port' => $puerto > 0 ? $puerto : 587,
            'mail.mailers.smtp.scheme' => $cifrado === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.auto_tls' => $cifrado !== 'ninguno',
            'mail.mailers.smtp.require_tls' => $cifrado === 'tls',
            'mail.mailers.smtp.username' => $this->lectura->valor('correo.usuario') ?: null,
            'mail.mailers.smtp.password' => $contrasena !== '' ? $contrasena : null,
            'mail.mailers.smtp.timeout' => 15,
        ]);

        $remitente = trim((string) $this->lectura->valor('correo.remitente'));

        if ($remitente !== '') {
            config(['mail.from.address' => $remitente]);
        }

        config(['mail.from.name' => trim((string) $this->lectura->valor('correo.remitente_nombre')) ?: 'DronAgro']);
    }
}
