<?php

namespace App\Dominios\Proveedores\Aplicacion\MaquinaEstados;

use App\Dominios\Proveedores\Dominio\EstadoDocumento;
use App\Dominios\Proveedores\Dominio\Excepciones\RegistroInvalido;
use App\Dominios\Proveedores\Dominio\TransicionesDocumento;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Documento;
use Illuminate\Support\Carbon;

/**
 * Única clase que crea y cambia el `estado` de un documento de proveedor
 * (invariante 7 de CLAUDE.md): consulta {@see TransicionesDocumento} antes de
 * escribir. Un documento nace `en_revision`; quién lo revisa y el motivo del
 * rechazo se fijan junto con la transición.
 */
final class MaquinaEstadosDocumento
{
    /** @param  array<string, mixed>  $atributos  sin `estado`: lo fija esta clase. */
    public function cargar(array $atributos): Documento
    {
        return Documento::query()->create([...$atributos, 'estado' => EstadoDocumento::EnRevision]);
    }

    /** @throws RegistroInvalido si el documento ya no está en revisión. */
    public function aprobar(Documento $documento, int $actorUsuarioId): Documento
    {
        $this->transicionar($documento, EstadoDocumento::Aprobado);

        $documento->revisado_por = $actorUsuarioId;
        $documento->revisado_en = Carbon::now();
        $documento->motivo_rechazo = null;
        $documento->save();

        return $documento;
    }

    /** @throws RegistroInvalido si el documento ya no está en revisión. */
    public function rechazar(Documento $documento, int $actorUsuarioId, string $motivo): Documento
    {
        $this->transicionar($documento, EstadoDocumento::Rechazado);

        $documento->revisado_por = $actorUsuarioId;
        $documento->revisado_en = Carbon::now();
        $documento->motivo_rechazo = trim($motivo);
        $documento->save();

        return $documento;
    }

    /** @throws RegistroInvalido si el documento ya venció o fue rechazado. */
    public function vencer(Documento $documento): Documento
    {
        $this->transicionar($documento, EstadoDocumento::Vencido);
        $documento->save();

        return $documento;
    }

    private function transicionar(Documento $documento, EstadoDocumento $hacia): void
    {
        if (! TransicionesDocumento::permitida($documento->estado, $hacia)) {
            throw RegistroInvalido::documentoYaRevisado();
        }

        $documento->estado = $hacia;
    }
}
