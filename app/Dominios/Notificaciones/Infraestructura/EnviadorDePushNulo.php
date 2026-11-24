<?php

namespace App\Dominios\Notificaciones\Infraestructura;

use App\Dominios\Notificaciones\Contratos\EnviadorDePush;

/** Sin proveedor de push configurado: no envía nada. */
final class EnviadorDePushNulo implements EnviadorDePush
{
    public function enviar(array $tokens, string $tipo, array $datos): void {}
}
