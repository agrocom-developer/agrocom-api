<?php

namespace App\Dominios\Comercial\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Campo (propiedad) del cliente (espec §4.1, tabla com_campos).
 *
 * @property int $id
 * @property int $cliente_id
 * @property string $nombre
 * @property string|null $ubicacion
 */
class Campo extends ModeloDominio
{
    protected $table = 'com_campos';

    /** @var list<string> */
    protected $fillable = [
        'cliente_id',
        'nombre',
        'ubicacion',
    ];

    /** @return BelongsTo<Cliente, $this> */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /** @return HasMany<Lote, $this> */
    public function lotes(): HasMany
    {
        return $this->hasMany(Lote::class, 'campo_id');
    }
}
