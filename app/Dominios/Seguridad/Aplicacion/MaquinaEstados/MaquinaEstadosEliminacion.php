<?php

namespace App\Dominios\Seguridad\Aplicacion\MaquinaEstados;

use App\Dominios\Seguridad\Dominio\EstadoDeEliminacion;
use App\Dominios\Seguridad\Dominio\Excepciones\TransicionDeEliminacionInvalida;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecEliminacion;

/**
 * Única clase que crea y cambia el `estado` de una solicitud de eliminación de cuenta (invariante 7 de CLAUDE.md), con su tabla de
 * transiciones en {@see EstadoDeEliminacion::siguientes()}. Nace `solicitada`; el estado nunca llega de un formulario.
 */
final class MaquinaEstadosEliminacion
{
    /** @param  array<string, mixed>  $atributos  sin `estado`: lo fija esta clase. */
    public function solicitar(array $atributos): SecEliminacion
    {
        return SecEliminacion::query()->create([...$atributos, 'estado' => EstadoDeEliminacion::Solicitada]);
    }

    /** La persona conservó su cuenta dentro de la gracia. */
    public function cancelar(SecEliminacion $eliminacion): SecEliminacion
    {
        $this->exigir($eliminacion, EstadoDeEliminacion::Cancelada);

        $eliminacion->forceFill(['estado' => EstadoDeEliminacion::Cancelada, 'cancelada_en' => now()])->save();

        return $eliminacion;
    }

    /** Vencida la gracia, algo la impide todavía: se reintenta cada día. */
    public function posponer(SecEliminacion $eliminacion, string $motivos): SecEliminacion
    {
        $this->exigir($eliminacion, EstadoDeEliminacion::Pospuesta);

        $eliminacion->forceFill(['estado' => EstadoDeEliminacion::Pospuesta, 'motivo_posposicion' => $motivos])->save();

        return $eliminacion;
    }

    /** Los datos personales ya se anonimizaron. */
    public function ejecutar(SecEliminacion $eliminacion): SecEliminacion
    {
        $this->exigir($eliminacion, EstadoDeEliminacion::Ejecutada);

        $eliminacion->forceFill(['estado' => EstadoDeEliminacion::Ejecutada, 'ejecutada_en' => now(), 'motivo_posposicion' => null])->save();

        return $eliminacion;
    }

    private function exigir(SecEliminacion $eliminacion, EstadoDeEliminacion $destino): void
    {
        if (! in_array($destino, $eliminacion->estado->siguientes(), true)) {
            throw TransicionDeEliminacionInvalida::entre($eliminacion->estado, $destino);
        }
    }
}
