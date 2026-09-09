<?php

namespace App\Dominios\Operaciones\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Corrección de sesión por rechazo (espec §5 "Inmutabilidad"; invariante 2
 * de CLAUDE.md; HU-14, tarea 14). El registro NUEVO que la invariante 2
 * exige en vez de un `UPDATE` sobre `ope_sesiones` — diseño completo,
 * incluida la alternativa descartada de reusar `ope_sesiones`, en
 * runs/14.md.
 *
 * `Sesion` es del mismo módulo (Operaciones), así que el `belongsTo` es
 * legítimo (ADR 0003, regla 1) — lo prohibido es cruzar hacia el modelo
 * Eloquent de OTRO módulo.
 *
 * Sin `estado` propio: un rechazo no transiciona, se crea una única vez
 * (el índice único parcial de la migración lo respalda) — por eso no hay
 * `Aplicacion/MaquinaEstados/` para esta entidad.
 *
 * @property int $id
 * @property int $anula_a_id
 * @property string $motivo
 * @property int $rechazado_por
 */
class SesionRechazo extends ModeloDominio
{
    use RegistraBitacora;

    /** Prefijo de módulo en el nombre físico (ADR 0011); el global lo pone la conexión. */
    protected $table = 'ope_sesion_rechazos';

    /** @var list<string> */
    protected $fillable = [
        'anula_a_id',
        'motivo',
        'rechazado_por',
    ];

    /** @return BelongsTo<Sesion, $this> */
    public function sesion(): BelongsTo
    {
        return $this->belongsTo(Sesion::class, 'anula_a_id');
    }
}
