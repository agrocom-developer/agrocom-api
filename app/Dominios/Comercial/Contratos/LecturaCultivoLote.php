<?php

namespace App\Dominios\Comercial\Contratos;

/**
 * Frontera de lectura de Comercial hacia otros módulos (ADR 0003, regla 2):
 * el informe de avance de HU-48 (tarea 75) necesita agrupar por cultivo
 * dentro de una campaña, sin importar los modelos Eloquent `LoteCampania`,
 * `Lote` ni `Cultivo` de este módulo.
 */
interface LecturaCultivoLote
{
    /**
     * Siembras vigentes (no borradas) de una campaña, una por lote.
     *
     * @return list<CultivoLotePorCampania>
     */
    public function porCampania(int $campaniaId): array;

    /**
     * Siembras vigentes de ESOS lotes en ESAS campañas (21/9/2026): lo que
     * necesita una pantalla que ya sabe qué lotes va a dibujar —la orden de
     * aplicación, con los lotes de su contrato— sin traerse la campaña entera.
     *
     * @param  list<int>  $loteIds
     * @param  list<int>  $campaniaIds
     * @return list<CultivoLotePorCampania>
     */
    public function deLotes(array $loteIds, array $campaniaIds): array;
}
