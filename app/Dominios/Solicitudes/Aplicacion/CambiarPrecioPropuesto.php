<?php

namespace App\Dominios\Solicitudes\Aplicacion;

use App\Dominios\Compartido\Dominio\MontoDelServicio;
use App\Dominios\Solicitudes\Contratos\Eventos\PrecioPropuestoCambiado;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;
use Illuminate\Support\Facades\DB;

/**
 * El cliente cambia el precio por hectárea propuesto mientras la solicitud está
 * en borrador, `abierta` o `con_ofertas` (`negociacion-contrato.md` §3.1). Las
 * ofertas vigentes no cambian; la negociación avisa a los proveedores con una
 * negociación vigente al escuchar {@see PrecioPropuestoCambiado}.
 */
final class CambiarPrecioPropuesto
{
    public function __construct(private readonly ObtenerSolicitudDelCliente $solicitudes) {}

    /** @throws SolicitudInvalida */
    public function ejecutar(int $usuarioId, int $solicitudId, string $precioHa): SolicitudServicio
    {
        PublicarSolicitud::validarPrecioDe($precioHa);

        $solicitud = $this->solicitudes->ejecutar($usuarioId, $solicitudId);

        return DB::transaction(function () use ($solicitud, $precioHa): SolicitudServicio {
            /** @var SolicitudServicio $actual */
            $actual = SolicitudServicio::query()->lockForUpdate()->findOrFail($solicitud->id);

            if (! in_array($actual->estado, [EstadoSolicitud::Borrador, EstadoSolicitud::Abierta, EstadoSolicitud::ConOfertas], true)) {
                throw SolicitudInvalida::precioNoEditable();
            }

            $anterior = $actual->precio_ha_propuesto;
            $actual->forceFill([
                'precio_ha_propuesto' => $precioHa,
                'monto_estimado' => MontoDelServicio::monto($actual->hectareas_solicitadas, $precioHa),
            ])->save();

            if ($actual->estado->enMercado()) {
                event(new PrecioPropuestoCambiado($actual->id, $anterior === null ? null : (string) $anterior, $precioHa));
            }

            return $actual->load(['terreno', 'historial']);
        });
    }
}
