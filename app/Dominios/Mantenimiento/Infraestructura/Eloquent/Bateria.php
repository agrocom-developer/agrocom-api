<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;

/**
 * Batería del catálogo (HU-39, tarea 51): identificador, ciclos acumulados,
 * estado y asignación a base. Ver docblock de
 * `database/migrations/2026_09_03_200002_create_man_baterias_table.php`
 * para el detalle de columnas y constraints.
 *
 * `base_id` es un entero plano — sin `belongsTo(PerBase::class)`: es una FK
 * hacia otro módulo (`Personal`), prohibido por ADR 0003 regla 3, mismo
 * criterio que `Vehiculo::base_id`.
 *
 * `estado` no es una máquina de estados de negocio (CLAUDE.md invariante 7
 * no aplica a este campo, ver docblock de la migración y `EstadoBateria`).
 *
 * `RegistraBitacora` (invariante 9 de CLAUDE.md): el alta, edición y baja
 * de una batería desde el panel es una mutación de negocio con autor y
 * momento auditables, mismo criterio que `Vehiculo`.
 *
 * @property int $id
 * @property string $identificador
 * @property int $ciclos_acumulados
 * @property string $estado
 * @property int|null $base_id
 */
class Bateria extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'man_baterias';

    /** @var list<string> */
    protected $fillable = [
        'identificador',
        'ciclos_acumulados',
        'estado',
        'base_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'ciclos_acumulados' => 'integer',
        ];
    }

    /**
     * Umbral de ciclos para la alerta de "retirar la batería" (CA de HU-39).
     * Mismo criterio que `RegistroRecarga::TEMPERATURA_MAX_C = 50.0`: un
     * valor fijo razonable, sin configuración por lote/modelo — no lo pide
     * el CA esencial. 300 ciclos es el punto donde la mayoría de packs LiPo
     * de uso agrícola empieza a perder capacidad de forma notoria.
     */
    public const int UMBRAL_CICLOS_ALERTA = 300;
}
