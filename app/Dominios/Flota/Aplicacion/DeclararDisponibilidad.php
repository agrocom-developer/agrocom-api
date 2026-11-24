<?php

namespace App\Dominios\Flota\Aplicacion;

use App\Dominios\Flota\Contratos\LecturaEquipos;
use App\Dominios\Flota\Dominio\Excepciones\OperacionDeFlotaInvalida;
use App\Dominios\Flota\Infraestructura\Eloquent\Disponibilidad;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * El proveedor (o su piloto) se pone «en línea» o «fuera de línea» desde la app
 * (`PUT /disponibilidad`, especificación §4.4 y §10.2). Opera sobre los equipos
 * vigentes del proveedor de la cuenta; un piloto que no es titular opera solo
 * sobre los equipos en los que está. Con `equipo_id` afecta a uno; sin él, a
 * todos los suyos. Idempotente: repetir la misma declaración deja lo mismo.
 */
final class DeclararDisponibilidad
{
    public function __construct(
        private readonly LecturaProveedores $proveedores,
        private readonly LecturaEquipos $equipos,
    ) {}

    /**
     * @return list<Disponibilidad>
     *
     * @throws OperacionDeFlotaInvalida
     */
    public function ejecutar(int $usuarioId, bool $enLinea, ?string $lat, ?string $lng, ?int $equipoId = null): array
    {
        $propios = $this->equiposDeLaCuenta($usuarioId);

        if ($equipoId !== null) {
            if (! in_array($equipoId, $propios, true)) {
                throw OperacionDeFlotaInvalida::equipoAjeno();
            }

            $propios = [$equipoId];
        }

        if ($propios === []) {
            throw OperacionDeFlotaInvalida::sinEquipos();
        }

        return DB::transaction(function () use ($propios, $enLinea, $lat, $lng): array {
            $filas = [];

            foreach ($propios as $id) {
                $fila = Disponibilidad::query()->firstOrNew(['equipo_id' => $id]);
                $fila->fill([
                    'en_linea' => $enLinea,
                    // Una ubicación nueva reemplaza a la anterior; sin ella se conserva la última conocida.
                    'ubicacion_lat' => $lat ?? $fila->ubicacion_lat,
                    'ubicacion_lng' => $lng ?? $fila->ubicacion_lng,
                    'actualizado_en' => Carbon::now(),
                ])->save();

                $filas[] = $fila;
            }

            return $filas;
        });
    }

    /**
     * @return list<Disponibilidad>
     */
    public function consultar(int $usuarioId): array
    {
        $ids = $this->equiposDeLaCuenta($usuarioId);

        return $ids === [] ? [] : Disponibilidad::query()->whereIn('equipo_id', $ids)->orderBy('equipo_id')->get()->all();
    }

    /** @return list<int> */
    public function equiposDeLaCuenta(int $usuarioId): array
    {
        $titular = $this->proveedores->proveedorDelTitular($usuarioId);

        if ($titular !== null) {
            return $this->equipos->vigentesDe($titular->id);
        }

        return $this->equipos->equiposDeCuenta($usuarioId);
    }
}
