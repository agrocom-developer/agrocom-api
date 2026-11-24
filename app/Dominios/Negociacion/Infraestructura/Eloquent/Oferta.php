<?php

namespace App\Dominios\Negociacion\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Negociacion\Dominio\EstadoOferta;
use Illuminate\Support\Carbon;

/**
 * Oferta o contraoferta (`neg_ofertas`, `negociacion-contrato.md` §2): el precio por
 * hectárea que una parte pone sobre una solicitud. Una negociación es el hilo de
 * ofertas entre un proveedor y el cliente; el autor alterna (impar proveedor, par
 * cliente). El `estado` solo lo cambia `MaquinaEstadosOferta` (invariante 7), por
 * eso no es `fillable`. `solicitud_id`, `proveedor_id` y `equipo_id` viajan sin
 * relación Eloquent: son de otros módulos.
 *
 * @property int $id
 * @property string $uuid_cliente
 * @property int $solicitud_id
 * @property int $proveedor_id
 * @property int|null $equipo_id
 * @property int $numero
 * @property string $autor `proveedor` o `cliente`
 * @property string $tipo `acepta_propuesta` o `contraoferta`
 * @property string $precio_ha
 * @property string $monto_estimado
 * @property EstadoOferta $estado
 * @property bool $es_definitiva
 * @property string|null $nota
 * @property int|null $oferta_anterior_id
 * @property Carbon $vence_en
 * @property Carbon|null $respondida_en
 * @property string|null $distancia_km
 * @property string|null $motivo
 */
class Oferta extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'neg_ofertas';

    /** @var list<string> */
    protected $fillable = [
        'uuid_cliente',
        'solicitud_id',
        'proveedor_id',
        'equipo_id',
        'numero',
        'autor',
        'tipo',
        'precio_ha',
        'monto_estimado',
        'es_definitiva',
        'nota',
        'oferta_anterior_id',
        'vence_en',
        'distancia_km',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'solicitud_id' => 'integer',
            'proveedor_id' => 'integer',
            'equipo_id' => 'integer',
            'numero' => 'integer',
            'estado' => EstadoOferta::class,
            'precio_ha' => 'decimal:4',
            'monto_estimado' => 'decimal:2',
            'es_definitiva' => 'boolean',
            'vence_en' => 'datetime',
            'respondida_en' => 'datetime',
            'distancia_km' => 'decimal:2',
        ];
    }
}
