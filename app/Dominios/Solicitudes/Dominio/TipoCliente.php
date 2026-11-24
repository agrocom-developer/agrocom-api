<?php

namespace App\Dominios\Solicitudes\Dominio;

/** Tipo de cliente del marketplace (especificación §4.1): persona o empresa. */
enum TipoCliente: string
{
    case Persona = 'persona';
    case Empresa = 'empresa';
}
