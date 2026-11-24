<?php

namespace App\Dominios\Negociacion\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;

/**
 * Foto de avance, final o de incidencia de un servicio (`neg_evidencias`,
 * `negociacion-contrato.md` §4): comprimida a menos de 300 KB, en la cola separada
 * de los registros livianos. El archivo vive en el disco privado; nunca en una URL pública.
 *
 * @property int $id
 * @property string $uuid_cliente
 * @property int $solicitud_id
 * @property int|null $avance_id
 * @property string $tipo
 * @property string $archivo
 * @property string $hash
 * @property int $bytes
 * @property int|null $usuario_id
 */
class Evidencia extends ModeloDominio
{
    protected $table = 'neg_evidencias';

    /** @var list<string> */
    protected $fillable = ['uuid_cliente', 'solicitud_id', 'avance_id', 'tipo', 'archivo', 'hash', 'bytes', 'usuario_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['solicitud_id' => 'integer', 'avance_id' => 'integer', 'bytes' => 'integer', 'usuario_id' => 'integer'];
    }
}
