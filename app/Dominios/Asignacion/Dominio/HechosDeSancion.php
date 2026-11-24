<?php

namespace App\Dominios\Asignacion\Dominio;

/**
 * Los hechos de un proveedor que la política de sanciones evalúa, ya
 * resueltos por el llamador (conteos en la ventana, sanciones vigentes).
 */
final readonly class HechosDeSancion
{
    public function __construct(
        public int $cancelacionesEnVentana,
        public bool $restriccionPorCancelacionesVigente,
        public bool $advertenciaPorCancelacionesVigente,
        public bool $hayRestriccionPorCancelacionesReciente,
        public string $calificacionPromedio,
        public int $calificacionesConteo,
        public bool $restriccionPorCalificacionVigente,
        public bool $advertenciaPorCalificacionVigente,
        public ?int $diasDesdeAdvertenciaDeCalificacion,
        public string $tasaAceptacion,
        public int $ofertasRespondidasEnVentana,
        public bool $advertenciaPorAceptacionVigente,
    ) {}
}
