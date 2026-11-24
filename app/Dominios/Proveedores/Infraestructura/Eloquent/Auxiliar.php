<?php

namespace App\Dominios\Proveedores\Infraestructura\Eloquent;

use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Auxiliar de un proveedor (`prv_auxiliares`): lo registra un piloto o el
 * proveedor y lo aprueba un administrador (ADR 0032, punto 2).
 *
 * @property int $id
 * @property int $proveedor_id
 * @property int $usuario_id
 * @property int|null $registrado_por_piloto_id
 * @property EstadoAprobacion $estado
 * @property string|null $motivo_estado
 * @property-read Proveedor $proveedor
 */
class Auxiliar extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'prv_auxiliares';

    /** @var list<string> */
    protected $fillable = [
        'proveedor_id',
        'usuario_id',
        'registrado_por_piloto_id',
        'estado',
        'motivo_estado',
        'aprobado_por',
        'aprobado_en',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'estado' => EstadoAprobacion::class,
            'proveedor_id' => 'integer',
            'usuario_id' => 'integer',
            'registrado_por_piloto_id' => 'integer',
            'aprobado_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Proveedor, $this> */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }
}
