<?php

namespace App\Dominios\Calificaciones\Infraestructura\Eloquent;

use App\Dominios\Calificaciones\Dominio\AutorCalificacion;
use App\Dominios\Calificaciones\Dominio\CausaModeracion;
use App\Dominios\Calificaciones\Dominio\EstadoCalificacion;
use App\Dominios\Calificaciones\Dominio\SujetoCalificacion;
use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Calificación de un servicio completado (`cal_calificaciones`, especificación
 * §4.7). La escribe un solo actor y nunca se edita; el `estado` solo lo cambia
 * `MaquinaEstadosCalificacion`, por eso ni el estado ni los datos de moderación
 * están en `$fillable`.
 *
 * @property int $id
 * @property string $uuid_cliente
 * @property int $solicitud_id
 * @property int|null $proveedor_id
 * @property int $cliente_id
 * @property int $autor_usuario_id
 * @property AutorCalificacion $autor_rol
 * @property SujetoCalificacion $sujeto_tipo
 * @property int $sujeto_id
 * @property int $estrellas
 * @property list<string> $etiquetas
 * @property string|null $comentario
 * @property EstadoCalificacion $estado
 * @property int|null $moderada_por
 * @property CausaModeracion|null $causa_moderacion
 * @property string|null $motivo_moderacion
 * @property Carbon|null $moderada_en
 */
class Calificacion extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'cal_calificaciones';

    /** @var list<string> */
    protected $fillable = [
        'uuid_cliente',
        'solicitud_id',
        'proveedor_id',
        'cliente_id',
        'autor_usuario_id',
        'autor_rol',
        'sujeto_tipo',
        'sujeto_id',
        'estrellas',
        'etiquetas',
        'comentario',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'solicitud_id' => 'integer',
            'proveedor_id' => 'integer',
            'cliente_id' => 'integer',
            'autor_usuario_id' => 'integer',
            'sujeto_id' => 'integer',
            'estrellas' => 'integer',
            'etiquetas' => 'array',
            'autor_rol' => AutorCalificacion::class,
            'sujeto_tipo' => SujetoCalificacion::class,
            'estado' => EstadoCalificacion::class,
            'causa_moderacion' => CausaModeracion::class,
            'moderada_por' => 'integer',
            'moderada_en' => 'datetime',
        ];
    }

    /** @return HasMany<Moderacion, $this> */
    public function moderaciones(): HasMany
    {
        return $this->hasMany(Moderacion::class, 'calificacion_id')->orderBy('id');
    }
}
