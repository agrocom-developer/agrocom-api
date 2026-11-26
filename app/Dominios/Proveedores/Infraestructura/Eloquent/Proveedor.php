<?php

namespace App\Dominios\Proveedores\Infraestructura\Eloquent;

use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Proveedores\Dominio\Disponibilidad;
use App\Dominios\Proveedores\Dominio\TipoProveedor;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Proveedor del marketplace (`prv_proveedores`, ADR 0028): propio, independiente
 * o tercero. El estado cambia solo por `ResolverAprobacion` (invariante 7).
 *
 * @property int $id
 * @property int|null $usuario_titular_id
 * @property TipoProveedor $tipo
 * @property string $razon_social
 * @property string|null $documento_fiscal
 * @property string|null $telefono
 * @property int|null $zona_base_id
 * @property string $radio_operacion_km
 * @property EstadoAprobacion $estado
 * @property string|null $motivo_estado
 * @property string $calificacion_promedio
 * @property int $calificaciones_conteo
 * @property string $tasa_aceptacion
 * @property string $tasa_cancelacion
 * @property int|null $aprobado_por
 * @property Carbon|null $aprobado_en
 * @property Carbon $created_at
 */
class Proveedor extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'prv_proveedores';

    /** @var list<string> */
    protected $fillable = [
        'usuario_titular_id',
        'tipo',
        'razon_social',
        'documento_fiscal',
        'telefono',
        'nombres',
        'apellidos',
        'documento_identidad',
        'fecha_nacimiento',
        'zona_base_id',
        'radio_operacion_km',
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
            'tipo' => TipoProveedor::class,
            'estado' => EstadoAprobacion::class,
            'usuario_titular_id' => 'integer',
            'zona_base_id' => 'integer',
            'aprobado_por' => 'integer',
            'aprobado_en' => 'datetime',
            'disponibilidad' => Disponibilidad::class,
            'disponibilidad_cambiada_en' => 'datetime',
            'disponibilidad_cambiada_por' => 'integer',
            'calificaciones_conteo' => 'integer',
        ];
    }

    /** @return HasMany<Piloto, $this> */
    public function pilotos(): HasMany
    {
        return $this->hasMany(Piloto::class, 'proveedor_id');
    }
}
