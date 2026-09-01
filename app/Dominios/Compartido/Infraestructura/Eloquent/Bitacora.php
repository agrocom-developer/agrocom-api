<?php

namespace App\Dominios\Compartido\Infraestructura\Eloquent;

use App\Dominios\Compartido\Dominio\AccionBitacora;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Fila de la bitácora de auditoría transversal (ADR 0007, invariante 9 de
 * CLAUDE.md). La escribe únicamente {@see BitacoraObserver} — nunca un caso
 * de uso a mano, que es justo la alternativa que el ADR 0007 descartó.
 *
 * No extiende `ModeloDominio` a propósito: ver el docblock de su migración
 * (`database/migrations/2026_08_31_100002_create_plt_bitacoras_table.php`)
 * para el porqué (sin soft delete, sin autoría propia, sin `updated_at`).
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $tabla
 * @property int $registro_id
 * @property AccionBitacora $accion
 * @property array<string, mixed>|null $antes
 * @property array<string, mixed>|null $despues
 * @property Carbon $created_at
 */
class Bitacora extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'plt_bitacoras';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'tabla',
        'registro_id',
        'accion',
        'antes',
        'despues',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'registro_id' => 'integer',
            'accion' => AccionBitacora::class,
            'antes' => 'array',
            'despues' => 'array',
        ];
    }
}
