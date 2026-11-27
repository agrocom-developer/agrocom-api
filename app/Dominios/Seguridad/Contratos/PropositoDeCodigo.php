<?php

namespace App\Dominios\Seguridad\Contratos;

/** Para qué se pidió un código (`sec_codigos_verificacion.proposito`). Un código solo sirve a su propósito. */
enum PropositoDeCodigo: string
{
    case VerificarTelefono = 'verificar_telefono';
    case RecuperarAcceso = 'recuperar_acceso';
    case CambiarContrasena = 'cambiar_contrasena';
    case CambiarTelefono = 'cambiar_telefono';
    case EliminarCuenta = 'eliminar_cuenta';
}
