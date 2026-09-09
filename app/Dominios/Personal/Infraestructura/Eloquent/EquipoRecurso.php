<?php

namespace App\Dominios\Personal\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Personal\Dominio\RecursoTipoEquipo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Equipamiento asignado a un equipo de trabajo, con vigencia (tarea 72,
 * HU-49, ADR 0015 punto 3): qué dron, vehículo o generador tenía asignado un
 * equipo en una fecha dada — resuelve hasta la unidad concreta un gasto que
 * antes no se podía imputar a nada.
 *
 * `recurso_tipo` + `recurso_id` es polimórfico A PROPÓSITO y SIN FK: el
 * destino depende del tipo y cruza dos módulos ajenos a Personal
 * (`ope_drones` en Operaciones; `man_vehiculos`/`man_generadores` en
 * Mantenimiento). Es la única excepción declarada a "FK real" de todo el ADR
 * 0015 (punto 3). La integridad la sostiene el caso de uso que asigna el
 * recurso —verificando que existe y está activo en su propia tabla antes de
 * guardar—, no un constraint de base; un test la cubre. Ver el docblock de
 * la migración `2026_09_08_500003_create_per_equipo_recursos_table.php`
 * para el detalle completo del porqué.
 *
 * `equipo_trabajo_id` es un `belongsTo` legítimo: `EquipoTrabajo` es del
 * mismo módulo.
 *
 * `RegistraBitacora` (invariante 9): asignar o desasignar un recurso es una
 * mutación de negocio auditable.
 *
 * @property int $id
 * @property int $equipo_trabajo_id
 * @property RecursoTipoEquipo $recurso_tipo
 * @property int $recurso_id
 * @property Carbon $desde
 * @property Carbon|null $hasta
 */
class EquipoRecurso extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'per_equipo_recursos';

    /** @var list<string> */
    protected $fillable = [
        'equipo_trabajo_id',
        'recurso_tipo',
        'recurso_id',
        'desde',
        'hasta',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'recurso_tipo' => RecursoTipoEquipo::class,
            'desde' => 'date',
            'hasta' => 'date',
        ];
    }

    /** @return BelongsTo<EquipoTrabajo, $this> */
    public function equipoTrabajo(): BelongsTo
    {
        return $this->belongsTo(EquipoTrabajo::class, 'equipo_trabajo_id');
    }
}
