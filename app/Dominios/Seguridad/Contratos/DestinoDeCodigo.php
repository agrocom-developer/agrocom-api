<?php

namespace App\Dominios\Seguridad\Contratos;

/**
 * A quién y adónde se envía un código: el `valor` es el teléfono E.164 (WhatsApp y SMS) o el correo. Solo viaja del
 * caso de uso al adaptador de envío; nunca se guarda ni se escribe en logs.
 */
final readonly class DestinoDeCodigo
{
    public function __construct(
        public CanalDeCodigo $canal,
        public string $valor,
        public string $nombre,
    ) {}
}
