<?php

namespace App\Dominios\Seguridad\Dominio\Excepciones;

use App\Dominios\Seguridad\Aplicacion\AsignarPermisosRol;
use DomainException;

/**
 * Una operación sobre el catálogo de roles dejaría el sistema en un estado
 * del que no se puede volver desde el panel. No es falta de permiso
 * ({@see PermisoDenegado}): el actor está autorizado, la operación misma es
 * la que no puede existir.
 *
 * Son las cuatro formas de tirar la llave adentro de la casa que la pantalla
 * de roles hace posibles por primera vez — antes roles y permisos solo se
 * tocaban por seeder, donde el error se corrige editando el archivo y
 * volviendo a sembrar. Desde el panel no hay tal salida: si el último rol que
 * puede administrar permisos se queda sin ese permiso, nadie puede volver a
 * otorgárselo, y recuperar el sistema exige acceso al servidor.
 *
 * `DomainException` y no `AuthorizationException`: el controlador la traduce
 * a un mensaje de vuelta en la pantalla (`withErrors`), no a un 403 — el
 * usuario tiene todo el derecho de estar ahí, lo que pidió es lo que no se
 * puede hacer.
 */
final class RolProtegido extends DomainException
{
    /**
     * El conjunto de roles vivos que pueden administrar permisos no puede
     * quedar vacío: sin ninguno, la pantalla de roles queda inaccesible para
     * todo el mundo y no hay forma de revertirlo desde el panel.
     */
    public static function porUltimaLlave(string $codigoPermiso): self
    {
        return new self(
            "No se puede dejar el sistema sin ningún rol activo que tenga '{$codigoPermiso}': ".
            'sería la última llave, y nadie podría volver a otorgarla desde el panel.'
        );
    }

    /**
     * Quitarse a uno mismo, desde el rol con el que está operando, el permiso
     * de ver o administrar roles. El middleware `ResolverRolActivo` revalida
     * los permisos en CADA request, así que el efecto es inmediato: el
     * siguiente clic ya sería un 403 sobre la pantalla que se está usando.
     */
    public static function porRolActivoPropio(string $codigoPermiso): self
    {
        return new self(
            "No podés quitarle '{$codigoPermiso}' al rol con el que estás operando: ".
            'perderías el acceso a esta pantalla en el próximo clic.'
        );
    }

    /**
     * Último rol vivo que tiene otorgado un permiso. Sin esta guarda el
     * permiso queda huérfano, y como nadie puede otorgar un permiso que no
     * tiene (la guarda anti-escalada de {@see AsignarPermisosRol}),
     * ningún rol podría recuperarlo: el permiso queda muerto en el catálogo
     * aunque el código lo siga exigiendo, y la pantalla que lo pide se vuelve
     * inalcanzable para siempre.
     */
    public static function porUltimoPortadorDelPermiso(string $codigoPermiso): self
    {
        return new self(
            "'{$codigoPermiso}' quedaría sin ningún rol activo que lo tenga. ".
            'Otorgáselo antes a otro rol: nadie puede conceder un permiso que no tiene, '.
            'así que un permiso huérfano no se recupera desde el panel.'
        );
    }

    /** Roles con cuentas vivas detrás: dar de baja el rol las dejaría sin él. */
    public static function porTenerUsuarios(string $nombreRol, int $cantidad): self
    {
        return new self(
            "El rol '{$nombreRol}' tiene {$cantidad} ".
            ($cantidad === 1 ? 'usuario asignado' : 'usuarios asignados').
            '. Reasignálos antes de darlo de baja.'
        );
    }

    /** Dar de baja o desactivar el rol con el que se está operando. */
    public static function porSerElRolActivo(string $nombreRol): self
    {
        return new self("No podés dar de baja ni desactivar '{$nombreRol}': es el rol con el que estás operando.");
    }
}
