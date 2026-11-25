<?php

namespace App\Dominios\Negociacion\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use Illuminate\Support\Carbon;

/**
 * Mensaje del chat entre el cliente y el proveedor adjudicado (`neg_mensajes`,
 * `negociacion-contrato.md` §5). Es de `tipo` `texto`, `foto` (archivo en el disco
 * privado) o `sistema` (evento que el servidor escribe al cambiar la solicitud, sin autor).
 * El id crece con el orden de creación y es el cursor de la consulta incremental.
 *
 * @property int $id
 * @property string $uuid_cliente
 * @property int $solicitud_id
 * @property int|null $autor_usuario_id nulo en los mensajes de sistema
 * @property string $autor_rol `cliente`, `proveedor` o `sistema`
 * @property string $tipo `texto`, `foto` o `sistema`
 * @property string|null $evento código del evento de sistema
 * @property string $texto texto, pie de la foto (puede ir vacío) o texto del evento
 * @property string|null $archivo
 * @property string|null $hash
 * @property int|null $bytes
 * @property Carbon|null $leido_en
 * @property Carbon $created_at
 */
class Mensaje extends ModeloDominio
{
    protected $table = 'neg_mensajes';

    /** @var list<string> */
    protected $fillable = ['uuid_cliente', 'solicitud_id', 'autor_usuario_id', 'autor_rol', 'tipo', 'evento', 'texto', 'archivo', 'hash', 'bytes'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'solicitud_id' => 'integer',
            'autor_usuario_id' => 'integer',
            'bytes' => 'integer',
            'leido_en' => 'datetime',
        ];
    }
}
