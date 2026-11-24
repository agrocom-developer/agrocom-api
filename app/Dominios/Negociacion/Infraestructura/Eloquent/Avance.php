<?php

namespace App\Dominios\Negociacion\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Support\Carbon;

/**
 * Registro de seguimiento del piloto (`neg_avances`, `negociacion-contrato.md` §4):
 * `inicio`, `avance` o `cierre`. Nace en el dispositivo con su `uuid_cliente`
 * (invariante 1), así que aplicarlo dos veces deja la base idéntica. Lo escribe
 * solo el piloto del equipo adjudicado (un solo rol escritor).
 *
 * @property int $id
 * @property string $uuid_cliente
 * @property int $solicitud_id
 * @property int $secuencia
 * @property string $tipo
 * @property string|null $hectareas_ejecutadas
 * @property string|null $porcentaje
 * @property string|null $nota
 * @property string|null $motivo_correccion
 * @property Carbon $registrado_en
 * @property int|null $usuario_id
 */
class Avance extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'neg_avances';

    /** @var list<string> */
    protected $fillable = ['uuid_cliente', 'solicitud_id', 'secuencia', 'tipo', 'hectareas_ejecutadas', 'porcentaje', 'nota', 'motivo_correccion', 'registrado_en', 'usuario_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'solicitud_id' => 'integer',
            'secuencia' => 'integer',
            'hectareas_ejecutadas' => 'decimal:4',
            'porcentaje' => 'decimal:2',
            'registrado_en' => 'datetime',
            'usuario_id' => 'integer',
        ];
    }
}
