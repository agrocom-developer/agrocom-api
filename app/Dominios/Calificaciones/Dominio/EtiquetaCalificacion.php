<?php

namespace App\Dominios\Calificaciones\Dominio;

/**
 * Catálogo cerrado de etiquetas (política de calificación, «Contenido»): puntualidad,
 * cobertura pareja, comunicación, cuidado del entorno, orden en el lote, acceso al
 * lote y claridad del pedido. Unas describen al proveedor y otras al cliente.
 */
enum EtiquetaCalificacion: string
{
    case Puntualidad = 'puntualidad';
    case CoberturaPareja = 'cobertura_pareja';
    case Comunicacion = 'comunicacion';
    case CuidadoEntorno = 'cuidado_entorno';
    case OrdenLote = 'orden_lote';
    case AccesoLote = 'acceso_lote';
    case ClaridadPedido = 'claridad_pedido';

    /** @return list<self> las que el cliente usa al calificar al proveedor, al piloto y al auxiliar. */
    public static function haciaProveedor(): array
    {
        return [self::Puntualidad, self::CoberturaPareja, self::Comunicacion, self::CuidadoEntorno, self::OrdenLote];
    }

    /** @return list<self> las que el proveedor usa al calificar al cliente. */
    public static function haciaCliente(): array
    {
        return [self::Puntualidad, self::Comunicacion, self::AccesoLote, self::ClaridadPedido];
    }
}
