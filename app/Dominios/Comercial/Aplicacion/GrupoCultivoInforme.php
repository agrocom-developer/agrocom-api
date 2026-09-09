<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Dominio\TramoAvance;

/**
 * Grupo de cultivo del informe de avance (HU-52, tarea 75, espec §9.1). Sirve
 * a las dos pestañas con la misma cuenta hecha una sola vez:
 *
 * - "Por cultivo" usa `contratos` (lista plana, ordenada por vencimiento).
 * - "Por cliente" usa `clientes` (los mismos contratos, agrupados dentro de
 *   este cultivo).
 *
 * Un mismo contrato puede aparecer en más de un `GrupoCultivoInforme`: el
 * contrato es de una campaña completa (todo el campo del cliente, ver
 * memoria "la campaña es por todo el campo"), y esa campaña puede tener
 * lotes de más de un cultivo (`com_lote_campania`). El contrato no distingue
 * hectáreas por lote, así que no hay forma de partir sus hectáreas entre
 * cultivos — aparece entero en cada grupo de cultivo al que pertenece su
 * campaña, y el totalizador de cada grupo sigue cuadrando contra sus PROPIOS
 * contratos (no hay una suma global entre grupos que deba cuadrar).
 */
final readonly class GrupoCultivoInforme
{
    /**
     * @param  list<FilaContratoInforme>  $contratos  ordenados por vencimiento más cercano.
     * @param  list<GrupoClienteInforme>  $clientes
     */
    public function __construct(
        public int $cultivoId,
        public string $cultivoNombre,
        public string $hectareasContratadas,
        public string $hectareasAplicadas,
        public string $hectareasAAplicar,
        public int $porcentaje,
        public TramoAvance $tramo,
        public array $contratos,
        public array $clientes,
    ) {}
}
