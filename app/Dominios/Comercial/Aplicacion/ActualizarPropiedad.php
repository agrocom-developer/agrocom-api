<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Aplicacion\Propiedad\ValidadorUbicacionGeografica;
use App\Dominios\Comercial\Dominio\ColorPropiedad;
use App\Dominios\Comercial\Dominio\Excepciones\PropiedadDuplicada;
use App\Dominios\Comercial\Dominio\Excepciones\UbicacionGeograficaInconsistente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use Illuminate\Database\QueryException;

/**
 * Edición de los datos principales de una propiedad (ADR 0020; ubicación
 * estructurada — adenda 16/9/2026 a ADR 0018 punto 1). Mismas reglas que
 * `CrearPropiedad`.
 *
 * NO toca latitud/longitud/geometría — eso es `ActualizarUbicacionMapaPropiedad`,
 * pantalla aparte (ver docblock de `CrearPropiedad`).
 */
final class ActualizarPropiedad
{
    /**
     * @throws PropiedadDuplicada si el nombre ya pertenece a otra propiedad
     *                            activa del mismo cliente.
     * @throws UbicacionGeograficaInconsistente
     *                                          si la provincia no pertenece al
     *                                          departamento, o el municipio no pertenece
     *                                          a la provincia.
     */
    public function ejecutar(
        Propiedad $propiedad,
        int $clienteId,
        string $nombre,
        ?string $hectareas,
        ?int $departamentoId,
        ?int $provinciaId,
        ?int $municipioId,
        ?string $localidad,
        ?string $color,
    ): Propiedad {
        ValidadorUbicacionGeografica::validar($departamentoId, $provinciaId, $municipioId);

        $propiedad->cliente_id = $clienteId;
        $propiedad->nombre = $nombre;
        $propiedad->hectareas = $hectareas;
        $propiedad->departamento_id = $departamentoId;
        $propiedad->provincia_id = $provinciaId;
        $propiedad->municipio_id = $municipioId;
        $propiedad->localidad = $localidad;
        // Sin color en el pedido conserva el que ya tenía; una propiedad de antes
        // de que el color fuera obligatorio recibe el de por defecto.
        $propiedad->color = $color ?? $propiedad->color ?? ColorPropiedad::porDefecto()->value;

        try {
            $propiedad->save();
        } catch (QueryException $excepcion) {
            $this->relanzarComoDuplicada($excepcion, $nombre);
        }

        return $propiedad->refresh();
    }

    /**
     * @throws PropiedadDuplicada si la violación corresponde al nombre.
     * @throws QueryException si la violación no es la contemplada.
     */
    private function relanzarComoDuplicada(QueryException $excepcion, string $nombre): never
    {
        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, 'com_propiedades_nombre_unico') || str_contains($mensaje, 'com_propiedades.nombre')) {
            throw PropiedadDuplicada::porNombre($nombre);
        }

        throw $excepcion;
    }
}
