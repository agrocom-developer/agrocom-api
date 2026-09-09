<?php

namespace App\Dominios\Seguridad\Dominio\Excepciones;

use App\Dominios\Seguridad\Aplicacion\CrearCuentaPortal;
use RuntimeException;

/**
 * El contrato elegido para una cuenta de portal (tarea 65, HU-41) no existe,
 * está borrado, o no está `vigente` — una cuenta `cliente` solo puede
 * apuntar a un contrato que el cliente puede ver hoy en su portal.
 *
 * Segunda línea de defensa: la validación de forma ya la cubre
 * `CrearCuentaPortalRequest`/`ActualizarCuentaPortalRequest`
 * (`Rule::exists('com_contratos', ...)->where('estado', 'vigente')`), pero
 * {@see CrearCuentaPortal} no depende de
 * eso: también lo invocan directo los tests y, más adelante, un seeder.
 */
final class ContratoNoDisponibleParaPortal extends RuntimeException
{
    public static function porId(int $contratoId): self
    {
        return new self("El contrato #{$contratoId} no existe o no está vigente.");
    }
}
