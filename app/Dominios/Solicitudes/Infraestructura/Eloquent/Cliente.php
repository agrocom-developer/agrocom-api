<?php

namespace App\Dominios\Solicitudes\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Solicitudes\Dominio\EstadoCliente;
use App\Dominios\Solicitudes\Dominio\TipoCliente;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Cliente del marketplace (`sol_clientes`, especificación §4.1): la ficha de
 * negocio de una cuenta con el rol `cliente`. La calificación promedio la
 * mantiene el módulo de calificaciones; nace en cero.
 *
 * @property int $id
 * @property int $usuario_id
 * @property TipoCliente $tipo
 * @property string $razon_social
 * @property string|null $documento_fiscal
 * @property string|null $telefono
 * @property EstadoCliente $estado
 * @property string|null $motivo_estado
 * @property string $calificacion_promedio
 * @property int $calificaciones_conteo
 * @property Carbon|null $aviso_calificacion_en
 * @property Carbon|null $publicacion_limitada_en
 */
class Cliente extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'sol_clientes';

    /** @var list<string> */
    protected $fillable = [
        'usuario_id',
        'tipo',
        'razon_social',
        'documento_fiscal',
        'telefono',
        'estado',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo' => TipoCliente::class,
            'estado' => EstadoCliente::class,
            'usuario_id' => 'integer',
            'calificaciones_conteo' => 'integer',
            'aviso_calificacion_en' => 'datetime',
            'publicacion_limitada_en' => 'datetime',
        ];
    }

    /** @return HasMany<ClienteContacto, $this> Vigentes, el principal primero. */
    public function contactos(): HasMany
    {
        return $this->hasMany(ClienteContacto::class, 'cliente_id')->orderByDesc('es_principal')->orderBy('id');
    }
}
