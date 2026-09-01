<?php

namespace App\Dominios\Personal\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persona operativa de campo (espec §4.2, tabla per_personas), alcance
 * mínimo para HU-01 (ADR 0011, extensión 26/8/2026, punto 6): id, nombre,
 * rol, base_id, activo. `sueldo_mensual` (jefe/encargado) sigue diferido a
 * planilla — fuera de alcance de HU-16.
 *
 * `tarifa_ha` (HU-16, tarea 16): agregada por `ALTER TABLE`, nullable y sin
 * default de negocio — las `per_personas` de antes de esta migración no
 * tienen tarifa. Qué pasa si una persona sin `tarifa_ha` termina siendo
 * `piloto_id`/`auxiliar_id` de una sesión que se valida es una decisión de
 * `Finanzas/Aplicacion/GenerarDevengosSesion.php`, no de este modelo — ver
 * runs/16.md.
 *
 * `PerBase` es del mismo módulo (Personal), así que el `belongsTo` es
 * legítimo — lo que está prohibido es cruzar hacia modelos Eloquent de
 * OTRO módulo (p. ej. Seguridad), nunca las relaciones intra-módulo.
 *
 * @property int $id
 * @property string $nombre
 * @property RolOperativoPersona $rol
 * @property string|null $tarifa_ha
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
        'tarifa_ha',
        'base_id',
        'activo',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'rol' => RolOperativoPersona::class,
            'tarifa_ha' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    /** @return BelongsTo<PerBase, $this> */
    public function base(): BelongsTo
    {
        return $this->belongsTo(PerBase::class, 'base_id');
    }
}
