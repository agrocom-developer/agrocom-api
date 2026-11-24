<?php

namespace App\Dominios\Flota\Dominio;

/** Por qué un equipo no puede recibir ofertas hoy (especificación §5.2 y §6, elegibilidad). */
enum MotivoNoApto: string
{
    case ProveedorNoAprobado = 'proveedor_no_aprobado';
    case PilotoNoAprobado = 'piloto_no_aprobado';
    case DronNoAprobado = 'dron_no_aprobado';
    case AuxiliarNoAprobado = 'auxiliar_no_aprobado';
    case DocumentosPilotoVencidos = 'documentos_piloto_incompletos';
    case DocumentosDronVencidos = 'documentos_dron_incompletos';
    case FueraDeVigencia = 'fuera_de_vigencia';
}
