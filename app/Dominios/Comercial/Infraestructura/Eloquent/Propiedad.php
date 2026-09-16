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
 * Ubicación (adenda 16/9/2026 a ADR 0018 punto 1): departamento/provincia/
 * municipio son catálogo cerrado (`Departamento`/`Provincia`/`Municipio`,
 * FK encadenada); `localidad` sigue siendo texto libre dentro del municipio
 * elegido (no hay datos de esa granularidad). Latitud/longitud/`geometria`
 * se editan aparte, en la pantalla de mapa (`ActualizarUbicacionMapaPropiedad`).
 *
 * `color`: paleta curada (no hex libre — ver `ColorPropiedad`), dato
 * de negocio elegido por el usuario para distinguir propiedades de
 * distintos clientes en listados y mapas. Sus lotes lo heredan
 * visualmente leyendo `propiedad->color`, nunca duplicado en `com_lotes`.
 *
 * `hectareas` (16/9/2026, pedido directo): superficie total DECLARADA de la
 * hacienda completa — puede incluir terreno que nunca se fumiga (p. ej.
 * ganadería), así que casi nunca coincide con la suma de `lotes.hectareas`
 * (eso es lo que se factura, invariante 6). Es dato a mano, igual que
 * `Lote::$hectareas`, nunca calculado del polígono dibujado en `geometria`
 * — un trazo impreciso no puede hacerle decir al portal del cliente una
 * superficie distinta a la de su título de propiedad.
 *
 * `RegistraBitacora` (invariante 9 de CLAUDE.md): el alta, edición y baja de
 * una propiedad es una mutación de negocio con autor y momento auditables.
 *
 * @property int $id
 * @property int $cliente_id
 * @property string $nombre
 * @property string|null $hectareas
 * @property int|null $departamento_id
 * @property int|null $provincia_id
 * @property int|null $municipio_id
 * @property string|null $localidad
 * @property string|null $color
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
        'hectareas',
        'departamento_id',
        'provincia_id',
        'municipio_id',
        'localidad',
        'color',
        'latitud',
        'longitud',
        'geometria',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'hectareas' => 'decimal:2',
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

    /** @return BelongsTo<Departamento, $this> */
    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'departamento_id');
    }

    /** @return BelongsTo<Provincia, $this> */
    public function provincia(): BelongsTo
    {
        return $this->belongsTo(Provincia::class, 'provincia_id');
    }

    /** @return BelongsTo<Municipio, $this> */
    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'municipio_id');
    }

    /** @return HasMany<Lote, $this> */
    public function lotes(): HasMany
    {
        return $this->hasMany(Lote::class, 'propiedad_id');
    }
}
