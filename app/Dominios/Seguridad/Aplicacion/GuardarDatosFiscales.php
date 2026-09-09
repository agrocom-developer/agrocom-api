<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecDatosFiscales;

/**
 * Persiste los datos fiscales de la empresa (tarea 78, HU-55) — siempre la
 * única fila viva de `sec_datos_fiscales`, nunca una segunda: no hay
 * "alta"/"edición" separadas porque hay una sola empresa.
 */
final class GuardarDatosFiscales
{
    /**
     * @param  array{razon_social_fiscal: string, nit: string, domicilio_fiscal: string, actividad_economica: string, leyenda_pie: ?string}  $datos
     */
    public function ejecutar(array $datos): SecDatosFiscales
    {
        $fiscales = SecDatosFiscales::query()->first() ?? new SecDatosFiscales;

        $fiscales->fill($datos);
        $fiscales->save();

        return $fiscales;
    }
}
