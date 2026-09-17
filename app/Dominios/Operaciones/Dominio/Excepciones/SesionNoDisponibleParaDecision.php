<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use DomainException;

/**
 * Una sesión no puede recibir una decisión (validar o rechazar) del jefe de
 * campo — HU-14, tarea 14.
 *
 * Dos causas, no una: `TransicionSesionNoPermitida` ya cubre "no está
 * `cerrado`" para VALIDAR (pasa por {@see
 * \App\Dominios\Operaciones\Dominio\MaquinaEstados\TransicionesSesion}), pero
 * RECHAZAR no transiciona ningún estado (ver `EstadoSesion::Validado`,
 * docblock) — nada en la tabla de transiciones lo detecta, así que esta
 * excepción cubre ambos motivos para las dos acciones: el estado de origen
 * no es `cerrado`, o la sesión ya fue anulada por un rechazo anterior
 * (`anulada_en` ya seteada — algo que tampoco es un `estado`).
 */
final class SesionNoDisponibleParaDecision extends DomainException
{
    public static function porEstadoInvalido(int $sesionId, EstadoSesion $actual): self
    {
        return new self(Texto::de('operaciones.errores.sesion_no_disponible_por_estado', [
            'id' => $sesionId,
            'estado' => $actual->value,
        ]));
    }

    public static function porYaAnulada(int $sesionId): self
    {
        return new self(Texto::de('operaciones.errores.sesion_no_disponible_por_anulada', ['id' => $sesionId]));
    }
}
