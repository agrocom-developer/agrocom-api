<?php

namespace App\Dominios\Finanzas\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Support\Carbon;

/**
 * Carga de combustible del generador o de un vehículo, imputada a la
 * campaña (espec §4.4, línea 146; HU-35, tarea 49): "como encargado, quiero
 * registrar el combustible del generador y de los vehículos, para
 * imputarlo a la campaña". Lo crea únicamente
 * `Finanzas/Aplicacion/CrearCombustible.php`.
 *
 * Entidad independiente de `ope_recargas.litros_combustible_generador`
 * (ver docblock de la migración) — no confundir ambas.
 *
 * `base_id` referencia `per_bases` solo por FK + entero plano (ADR 0003
 * regla 3) — sin `belongsTo` cross-módulo, mismo criterio que
 * `Gasto::base_id`.
 *
 * Inmutable salvo baja (mismo criterio que `Gasto`/`Anticipo`): sin caso de
 * uso de edición — si está mal, se da de baja (`EliminarCombustible`) y se
 * recarga.
 *
 * `RegistraBitacora` (invariante 9 de CLAUDE.md): es dinero, mismo criterio
 * que `Gasto`/`Anticipo`/`DevengoPersonal`.
 *
 * @property int $id
 * @property Carbon $fecha
 * @property int $base_id
 * @property string $destino
 * @property string $litros
 * @property string $monto
 * @property string|null $descripcion
 */
class Combustible extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'fin_combustibles';

    /** @var list<string> */
    protected $fillable = [
        'fecha',
        'base_id',
        'destino',
        'litros',
        'monto',
        'descripcion',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'litros' => 'decimal:2',
            'monto' => 'decimal:2',
        ];
    }
}
