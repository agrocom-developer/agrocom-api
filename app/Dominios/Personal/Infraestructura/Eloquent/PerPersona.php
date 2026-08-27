<?php

namespace App\Dominios\Personal\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persona operativa de campo (espec §4.2, tabla per_personas), alcance
 * mínimo para HU-01 (ADR 0011, extensión 26/8/2026, punto 6): id, nombre,
 * rol, base_id, activo. `tarifa_ha`/`sueldo_mensual` quedan diferidos a
 * devengos/planilla.
 *
 * `PerBase` es del mismo módulo (Personal), así que el `belongsTo` es
 * legítimo — lo que está prohibido es cruzar hacia modelos Eloquent de
 * OTRO módulo (p. ej. Seguridad), nunca las relaciones intra-módulo.
 *
 * @property int $id
 * @property string $nombre
 * @property RolOperativoPersona $rol
 * @property int|null $base_id
 * @property bool $activo
 */
class PerPersona extends ModeloDominio
{
    protected $table = 'per_personas';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'rol',
        'base_id',
        'activo',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'rol' => RolOperativoPersona::class,
            'activo' => 'boolean',
        ];
    }

    /** @return BelongsTo<PerBase, $this> */
    public function base(): BelongsTo
    {
        return $this->belongsTo(PerBase::class, 'base_id');
    }
}
