<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Dominio\CicloVidaCultivo;
use App\Dominios\Comercial\Dominio\Excepciones\CultivoDuplicado;
use App\Dominios\Comercial\Dominio\TipoCultivo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cultivo;
use Illuminate\Database\QueryException;

/**
 * Edición de un cultivo (HU-48, tarea 71). Mismas reglas que `CrearCultivo`.
 */
final class ActualizarCultivo
{
    public function ejecutar(
        Cultivo $cultivo,
        string $nombreComun,
        ?string $nombreCientifico,
        ?string $tipoCultivo,
        ?string $cicloVida,
        ?string $notasAgronomicas,
    ): Cultivo {
        $cultivo->nombre_comun = $nombreComun;
        $cultivo->nombre_cientifico = $nombreCientifico;
        $cultivo->tipo_cultivo = $tipoCultivo !== null ? TipoCultivo::from($tipoCultivo) : null;
        $cultivo->ciclo_vida = $cicloVida !== null ? CicloVidaCultivo::from($cicloVida) : null;
        $cultivo->notas_agronomicas = $notasAgronomicas;

        try {
            $cultivo->save();
        } catch (QueryException $excepcion) {
            $this->relanzarComoDuplicado($excepcion, $nombreComun);
        }

        return $cultivo->refresh();
    }

    /**
     * @throws CultivoDuplicado si la violación corresponde al nombre.
     * @throws QueryException si la violación no es la contemplada.
     */
    private function relanzarComoDuplicado(QueryException $excepcion, string $nombreComun): never
    {
        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, 'com_cultivos_nombre_comun_unico') || str_contains($mensaje, 'com_cultivos.nombre_comun')) {
            throw CultivoDuplicado::porNombre($nombreComun);
        }

        throw $excepcion;
    }
}
