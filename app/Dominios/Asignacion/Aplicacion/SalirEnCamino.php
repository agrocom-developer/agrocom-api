<?php

namespace App\Dominios\Asignacion\Aplicacion;

use App\Dominios\Asignacion\Contratos\Eventos\EquipoEnCamino;
use App\Dominios\Asignacion\Dominio\Excepciones\AsignacionInvalida;
use App\Dominios\Solicitudes\Contratos\AsignacionDeSolicitud;
use App\Dominios\Solicitudes\Contratos\LecturaSolicitudesParaAsignar;
use App\Dominios\Solicitudes\Contratos\SolicitudNoAsignable;
use Illuminate\Support\Facades\DB;

/**
 * El proveedor avisa que el equipo salió hacia el lote (`POST
 * /solicitudes/{id}/en-camino`, especificación §10.2): la solicitud `asignada`
 * pasa a `en_camino` y se avisa al cliente. Solo el proveedor al que se asignó
 * el servicio puede hacerlo; uno ajeno recibe el mismo 404 que una solicitud
 * inexistente (invariante 5).
 *
 * Idempotente: avisar dos veces deja la solicitud `en_camino` y no repite el aviso.
 */
final class SalirEnCamino
{
    public function __construct(
        private readonly ProveedorDeLaCuenta $cuentas,
        private readonly LecturaSolicitudesParaAsignar $solicitudes,
        private readonly AsignacionDeSolicitud $asignacion,
    ) {}

    /** @throws AsignacionInvalida */
    public function ejecutar(int $usuarioId, int $solicitudId): void
    {
        $proveedor = $this->cuentas->ejecutar($usuarioId);

        DB::transaction(function () use ($proveedor, $solicitudId, $usuarioId): void {
            $solicitud = $this->solicitudes->datosBloqueando($solicitudId);

            if ($solicitud === null || $solicitud->proveedorId !== $proveedor->id || $solicitud->equipoId === null) {
                throw AsignacionInvalida::solicitudAjena();
            }

            if ($solicitud->estado === 'en_camino') {
                return;
            }

            if ($solicitud->estado !== 'asignada') {
                throw AsignacionInvalida::solicitudNoPuedeSalir();
            }

            try {
                $this->asignacion->salirEnCamino($solicitudId, $usuarioId);
            } catch (SolicitudNoAsignable) {
                throw AsignacionInvalida::solicitudNoPuedeSalir();
            }

            event(new EquipoEnCamino($solicitudId, $solicitud->clienteUsuarioId, $proveedor->id, $solicitud->equipoId));
        });
    }
}
