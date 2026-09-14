<?php

namespace App\Dominios\Mantenimiento\Aplicacion;

use App\Dominios\Mantenimiento\Dominio\EstadoBateria;
use App\Dominios\Mantenimiento\Dominio\Excepciones\BateriaDuplicada;
use App\Dominios\Mantenimiento\Dominio\Excepciones\CorreccionCiclosNoAutorizada;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Bateria;
use Illuminate\Database\QueryException;

/**
 * Edición de una batería del catálogo (HU-39, tarea 51), incluidos sus
 * ciclos acumulados: a diferencia de un monto ya validado (p. ej.
 * `fin_gastos`), acá SÍ se espera actualizar el contador con el uso — es el
 * dato que dispara la alerta de retiro (ver `ListarBaterias`). Mismo
 * criterio que `ActualizarVehiculo` para la traducción de la violación del
 * índice único parcial.
 *
 * `ciclos_inicial` (HU-83, tarea 98) NO se recibe acá a propósito: es
 * inmutable después del alta (fijado por `CrearBateria`), ese es el
 * criterio de aceptación central de la HU. `ActualizarBateriaRequest`
 * tampoco lo valida, así que ni siquiera llega en `$datos`.
 *
 * `ciclos_acumulados` es un odómetro (HU-87, tarea 102): sube solo con cada
 * recarga real (`IncrementarCiclosBateria`, oyente de `RecargaRegistrada`).
 * Bajarlo a mano SIN dejar rastro de por qué queda cerrado —
 * `$motivoCorreccion` es la única puerta: nullable porque la inmensa
 * mayoría de las ediciones no bajan el contador y no deberían tener que
 * justificar nada, pero obligatorio en el momento en que sí se baja. No hay
 * tabla de corrección tipo `anula_a_id` (invariante 2 de CLAUDE.md: eso es
 * para registros validados de un flujo de dominio, y un contador de
 * catálogo no lo es) — la bitácora automática de `Bateria`
 * (`RegistraBitacora`, invariante 9) ya deja antes/después + autor +
 * momento, así que el motivo en sí no se persiste, solo autoriza la baja.
 */
final class ActualizarBateria
{
    /**
     * @throws BateriaDuplicada si el identificador ya pertenece a otra
     *                          batería activa (índice parcial
     *                          `man_baterias_identificador_unico`).
     * @throws CorreccionCiclosNoAutorizada si `$ciclosAcumulados` baja el
     *                                      contador actual sin
     *                                      `$motivoCorreccion`.
     */
    public function ejecutar(Bateria $bateria, string $identificador, int $ciclosAcumulados, ?int $baseId, EstadoBateria $estado, ?string $motivoCorreccion = null): Bateria
    {
        if ($ciclosAcumulados < $bateria->ciclos_acumulados && ($motivoCorreccion === null || $motivoCorreccion === '')) {
            throw CorreccionCiclosNoAutorizada::porBaja($bateria->identificador, $bateria->ciclos_acumulados, $ciclosAcumulados);
        }

        $bateria->identificador = $identificador;
        $bateria->ciclos_acumulados = $ciclosAcumulados;
        $bateria->base_id = $baseId;
        $bateria->estado = $estado->value;

        try {
            $bateria->save();
        } catch (QueryException $excepcion) {
            $this->relanzarComoDuplicada($excepcion, $identificador);
        }

        return $bateria->refresh();
    }

    /**
     * @throws BateriaDuplicada si la violación corresponde al identificador.
     * @throws QueryException si la violación no es la contemplada.
     */
    private function relanzarComoDuplicada(QueryException $excepcion, string $identificador): never
    {
        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, 'man_baterias_identificador_unico') || str_contains($mensaje, 'man_baterias.identificador')) {
            throw BateriaDuplicada::porIdentificador($identificador);
        }

        throw $excepcion;
    }
}
