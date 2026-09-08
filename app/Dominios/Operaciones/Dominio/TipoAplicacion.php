<?php

namespace App\Dominios\Operaciones\Dominio;

/**
 * Momento del ciclo del cultivo en que se hace la aplicación (HU-47, tarea
 * 70; `CHECK` en `ope_ordenes_aplicacion`). `Desarrollo` es el default de la
 * migración: cubre el grueso de las aplicaciones de un contrato (espec,
 * `ventana_al_negocio.md` §67), no solo los dos momentos que nombró el dueño.
 */
enum TipoAplicacion: string
{
    case Siembra = 'siembra';
    case Desarrollo = 'desarrollo';
    case Cosecha = 'cosecha';
}
