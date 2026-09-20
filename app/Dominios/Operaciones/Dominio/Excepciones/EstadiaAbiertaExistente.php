<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * Traducción legible de la violación del índice único parcial
 * `ope_estadias_hacienda_equipo_abierta_unico` (mismo mecanismo que ya usa el
 * motor de sync en `EscrituraSincronizacionEloquent::resultadoAperturaEstadiaDesdeExcepcion()`,
 * ahora también para el alta desde el panel, `Aplicacion/RegistrarEstadiaHacienda`):
 * una cuadrilla no puede tener dos estadías EN CURSO a la vez (`salida IS
 * NULL`). El caso de uso captura la `QueryException` y la relanza como esta
 * excepción — nunca deja propagarse el 500 crudo del motor de base de datos.
 *
 * El formato del mensaje difiere por driver: Postgres nombra el índice;
 * SQLite (motor de los tests) nombra tabla.columna — mismo criterio que
 * `DronDuplicado`.
 */
final class EstadiaAbiertaExistente extends RuntimeException
{
    public static function porEquipoTrabajoId(int $equipoTrabajoId): self
    {
        return new self(Texto::de('operaciones.errores.estadia_abierta_existente', ['id' => $equipoTrabajoId]));
    }
}
