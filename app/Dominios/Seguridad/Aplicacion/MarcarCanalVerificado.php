<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Contratos\CanalDeCodigo;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;

/**
 * Confirmar un código por un canal prueba la posesión de ese teléfono o correo (ADR 0046, puntos 27 y 29): marca
 * `telefono_verificado_en` (WhatsApp o SMS) o `email_verificado_en` (correo) si todavía no estaban. No guarda la cuenta:
 * la guarda quien llama, junto con su propio cambio.
 */
final class MarcarCanalVerificado
{
    public function ejecutar(SecUser $usuario, CanalDeCodigo $canal): void
    {
        if ($canal->esDeTelefono()) {
            $usuario->telefono_verificado_en ??= now();
        } else {
            $usuario->email_verificado_en ??= now();
        }

        $usuario->save();
    }
}
