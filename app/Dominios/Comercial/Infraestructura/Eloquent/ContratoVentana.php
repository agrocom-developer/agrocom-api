<?php

namespace App\Dominios\Comercial\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ventana horaria permitida del contrato (insumos §4, RF-60, tabla
 * com_contrato_ventanas). Las horas quedan como string `HH:MM:SS` — son
 * columnas TIME sin fecha; convertirlas a datetime inventaría un día.
 *
 * @property int $id
 * @property int $contrato_id
 * @property string $hora_inicio
 * @property string $hora_fin
 */
class ContratoVentana extends ModeloDominio
{
    protected $table = 'com_contrato_ventanas';

    /** @var list<string> */
    protected $fillable = [
        'contrato_id',
        'hora_inicio',
        'hora_fin',
    ];

    /** @return BelongsTo<Contrato, $this> */
    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }
}
