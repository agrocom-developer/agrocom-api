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
 * `negociacion-contrato.md` §1): `borrador` pasa a `abierta`. La solicitud no vence
 * sola: sigue en el mercado hasta que se adjudica o el cliente la cancela. Guardas:
 * precio por hectárea propuesto y dentro del tope de sanidad, hectáreas que no superan
 * las del terreno, fecha deseada (opcional) no pasada ni más allá de la anticipación
 * máxima, y cuenta con teléfono. El aviso a los pilotos lo da la negociación al
 * escuchar {@see SolicitudAbierta}.
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

        $this->validarPrecio($solicitud->precio_ha_propuesto);

        if (BigDecimal::of($solicitud->hectareas_solicitadas)->isGreaterThan(BigDecimal::of($solicitud->terreno->hectareas))) {
            throw SolicitudInvalida::hectareasExcedenTerreno($solicitud->hectareas_solicitadas, $solicitud->terreno->hectareas);
        }

        self::validarVentanaDe($solicitud->ventana_hasta?->toDateString());

        $abierta = $this->estados->transicionar($solicitud, EstadoSolicitud::Abierta, ActorSolicitud::Cliente, $usuarioId, null, [
            'publicada_en' => Carbon::now(),
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
        ));

        return $abierta->load(['terreno', 'historial']);
    }

    /**
     * La fecha deseada es opcional y no vence nada: solo se pide que no haya pasado y que no caiga más allá de la
     * anticipación máxima (`negociacion.anticipacion_maxima_dias`, validación de sanidad).
     *
     * @throws SolicitudInvalida
     */
    public static function validarVentanaDe(?string $hasta): void
    {
        if ($hasta === null) {
            return;
        }

        $fecha = Carbon::parse($hasta)->startOfDay();

        if ($fecha->lt(Carbon::today())) {
            throw SolicitudInvalida::ventanaVencida();
        }

        $maximo = (int) config('negociacion.anticipacion_maxima_dias', 365);

        if ($fecha->gt(Carbon::today()->addDays($maximo))) {
            throw SolicitudInvalida::ventanaFueraDeRango($maximo);
        }
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
