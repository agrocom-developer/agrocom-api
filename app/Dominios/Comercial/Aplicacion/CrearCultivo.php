<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Dominio\Excepciones\CultivoDuplicado;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cultivo;
use Illuminate\Database\QueryException;

/**
 * Alta de un cultivo (HU-48, tarea 71): catálogo simple, nombre común único
 * entre cultivos activos (índice parcial `com_cultivos_nombre_comun_unico`).
 *
 * `tipoCultivo`/`cicloVida` viajan como string (valor del enum, no el enum
 * en sí) — mismo criterio que `CrearCliente::tipoPersona`: el cast del
 * modelo los resuelve al asignarlos por `fill()`.
 */
final class CrearCultivo
{
    public function ejecutar(
        string $nombreComun,
        ?string $nombreCientifico,
        ?string $tipoCultivo,
        ?string $cicloVida,
        ?string $notasAgronomicas,
    ): Cultivo {
        $cultivo = new Cultivo([
            'nombre_comun' => $nombreComun,
            'nombre_cientifico' => $nombreCientifico,
            'tipo_cultivo' => $tipoCultivo,
            'ciclo_vida' => $cicloVida,
            'notas_agronomicas' => $notasAgronomicas,
            'activo' => true,
        ]);

        try {
            $cultivo->save();
        } catch (QueryException $excepcion) {
            $this->relanzarComoDuplicado($excepcion, $nombreComun);
        }

        return $cultivo->refresh();
    }

    /**
     * Mismo criterio que `CrearCliente`/`CrearPropiedad`: el formato del mensaje
     * difiere por driver (Postgres nombra el índice; SQLite nombra
     * tabla.columna).
     *
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
