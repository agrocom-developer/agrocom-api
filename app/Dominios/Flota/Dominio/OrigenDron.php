<?php

namespace App\Dominios\Flota\Dominio;

/**
 * De dónde viene el dron (especificación §4.4, ADR 0029): de la flota de
 * Agrocom, vendido por Agrocom a un comprador (que recibe trabajo asegurado por
 * la plataforma y prioridad de matching dentro de los terceros) o externo. Solo
 * un administrador marca `vendido_por_agrocom`: no se pide por la API.
 */
enum OrigenDron: string
{
    case FlotaAgrocom = 'flota_agrocom';
    case VendidoPorAgrocom = 'vendido_por_agrocom';
    case Externo = 'externo';
}
