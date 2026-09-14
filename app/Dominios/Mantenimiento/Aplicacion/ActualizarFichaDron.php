<?php

namespace App\Dominios\Mantenimiento\Aplicacion;

use App\Dominios\Mantenimiento\Dominio\Excepciones\FichaDronDuplicada;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\FichaDron;
use Illuminate\Database\QueryException;

/**
 * Edición de una ficha de inventario de dron (HU-82, tarea 97). Mismo
 * criterio que `ActualizarBateria` para la traducción de la violación del
 * índice único parcial.
 */
final class ActualizarFichaDron
{
    /**
     * @throws FichaDronDuplicada si el identificador ya pertenece a otra
     *                            ficha activa (índice parcial
     *                            `man_drones_identificador_dron_unico`).
     */
    public function ejecutar(
        FichaDron $ficha,
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
        $ficha->identificador_dron = $identificadorDron;
        $ficha->numero_serie = $numeroSerie;
        $ficha->chasis = $chasis;
        $ficha->version_software = $versionSoftware;
        $ficha->region = $region;
        $ficha->serie_control = $serieControl;
        $ficha->tiene_cargador_control = $tieneCargadorControl;
        $ficha->tiene_modem = $tieneModem;
        $ficha->tiene_maletin = $tieneMaletin;

        try {
            $ficha->save();
        } catch (QueryException $excepcion) {
            $this->relanzarComoDuplicada($excepcion, $identificadorDron);
        }

        return $ficha->refresh();
    }

    /**
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
