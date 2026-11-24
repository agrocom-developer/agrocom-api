<?php

namespace App\Dominios\Notificaciones\Aplicacion\Reglas;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Notificaciones\Dominio\Destinatario;
use App\Dominios\Notificaciones\Dominio\NotificacionArmada;
use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Dominio\ReglaNotificacion;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use App\Dominios\Proveedores\Contratos\Eventos\DocumentoVencido;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Un documento aprobado venció y el sujeto queda suspendido hasta que cargue uno
 * vigente (especificación §12: `DocumentoVencido`; ADR 0032, punto 4). Le
 * importa al sujeto y al titular de su proveedor.
 */
final class ReglaDocumentoVencido implements ReglaNotificacion
{
    public function evento(): string
    {
        return DocumentoVencido::class;
    }

    public function armar(object $evento): NotificacionArmada
    {
        if (! $evento instanceof DocumentoVencido) {
            throw new InvalidArgumentException('ReglaDocumentoVencido solo atiende DocumentoVencido.');
        }

        return new NotificacionArmada(
            tipo: TipoNotificacion::DocumentoVencido,
            claveEvento: "documento_vencido:{$evento->documentoId}",
            parametros: [
                'documento' => mb_strtolower(Texto::de('proveedores.documentos.tipo.'.$evento->tipoDocumento)),
                'sujeto' => mb_strtolower(Texto::de('aprobacion.sujeto.'.$evento->sujetoTipo)),
                'fecha' => (new DateTimeImmutable($evento->venceEn))->format('d/m/Y'),
            ],
            recurso: RecursoNotificable::Documento,
            recursoId: $evento->documentoId,
            destinatarios: array_map(static fn (int $usuarioId): Destinatario => Destinatario::usuario($usuarioId), $evento->usuariosDestinatarios),
        );
    }
}
