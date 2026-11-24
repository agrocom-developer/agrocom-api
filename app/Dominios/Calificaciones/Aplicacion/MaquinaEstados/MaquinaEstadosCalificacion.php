<?php

namespace App\Dominios\Calificaciones\Aplicacion\MaquinaEstados;

use App\Dominios\Calificaciones\Dominio\CausaModeracion;
use App\Dominios\Calificaciones\Dominio\EstadoCalificacion;
use App\Dominios\Calificaciones\Dominio\Excepciones\CalificacionInvalida;
use App\Dominios\Calificaciones\Dominio\TransicionesCalificacion;
use App\Dominios\Calificaciones\Infraestructura\Eloquent\Calificacion;
use App\Dominios\Calificaciones\Infraestructura\Eloquent\Moderacion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Única clase que crea y cambia el `estado` de una calificación (invariante 7 de
 * CLAUDE.md): consulta {@see TransicionesCalificacion} antes de escribir y deja una
 * fila de moderación por cada cambio, con autor, causa y motivo. El contenido de
 * la calificación (estrellas, etiquetas, comentario) nunca se toca. Todo ocurre
 * en una transacción con la fila bloqueada: dos administradores decidiendo a la
 * vez no se pisan.
 */
final class MaquinaEstadosCalificacion
{
    /**
     * Crea la calificación `visible`.
     *
     * @param  array<string, mixed>  $atributos  sin `estado`: lo fija esta clase.
     */
    public function crear(array $atributos): Calificacion
    {
        $calificacion = new Calificacion($atributos);
        $calificacion->estado = EstadoCalificacion::Visible;
        $calificacion->save();

        return $calificacion;
    }

    /**
     * Cambia el estado. Pasar al estado en que ya está no hace nada.
     *
     * @param  string  $autorRol  `administrador` o `proveedor` (quien reporta).
     *
     * @throws CalificacionInvalida si la tabla no admite el paso.
     */
    public function transicionar(
        Calificacion $calificacion,
        EstadoCalificacion $hacia,
        string $autorRol,
        int $autorUsuarioId,
        string $motivo,
        ?CausaModeracion $causa = null,
    ): Calificacion {
        return DB::transaction(function () use ($calificacion, $hacia, $autorRol, $autorUsuarioId, $motivo, $causa): Calificacion {
            /** @var Calificacion $actual */
            $actual = Calificacion::query()->lockForUpdate()->findOrFail($calificacion->id);

            if ($actual->estado === $hacia) {
                return $actual;
            }

            if (! TransicionesCalificacion::permitida($actual->estado, $hacia)) {
                throw CalificacionInvalida::transicionNoPermitida($actual->estado->value, $hacia->value);
            }

            if ($autorRol === 'administrador') {
                $actual->forceFill([
                    'moderada_por' => $autorUsuarioId,
                    'causa_moderacion' => $causa,
                    'motivo_moderacion' => $motivo,
                    'moderada_en' => Carbon::now(),
                ]);
            }

            $desde = $actual->estado;
            $actual->forceFill(['estado' => $hacia])->save();

            Moderacion::query()->create([
                'calificacion_id' => $actual->id,
                'estado_anterior' => $desde,
                'estado_nuevo' => $hacia,
                'causa' => $causa,
                'motivo' => $motivo,
                'autor_usuario_id' => $autorUsuarioId,
                'autor_rol' => $autorRol,
            ]);

            return $actual;
        });
    }
}
