<?php

namespace App\Dominios\Notificaciones\Aplicacion\Reglas;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Notificaciones\Dominio\Destinatario;
use App\Dominios\Notificaciones\Dominio\NotificacionArmada;
use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Dominio\ReglaNotificacion;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use App\Dominios\Proveedores\Contratos\Eventos\DocumentoPorVencer;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Un documento aprobado vence pronto (especificación §12: `DocumentoPorVencer`,
 * aviso previo de 30 y 7 días, ADR 0032). Le importa al sujeto y al titular de su
 * proveedor. La clave lleva la ventana del aviso (30 o 7): cada una se avisa una
 * sola vez por documento.
 */
final class ReglaDocumentoPorVencer implements ReglaNotificacion
{
    public function evento(): string
    {
        return DocumentoPorVencer::class;
    }

    public function armar(object $evento): NotificacionArmada
    {
        if (! $evento instanceof DocumentoPorVencer) {
            throw new InvalidArgumentException('ReglaDocumentoPorVencer solo atiende DocumentoPorVencer.');
        }

        $ventana = $evento->diasRestantes <= 7 ? 7 : 30;

        return new NotificacionArmada(
            tipo: TipoNotificacion::DocumentoPorVencer,
            claveEvento: "documento_por_vencer:{$evento->documentoId}:{$ventana}",
            parametros: [
                'documento' => mb_strtolower(Texto::de('proveedores.documentos.tipo.'.$evento->tipoDocumento)),
                'sujeto' => mb_strtolower(Texto::de('aprobacion.sujeto.'.$evento->sujetoTipo)),
                'fecha' => (new DateTimeImmutable($evento->venceEn))->format('d/m/Y'),
                'dias' => $evento->diasRestantes,
            ],
            recurso: RecursoNotificable::Documento,
            recursoId: $evento->documentoId,
            destinatarios: array_map(static fn (int $usuarioId): Destinatario => Destinatario::usuario($usuarioId), $evento->usuariosDestinatarios),
        );
    }
}
