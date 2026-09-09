<?php

namespace App\Dominios\Personal\Dominio;

/**
 * Clasificación operativa única de una persona de campo (espec §4.2;
 * `per_personas.rol`, CHECK en la migración). NO confundir con `sec_role`:
 * este enum es un atributo descriptivo de la persona, no gobierna permisos
 * ni admite más de un valor — el multi-rol de acceso vive en `sec_user_role`
 * (HU-01, diseño `modulos-roles` §5).
 */
enum RolOperativoPersona: string
{
    case Piloto = 'piloto';
    case Auxiliar = 'auxiliar';
    case JefeCampo = 'jefe_campo';
    case EncargadoOperaciones = 'encargado_operaciones';
    case Dueno = 'dueno';
}
