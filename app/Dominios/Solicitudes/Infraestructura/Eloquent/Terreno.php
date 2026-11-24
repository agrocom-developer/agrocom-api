<?php

namespace App\Dominios\Solicitudes\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Terreno de un cliente (`sol_terrenos`, especificación §4.1): el cliente lo
 * registra una vez y pide servicios sobre él, solicitud por solicitud. La
 * geometría es el polígono GeoJSON; `hectareas` es decimal exacto (string).
 *
 * @property int $id
 * @property int $cliente_id
 * @property string $nombre
 * @property array<string, mixed>|null $geometria
 * @property string $hectareas
 * @property string|null $cultivo
 * @property string|null $referencias_acceso
 * @property string|null $colindantes_sensibles
 * @property bool $acceso_dificil
 * @property int|null $zona_id
 */
class Terreno extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'sol_terrenos';

    /** @var list<string> */
    protected $fillable = [
        'cliente_id',
        'nombre',
        'geometria',
        'hectareas',
        'cultivo',
        'referencias_acceso',
        'colindantes_sensibles',
        'acceso_dificil',
        'zona_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'cliente_id' => 'integer',
            'zona_id' => 'integer',
            'geometria' => 'array',
            'acceso_dificil' => 'boolean',
            'hectareas' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<Zona, $this> */
    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class, 'zona_id');
    }

    /** @return BelongsTo<Cliente, $this> */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }
}
