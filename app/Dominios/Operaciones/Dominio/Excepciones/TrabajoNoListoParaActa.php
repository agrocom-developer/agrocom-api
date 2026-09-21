<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use DomainException;

/**
 * El trabajo referenciado por `POST /api/trabajos/{uuid_cliente}/acta` no
 * está en condiciones de generar su acta de conformidad (HU-17, tarea 24).
 *
 * Dos guardas de negocio, ambas documentadas en runs/24.md:
 *   - el trabajo debe estar `cerrado` (espec §4.3: el acta certifica un lote
 *     terminado, no uno en curso);
 *   - TODAS sus sesiones vigentes deben estar `validado` (decisión propia de
 *     esta tarea, no impuesta por la espec: el propósito del acta es cobrar
 *     sobre datos ya validados por el jefe de campo, no sobre lo declarado
 *     en crudo por el piloto).
 *
 * Una tercera causa, de integridad de datos, no de negocio (hallazgo de la
 * revisión crítica de esta tarea): un `uuid_cliente` de acta que choca con
 * el de un acta de OTRO trabajo — el `lockForUpdate()` de
 * `GenerarActaTrabajo` serializa dos pedidos concurrentes del MISMO trabajo,
 * pero no evita que dos trabajos DISTINTOS reciban por error el mismo
 * `uuid_cliente` de acta.
 */
final class TrabajoNoListoParaActa extends DomainException
{
    public static function porNoEstarCerrado(int $trabajoId): self
    {
        return new self(Texto::de('operaciones.errores.trabajo_no_cerrado_para_acta', ['id' => $trabajoId]));
    }

    public static function porSesionesSinValidar(int $trabajoId): self
    {
        return new self(Texto::de('operaciones.errores.trabajo_con_sesiones_sin_validar', ['id' => $trabajoId]));
    }

    public static function porConflictoDeUuidCliente(int $trabajoId): self
    {
        return new self(Texto::de('operaciones.errores.acta_uuid_cliente_en_uso', ['id' => $trabajoId]));
    }
}
