<?php

namespace App\Dominios\Solicitudes\Dominio;

/** Qué se aplica en el terreno (especificación del marketplace §4.2). La tarifa depende de este tipo. */
enum TipoAplicacionServicio: string
{
    case Herbicida = 'herbicida';
    case Insecticida = 'insecticida';
    case Fungicida = 'fungicida';
    case FertilizanteFoliar = 'fertilizante_foliar';
    case Desecante = 'desecante';
    case Otro = 'otro';
}
