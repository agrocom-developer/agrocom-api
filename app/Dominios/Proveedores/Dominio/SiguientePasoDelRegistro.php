<?php

namespace App\Dominios\Proveedores\Dominio;

use App\Dominios\Compartido\Dominio\EstadoAprobacion;

/**
 * Qué sigue para quien ya tiene su cuenta de proveedor creada (ADR 0046, punto 26). Registrarse con un teléfono o un
 * documento que ya tiene cuenta responde `409 cuenta_ya_registrada` y la persona inicia sesión en lugar de crear otra
 * cuenta; con la sesión, `GET /api/proveedores/mi` le dice en qué punto del registro está y la app la lleva al paso
 * que falta, sin repetir el formulario.
 */
enum SiguientePasoDelRegistro: string
{
    /** Aprobado: nada pendiente del registro. */
    case Listo = 'listo';

    /** Pendiente y le faltan documentos obligatorios por subir (`documentos_faltantes`). */
    case SubirDocumentos = 'subir_documentos';

    /** Pendiente con todos los documentos subidos: espera la revisión de un administrador. */
    case EnRevision = 'en_revision';

    /** Rechazado: corrige lo observado y reenvía a revisión (`POST /api/proveedores/mi/reenviar`). */
    case Corregir = 'corregir';

    case Suspendido = 'suspendido';

    case Baja = 'baja';

    /** @param  list<string>  $documentosFaltantes */
    public static function de(EstadoAprobacion $estado, array $documentosFaltantes): self
    {
        return match ($estado) {
            EstadoAprobacion::Aprobado => self::Listo,
            EstadoAprobacion::Pendiente => $documentosFaltantes === [] ? self::EnRevision : self::SubirDocumentos,
            EstadoAprobacion::Rechazado => self::Corregir,
            EstadoAprobacion::Suspendido => self::Suspendido,
            EstadoAprobacion::Baja => self::Baja,
        };
    }
}
