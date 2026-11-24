<?php

namespace App\Dominios\Notificaciones\Contratos;

/**
 * El envío de un aviso al sistema operativo (FCM, APNs). El proveedor de push
 * todavía no está configurado: la implementación vigente no envía y el aviso
 * vive en `GET /api/notificaciones`. Conectar un proveedor es reemplazar el
 * enlace de este contrato; nada más cambia.
 */
interface EnviadorDePush
{
    /**
     * @param  list<string>  $tokens  tokens activos de la cuenta destinataria
     * @param  array<string, scalar|null>  $datos  `tipo`, `recurso_tipo` y `recurso_id` del aviso; nunca datos personales
     */
    public function enviar(array $tokens, string $tipo, array $datos): void;
}
