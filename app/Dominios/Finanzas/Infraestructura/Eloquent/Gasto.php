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
 * `base_id`/`trabajo_id`/`campania_id` referencian `per_bases`/`ope_trabajos`/
 * `cpn_campanias` solo por FK + entero plano (ADR 0003 regla 3) — sin
 * `belongsTo` cross-módulo, mismo criterio que `Anticipo::persona_id`.
 * `campania_id` (ADR 0015 punto 6, tarea 69): en qué campaña del cliente se
 * CONSUMIÓ el gasto — atribución de costo, nunca de cobro; el cliente no
 * paga combustible, paga por hectárea aplicada.
 *
 * Inmutable salvo baja (misma decisión que `Anticipo`, documentada en
 * `Aplicacion/CrearGasto`): un gasto cargado no se edita — si está mal, se da
 * de baja y se recarga. Evita que el monto de un gasto cambie por debajo de
 * una rendición en curso (HU-34, tarea 48).
 *
 * `rendicion_id` (HU-34, tarea 48, `ALTER TABLE`): a diferencia de
 * `base_id`/`trabajo_id`, `Rendicion` SÍ es del mismo módulo (Finanzas), así
 * que acá el `belongsTo` es legítimo — lo prohibido por ADR 0003 regla 2 es
 * cruzar hacia modelos Eloquent de OTRO módulo, nunca las relaciones
 * intra-módulo.
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
 * @property int|null $campania_id
 * @property int|null $rendicion_id
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
        'campania_id',
        'rendicion_id',
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

    /** @return BelongsTo<Rendicion, $this> */
    public function rendicion(): BelongsTo
    {
        return $this->belongsTo(Rendicion::class, 'rendicion_id');
    }
}
