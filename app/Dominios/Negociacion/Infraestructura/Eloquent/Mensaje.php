<?php

namespace App\Dominios\Negociacion\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use Illuminate\Support\Carbon;

/**
 * Mensaje del chat entre el cliente y el proveedor adjudicado (`neg_mensajes`,
 * `negociacion-contrato.md` §5). Sin adjuntos: las fotos van como evidencia. El
 * id crece con el orden de creación y es el cursor de la consulta incremental.
 *
 * @property int $id
 * @property string $uuid_cliente
 * @property int $solicitud_id
 * @property int $autor_usuario_id
 * @property string $autor_rol `cliente` o `proveedor`
 * @property string $texto
 * @property Carbon|null $leido_en
 * @property Carbon $created_at
 */
class Mensaje extends ModeloDominio
{
    protected $table = 'neg_mensajes';

    /** @var list<string> */
    protected $fillable = ['uuid_cliente', 'solicitud_id', 'autor_usuario_id', 'autor_rol', 'texto'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'solicitud_id' => 'integer',
            'autor_usuario_id' => 'integer',
            'leido_en' => 'datetime',
        ];
    }
}
