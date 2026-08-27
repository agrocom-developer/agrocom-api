<?php

namespace App\Dominios\Seguridad\Dominio;

/**
 * Tipo de cuenta de `sec_user` (HU-01, diseño `modulos-roles` §5). Gobierna
 * el CHECK de exclusión mutua en la base: una cuenta interna enlaza a
 * `persona_id` (nunca `contrato_id`); una cuenta de portal enlaza a
 * `contrato_id` (nunca `persona_id`). El foco de HU-01 es `Interno`; `Cliente`
 * queda declarado para no bloquear el portal cuando llegue su HU.
 */
enum TipoUsuario: string
{
    case Interno = 'interno';
    case Cliente = 'cliente';
}
