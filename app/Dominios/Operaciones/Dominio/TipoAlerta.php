<?php

namespace App\Dominios\Operaciones\Dominio;

/**
 * Catálogo de alertas por excepción que este sistema genera hoy (espec §10,
 * HU-19, tarea 26) — 4 de las 13 de la tabla completa, las que ya tienen
 * datos reales detrás. Ver el docblock de la migración `ope_alertas` para el
 * porqué del recorte.
 */
enum TipoAlerta: string
{
    case BateriaCaliente = 'bateria_caliente';
    case DronSospechoso = 'dron_sospechoso';
    case CondicionesForzadas = 'condiciones_forzadas';
    case SumaExcedida = 'suma_excedida';
}
