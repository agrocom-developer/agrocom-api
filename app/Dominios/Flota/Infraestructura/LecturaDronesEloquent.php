<?php

namespace App\Dominios\Flota\Infraestructura;

use App\Dominios\Flota\Contratos\DatosDron;
use App\Dominios\Flota\Contratos\LecturaDrones;
use App\Dominios\Flota\Infraestructura\Eloquent\Dron;
use Illuminate\Support\Facades\DB;

/** Lectura de drones para otros módulos (ver {@see LecturaDrones}). */
final class LecturaDronesEloquent implements LecturaDrones
{
    public function dron(int $id): ?DatosDron
    {
        $dron = Dron::query()->find($id);

        return $dron === null ? null : new DatosDron(
            $dron->id,
            $dron->dueno_proveedor_id,
            $dron->operador_proveedor_id,
            $dron->marca,
            $dron->modelo,
            $dron->numero_serie,
            $dron->estado->value,
            $dron->anio,
            (string) $dron->capacidad_tanque_l,
        );
    }

    public function fotosAprobadas(int $dronId): array
    {
        return DB::table('flo_dron_fotos')
            ->where('dron_id', $dronId)
            ->where('estado', 'aprobada')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'tipo'])
            ->map(fn (object $foto): array => ['id' => (int) $foto->id, 'tipo' => (string) $foto->tipo])
            ->all();
    }
}
