<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;

/**
 * Plan de mantenimiento preventivo por horas de vuelo (HU-38, tarea 54):
 * modelo de dron, tarea preventiva y umbral de horas. Ver docblock de
 * `database/migrations/2026_09_03_400001_create_man_planes_mantenimiento_table.php`
 * para el detalle de columnas, constraints y los recortes frente al modelo
 * completo de la especificación.
 *
 * `modelo` es texto plano — sin `belongsTo` hacia `Dron` (otro módulo,
 * `Operaciones`, ADR 0003 regla 3) y sin FK real (correlación por igualdad
 * de texto, ver el docblock de la migración y de
 * `App\Dominios\Operaciones\Contratos\LecturaHorasVueloPorModelo`).
 *
 * `RegistraBitacora` (invariante 9 de CLAUDE.md): el alta, edición y baja de
 * un plan desde el panel es una mutación de negocio con autor y momento
 * auditables, mismo criterio que `Bateria`/`Vehiculo`.
 *
 * @property int $id
 * @property string $modelo
 * @property string $tarea
 * @property string $horas_umbral
 * @property bool $alerta atributo NO persistido, calculado y asignado por
 *                        `ListarPlanesMantenimiento` — ausente fuera de ese
 *                        caso de uso.
 */
class PlanMantenimiento extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'man_planes_mantenimiento';

    /** @var list<string> */
    protected $fillable = [
        'modelo',
        'tarea',
        'horas_umbral',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'horas_umbral' => 'decimal:2',
        ];
    }
}
