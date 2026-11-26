<?php

namespace App\Dominios\Flota\Aplicacion;

use App\Dominios\Compartido\Dominio\AccionAprobacion;
use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Compartido\Dominio\OrigenCambio;
use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Flota\Infraestructura\Eloquent\Dron;
use App\Dominios\Flota\Infraestructura\Eloquent\HistorialEstadoDron;
use App\Dominios\Proveedores\Contratos\Eventos\DocumentoAprobado;
use App\Dominios\Proveedores\Contratos\Eventos\DocumentoVencido;
use App\Dominios\Proveedores\Contratos\VerificacionDocumental;

/**
 * Cuando vence un documento de un dron, el dron queda suspendido hasta que se
 * cargue uno vigente (ADR 0032, punto 4); cuando se aprueba un documento y el
 * dron ya tiene todos los suyos, se levanta la suspensión que puso el sistema.
 * Una suspensión de un administrador nunca se levanta sola. Los documentos de
 * proveedor y piloto los resuelve `Proveedores`.
 */
final class ReaccionarADocumentosDeDron
{
    public function __construct(
        private readonly VerificacionDocumental $documentos,
        private readonly ResolverAprobacionDron $aprobacion,
    ) {}

    public function alVencer(DocumentoVencido $evento): void
    {
        if ($evento->sujetoTipo !== 'dron') {
            return;
        }

        $dron = Dron::query()->find($evento->sujetoId);

        if ($dron === null || $dron->estado !== EstadoAprobacion::Aprobado) {
            return;
        }

        // Un documento nuevo del mismo tipo, aprobado y vigente, cubre al vencido.
        if (! in_array($evento->tipoDocumento, $this->documentos->faltantes('dron', $dron->id), true)) {
            return;
        }

        $this->aprobacion->ejecutar(
            $dron->id,
            AccionAprobacion::Suspender,
            null,
            Texto::de('proveedores.vencimientos.motivo_suspension', [
                'documento' => mb_strtolower(Texto::de('proveedores.documentos.tipo.'.$evento->tipoDocumento)),
                'fecha' => date('d/m/Y', strtotime($evento->venceEn)),
            ]),
            OrigenCambio::Sistema,
        );
    }

    public function alAprobar(DocumentoAprobado $evento): void
    {
        if ($evento->sujetoTipo !== 'dron') {
            return;
        }

        $dron = Dron::query()->find($evento->sujetoId);

        if ($dron === null || $dron->estado !== EstadoAprobacion::Suspendido) {
            return;
        }

        $ultimo = HistorialEstadoDron::query()->where('dron_id', $dron->id)->orderByDesc('id')->first();

        if ($ultimo === null || $ultimo->origen !== OrigenCambio::Sistema || $ultimo->estado_nuevo !== EstadoAprobacion::Suspendido) {
            return;
        }

        if ($this->documentos->faltantes('dron', $dron->id) !== []) {
            return;
        }

        $this->aprobacion->ejecutar($dron->id, AccionAprobacion::Rehabilitar, null, null, OrigenCambio::Sistema);
    }
}
