<?php

namespace App\Dominios\Solicitudes\Aplicacion;

use App\Dominios\Compartido\Dominio\MontoDelServicio;
use App\Dominios\Solicitudes\Aplicacion\MaquinaEstados\MaquinaEstadosSolicitud;
use App\Dominios\Solicitudes\Contratos\Eventos\SolicitudAbierta;
use App\Dominios\Solicitudes\Dominio\ActorSolicitud;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;
use App\Dominios\Solicitudes\Dominio\Excepciones\TransicionSolicitudNoPermitida;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;

/**
 * Abre una solicitud al mercado (`POST /solicitudes/{id}/publicar`,
 * `negociacion-contrato.md` §1): `borrador` pasa a `abierta` con su vigencia, y una
 * `expirada` se renueva dentro del plazo de renovación. Guardas: precio por
 * hectárea propuesto y dentro del tope de sanidad, hectáreas que no superan las
 * del terreno, fecha deseada no pasada y cuenta con teléfono. El aviso a los
 * pilotos lo da la negociación al escuchar {@see SolicitudAbierta}.
 */
final class PublicarSolicitud
{
    public function __construct(
        private readonly ClienteDeLaCuenta $clientes,
        private readonly ObtenerSolicitudDelCliente $solicitudes,
        private readonly MaquinaEstadosSolicitud $estados,
    ) {}

    /**
     * @throws SolicitudInvalida
     * @throws TransicionSolicitudNoPermitida
     */
    public function ejecutar(int $usuarioId, int $solicitudId): SolicitudServicio
    {
        $cliente = $this->clientes->activo($usuarioId);

        if ($cliente->publicacion_limitada_en !== null) {
            throw SolicitudInvalida::publicacionLimitada();
        }

        if (trim((string) $cliente->telefono) === '') {
            throw SolicitudInvalida::sinTelefono();
        }

        $solicitud = $this->solicitudes->ejecutar($usuarioId, $solicitudId);
        $renueva = $solicitud->estado === EstadoSolicitud::Expirada;

        if ($renueva && ($solicitud->expirada_en === null || $solicitud->expirada_en->lt(Carbon::now()->subDays((int) config('negociacion.plazo_renovacion_dias', 30))))) {
            throw SolicitudInvalida::noRenovable();
        }

        $this->validarPrecio($solicitud->precio_ha_propuesto);

        if (BigDecimal::of($solicitud->hectareas_solicitadas)->isGreaterThan(BigDecimal::of($solicitud->terreno->hectareas))) {
            throw SolicitudInvalida::hectareasExcedenTerreno($solicitud->hectareas_solicitadas, $solicitud->terreno->hectareas);
        }

        if (Carbon::parse($solicitud->ventana_hasta)->startOfDay()->lt(Carbon::today())) {
            throw SolicitudInvalida::ventanaVencida();
        }

        $abierta = $this->estados->transicionar($solicitud, EstadoSolicitud::Abierta, ActorSolicitud::Cliente, $usuarioId, null, [
            'publicada_en' => Carbon::now(),
            'vence_en' => Carbon::now()->addDays((int) config('negociacion.vigencia_solicitud_dias', 7)),
            'expirada_en' => null,
            'monto_estimado' => MontoDelServicio::monto($solicitud->hectareas_solicitadas, (string) $solicitud->precio_ha_propuesto),
        ]);

        $abierta->loadMissing('terreno.zona');

        event(new SolicitudAbierta(
            $abierta->id,
            $abierta->cliente_id,
            $cliente->usuario_id,
            $abierta->terreno->zona_id,
            $abierta->terreno->zona?->nombre,
            $abierta->terreno->zona?->macrorregion,
            $abierta->hectareas_solicitadas,
            (string) $abierta->precio_ha_propuesto,
            $renueva,
        ));

        return $abierta->load(['terreno', 'historial']);
    }

    /** @throws SolicitudInvalida */
    public static function validarPrecioDe(?string $precio): void
    {
        if ($precio === null || BigDecimal::of($precio)->isLessThanOrEqualTo(0)) {
            throw SolicitudInvalida::precioRequerido();
        }

        if (BigDecimal::of($precio)->isGreaterThan(BigDecimal::of((string) config('negociacion.precio_ha_maximo', '10000')))) {
            throw SolicitudInvalida::precioExcedeMaximo((string) config('negociacion.precio_ha_maximo', '10000'));
        }
    }

    private function validarPrecio(?string $precio): void
    {
        self::validarPrecioDe($precio);
    }
}
