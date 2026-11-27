<?php

namespace App\Dominios\Flota\Aplicacion;

use App\Dominios\Compartido\Aplicacion\Medios\AlmacenDeMedios;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Seguridad\Contratos\ParticipanteDeEliminacion;
use Illuminate\Support\Facades\DB;

/**
 * Lo que Flota aporta a la eliminación de una cuenta de proveedor (ADR 0047, punto 6; política de eliminación de
 * cuenta: «documentos y fotos del dron … se borran»): al ejecutarla se borran las fotos y los documentos de los
 * drones del proveedor, con su archivo original y sus derivados. El dron y su historial se conservan porque las ofertas
 * y los servicios lo referencian. Los documentos del dron viven en `prv_documentos` (Proveedores) pero cuelgan del dron,
 * que solo Flota conoce: por eso los borra este participante.
 */
final class EliminacionDeFlota implements ParticipanteDeEliminacion
{
    public function __construct(
        private readonly LecturaProveedores $proveedores,
        private readonly AlmacenDeMedios $almacen,
    ) {}

    public function impedimentos(int $usuarioId): array
    {
        return [];
    }

    public function alSolicitar(int $usuarioId): void {}

    public function anonimizar(int $usuarioId): void
    {
        $proveedor = $this->proveedores->proveedorDelTitular($usuarioId);

        if ($proveedor === null) {
            return;
        }

        $drones = DB::table('flo_drones')->where('dueno_proveedor_id', $proveedor->id)->pluck('id');
        $disco = (string) config('proveedores.disco_documentos', 'local');

        foreach (DB::table('prv_documentos')->where('sujeto_tipo', 'dron')->whereIn('sujeto_id', $drones)->get(['id', 'archivo_ref']) as $documento) {
            $this->almacen->borrar($documento->archivo_ref, $disco);
            DB::table('prv_documentos')->where('id', $documento->id)->delete();
        }

        foreach (DB::table('flo_dron_fotos')->whereIn('dron_id', $drones)->get(['id', 'archivo']) as $foto) {
            $this->almacen->borrar($foto->archivo);
            DB::table('flo_dron_fotos')->where('id', $foto->id)->delete();
        }
    }
}
