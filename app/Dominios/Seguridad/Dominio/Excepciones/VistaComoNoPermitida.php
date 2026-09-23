<?php

namespace App\Dominios\Seguridad\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * No se puede abrir una vista "como otro usuario" (tarea 140): la sesión ya
 * tiene una abierta, la cuenta no está disponible para mirarla, o falta elegir
 * (o no es válido) el rol bajo el que se la quiere ver.
 *
 * Extiende `AuthorizationException` (mismo patrón que {@see PermisoDenegado} y
 * {@see RolNoAsignado}) para que el manejador de excepciones la traduzca a 403
 * sin mapeo adicional: el servidor revalida todo aunque el formulario solo
 * ofreciera opciones legítimas.
 */
final class VistaComoNoPermitida extends AuthorizationException
{
    public static function yaHayUnaAbierta(): self
    {
        return new self(Texto::de('seguridad.errores.vista_como_ya_abierta'));
    }

    /** Bloqueada, dada de baja, del propio administrador o inexistente: no se distingue a propósito. */
    public static function cuentaNoDisponible(): self
    {
        return new self(Texto::de('seguridad.errores.vista_como_cuenta_no_disponible'));
    }

    public static function rolRequerido(): self
    {
        return new self(Texto::de('seguridad.errores.vista_como_rol_requerido'));
    }

    public static function rolNoDisponible(): self
    {
        return new self(Texto::de('seguridad.errores.vista_como_rol_no_disponible'));
    }

    public static function sinContrato(): self
    {
        return new self(Texto::de('seguridad.errores.vista_como_sin_contrato'));
    }
}
