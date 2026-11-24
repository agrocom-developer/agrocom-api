<?php

namespace App\Dominios\Calificaciones\Infraestructura\Eloquent;

use App\Dominios\Calificaciones\Dominio\CausaModeracion;
use App\Dominios\Calificaciones\Dominio\EstadoCalificacion;
use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Support\Carbon;

/**
 * Fila del historial de moderación de una calificación (`cal_moderaciones`): quién
 * la reportó, ocultó o restableció, por qué y cuándo. Solo se inserta.
 *
 * @property int $id
 * @property int $calificacion_id
 * @property EstadoCalificacion $estado_anterior
 * @property EstadoCalificacion $estado_nuevo
 * @property CausaModeracion|null $causa
 * @property string $motivo
 * @property int $autor_usuario_id
 * @property string $autor_rol
 * @property Carbon $created_at
 */
class Moderacion extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'cal_moderaciones';

    /** @var list<string> */
    protected $fillable = [
        'calificacion_id',
        'estado_anterior',
        'estado_nuevo',
        'causa',
        'motivo',
        'autor_usuario_id',
        'autor_rol',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'calificacion_id' => 'integer',
            'autor_usuario_id' => 'integer',
            'estado_anterior' => EstadoCalificacion::class,
            'estado_nuevo' => EstadoCalificacion::class,
            'causa' => CausaModeracion::class,
        ];
    }
}
