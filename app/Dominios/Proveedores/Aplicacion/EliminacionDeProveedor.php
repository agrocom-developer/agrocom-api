<?php

namespace App\Dominios\Proveedores\Aplicacion;

use App\Dominios\Proveedores\Dominio\TipoSujeto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Piloto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Proveedor;
use App\Dominios\Seguridad\Contratos\ParticipanteDeEliminacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Lo que Proveedores aporta a la eliminación de una cuenta de proveedor o piloto (ADR 0047, puntos 3 y 6): los pilotos
 * vinculados activos de la empresa de la que la cuenta es titular la impiden (hay que liberarlos); al ejecutarla se borran los
 * documentos (con sus archivos) y los datos personales de la ficha: razón social, documento fiscal, teléfono y licencia. La
 * ficha y su historial de estados se conservan porque los servicios y las calificaciones la referencian.
 */
final class EliminacionDeProveedor implements ParticipanteDeEliminacion
{
    public function impedimentos(int $usuarioId): array
    {
        $vinculados = Piloto::query()
            ->whereIn('proveedor_id', $this->proveedores($usuarioId))
            ->where('usuario_id', '!=', $usuarioId)
            ->where('estado', 'aprobado')
            ->exists();

        return $vinculados ? ['pilotos_vinculados'] : [];
    }

    public function alSolicitar(int $usuarioId): void {}

    public function anonimizar(int $usuarioId): void
    {
        $proveedores = $this->proveedores($usuarioId);
        $pilotos = Piloto::query()->where('usuario_id', $usuarioId)->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $disco = (string) config('proveedores.disco_documentos', 'local');

        foreach ([[TipoSujeto::Proveedor->value, $proveedores], [TipoSujeto::Piloto->value, $pilotos]] as [$tipo, $ids]) {
            foreach (DB::table('prv_documentos')->where('sujeto_tipo', $tipo)->whereIn('sujeto_id', $ids)->get(['id', 'archivo_ref']) as $documento) {
                Storage::disk($disco)->delete($documento->archivo_ref);
                DB::table('prv_documentos')->where('id', $documento->id)->delete();
            }
        }

        DB::table('prv_proveedores')->whereIn('id', $proveedores)->update([
            'razon_social' => __('seguridad.eliminacion.nombre_anonimo'),
            'documento_fiscal' => null,
            'telefono' => null,
        ]);

        DB::table('prv_pilotos')->whereIn('id', $pilotos)->update([
            'licencia_numero' => '-',
            'categoria_habilitacion' => '-',
        ]);
    }

    /** @return list<int> */
    private function proveedores(int $usuarioId): array
    {
        return Proveedor::query()->where('usuario_titular_id', $usuarioId)->pluck('id')->map(static fn ($id): int => (int) $id)->all();
    }
}
