<?php

namespace App\Dominios\Finanzas\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Finanzas\Dominio\PoliticaEdicionRendicion;
use DomainException;

/**
 * Se intentó editar la cabecera de una rendición que ya no está `Abierta`
 * (tarea 134) — la guarda de `Aplicacion/ActualizarRendicion`, que se apoya
 * en {@see PoliticaEdicionRendicion} para decidirlo: `presentada` ya congeló
 * su `monto`, `aprobada` ya es historia liquidada.
 */
final class RendicionNoEditable extends DomainException
{
    public static function porEstado(int $rendicionId, string $estado): self
    {
        return new self(Texto::de('finanzas.errores.rendicion_no_editable', [
            'rendicion_id' => $rendicionId,
            'estado' => $estado,
        ]));
    }
}
