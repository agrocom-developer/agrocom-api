<?php

namespace App\Dominios\Personal\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Personal\Dominio\RolEquipo;
use App\Dominios\Personal\Dominio\ValidadorSolapamientoVigencias;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Integrante de un equipo de trabajo, con vigencia (tarea 72, HU-49, ADR 0015
 * punto 3): quién era el piloto o el auxiliar en una fecha dada, no quién lo
 * es hoy — un gasto de marzo se atribuye a la formación de marzo.
 *
 * Sin bloqueo de solapamiento entre equipos distintos —
 * {@see ValidadorSolapamientoVigencias}—: la
 * pertenencia a un equipo no es exclusiva (corrección del dueño del
 * 7/9/2026). Ver el docblock de la migración
 * `2026_09_08_500002_create_per_equipo_integrantes_table.php`.
 *
 * `equipo_trabajo_id`/`persona_id` son `belongsTo` legítimos: `EquipoTrabajo`
 * y `PerPersona` son del mismo módulo (ADR 0003 regla 3).
 *
 * `RegistraBitacora` (invariante 9): asignar o desasignar un integrante es
 * una mutación de negocio auditable.
 *
 * @property int $id
 * @property int $equipo_trabajo_id
 * @property int $persona_id
 * @property RolEquipo $rol_equipo
 * @property Carbon $desde
 * @property Carbon|null $hasta
 */
class EquipoIntegrante extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'per_equipo_integrantes';

    /** @var list<string> */
    protected $fillable = [
        'equipo_trabajo_id',
        'persona_id',
        'rol_equipo',
        'desde',
        'hasta',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'rol_equipo' => RolEquipo::class,
            'desde' => 'date',
            'hasta' => 'date',
        ];
    }

    /** @return BelongsTo<EquipoTrabajo, $this> */
    public function equipoTrabajo(): BelongsTo
    {
        return $this->belongsTo(EquipoTrabajo::class, 'equipo_trabajo_id');
    }

    /** @return BelongsTo<PerPersona, $this> */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(PerPersona::class, 'persona_id');
    }
}
