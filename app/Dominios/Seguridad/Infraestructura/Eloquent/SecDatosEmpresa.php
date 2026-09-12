<?php

namespace App\Dominios\Seguridad\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;

/**
 * Datos de presentación de Agrocom SRL: nombre, rubro y contacto que se
 * muestran en la pestaña "Organización" de `/panel/organizacion`. Fila única
 * (ver la migración) — sin lógica de negocio propia más allá de guardarse,
 * mismo criterio que `SecDatosFiscales`.
 *
 * `logo_path` deliberadamente NO es `fillable` (ADR 0019): solo
 * `GuardarDatosEmpresa` lo escribe, con asignación directa tras guardar el
 * archivo — nunca vía `fill()` de datos crudos del request.
 */
final class SecDatosEmpresa extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'sec_datos_empresa';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'rubro',
        'email',
        'telefono',
        'direccion',
    ];
}
