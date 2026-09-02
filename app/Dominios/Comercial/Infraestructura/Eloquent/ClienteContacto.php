<?php

namespace App\Dominios\Comercial\Infraestructura\Eloquent;

use App\Dominios\Comercial\Dominio\TipoContactoCliente;
use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contacto del cliente (insumos §4, tabla com_cliente_contactos): dueño,
 * agrónomo (emite órdenes, firma actas), encargado de la propiedad u otro.
 *
 * `RegistraBitacora` (HU-22, mismo criterio que {@see Cliente}): se edita en
 * la misma operación que el cliente, y su auditoría tiene que quedar tan
 * completa como la de su padre.
 *
 * @property int $id
 * @property int $cliente_id
 * @property TipoContactoCliente $tipo
 * @property string $nombre
 * @property string|null $telefono
 * @property string|null $email
 * @property string|null $observaciones
 */
class ClienteContacto extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'com_cliente_contactos';

    /** @var list<string> */
    protected $fillable = [
        'cliente_id',
        'tipo',
        'nombre',
        'telefono',
        'email',
        'observaciones',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo' => TipoContactoCliente::class,
        ];
    }

    /** @return BelongsTo<Cliente, $this> */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }
}
