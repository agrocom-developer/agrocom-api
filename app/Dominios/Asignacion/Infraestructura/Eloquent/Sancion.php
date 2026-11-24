<?php

namespace App\Dominios\Asignacion\Infraestructura\Eloquent;

use App\Dominios\Asignacion\Dominio\CausaSancion;
use App\Dominios\Asignacion\Dominio\TipoSancion;
use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Support\Carbon;

/**
 * Sanción de un proveedor (`asg_sanciones`, ADR 0038): advertencia, restricción
 * temporal de ofertas o revisión de suspensión. Vigente mientras no esté
 * levantada y, si tiene `hasta`, no haya pasado.
 *
 * @property int $id
 * @property int $proveedor_id
 * @property TipoSancion $tipo
 * @property CausaSancion $causa
 * @property string $motivo
 * @property Carbon $desde
 * @property Carbon|null $hasta
 * @property Carbon|null $levantada_en
 * @property int|null $levantada_por
 * @property string|null $levantada_motivo
 */
class Sancion extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'asg_sanciones';

    /** @var list<string> */
    protected $fillable = ['proveedor_id', 'tipo', 'causa', 'motivo', 'desde', 'hasta', 'levantada_en', 'levantada_por', 'levantada_motivo'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'proveedor_id' => 'integer',
            'tipo' => TipoSancion::class,
            'causa' => CausaSancion::class,
            'desde' => 'datetime',
            'hasta' => 'datetime',
            'levantada_en' => 'datetime',
            'levantada_por' => 'integer',
        ];
    }

    public function estaVigente(?Carbon $ahora = null): bool
    {
        $ahora ??= Carbon::now();

        return $this->levantada_en === null && ($this->hasta === null || $this->hasta->greaterThan($ahora));
    }
}
