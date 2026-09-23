<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;

/**
 * Una vista "como otro usuario" ya revalidada contra la base (tarea 140): el
 * administrador real, la cuenta que se mira y, si es interna, el rol bajo el
 * que se la ve. Lo arma {@see ResolverVistaComo} en cada request.
 *
 * `$observado` es el subtipo que corresponde a su guard (`SecUsuarioInterno` o
 * `SecUsuarioCliente`), el mismo que el provider del guard habría cargado en
 * una sesión real de esa persona.
 */
final readonly class VistaComoResuelta
{
    public function __construct(
        public SecUser $admin,
        public SecUser $observado,
        public ?SecRole $rol,
    ) {}
}
