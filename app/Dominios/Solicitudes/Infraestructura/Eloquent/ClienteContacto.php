<?php

namespace App\Dominios\Solicitudes\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;

/**
 * Persona de referencia de un cliente (`sol_cliente_contactos`): con ella
 * coordina el proveedor el servicio. Sus datos se entregan al proveedor solo
 * tras adjudicar (política de datos del cliente).
 *
 * @property int $id
 * @property int $cliente_id
 * @property string $nombre
 * @property string $telefono
 * @property string|null $cargo
 * @property bool $es_principal
 */
class ClienteContacto extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'sol_cliente_contactos';

    /** @var list<string> */
    protected $fillable = [
        'cliente_id',
        'nombre',
        'telefono',
        'cargo',
        'es_principal',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'cliente_id' => 'integer',
            'es_principal' => 'boolean',
        ];
    }
}
