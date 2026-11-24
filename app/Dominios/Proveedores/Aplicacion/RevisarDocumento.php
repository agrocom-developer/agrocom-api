<?php

namespace App\Dominios\Proveedores\Aplicacion;

use App\Dominios\Compartido\Dominio\AccionAprobacion;
use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Compartido\Dominio\Excepciones\MotivoRequerido;
use App\Dominios\Compartido\Dominio\OrigenCambio;
use App\Dominios\Proveedores\Aplicacion\MaquinaEstados\MaquinaEstadosDocumento;
use App\Dominios\Proveedores\Contratos\Eventos\DocumentoAprobado;
use App\Dominios\Proveedores\Dominio\EstadoDocumento;
use App\Dominios\Proveedores\Dominio\Excepciones\RegistroInvalido;
use App\Dominios\Proveedores\Dominio\TipoSujeto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Documento;
use App\Dominios\Proveedores\Infraestructura\Eloquent\HistorialEstado;
use Illuminate\Support\Facades\DB;

/**
 * Un administrador aprueba o rechaza un documento en revisión (ADR 0032,
 * punto 3). El rechazo exige motivo. Al aprobar, si el sujeto estaba suspendido
 * por el sistema a causa de un documento vencido y ahora tiene todos sus
 * documentos vigentes, se levanta la suspensión sin esperar a nadie («hasta
 * que se cargue uno vigente», especificación §5.2). Una suspensión puesta por
 * un administrador nunca se levanta sola.
 */
final class RevisarDocumento
{
    public function __construct(
        private readonly EstadoDocumentalDeSujeto $estadoDocumental,
        private readonly ResolverAprobacion $aprobacion,
        private readonly MaquinaEstadosDocumento $estados,
    ) {}

    /**
     * @throws RegistroInvalido si el documento ya fue revisado o, al aprobarlo, ya venció.
     * @throws MotivoRequerido si se rechaza sin motivo.
     */
    public function aprobar(int $documentoId, int $actorUsuarioId): Documento
    {
        $documento = DB::transaction(function () use ($documentoId, $actorUsuarioId): Documento {
            $documento = $this->bloquear($documentoId);

            if ($documento->vence_en !== null && $documento->vence_en->toDateString() < now()->toDateString()) {
                throw RegistroInvalido::documentoVencido();
            }

            $this->estados->aprobar($documento, $actorUsuarioId);

            event(new DocumentoAprobado($documento->id, $documento->sujeto_tipo->value, $documento->sujeto_id, $documento->tipo->value));

            return $documento;
        });

        $this->levantarSuspensionAutomatica($documento);

        return $documento;
    }

    public function rechazar(int $documentoId, int $actorUsuarioId, ?string $motivo): Documento
    {
        if (trim((string) $motivo) === '') {
            throw MotivoRequerido::para(AccionAprobacion::Rechazar);
        }

        return DB::transaction(function () use ($documentoId, $actorUsuarioId, $motivo): Documento {
            $documento = $this->bloquear($documentoId);

            return $this->estados->rechazar($documento, $actorUsuarioId, (string) $motivo);
        });
    }

    private function bloquear(int $documentoId): Documento
    {
        $documento = Documento::query()->lockForUpdate()->findOrFail($documentoId);

        if ($documento->estado !== EstadoDocumento::EnRevision) {
            throw RegistroInvalido::documentoYaRevisado();
        }

        return $documento;
    }

    /**
     * El dron lo resuelve Flota al escuchar `DocumentoAprobado`; acá solo
     * proveedor, piloto y auxiliar.
     */
    private function levantarSuspensionAutomatica(Documento $documento): void
    {
        $tipo = $documento->sujeto_tipo;

        if ($tipo === TipoSujeto::Dron) {
            return;
        }

        $ultimo = HistorialEstado::query()
            ->where('sujeto_tipo', $tipo->value)
            ->where('sujeto_id', $documento->sujeto_id)
            ->orderByDesc('id')
            ->first();

        $suspendidoPorElSistema = $ultimo !== null
            && $ultimo->estado_nuevo === EstadoAprobacion::Suspendido
            && $ultimo->origen === OrigenCambio::Sistema;

        if (! $suspendidoPorElSistema) {
            return;
        }

        if ($this->estadoDocumental->faltantesDe($tipo, $documento->sujeto_id) !== []) {
            return;
        }

        $this->aprobacion->ejecutar(
            $tipo,
            $documento->sujeto_id,
            AccionAprobacion::Rehabilitar,
            null,
            null,
            OrigenCambio::Sistema,
        );
    }
}
