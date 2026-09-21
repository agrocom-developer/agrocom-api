<?php

namespace App\Dominios\Comercial\Contratos;

/**
 * Frontera de lectura de Comercial hacia Campania (ADR 0003, regla 2): el
 * resumen de contratos/facturación de una campaña, para el aside de
 * `CampaniasController::resumenCampania()` — nunca las tablas
 * `com_contratos`/`com_facturas` ni sus modelos Eloquent cruzando la
 * frontera hacia Campania.
 */
interface LecturaResumenComercialCampania
{
    public function resumen(int $campaniaId): DatosResumenComercialCampania;
}
