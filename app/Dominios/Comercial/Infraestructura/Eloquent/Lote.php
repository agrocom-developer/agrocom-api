<?php

namespace App\Dominios\Comercial\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lote de la propiedad (espec §4.1, tabla com_lotes; ADR 0020 — cuelga
 * directo de `Propiedad`, sin el nivel intermedio `Campo`). Hectáreas en
 * DECIMAL — las hectáreas son dinero (invariante 6); la geometría es GeoJSON
 * en JSONB, se guarda y se dibuja, no se consulta espacialmente (sin PostGIS
 * en v1).
 *
 * `RegistraBitacora` (HU-24, tarea 35): mismo criterio que {@see Propiedad}.
 *
 * @property int $id
 * @property int $propiedad_id
 * @property string $codigo
 * @property string $hectareas
 * @property array<string, mixed>|null $geometria
 * @property string|null $restricciones
 * @property string|null $desnivel
 * @property string|null $limpieza
 */
class Lote extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'com_lotes';

    /** @var list<string> */
    protected $fillable = [
        'propiedad_id',
        'codigo',
        'hectareas',
        'geometria',
        'restricciones',
        'desnivel',
        'limpieza',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'hectareas' => 'decimal:2',
            'geometria' => 'array',
        ];
    }

    /**
     * Orden NATURAL por código (19/9/2026, pedido directo): L1, L2, … L10, no
     * L1, L10, L11, L2, que es lo que da ordenar el texto. Se ordena por la
     * parte de letras (sin mayúsculas) y después por el PRIMER número del
     * código; lo que empata queda por código y por id. Un código sin número
     * queda antes que los numerados de su mismo prefijo.
     *
     * Usa funciones de PostgreSQL (`substring(... from '<regex>')`): es el
     * motor de este proyecto (ADR 0001). Un código con más de un número
     * ("A1-B2") se ordena solo por el primero y desempata por el texto — para
     * los códigos que arma `CrearLotesMasivo` (prefijo + número correlativo)
     * es exacto.
     *
     * @param  Builder<Lote>  $consulta
     */
    public function scopeOrdenadosPorCodigo(Builder $consulta): void
    {
        $codigo = $consulta->getQuery()->getGrammar()->wrap($consulta->qualifyColumn('codigo'));

        $consulta
            ->orderByRaw("lower(substring({$codigo} from '^[^0-9]*'))")
            ->orderByRaw("coalesce(nullif(substring({$codigo} from '[0-9]+'), ''), '0')::numeric")
            ->orderBy('codigo')
            ->orderBy('id');
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class, 'propiedad_id');
    }
}
