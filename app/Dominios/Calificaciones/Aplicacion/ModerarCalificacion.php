<?php

namespace App\Dominios\Calificaciones\Aplicacion;

use App\Dominios\Calificaciones\Aplicacion\MaquinaEstados\MaquinaEstadosCalificacion;
use App\Dominios\Calificaciones\Contratos\Eventos\CalificacionModerada;
use App\Dominios\Calificaciones\Dominio\AutorCalificacion;
use App\Dominios\Calificaciones\Dominio\CausaModeracion;
use App\Dominios\Calificaciones\Dominio\EstadoCalificacion;
use App\Dominios\Calificaciones\Dominio\Excepciones\CalificacionInvalida;
use App\Dominios\Calificaciones\Infraestructura\Eloquent\Calificacion;

/**
 * El administrador modera una calificación (`/admin/calificaciones/{id}/moderar`,
 * especificación §10.4, política de calificación): la oculta, con causa y motivo,
 * si incumple la política, o la deja visible si el reporte no procede. Ocultar no
 * borra: la calificación conserva su registro y deja de contar para el promedio, que
 * se recalcula en el acto. Nadie modera una calificación que escribió él mismo.
 * Idempotente: ocultar una ya oculta no repite el aviso ni el cálculo.
 */
final class ModerarCalificacion
{
    public function __construct(
        private readonly MaquinaEstadosCalificacion $estados,
        private readonly RecalcularReputacion $reputacion,
    ) {}

    /** @throws CalificacionInvalida */
    public function ocultar(int $actorUsuarioId, int $calificacionId, CausaModeracion $causa, string $motivo): Calificacion
    {
        return $this->aplicar($actorUsuarioId, $calificacionId, EstadoCalificacion::Oculta, $causa, $motivo);
    }

    /**
     * Deja la calificación visible: el reporte no procede o la moderación fue un error.
     *
     * @throws CalificacionInvalida
     */
    public function dejarVisible(int $actorUsuarioId, int $calificacionId, string $motivo): Calificacion
    {
        return $this->aplicar($actorUsuarioId, $calificacionId, EstadoCalificacion::Visible, null, $motivo);
    }

    private function aplicar(int $actorUsuarioId, int $calificacionId, EstadoCalificacion $hacia, ?CausaModeracion $causa, string $motivo): Calificacion
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw CalificacionInvalida::motivoRequerido();
        }

        $calificacion = Calificacion::query()->find($calificacionId) ?? throw CalificacionInvalida::calificacionAjena();

        if ($calificacion->autor_usuario_id === $actorUsuarioId) {
            throw CalificacionInvalida::propiaCalificacion();
        }

        $antes = $calificacion->estado;
        $moderada = $this->estados->transicionar($calificacion, $hacia, 'administrador', $actorUsuarioId, $motivo, $causa);

        if ($antes === $moderada->estado) {
            return $moderada;
        }

        if ($moderada->autor_rol === AutorCalificacion::Cliente && $moderada->proveedor_id !== null) {
            $this->reputacion->deProveedor($moderada->proveedor_id);
        } else {
            $this->reputacion->deCliente($moderada->cliente_id);
        }

        event(new CalificacionModerada($moderada->id, $moderada->solicitud_id, $moderada->estado->value, $causa?->value, $actorUsuarioId));

        return $moderada;
    }
}
