<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Dominio\EstadoEstadia;
use App\Dominios\Operaciones\Dominio\Excepciones\EstadiaYaFinalizada;
use App\Dominios\Operaciones\Dominio\TipoAlojamiento;
use App\Dominios\Operaciones\Infraestructura\Eloquent\EstadiaHacienda;
use Carbon\CarbonImmutable;

/**
 * Edición de una estadía en hacienda (reforma 19/9/2026), SOLO mientras está
 * EN CURSO: una estadía `finalizada` ya cuenta días para justificar gastos,
 * así que no se corrige — se da de baja (`Aplicacion/EliminarEstadiaHacienda`)
 * y, si corresponde, se vuelve a registrar. La guarda pasa por
 * {@see EstadoEstadia} (invariante 7 de CLAUDE.md: el estado, aunque
 * derivado, no se decide a mano en el controller).
 *
 * La cuadrilla (`equipo_trabajo_id`) NO es un campo editable acá — cambiarla
 * es, en los hechos, otra estadía; lo que se corrige es propiedad, entrada,
 * alojamiento, vehículo y observación.
 */
final class ActualizarEstadiaHacienda
{
    /** @throws EstadiaYaFinalizada si la estadía ya no está en curso. */
    public function ejecutar(
        EstadiaHacienda $estadia,
        int $propiedadId,
        string $entrada,
        TipoAlojamiento $tipoAlojamiento,
        ?int $vehiculoId,
        ?string $observacion,
    ): EstadiaHacienda {
        if ($estadia->estado() === EstadoEstadia::Finalizada) {
            throw EstadiaYaFinalizada::porId($estadia->id);
        }

        $estadia->propiedad_id = $propiedadId;
        $estadia->entrada = CarbonImmutable::parse($entrada)->utc();
        $estadia->tipo_alojamiento = $tipoAlojamiento;
        $estadia->vehiculo_id = $vehiculoId;
        $estadia->observacion = $observacion;
        $estadia->save();

        return $estadia->refresh();
    }
}
