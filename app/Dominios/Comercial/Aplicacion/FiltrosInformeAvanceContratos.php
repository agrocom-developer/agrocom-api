<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Dominio\SaldoContrato;

/**
 * Filtros del informe de avance de contratos (HU-52, tarea 75, espec §9.1).
 * `clienteIds` y `cultivoIds` son la entrada obligatoria de la pantalla de
 * inicio — sin al menos uno de cada uno no se genera el informe (lo aplica
 * el request y, como guarda de dominio, {@see ObtenerInformeAvanceContratos}).
 * El resto son los filtros de la pantalla aparte (espec §9.1, punto 2).
 *
 * `fechaDesde`/`fechaHasta` viajan como `Y-m-d` (mismo criterio permisivo que
 * `ListarGastos::$periodo`): las compone el request, no hace falta un value
 * object de fecha para comparar contra columnas `date` de Postgres/SQLite.
 */
final readonly class FiltrosInformeAvanceContratos
{
    /**
     * @param  list<int>  $clienteIds
     * @param  list<int>  $cultivoIds
     * @param  list<int>  $campaniaIds
     */
    public function __construct(
        public array $clienteIds,
        public array $cultivoIds,
        public array $campaniaIds = [],
        public ?string $fechaDesde = null,
        public ?string $fechaHasta = null,
        public ?EstadoContrato $estado = null,
        public ?SaldoContrato $saldo = null,
        public bool $incluirDeshabilitados = false,
    ) {}
}
