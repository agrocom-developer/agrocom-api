<?php

namespace App\Dominios\Solicitudes\Aplicacion;

use App\Dominios\Flota\Contratos\LecturaIntegrantesDeEquipo;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Solicitudes\Aplicacion\MaquinaEstados\MaquinaEstadosSolicitud;
use App\Dominios\Solicitudes\Contratos\Eventos\SolicitudCompletada;
use App\Dominios\Solicitudes\Dominio\ActorSolicitud;
use App\Dominios\Solicitudes\Dominio\ComisionDeServicio;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Dominio\Excepciones\TransicionSolicitudNoPermitida;
use App\Dominios\Solicitudes\Dominio\MontoDelServicio;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;

/**
 * Da la solicitud por completada (especificación §5.1): congela el monto final y
 * la comisión que Agrocom devenga y avisa al cliente y al proveedor. Es el único
 * lugar donde se calcula la comisión (política de comisión, ADR 0034): la
 * conformidad del cliente, la conformidad tácita y el cierre de una disputa
 * llegan acá. La comisión se calcula, no se cobra.
 *
 * El monto final escala sobre el estimado congelado al publicar con las
 * hectáreas facturadas (las confirmadas con tolerancia; sin confirmación, las
 * solicitadas). El porcentaje depende de quién prestó el servicio: propio 0 %,
 * independiente y tercero la tasa base, y la tasa preferencial si el dron lo
 * vendió Agrocom.
 */
final class CompletarServicio
{
    public function __construct(
        private readonly MaquinaEstadosSolicitud $estados,
        private readonly LecturaProveedores $proveedores,
        private readonly LecturaIntegrantesDeEquipo $equipos,
        private readonly ParametrosDeServicio $parametros,
    ) {}

    /**
     * @param  string|null  $hectareasConfirmadas  las que confirma el acta; `null` factura lo solicitado.
     *
     * @throws TransicionSolicitudNoPermitida si la solicitud no está en un estado que se complete.
     */
    public function ejecutar(
        SolicitudServicio $solicitud,
        ActorSolicitud $origen,
        ?int $actorUsuarioId,
        ?string $motivo = null,
        ?string $hectareasConfirmadas = null,
    ): SolicitudServicio {
        $cambios = $this->cifras($solicitud, $hectareasConfirmadas);

        $completada = $this->estados->transicionar($solicitud, EstadoSolicitud::Completada, $origen, $actorUsuarioId, $motivo, $cambios);

        $completada->loadMissing('cliente');

        event(new SolicitudCompletada(
            $completada->id,
            $completada->cliente_id,
            $completada->cliente->usuario_id,
            $completada->proveedor_id,
            $completada->proveedor_id === null ? [] : $this->proveedores->usuariosDeAviso($completada->proveedor_id),
            (string) $completada->monto_final,
            $completada->comision_porcentaje,
            $completada->comision_monto,
            $origen->value,
        ));

        return $completada;
    }

    /** @return array{monto_final: string, comision_porcentaje: string, comision_monto: string} */
    private function cifras(SolicitudServicio $solicitud, ?string $hectareasConfirmadas): array
    {
        $facturadas = MontoDelServicio::hectareasFacturadas(
            $solicitud->hectareas_solicitadas,
            $hectareasConfirmadas,
            $this->parametros->toleranciaHectareasPct(),
        );

        $montoFinal = MontoDelServicio::montoFinal((string) ($solicitud->monto_estimado ?? '0'), $solicitud->hectareas_solicitadas, $facturadas);

        $proveedor = $solicitud->proveedor_id === null ? null : $this->proveedores->proveedor($solicitud->proveedor_id);
        $equipo = $solicitud->equipo_id === null ? null : $this->equipos->integrantes($solicitud->equipo_id);

        $porcentaje = $proveedor === null
            ? '0.00'
            : ComisionDeServicio::porcentaje($proveedor->tipo, $equipo?->dronVendidoPorAgrocom() ?? false, $this->parametros->tasasDeComision());

        return [
            'monto_final' => $montoFinal,
            'comision_porcentaje' => $porcentaje,
            'comision_monto' => ComisionDeServicio::monto($montoFinal, $porcentaje),
        ];
    }
}
