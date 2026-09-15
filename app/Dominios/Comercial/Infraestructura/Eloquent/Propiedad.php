<?php

namespace App\Dominios\Comercial\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Propiedad del cliente (ADR 0020, tabla com_propiedades): nivel de negocio
 * entre `Cliente` y `Lote` — un cliente tiene varias propiedades, y sus
 * lotes cuelgan directo de la propiedad, sin el nivel intermedio `Campo`
 * que existió bajo ADR 0018 (reemplazado por ADR 0020). El caso "Gamelera"
 * (dos mitades de 1500 ha separadas por una carretera) se representa con
 * `geometria` (GeoJSON `MultiPolygon`, un terreno por elemento), no con
 * filas hijas: no tiene código propio, ni hectáreas propias, ni
 * restricciones — es geometría de referencia, no una entidad de negocio. La
 * independencia de campaña por terreno la resuelve `com_lote_campania` a
 * nivel `Lote`.
 *
 * `RegistraBitacora` (invariante 9 de CLAUDE.md): el alta, edición y baja de
 * una propiedad es una mutación de negocio con autor y momento auditables.
 *
 * @property int $id
 * @property int $cliente_id
 * @property string $nombre
 * @property string|null $ubicacion
 * @property string|null $departamento
 * @property string|null $municipio
 * @property string|null $localidad
 * @property string|null $latitud
 * @property string|null $longitud
 * @property array<string, mixed>|null $geometria
 */
class Propiedad extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'com_propiedades';

    /** @var list<string> */
    protected $fillable = [
        'cliente_id',
        'nombre',
        'ubicacion',
        'departamento',
        'municipio',
        'localidad',
        'latitud',
        'longitud',
        'geometria',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'latitud' => 'decimal:6',
            'longitud' => 'decimal:6',
            'geometria' => 'array',
        ];
    }

    /** @return BelongsTo<Cliente, $this> */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /** @return HasMany<Lote, $this> */
    public function lotes(): HasMany
    {
        return $this->hasMany(Lote::class, 'propiedad_id');
    }
}
