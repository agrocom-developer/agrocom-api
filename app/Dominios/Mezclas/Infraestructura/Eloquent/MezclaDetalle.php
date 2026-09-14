<?php

namespace App\Dominios\Mezclas\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Renglón de producto + cantidad + unidad de una mezcla (espec §7, HU-78,
 * tarea 94, revierte CR-01). Ver
 * `database/migrations/2026_09_14_100009_create_mez_mezcla_detalles_table.php`.
 *
 * @property int $id
 * @property int $mezcla_id
 * @property int $producto_id
 * @property string $cantidad
 * @property string $unidad
 */
class MezclaDetalle extends ModeloDominio
{
    use RegistraBitacora;

    /** Prefijo de módulo en el nombre físico (ADR 0011); el global lo pone la conexión. */
    protected $table = 'mez_mezcla_detalles';

    /** @var list<string> */
    protected $fillable = ['mezcla_id', 'producto_id', 'cantidad', 'unidad'];

    /**
     * `decimal:2` (mismo criterio que `RecepcionCaldo::$litros`): sin el
     * cast, SQLite (el motor de los tests) devuelve `2.5` en vez de `2.50` —
     * Postgres sí completa la escala del `DECIMAL` de la columna, SQLite no.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['cantidad' => 'decimal:2'];
    }

    /** @return BelongsTo<Mezcla, $this> */
    public function mezcla(): BelongsTo
    {
        return $this->belongsTo(Mezcla::class, 'mezcla_id');
    }

    /** @return BelongsTo<Producto, $this> */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
