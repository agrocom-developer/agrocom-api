<?php

namespace App\Dominios\Compartido\Aplicacion;

use App\Dominios\Compartido\Infraestructura\Notificaciones\CorreoDePrueba;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Envía un correo de prueba con la configuración efectiva y devuelve un resultado
 * legible: `ok` o una categoría de fallo (`autenticacion`, `conexion`, `tiempo`,
 * `cifrado`, `desconocido`) que la pantalla traduce.
 *
 * Nunca devuelve ni registra el mensaje de la excepción: el diálogo SMTP puede
 * incluir el usuario o el servidor, y la contraseña no debe aparecer en ningún
 * lugar. En el log queda solo la categoría y la clase de la excepción.
 */
final class EnviarCorreoDePrueba
{
    public function __construct(private readonly ConfiguracionDeCorreo $correo) {}

    /** @return array{ok: bool, categoria: string} */
    public function ejecutar(string $destino): array
    {
        if (! $this->correo->estaConfigurado()) {
            return ['ok' => false, 'categoria' => 'sin_configurar'];
        }

        try {
            // Releer la base y descartar el transporte ya armado en este proceso.
            $this->correo->aplicar();
            Mail::purge();

            Notification::route('mail', $destino)->notifyNow(new CorreoDePrueba);
        } catch (Throwable $e) {
            $categoria = $this->clasificar($e);

            Log::warning('Falló el correo de prueba.', ['categoria' => $categoria, 'excepcion' => $e::class]);

            return ['ok' => false, 'categoria' => $categoria];
        }

        return ['ok' => true, 'categoria' => 'ok'];
    }

    private function clasificar(Throwable $e): string
    {
        $texto = mb_strtolower($e->getMessage());

        return match (true) {
            str_contains($texto, '535') || str_contains($texto, 'authenticat') || str_contains($texto, 'username and password') || str_contains($texto, 'credentials') => 'autenticacion',
            str_contains($texto, 'timed out') || str_contains($texto, 'timeout') => 'tiempo',
            str_contains($texto, 'tls') || str_contains($texto, 'ssl') || str_contains($texto, 'certificate') => 'cifrado',
            str_contains($texto, 'connection') || str_contains($texto, 'getaddrinfo') || str_contains($texto, 'resolve') => 'conexion',
            default => 'desconocido',
        };
    }
}
