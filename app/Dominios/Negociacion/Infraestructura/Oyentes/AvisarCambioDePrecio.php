<?php

namespace App\Dominios\Negociacion\Infraestructura\Oyentes;

use App\Dominios\Negociacion\Aplicacion\Avisador;
use App\Dominios\Negociacion\Dominio\EstadoOferta;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Oferta;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Solicitudes\Contratos\Eventos\PrecioPropuestoCambiado;

/** Cuando el cliente cambia el precio propuesto, avisa a los proveedores con una negociación vigente (§3.1). Las ofertas vigentes no cambian. */
final class AvisarCambioDePrecio
{
    public function __construct(
        private readonly LecturaProveedores $proveedores,
        private readonly Avisador $avisos,
    ) {}

    public function handle(PrecioPropuestoCambiado $evento): void
    {
        $usuarios = [];

        foreach (Oferta::query()->where('solicitud_id', $evento->solicitudId)->where('estado', EstadoOferta::Vigente->value)->pluck('proveedor_id')->unique() as $proveedorId) {
            array_push($usuarios, ...$this->proveedores->usuariosDeAviso((int) $proveedorId));
        }

        $this->avisos->avisar('precio_cambiado', "precio_cambiado:{$evento->solicitudId}:{$evento->precioNuevo}", [
            'solicitud' => $evento->solicitudId,
            'precio' => $evento->precioNuevo,
        ], $evento->solicitudId, $usuarios, 'ofertas');
    }
}
