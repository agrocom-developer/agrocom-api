<?php

namespace App\Dominios\Proveedores\Aplicacion;

use App\Dominios\Compartido\Aplicacion\Medios\AlmacenDeMedios;
use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Dominio\Excepciones\MotivoRequerido;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Proveedores\Dominio\EstadoDocumento;
use App\Dominios\Proveedores\Dominio\Excepciones\RegistroInvalido;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Documento;
use Illuminate\Support\Facades\DB;

/**
 * Un administrador elimina un documento de verificación (proveedor, piloto o dron). ZONA CRÍTICA: son archivos de
 * personas.
 *
 * - **Borrado lógico** (ADR 0007): la fila queda marcada con `deleted_at` y deja de listarse; la bitácora
 *   (`RegistraBitacora`) registra quién y cuándo, y este caso de uso agrega una constancia con el motivo. Nunca
 *   entra el contenido ni el nombre del archivo.
 * - **El archivo sí se borra, y de inmediato** (original y derivados, por {@see AlmacenDeMedios}): un documento de
 *   identidad o de licencia es dato personal y el borrado lógico no lo protege (ADR 0047). Ninguna pantalla
 *   restaura documentos, así que diferir el borrado físico solo dejaría el archivo sin dueño ni uso. Decisión
 *   revisable: si se agrega «restaurar», el borrado físico debe pasar a un barrido diferido.
 * - **Motivo obligatorio.**
 * - **Regla de dominio:** no se elimina un documento aprobado y vigente de un sujeto que sigue aprobado, porque lo
 *   dejaría operando sin su verificación; primero se suspende al sujeto (o se espera el vencimiento).
 */
final class EliminarDocumento
{
    public function __construct(
        private readonly ResolverSujeto $sujetos,
        private readonly AlmacenDeMedios $almacen,
    ) {}

    /**
     * @throws MotivoRequerido si falta el motivo.
     * @throws RegistroInvalido si el documento está aprobado y vigente y su sujeto sigue aprobado.
     */
    public function ejecutar(int $documentoId, int $actorUsuarioId, ?string $motivo): void
    {
        $motivo = trim((string) $motivo);

        if ($motivo === '') {
            throw new MotivoRequerido(Texto::de('proveedores.documentos.eliminar.motivo_requerido'));
        }

        $archivo = DB::transaction(function () use ($documentoId, $actorUsuarioId, $motivo): array {
            $documento = Documento::query()->lockForUpdate()->findOrFail($documentoId);

            if ($this->esAprobadoVigenteDeSujetoAprobado($documento)) {
                throw RegistroInvalido::documentoVigenteDeSujetoAprobado();
            }

            $archivo = [$documento->archivo_ref, (string) config('proveedores.disco_documentos', 'local')];

            $documento->delete();

            Bitacora::query()->create([
                'user_id' => $actorUsuarioId,
                'tabla' => $documento->getTable(),
                'registro_id' => $documento->id,
                'accion' => AccionBitacora::Eliminado,
                'antes' => null,
                'despues' => ['motivo' => $motivo, 'archivo_retirado' => true],
                'zona_horaria' => DB::table('sec_user_preferencia')->where('user_id', $actorUsuarioId)->value('zona_horaria'),
            ]);

            return $archivo;
        });

        // Fuera de la transacción: un fallo del disco no deshace la baja lógica, y borrar es idempotente.
        $this->almacen->borrar($archivo[0], $archivo[1]);
    }

    private function esAprobadoVigenteDeSujetoAprobado(Documento $documento): bool
    {
        if ($documento->estado !== EstadoDocumento::Aprobado) {
            return false;
        }

        if ($documento->vence_en !== null && $documento->vence_en->toDateString() < now()->toDateString()) {
            return false;
        }

        return $this->sujetos->ejecutar($documento->sujeto_tipo, $documento->sujeto_id)?->estado === 'aprobado';
    }
}
