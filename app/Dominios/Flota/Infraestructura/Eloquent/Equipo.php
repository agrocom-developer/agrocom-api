<?php

namespace App\Dominios\Flota\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Flota\Dominio\Vigencia;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Equipo operativo (`flo_equipos`): piloto + dron (+ auxiliar) que ejecuta una
 * solicitud. `piloto_id` y `auxiliar_id` apuntan a `Proveedores` solo por id
 * (sin relación Eloquent entre módulos).
 *
 * @property int $id
 * @property int $proveedor_id
 * @property int $piloto_id
 * @property int $dron_id
 * @property int|null $auxiliar_id
 * @property Carbon $vigente_desde
 * @property Carbon|null $vigente_hasta
 * @property-read Dron $dron
 */
class Equipo extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'flo_equipos';

    /** @var list<string> */
    protected $fillable = [
        'proveedor_id',
        'piloto_id',
        'dron_id',
        'auxiliar_id',
        'vigente_desde',
        'vigente_hasta',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'proveedor_id' => 'integer',
            'piloto_id' => 'integer',
            'dron_id' => 'integer',
            'auxiliar_id' => 'integer',
            'vigente_desde' => 'date',
            'vigente_hasta' => 'date',
        ];
    }

    /** @return BelongsTo<Dron, $this> */
    public function dron(): BelongsTo
    {
        return $this->belongsTo(Dron::class, 'dron_id');
    }

    public function vigencia(): Vigencia
    {
        return new Vigencia($this->vigente_desde->toDateString(), $this->vigente_hasta?->toDateString());
    }
}
