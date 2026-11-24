<?php

namespace App\Dominios\Asignacion\Dominio;

/** Por qué un proveedor no recibe una oferta (especificación §6, puntos 1 y 7). */
enum MotivoExclusion: string
{
    case SinEquipoLibre = 'sin_equipo_libre';
    case SancionVigente = 'sancion_vigente';
    case CalificacionBajoUmbral = 'calificacion_bajo_umbral';
    case FueraDelRadioDeOperacion = 'fuera_del_radio_de_operacion';
    case FueraDelRadioDeLaRonda = 'fuera_del_radio_de_la_ronda';
    case SinUbicacion = 'sin_ubicacion';
}
