<?php

namespace App\Dominios\Calificaciones\Dominio;

/**
 * Por qué un administrador oculta una calificación (política de calificación,
 * «Moderación»): insultos o datos personales, un factor ajeno al proveedor (clima
 * o demora del cliente), una represalia tras una disputa o una cuenta sin servicio
 * real.
 */
enum CausaModeracion: string
{
    case InsultosODatosPersonales = 'insultos_o_datos_personales';
    case CausaAjena = 'causa_ajena';
    case Represalia = 'represalia';
    case SinServicioReal = 'sin_servicio_real';
    case Otra = 'otra';
}
