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
}
