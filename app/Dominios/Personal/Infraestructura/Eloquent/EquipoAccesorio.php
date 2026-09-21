<?php

namespace App\Dominios\Personal\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Accesorio asignado a un equipo de trabajo, con cantidad (tarea
 * "cuadrillas-estadias", pedido del dueño 19/9/2026): "2 palas", no una
 * unidad identificada con su propia vigencia como {@see EquipoRecurso}. Ver
 * el docblock de la migración `create_per_equipo_accesorios_table` para el
 * detalle completo.
 *
 * `equipo_trabajo_id`/`accesorio_id` son `belongsTo` legítimos: `EquipoTrabajo`
 * y `Accesorio` son del mismo módulo (ADR 0003 regla 3).
 *
 * `RegistraBitacora`: agregar, actualizar la cantidad o quitar un accesorio
 * es una mutación de negocio auditable, mismo criterio que
 * `EquipoIntegrante`/`EquipoRecurso`.
 *
 * @property int $id
 * @property int $equipo_trabajo_id
 * @property int $accesorio_id
 * @property int $cantidad
 * @property string|null $observacion
 */
class EquipoAccesorio extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'per_equipo_accesorios';

    /** @var list<string> */
    protected $fillable = [
        'equipo_trabajo_id',
        'accesorio_id',
        'cantidad',
        'observacion',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
        ];
    }

    /** @return BelongsTo<EquipoTrabajo, $this> */
    public function equipoTrabajo(): BelongsTo
    {
        return $this->belongsTo(EquipoTrabajo::class, 'equipo_trabajo_id');
    }

    /** @return BelongsTo<Accesorio, $this> */
    public function accesorio(): BelongsTo
    {
        return $this->belongsTo(Accesorio::class, 'accesorio_id');
    }
}
