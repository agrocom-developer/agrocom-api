<?php

namespace App\Dominios\Seguridad\Infraestructura\Codigos;

use App\Dominios\Compartido\Aplicacion\ConfiguracionDeCorreo;
use App\Dominios\Seguridad\Contratos\CanalDeCodigo;
use App\Dominios\Seguridad\Contratos\DestinoDeCodigo;
use App\Dominios\Seguridad\Contratos\EnviadorDeCodigo;
use App\Dominios\Seguridad\Contratos\PropositoDeCodigo;
use App\Dominios\Seguridad\Dominio\Excepciones\CanalNoDisponible;
use App\Dominios\Seguridad\Infraestructura\Notificaciones\CodigoDeVerificacion;
use Illuminate\Support\Facades\Notification;

/**
 * El {@see EnviadorDeCodigo} de la aplicación: enruta cada canal a su salida (ADR 0046, punto 29).
 *
 * - `correo`: `Notification` sobre el correo de respaldo, con el mailer configurado.
 * - `whatsapp` y `sms`: el adaptador que `config/verificacion.php` declara para el canal, otra implementación de
 *   {@see EnviadorDeCodigo} que enchufa la función de envío que ya usa el panel. Sin adaptador, el canal no está
 *   disponible. En modo de desarrollo no se envía nada y el canal queda disponible (el código es fijo).
 */
final class EnviadorEnrutado implements EnviadorDeCodigo
{
    public function __construct(private readonly ConfiguracionDeCorreo $correo) {}

    public function disponible(CanalDeCodigo $canal): bool
    {
        if ($canal === CanalDeCodigo::Correo) {
            return $this->correo->estaConfigurado();
        }

        return $this->enModoDeDesarrollo() || $this->adaptador($canal) !== null;
    }

    public function enviar(DestinoDeCodigo $destino, string $codigo, PropositoDeCodigo $proposito): void
    {
        if ($destino->canal === CanalDeCodigo::Correo) {
            if ($this->enModoDeDesarrollo() && config('mail.default') === 'array') {
                return;
            }

            Notification::route('mail', $destino->valor)->notify(
                new CodigoDeVerificacion($destino->nombre, $codigo, $proposito, (int) config('verificacion.codigo.vigencia_minutos', 10)),
            );

            return;
        }

        if ($this->enModoDeDesarrollo()) {
            return;
        }

        ($this->adaptador($destino->canal) ?? throw new CanalNoDisponible($destino->canal))->enviar($destino, $codigo, $proposito);
    }

    private function enModoDeDesarrollo(): bool
    {
        return (bool) config('verificacion.modo_desarrollo') && in_array(config('app.env'), ['local', 'testing'], true);
    }

    private function adaptador(CanalDeCodigo $canal): ?EnviadorDeCodigo
    {
        $clase = config('verificacion.canales.'.$canal->value.'.adaptador');

        if (! is_string($clase) || $clase === '' || ! class_exists($clase)) {
            return null;
        }

        $adaptador = app($clase);

        return $adaptador instanceof EnviadorDeCodigo ? $adaptador : null;
    }
}
