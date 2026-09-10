<?php

namespace App\Dominios\Comercial\Dominio;

/**
 * Tipo de persona del cliente (ADR 0018, punto 3): distingue el cliente
 * unipersonal (física) de la sociedad (jurídica, ej. "Frigosis") — CHECK en
 * `com_clientes.tipo_persona`. Dato descriptivo, sin impacto en el resto del
 * modelo: el dato del dueño de una sociedad no necesita entidad propia, ya
 * vive en `com_cliente_contactos` con `tipo = 'dueno'`
 * ({@see TipoContactoCliente::Dueno}).
 */
enum TipoPersonaCliente: string
{
    case Fisica = 'fisica';
    case Juridica = 'juridica';
}
