<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * Guarda de `Aplicacion/RegistrarEstadiaHacienda` y `Aplicacion/FinalizarEstadiaHacienda`:
 * la salida de una estadía no puede ser anterior a (ni el mismo instante que)
 * su entrada — mismo criterio de exactitud que `PausaFinAnteriorAInicio`, y
 * la misma regla que ya aplica `EscrituraSincronizacionEloquent::cerrarEstadia()`
 * al evento `estadia_salida` del sync. Replica en el caso de uso el `CHECK
 * (salida IS NULL OR salida > entrada)` de la migración original de la
 * tabla, que SQLite no puede probar en la suite local.
 */
final class SalidaAnteriorAEntrada extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(Texto::de('operaciones.errores.estadia_salida_anterior_a_entrada'));
    }
}
