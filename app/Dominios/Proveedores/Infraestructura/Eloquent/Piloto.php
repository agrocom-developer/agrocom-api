<?php

namespace App\Dominios\Proveedores\Infraestructura\Eloquent;

use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Proveedores\Dominio\Disponibilidad;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Piloto de un proveedor (`prv_pilotos`). Comparte el ciclo de aprobación.
 *
 * @property int $id
 * @property int $proveedor_id
 * @property int $usuario_id
 * @property string $licencia_numero
 * @property Carbon $licencia_vence
 * @property string $categoria_habilitacion
 * @property EstadoAprobacion $estado
 * @property string|null $motivo_estado
 * @property Disponibilidad|null $disponibilidad
 * @property Carbon|null $disponibilidad_cambiada_en
 * @property int|null $disponibilidad_cambiada_por
 * @property-read Proveedor $proveedor
 */
class Piloto extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'prv_pilotos';

    /** @var list<string> */
    protected $fillable = [
        'proveedor_id',
        'usuario_id',
        'licencia_numero',
        'licencia_vence',
        'categoria_habilitacion',
        'estado',
        'motivo_estado',
        'aprobado_por',
        'aprobado_en',
        'disponibilidad',
        'disponibilidad_cambiada_en',
        'disponibilidad_cambiada_por',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'estado' => EstadoAprobacion::class,
            'proveedor_id' => 'integer',
            'usuario_id' => 'integer',
            'licencia_vence' => 'date',
            'aprobado_en' => 'datetime',
            'disponibilidad' => Disponibilidad::class,
            'disponibilidad_cambiada_en' => 'datetime',
            'disponibilidad_cambiada_por' => 'integer',
        ];
    }

    /** @return BelongsTo<Proveedor, $this> */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }
}
