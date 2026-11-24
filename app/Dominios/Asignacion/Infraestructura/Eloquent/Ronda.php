<?php

namespace App\Dominios\Asignacion\Infraestructura\Eloquent;

use App\Dominios\Asignacion\Dominio\GrupoOferta;
use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Support\Carbon;

/**
 * Ronda del motor (`asg_rondas`, especificación §4.5): cada vuelta de ofertas
 * sobre una solicitud, con su grupo y radio. Alimenta las métricas del mercado.
 *
 * @property int $id
 * @property int $solicitud_id
 * @property int $ciclo
 * @property int $ronda
 * @property GrupoOferta $grupo
 * @property string|null $radio_km
 * @property int $proveedores_ofrecidos
 * @property Carbon $iniciada_en
 * @property Carbon|null $cerrada_en
 */
class Ronda extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'asg_rondas';

    /** @var list<string> */
    protected $fillable = ['solicitud_id', 'ciclo', 'ronda', 'grupo', 'radio_km', 'proveedores_ofrecidos', 'iniciada_en', 'cerrada_en'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'solicitud_id' => 'integer',
            'ciclo' => 'integer',
            'ronda' => 'integer',
            'grupo' => GrupoOferta::class,
            'radio_km' => 'decimal:2',
            'proveedores_ofrecidos' => 'integer',
            'iniciada_en' => 'datetime',
            'cerrada_en' => 'datetime',
        ];
    }
}
