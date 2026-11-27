<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Aplicacion\MaquinaEstados\MaquinaEstadosEliminacion;
use App\Dominios\Seguridad\Contratos\ParticipanteDeEliminacion;
use App\Dominios\Seguridad\Dominio\EstadoDeEliminacion;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecEliminacion;
use Illuminate\Support\Facades\DB;

/**
 * La tarea diaria que ejecuta las eliminaciones cuya gracia venció (ADR 0047, punto 5). Cada cuenta va en su transacción, con
 * la solicitud bloqueada, y es idempotente: correrla otra vez no cambia nada. Si algo todavía la impide (un reclamo que se abrió
 * después), la solicitud queda `pospuesta` con el motivo y se reintenta al día siguiente.
 */
final class EjecutarEliminacionesDeCuenta
{
    /** @param  iterable<ParticipanteDeEliminacion>  $participantes */
    public function __construct(
        private readonly iterable $participantes,
        private readonly AnonimizarCuentaDeSeguridad $seguridad,
        private readonly MaquinaEstadosEliminacion $estados,
    ) {}

    /** @return array{ejecutadas: int, pospuestas: int} */
    public function ejecutar(): array
    {
        $ejecutadas = 0;
        $pospuestas = 0;

        $pendientes = SecEliminacion::query()
            ->whereIn('estado', [EstadoDeEliminacion::Solicitada->value, EstadoDeEliminacion::Pospuesta->value])
            ->where('ejecutar_desde', '<=', now())
            ->orderBy('id')
            ->pluck('id');

        foreach ($pendientes as $id) {
            DB::transaction(function () use ($id, &$ejecutadas, &$pospuestas): void {
                $eliminacion = SecEliminacion::query()->lockForUpdate()->find($id);

                if ($eliminacion === null || ! $eliminacion->estado->vigente()) {
                    return;
                }

                $motivos = [];

                foreach ($this->participantes as $participante) {
                    array_push($motivos, ...$participante->impedimentos($eliminacion->usuario_id));
                }

                if ($motivos !== []) {
                    $this->estados->posponer($eliminacion, implode(',', array_unique($motivos)));
                    $pospuestas++;

                    return;
                }

                foreach ($this->participantes as $participante) {
                    $participante->anonimizar($eliminacion->usuario_id);
                }

                $this->seguridad->ejecutar($eliminacion->usuario_id);

                $this->estados->ejecutar($eliminacion);
                $ejecutadas++;
            });
        }

        return ['ejecutadas' => $ejecutadas, 'pospuestas' => $pospuestas];
    }
}
