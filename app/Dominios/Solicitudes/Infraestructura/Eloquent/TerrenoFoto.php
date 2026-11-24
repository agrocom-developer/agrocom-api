<?php

namespace App\Dominios\Solicitudes\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Foto del estado de un terreno (`sol_terreno_fotos`): la sube el cliente y la ve el
 * piloto antes de ofertar. El archivo vive en el disco privado.
 *
 * @property int $id
 * @property int $terreno_id
 * @property string $archivo
 * @property string $hash
 * @property int $bytes
 * @property string|null $descripcion
 */
class TerrenoFoto extends ModeloDominio
{
    protected $table = 'sol_terreno_fotos';

    /** @var list<string> */
    protected $fillable = ['terreno_id', 'archivo', 'hash', 'bytes', 'descripcion'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['terreno_id' => 'integer', 'bytes' => 'integer'];
    }

    /** @return BelongsTo<Terreno, $this> */
    public function terreno(): BelongsTo
    {
        return $this->belongsTo(Terreno::class, 'terreno_id');
    }
}
