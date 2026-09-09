<?php

namespace App\Dominios\Seguridad\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;

/**
 * Datos fiscales de Agrocom SRL (tarea 78, HU-55): razón social, NIT,
 * domicilio y actividad económica con que se emite una factura, más la
 * leyenda al pie del documento. Fila única (ver la migración) — sin lógica de
 * negocio propia más allá de guardarse, por eso vive como modelo plano en
 * `Infraestructura/` (mismo criterio que `SecUserPreferencia`).
 *
 * No es un secreto: `RegistraBitacora` audita estos campos completos, a
 * diferencia de `Configuracion` (`Compartido`), donde el valor SÍ se excluye.
 */
final class SecDatosFiscales extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'sec_datos_fiscales';

    /** @var list<string> */
    protected $fillable = [
        'razon_social_fiscal',
        'nit',
        'domicilio_fiscal',
        'actividad_economica',
        'leyenda_pie',
    ];
}
