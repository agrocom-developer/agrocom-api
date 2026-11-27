<?php

namespace App\Dominios\Calificaciones\Aplicacion;

use App\Dominios\Calificaciones\Aplicacion\MaquinaEstados\MaquinaEstadosCalificacion;
use App\Dominios\Calificaciones\Contratos\Eventos\CalificacionReportada;
use App\Dominios\Calificaciones\Dominio\AutorCalificacion;
use App\Dominios\Calificaciones\Dominio\EstadoCalificacion;
use App\Dominios\Calificaciones\Dominio\Excepciones\CalificacionInvalida;
use App\Dominios\Calificaciones\Infraestructura\Eloquent\Calificacion;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;

/**
 * El proveedor reporta una calificación que recibió y considera abusiva (`POST
 * /calificaciones/{id}/reportar`, política de calificación y de reclamos): queda
 * `en_moderacion` con el motivo y un administrador decide. Reportar no la oculta
 * ni la saca del promedio. Solo reporta las calificaciones de su proveedor (una
 * ajena responde 404, invariante 5 de CLAUDE.md) y solo las del cliente.
 * Idempotente: reportar una ya reportada no repite el aviso.
 */
final class ReportarCalificacion
{
    public function __construct(
        private readonly LecturaProveedores $proveedores,
        private readonly MaquinaEstadosCalificacion $estados,
    ) {}

    /** @throws CalificacionInvalida */
    public function ejecutar(int $usuarioId, int $calificacionId, string $motivo): Calificacion
    {
        $proveedor = $this->proveedores->proveedorDeLaCuenta($usuarioId) ?? throw CalificacionInvalida::sinProveedor();
        $motivo = trim($motivo);

        $calificacion = Calificacion::query()
            ->where('proveedor_id', $proveedor->id)
            ->where('autor_rol', AutorCalificacion::Cliente->value)
            ->find($calificacionId) ?? throw CalificacionInvalida::calificacionAjena();

        if ($motivo === '') {
            throw CalificacionInvalida::motivoRequerido();
        }

        if ($calificacion->estado === EstadoCalificacion::Oculta) {
            throw CalificacionInvalida::noReportable();
        }

        $yaReportada = $calificacion->estado === EstadoCalificacion::EnModeracion;
        $reportada = $this->estados->transicionar($calificacion, EstadoCalificacion::EnModeracion, 'proveedor', $usuarioId, $motivo);

        if (! $yaReportada) {
            event(new CalificacionReportada($reportada->id, $reportada->solicitud_id, $proveedor->id, $proveedor->razonSocial));
        }

        return $reportada;
    }
}
