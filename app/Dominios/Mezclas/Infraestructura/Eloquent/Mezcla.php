<?php

namespace App\Dominios\Mezclas\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cabecera de la mezcla cargada al caldo (espec §7, HU-78, tarea 94, revierte
 * CR-01). `trabajo_id` referencia `ope_trabajos` solo por FK + entero plano
 * (ADR 0003, regla 3) — resuelto por `Operaciones\Contratos\LecturaTrabajos`,
 * nunca por una relación Eloquent cruzada. Ver
 * `database/migrations/2026_09_14_100008_create_mez_mezclas_table.php`.
 *
 * @property int $id
 * @property string $uuid_cliente
 * @property int $trabajo_id
 * @property CarbonImmutable $hora
 */
class Mezcla extends ModeloDominio
{
    use RegistraBitacora;

    /** Prefijo de módulo en el nombre físico (ADR 0011); el global lo pone la conexión. */
    protected $table = 'mez_mezclas';

    /** @var list<string> */
    protected $fillable = ['uuid_cliente', 'trabajo_id', 'hora'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'hora' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<MezclaDetalle, $this> */
    public function detalles(): HasMany
    {
        return $this->hasMany(MezclaDetalle::class, 'mezcla_id');
    }
}
