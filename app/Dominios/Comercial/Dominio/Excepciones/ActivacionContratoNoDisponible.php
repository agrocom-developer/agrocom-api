<?php

namespace App\Dominios\Comercial\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use Carbon\CarbonImmutable;
use DomainException;

/**
 * Guarda de negocio de la transición `borrador → vigente` (HU-23, tarea 34)
 * que la tabla de {@see
 * \App\Dominios\Comercial\Dominio\MaquinaEstados\TransicionesContrato} no
 * puede expresar por sí sola, porque depende de los DATOS del contrato, no
 * solo de su estado actual — ver el docblock de
 * `Aplicacion/MaquinaEstados/MaquinaEstadosContrato::activar()` para el
 * porqué.
 */
final class ActivacionContratoNoDisponible extends DomainException
{
    public static function porFechaInicioEnElPasado(int $contratoId, CarbonImmutable $fechaInicio): self
    {
        return new self(Texto::de('comercial.errores.contrato_fecha_inicio_pasada', [
            'id' => $contratoId,
            'fecha' => $fechaInicio->toDateString(),
        ]));
    }

    /**
     * Red de seguridad de la exclusividad de lotes (ADR 0021): un contrato
     * `borrador` no debería tener lotes retenidos por otro (el barrido de
     * `reconciliarConflictos()` lo pasa a `conflicto` antes), pero la guarda
     * real vive acá, en el momento de aprobar, por si el barrido no llegó a
     * correr (datos previos al ADR, carrera entre dos aprobaciones).
     *
     * @param  non-empty-list<array{lote_id: int, codigo: string, contrato_id: int}>  $ocupados
     */
    public static function porLotesOcupados(int $contratoId, array $ocupados): self
    {
        if (count($ocupados) === 1) {
            return new self(Texto::de('comercial.errores.contrato_activacion_lote_ocupado', [
                'id' => $contratoId,
                'codigo' => $ocupados[0]['codigo'],
                'contrato' => $ocupados[0]['contrato_id'],
            ]));
        }

        return new self(Texto::de('comercial.errores.contrato_activacion_lotes_ocupados', [
            'id' => $contratoId,
            'codigos' => implode(', ', array_column($ocupados, 'codigo')),
        ]));
    }
}
