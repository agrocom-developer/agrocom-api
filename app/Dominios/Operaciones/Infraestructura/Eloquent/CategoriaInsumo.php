<?php

namespace App\Dominios\Operaciones\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Operaciones\Dominio\TipoInsumo;

/**
 * Catálogo de categorías de insumo (HU-79, tarea 110, tabla
 * ope_categorias_insumo): qué se aplica y si es sólido o líquido — ver
 * docblock de la migración para el porqué de un modelo propio en vez de un
 * enum embebido.
 *
 * `RegistraBitacora`: mismo criterio que `Cultivo` — aunque hoy el catálogo
 * entra sembrado (sin pantalla de alta), es una mutación de negocio con
 * autor y momento auditables si en el futuro se edita.
 *
 * @property int $id
 * @property string $nombre
 * @property TipoInsumo $tipo_insumo
 */
class CategoriaInsumo extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'ope_categorias_insumo';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'tipo_insumo',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo_insumo' => TipoInsumo::class,
        ];
    }
}
