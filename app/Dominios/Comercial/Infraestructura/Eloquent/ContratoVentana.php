<?php

namespace App\Dominios\Comercial\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ventana horaria permitida del contrato (insumos §4, RF-60, tabla
 * com_contrato_ventanas). Las horas quedan como string `HH:MM:SS` — son
 * columnas TIME sin fecha; convertirlas a datetime inventaría un día.
 *
 * `RegistraBitacora` (HU-23, mismo criterio que {@see
 * \App\Dominios\Comercial\Infraestructura\Eloquent\ClienteContacto}): se
 * edita en la misma operación que el contrato, y su auditoría tiene que
 * quedar tan completa como la de su padre.
 *
 * @property int $id
 * @property int $contrato_id
 * @property string $hora_inicio
 * @property string $hora_fin
 */
class ContratoVentana extends ModeloDominio
{
    use RegistraBitacora;

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
