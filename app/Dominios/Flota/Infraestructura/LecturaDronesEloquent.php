<?php

namespace App\Dominios\Flota\Infraestructura;

use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Flota\Aplicacion\FotosObligatorias;
use App\Dominios\Flota\Contratos\DatosDron;
use App\Dominios\Flota\Contratos\LecturaDrones;
use App\Dominios\Flota\Infraestructura\Eloquent\Dron;
use Illuminate\Database\Eloquent\Builder;
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

    public function tieneDron(int $proveedorId): bool
    {
        return $this->dronesVivosDe($proveedorId)->exists();
    }

    public function tieneDronConFotosSubidas(int $proveedorId): bool
    {
        $obligatorias = (new FotosObligatorias)->tipos();

        foreach ($this->dronesVivosDe($proveedorId)->pluck('id') as $dronId) {
            $subidas = DB::table('flo_dron_fotos')
                ->where('dron_id', $dronId)
                ->whereIn('estado', ['en_revision', 'aprobada'])
                ->whereNull('deleted_at')
                ->distinct()
                ->pluck('tipo')
                ->map(fn ($tipo): string => (string) $tipo)
                ->all();

            if (array_diff($obligatorias, $subidas) === []) {
                return true;
            }
        }

        return false;
    }

    /** @return Builder<Dron> */
    private function dronesVivosDe(int $proveedorId): Builder
    {
        return Dron::query()
            ->where(fn (Builder $consulta) => $consulta->where('dueno_proveedor_id', $proveedorId)->orWhere('operador_proveedor_id', $proveedorId))
            ->where('estado', '!=', EstadoAprobacion::Baja->value);
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
