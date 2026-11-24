<?php

namespace App\Dominios\Asignacion\Aplicacion;

use App\Dominios\Asignacion\Infraestructura\Eloquent\Oferta;
use App\Dominios\Solicitudes\Contratos\DatosSolicitudParaAsignar;

/**
 * Una oferta con lo que el proveedor puede ver del servicio (especificación §6,
 * punto 8): el terreno aproximado (la zona y un punto redondeado a unos
 * kilómetros), las hectáreas, el pago estimado y la distancia. Nunca el nombre
 * del terreno ni datos de contacto del cliente: eso se conoce al aceptar.
 */
final readonly class OfertaParaProveedor
{
    /**
     * @param  array{lat: string, lng: string}|null  $ubicacionAproximada
     */
    public function __construct(
        public Oferta $oferta,
        public ?DatosSolicitudParaAsignar $solicitud,
        public ?array $ubicacionAproximada,
        public int $segundosRestantes,
    ) {}
}
