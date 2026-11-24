<?php

namespace App\Dominios\Asignacion\Aplicacion\MaquinaEstados;

use App\Dominios\Asignacion\Dominio\EstadoOferta;
use App\Dominios\Asignacion\Dominio\Excepciones\AsignacionInvalida;
use App\Dominios\Asignacion\Dominio\TransicionesOferta;
use App\Dominios\Asignacion\Infraestructura\Eloquent\Oferta;
use Illuminate\Support\Carbon;

/**
 * Única clase que crea y cambia el `estado` de una oferta (invariante 7 de
 * CLAUDE.md): consulta {@see TransicionesOferta} antes de escribir. Quien la
 * llama ya tiene bloqueada la fila de la solicitud (la decisión de asignación
 * empieza siempre por ahí), así que dos respuestas simultáneas a la misma
 * oferta no se cruzan.
 */
final class MaquinaEstadosOferta
{
    /**
     * Crea la oferta en `ofrecida`.
     *
     * @param  array<string, mixed>  $atributos  sin `estado`: lo fija esta clase.
     */
    public function ofrecer(array $atributos): Oferta
    {
        $oferta = new Oferta($atributos);
        $oferta->estado = EstadoOferta::Ofrecida;
        $oferta->save();

        return $oferta;
    }

    /**
     * @param  array<string, mixed>  $cambios  atributos que se fijan junto con el estado (motivo, equipo).
     *
     * @throws AsignacionInvalida si la tabla no admite el paso.
     */
    public function transicionar(Oferta $oferta, EstadoOferta $hacia, array $cambios = []): Oferta
    {
        if (! TransicionesOferta::permitida($oferta->estado, $hacia)) {
            throw AsignacionInvalida::ofertaNoDisponible($oferta->estado->value);
        }

        $oferta->forceFill([
            ...$cambios,
            'estado' => $hacia,
            'respondida_en' => $oferta->respondida_en ?? ($hacia === EstadoOferta::Retirada ? null : Carbon::now()),
        ])->save();

        return $oferta;
    }
}
