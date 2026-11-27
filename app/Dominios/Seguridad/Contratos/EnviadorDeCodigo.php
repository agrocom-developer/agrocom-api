<?php

namespace App\Dominios\Seguridad\Contratos;

use App\Dominios\Seguridad\Dominio\Excepciones\CanalNoDisponible;

/**
 * Envía un código de un solo uso por un canal (ADR 0046, punto 29). El dominio solo conoce esta interfaz: el correo sale
 * por `Notification`, y WhatsApp y SMS por el adaptador que el dueño enchufa en `config/verificacion.php`
 * (`canales.whatsapp.adaptador`, `canales.sms.adaptador`), con la función de envío que ya usa el panel.
 */
interface EnviadorDeCodigo
{
    /** Si el canal puede enviar ahora (el adaptador existe y el correo está configurado). */
    public function disponible(CanalDeCodigo $canal): bool;

    /**
     * @throws CanalNoDisponible si el canal no puede enviar.
     */
    public function enviar(DestinoDeCodigo $destino, string $codigo, PropositoDeCodigo $proposito): void;
}
