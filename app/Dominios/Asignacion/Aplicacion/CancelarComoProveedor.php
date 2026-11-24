<?php

namespace App\Dominios\Asignacion\Aplicacion;

use App\Dominios\Asignacion\Contratos\Eventos\SolicitudReasignada;
use App\Dominios\Asignacion\Dominio\CausaCancelacion;
use App\Dominios\Asignacion\Dominio\Excepciones\AsignacionInvalida;
use App\Dominios\Asignacion\Dominio\TasasDeProveedor;
use App\Dominios\Asignacion\Infraestructura\Eloquent\CancelacionDeProveedor;
use App\Dominios\Asignacion\Infraestructura\Eloquent\Oferta;
use App\Dominios\Asignacion\Infraestructura\Eloquent\Ronda;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Solicitudes\Contratos\AsignacionDeSolicitud;
use App\Dominios\Solicitudes\Contratos\LecturaSolicitudesParaAsignar;
use App\Dominios\Solicitudes\Contratos\SolicitudNoAsignable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * El proveedor cancela un servicio que ya aceptó (`POST
 * /solicitudes/{id}/cancelar-proveedor`, especificación §10.2, política de
 * cancelación): la solicitud vuelve a `en_asignacion` con prioridad (un ciclo
 * nuevo desde la ronda de propios, sin ofrecérsela a quien canceló), el
 * proveedor suma a su tasa de cancelación y se avisa al cliente.
 *
 * Pesa el doble si faltan menos de 12 horas para el inicio de la ventana; no
 * pesa si la causa es una emergencia o una falla de equipo avisada con más de
 * 12 horas. Solo existe mientras la solicitud está `asignada` o `en_camino`.
 */
final class CancelarComoProveedor
{
    public function __construct(
        private readonly ProveedorDeLaCuenta $cuentas,
        private readonly LecturaSolicitudesParaAsignar $solicitudes,
        private readonly AsignacionDeSolicitud $asignacion,
        private readonly AvanzarAsignacion $avanzar,
        private readonly EvaluarProveedor $evaluar,
        private readonly ParametrosDeAsignacion $parametros,
        private readonly LecturaProveedores $proveedores,
    ) {}

    /**
     * @throws AsignacionInvalida
     */
    public function ejecutar(int $usuarioId, int $solicitudId, string $motivo, CausaCancelacion $causa = CausaCancelacion::Otra, ?Carbon $ahora = null): CancelacionDeProveedor
    {
        $proveedor = $this->cuentas->ejecutar($usuarioId);
        $motivo = trim($motivo);
        $ahora ??= Carbon::now();

        if ($motivo === '') {
            throw AsignacionInvalida::motivoRequerido();
        }

        $cancelacion = DB::transaction(function () use ($proveedor, $solicitudId, $motivo, $causa, $usuarioId, $ahora): CancelacionDeProveedor {
            $solicitud = $this->solicitudes->datosBloqueando($solicitudId);

            if ($solicitud === null || $solicitud->proveedorId !== $proveedor->id) {
                throw AsignacionInvalida::solicitudAjena();
            }

            if (! in_array($solicitud->estado, ['asignada', 'en_camino'], true)) {
                throw AsignacionInvalida::solicitudNoCancelable();
            }

            $horas = max(0.0, ($ahora->diffInMinutes(Carbon::parse($solicitud->ventanaDesde)->startOfDay(), false)) / 60);
            $peso = TasasDeProveedor::pesoDeCancelacion($causa, $horas, $this->parametros->horasCancelacionTardia(), $this->parametros->pesoCancelacionTardia());

            $oferta = Oferta::query()
                ->where('solicitud_id', $solicitudId)
                ->where('proveedor_id', $proveedor->id)
                ->where('estado', 'aceptada')
                ->orderByDesc('ciclo')
                ->first();

            $cancelacion = CancelacionDeProveedor::query()->create([
                'solicitud_id' => $solicitudId,
                'proveedor_id' => $proveedor->id,
                'oferta_id' => $oferta?->id,
                'equipo_id' => $solicitud->equipoId,
                'causa' => $causa->value,
                'motivo' => $motivo,
                'peso' => $peso,
                'actor_usuario_id' => $usuarioId,
                'cancelada_en' => $ahora,
            ]);

            try {
                $this->asignacion->devolver($solicitudId, 'proveedor', $usuarioId, $motivo);
            } catch (SolicitudNoAsignable) {
                throw AsignacionInvalida::solicitudNoCancelable();
            }

            $ciclo = (int) Ronda::query()->where('solicitud_id', $solicitudId)->max('ciclo') + 1;

            $this->avanzar->ejecutar($this->solicitudes->datos($solicitudId) ?? $solicitud, $ciclo, 1);

            event(new SolicitudReasignada(
                $solicitudId,
                $ciclo,
                $solicitud->clienteUsuarioId,
                $proveedor->id,
                $this->proveedores->usuariosDeAviso($proveedor->id),
            ));

            return $cancelacion;
        });

        $this->evaluar->ejecutar($proveedor->id, $ahora);

        return $cancelacion;
    }
}
