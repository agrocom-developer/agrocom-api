<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Aplicacion\Propiedad\ValidadorUbicacionGeografica;
use App\Dominios\Comercial\Dominio\ColorPropiedad;
use App\Dominios\Comercial\Dominio\Excepciones\PropiedadDuplicada;
use App\Dominios\Comercial\Dominio\Excepciones\UbicacionGeograficaInconsistente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use Illuminate\Database\QueryException;

/**
 * Alta de una propiedad (ADR 0020; ubicación estructurada — adenda
 * 16/9/2026 a ADR 0018 punto 1). Sin sub-entidad propia en esta operación
 * (a diferencia de `CrearCliente`, que trae sus contactos en la misma
 * transacción): los lotes de una propiedad se cargan por su propia
 * pantalla (`LotesController`), no acá.
 *
 * Latitud/longitud/geometría NO se cargan en el alta (ver
 * `ActualizarUbicacionMapaPropiedad`, tarea aparte tal como ADR 0020 ya
 * anticipaba): un registro recién creado no tiene todavía nada que ubicar
 * en el mapa, mismo criterio que el aside de resumen relacionado (§6.3.1 de
 * la guía de pantalla), que tampoco existe en alta.
 */
final class CrearPropiedad
{
    /**
     * @throws PropiedadDuplicada si el nombre ya pertenece a otra propiedad
     *                            activa del mismo cliente (índice parcial
     *                            `com_propiedades_nombre_unico`).
     * @throws UbicacionGeograficaInconsistente
     *                                          si la provincia no pertenece al
     *                                          departamento, o el municipio no pertenece
     *                                          a la provincia.
     */
    public function ejecutar(
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

        $propiedad = new Propiedad([
            'cliente_id' => $clienteId,
            'nombre' => $nombre,
            'hectareas' => $hectareas,
            'departamento_id' => $departamentoId,
            'provincia_id' => $provinciaId,
            'municipio_id' => $municipioId,
            'localidad' => $localidad,
            'color' => $color ?? ColorPropiedad::porDefecto()->value,
        ]);

        try {
            $propiedad->save();
        } catch (QueryException $excepcion) {
            $this->relanzarComoDuplicada($excepcion, $nombre);
        }

        return $propiedad->refresh();
    }

    /**
     * Mismo criterio que `CrearCliente`: el formato del mensaje
     * difiere por driver (Postgres nombra el índice; SQLite, motor de los
     * tests, nombra tabla.columna).
     *
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
