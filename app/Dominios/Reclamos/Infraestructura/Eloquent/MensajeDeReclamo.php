<?php

namespace App\Dominios\Reclamos\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Support\Carbon;

/**
 * Mensaje de la conversación de un reclamo (`rcl_mensajes`): del cliente, del
 * proveedor o de soporte. Solo se inserta.
 *
 * @property int $id
 * @property int $reclamo_id
 * @property int $autor_usuario_id
 * @property string $autor_rol `cliente`, `proveedor` o `soporte`.
 * @property string $tipo `mensaje`, `informacion` (soporte pide información) o `apelacion`.
 * @property string $texto
 * @property Carbon $created_at
 */
class MensajeDeReclamo extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'rcl_mensajes';

    /** @var list<string> */
    protected $fillable = ['reclamo_id', 'autor_usuario_id', 'autor_rol', 'tipo', 'texto'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'reclamo_id' => 'integer',
            'autor_usuario_id' => 'integer',
        ];
    }
}
