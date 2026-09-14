<?php

namespace App\Dominios\Mantenimiento\Aplicacion;

use App\Dominios\Mantenimiento\Dominio\Excepciones\FichaDronDuplicada;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\FichaDron;
use Illuminate\Database\QueryException;

/**
 * Alta de una ficha de inventario de dron (HU-82, tarea 97): serie, chasis,
 * versión de software, región, serie del control y accesorios. No valida
 * que `identificadorDron` corresponda a un dron real de `ope_drones` — esa
 * validación cruzada vive en el Request (`Rule::exists()`), la única lectura
 * permitida hacia ese módulo ajeno.
 */
final class CrearFichaDron
{
    /**
     * @throws FichaDronDuplicada si el identificador ya pertenece a otra
     *                            ficha activa (índice parcial
     *                            `man_drones_identificador_dron_unico`).
     */
    public function ejecutar(
        string $identificadorDron,
        ?string $numeroSerie,
        ?string $chasis,
        ?string $versionSoftware,
        ?string $region,
        ?string $serieControl,
        bool $tieneCargadorControl,
        bool $tieneModem,
        bool $tieneMaletin,
    ): FichaDron {
        $ficha = new FichaDron([
            'identificador_dron' => $identificadorDron,
            'numero_serie' => $numeroSerie,
            'chasis' => $chasis,
            'version_software' => $versionSoftware,
            'region' => $region,
            'serie_control' => $serieControl,
            'tiene_cargador_control' => $tieneCargadorControl,
            'tiene_modem' => $tieneModem,
            'tiene_maletin' => $tieneMaletin,
        ]);

        try {
            $ficha->save();
        } catch (QueryException $excepcion) {
            $this->relanzarComoDuplicada($excepcion, $identificadorDron);
        }

        return $ficha->refresh();
    }

    /**
     * Traduce la violación del índice único parcial a una excepción de
     * dominio legible — mismo criterio que
     * `CrearBateria::relanzarComoDuplicada`. El formato del mensaje difiere
     * por driver: Postgres nombra el índice
     * (`man_drones_identificador_dron_unico`); SQLite (motor de los tests)
     * nombra tabla.columna (`man_drones.identificador_dron`).
     *
     * @throws FichaDronDuplicada si la violación corresponde al identificador.
     * @throws QueryException si la violación no es la contemplada.
     */
    private function relanzarComoDuplicada(QueryException $excepcion, string $identificadorDron): never
    {
        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, 'man_drones_identificador_dron_unico') || str_contains($mensaje, 'man_drones.identificador_dron')) {
            throw FichaDronDuplicada::porIdentificador($identificadorDron);
        }

        throw $excepcion;
    }
}
