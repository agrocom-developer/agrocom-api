<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;

/**
 * Una vista "como otro usuario" ya revalidada contra la base (tarea 140): el
 * administrador real, la cuenta de panel que se mira y el rol bajo el
 * que se la ve. Lo arma {@see ResolverVistaComo} en cada request.
 *
 * `$observado` es el subtipo del guard `interno` (`SecUsuarioInterno`), el mismo
 * que el provider del guard habría cargado en una sesión real de esa persona.
 */
final readonly class VistaComoResuelta
{
    public function __construct(
        public SecUser $admin,
        public SecUser $observado,
        public ?SecRole $rol,
    ) {}
}
