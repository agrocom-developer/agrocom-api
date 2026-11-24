<?php

namespace App\Dominios\Flota\Infraestructura\Eloquent;

use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Flota\Dominio\OrigenDron;
use Illuminate\Support\Carbon;

/**
 * Dron del marketplace (`flo_drones`, ADR 0029): el vehículo, con dueño y
 * operador. `ope_dron_id` lo enlaza con el catálogo operativo `ope_drones` que
 * usa la ejecución en el lote. Nombrado `Dron` dentro de `Flota`, distinto del
 * `Dron` del catálogo de Operaciones (otro módulo, sin relación entre clases).
 *
 * @property int $id
 * @property int $dueno_proveedor_id
 * @property int $operador_proveedor_id
 * @property int|null $ope_dron_id
 * @property string $marca
 * @property string $modelo
 * @property string $numero_serie
 * @property string $capacidad_tanque_l
 * @property int|null $anio
 * @property string $ancho_pasada_m
 * @property int $ciclos_bateria
 * @property EstadoAprobacion $estado
 * @property string|null $motivo_estado
 * @property OrigenDron $origen
 * @property int|null $aprobado_por
 * @property Carbon|null $aprobado_en
 * @property Carbon $created_at
 */
class Dron extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'flo_drones';

    /** @var list<string> */
    protected $fillable = [
        'dueno_proveedor_id',
        'operador_proveedor_id',
        'ope_dron_id',
        'marca',
        'modelo',
        'numero_serie',
        'capacidad_tanque_l',
        'anio',
        'ancho_pasada_m',
        'ciclos_bateria',
        'estado',
        'motivo_estado',
        'origen',
        'aprobado_por',
        'aprobado_en',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'estado' => EstadoAprobacion::class,
            'origen' => OrigenDron::class,
            'dueno_proveedor_id' => 'integer',
            'operador_proveedor_id' => 'integer',
            'ope_dron_id' => 'integer',
            'ciclos_bateria' => 'integer',
            'aprobado_en' => 'datetime',
        ];
    }
}
