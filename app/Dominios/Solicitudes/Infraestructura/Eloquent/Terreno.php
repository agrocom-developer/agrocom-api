<?php

namespace App\Dominios\Solicitudes\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
 * @property string|null $departamento_codigo Código INE del departamento (catálogo `sol_departamentos`).
 * @property string|null $provincia_codigo Código INE de la provincia, del mismo departamento.
 * @property string|null $municipio Texto libre de referencia.
 * @property string|null $comunidad Texto libre de referencia.
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
        'departamento_codigo',
        'provincia_codigo',
        'municipio',
        'comunidad',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'cliente_id' => 'integer',
            'geometria' => 'array',
            'acceso_dificil' => 'boolean',
            'hectareas' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<Cliente, $this> */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /** @return HasMany<TerrenoFoto, $this> */
    public function fotos(): HasMany
    {
        return $this->hasMany(TerrenoFoto::class, 'terreno_id')->orderBy('id');
    }
}
