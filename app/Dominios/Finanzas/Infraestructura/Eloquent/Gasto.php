<?php

namespace App\Dominios\Finanzas\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Gasto real de campaña (espec §4.4, línea 145; HU-33, tarea 47): "como
 * encargado, quiero cargar gastos con su categoría y comprobante, para que la
 * campaña tenga costo real". Lo crea únicamente
 * `Finanzas/Aplicacion/CrearGasto.php`.
 *
 * `base_id`/`trabajo_id` referencian `per_bases`/`ope_trabajos` solo por FK +
 * entero plano (ADR 0003 regla 3) — sin `belongsTo` cross-módulo, mismo
 * criterio que `Anticipo::persona_id`.
 *
 * Inmutable salvo baja (misma decisión que `Anticipo`, documentada en
 * `Aplicacion/CrearGasto`): un gasto cargado no se edita — si está mal, se da
 * de baja y se recarga. Evita que el monto de un gasto cambie por debajo de
 * una rendición en curso una vez que exista `fin_rendiciones` (HU-34).
 *
 * `RegistraBitacora` (invariante 9 de CLAUDE.md): es dinero, mismo criterio
 * que `Anticipo`/`DevengoPersonal`.
 *
 * @property int $id
 * @property Carbon $fecha
 * @property int $rubro_id
 * @property int|null $subrubro_id
 * @property string $cantidad
 * @property string $precio_unitario
 * @property string $monto
 * @property int|null $base_id
 * @property int|null $trabajo_id
 * @property string|null $comprobante_url
 * @property string|null $comprobante_hash
 */
class Gasto extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'fin_gastos';

    /** @var list<string> */
    protected $fillable = [
        'fecha',
        'rubro_id',
        'subrubro_id',
        'cantidad',
        'precio_unitario',
        'monto',
        'base_id',
        'trabajo_id',
        'comprobante_url',
        'comprobante_hash',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'cantidad' => 'decimal:2',
            'precio_unitario' => 'decimal:2',
            'monto' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Rubro, $this> */
    public function rubro(): BelongsTo
    {
        return $this->belongsTo(Rubro::class, 'rubro_id');
    }

    /** @return BelongsTo<Subrubro, $this> */
    public function subrubro(): BelongsTo
    {
        return $this->belongsTo(Subrubro::class, 'subrubro_id');
    }
}
