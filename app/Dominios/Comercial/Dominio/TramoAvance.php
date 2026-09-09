<?php

namespace App\Dominios\Comercial\Dominio;

/**
 * Tramo de color de la barra de avance del informe de contratos (HU-52,
 * tarea 75, espec §9.1): cinco tramos, cada uno con su propio token CSS
 * (invariante 11 de CLAUDE.md — ninguno hardcodeado). El quinto tramo
 * (`MasDeCien`) existe porque una campaña puede terminar aplicando más
 * hectáreas de las contratadas — no se descarta por "no debería pasar".
 */
enum TramoAvance: string
{
    case Bajo = '0-33';
    case Medio = '34-66';
    case Alto = '67-99';
    case Cumplido = '100';
    case MasDeCien = 'mas-100';

    public static function desde(int $porcentaje): self
    {
        return match (true) {
            $porcentaje > 100 => self::MasDeCien,
            $porcentaje === 100 => self::Cumplido,
            $porcentaje >= 67 => self::Alto,
            $porcentaje >= 34 => self::Medio,
            default => self::Bajo,
        };
    }
}
