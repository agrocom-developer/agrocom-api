<?php

namespace App\Dominios\Personal\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Personal\Dominio\EstadoEquipoTrabajo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Equipo de trabajo (tarea 72, HU-49, ADR 0015 punto 3): el piloto y su
 * auxiliar, con el equipamiento que tienen asignado — no "dónde están
 * trabajando". Ver el docblock de la migración
 * `2026_09_08_500001_create_per_equipos_trabajo_table.php` para el detalle
 * de columnas y el porqué de no llevar `campania_id`.
 *
 * `base_id` es un `belongsTo` legítimo: `PerBase` es del mismo módulo
 * (Personal) — a diferencia de la FK hacia `ope_drones`/`man_*` de
 * {@see EquipoRecurso}, que SÍ cruza módulo y por eso no tiene relación
 * Eloquent (ADR 0003 regla 3).
 *
 * `RegistraBitacora` (invariante 9 de CLAUDE.md): alta, edición y baja de un
 * equipo es una mutación de negocio auditable, mismo criterio que
 * `PerPersona`/`Generador`.
 *
 * @property int $id
 * @property string $codigo
 * @property string|null $nombre
 * @property int $base_id
 * @property EstadoEquipoTrabajo $estado
 * @property Carbon $desde
 * @property Carbon|null $hasta
 */
class EquipoTrabajo extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'per_equipos_trabajo';

    /** @var list<string> */
    protected $fillable = [
        'codigo',
        'nombre',
        'base_id',
        'estado',
        'desde',
        'hasta',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'estado' => EstadoEquipoTrabajo::class,
            'desde' => 'date',
            'hasta' => 'date',
        ];
    }

    /** @return BelongsTo<PerBase, $this> */
    public function base(): BelongsTo
    {
        return $this->belongsTo(PerBase::class, 'base_id');
    }

    /** @return HasMany<EquipoIntegrante, $this> */
    public function integrantes(): HasMany
    {
        return $this->hasMany(EquipoIntegrante::class, 'equipo_trabajo_id');
    }

    /** @return HasMany<EquipoRecurso, $this> */
    public function recursos(): HasMany
    {
        return $this->hasMany(EquipoRecurso::class, 'equipo_trabajo_id');
    }
}
