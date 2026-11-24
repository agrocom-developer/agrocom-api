<?php

namespace App\Dominios\Solicitudes\Aplicacion;

use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Zona;
use Illuminate\Support\Facades\DB;

/**
 * Alta, edición y baja de las zonas de Santa Cruz desde el panel. Una zona la
 * usan los terrenos del cliente y la base de los proveedores, así que solo se da
 * de baja si nadie la usa (soft delete, invariante 8). Las coordenadas viajan
 * como texto decimal exacto. `radio_km` es un campo heredado de la tabla: ya no
 * decide nada y no se pide en el panel; las zonas nuevas nacen con el valor de
 * referencia y las existentes conservan el suyo.
 */
final class GestionarZonas
{
    /** Valor de referencia del campo heredado `radio_km` (la columna es obligatoria). */
    private const RADIO_REFERENCIA_KM = '30.0';

    /** @param  array{nombre: string, macrorregion: string, latitud: string, longitud: string}  $datos */
    public function crear(array $datos): Zona
    {
        return Zona::query()->create([
            'nombre' => trim($datos['nombre']),
            'macrorregion' => $datos['macrorregion'],
            'centroide_lat' => $datos['latitud'],
            'centroide_lng' => $datos['longitud'],
            'radio_km' => self::RADIO_REFERENCIA_KM,
        ]);
    }

    /** @param  array{nombre: string, macrorregion: string, latitud: string, longitud: string}  $datos */
    public function actualizar(Zona $zona, array $datos): Zona
    {
        $zona->fill([
            'nombre' => trim($datos['nombre']),
            'macrorregion' => $datos['macrorregion'],
            'centroide_lat' => $datos['latitud'],
            'centroide_lng' => $datos['longitud'],
        ])->save();

        return $zona;
    }

    /**
     * @return array{terrenos: int, proveedores: int} cuántos registros usan la zona.
     */
    public function usos(int $zonaId): array
    {
        return [
            'terrenos' => DB::table('sol_terrenos')->where('zona_id', $zonaId)->whereNull('deleted_at')->count(),
            'proveedores' => DB::table('prv_proveedores')->where('zona_base_id', $zonaId)->whereNull('deleted_at')->count(),
        ];
    }

    /** @throws SolicitudInvalida si la zona está en uso. */
    public function eliminar(Zona $zona): void
    {
        $usos = $this->usos($zona->id);

        if ($usos['terrenos'] > 0 || $usos['proveedores'] > 0) {
            throw SolicitudInvalida::zonaEnUso($usos['terrenos'], $usos['proveedores']);
        }

        $zona->delete();
    }
}
