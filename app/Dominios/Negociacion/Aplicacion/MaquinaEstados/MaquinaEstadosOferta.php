<?php

namespace App\Dominios\Negociacion\Aplicacion\MaquinaEstados;

use App\Dominios\Negociacion\Dominio\EstadoOferta;
use App\Dominios\Negociacion\Dominio\Excepciones\NegociacionInvalida;
use App\Dominios\Negociacion\Dominio\TransicionesOferta;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Oferta;
use Illuminate\Support\Carbon;

/**
 * Única clase que crea y cambia el `estado` de una oferta (invariante 7 de
 * CLAUDE.md): consulta {@see TransicionesOferta} antes de escribir. Quien la llama
 * ya bloqueó la fila de la solicitud (toda decisión empieza por ahí) y después
 * vuelve a leer la oferta bajo bloqueo, así que dos respuestas simultáneas a la
 * misma oferta no se cruzan.
 */
final class MaquinaEstadosOferta
{
    /**
     * Crea la oferta `vigente`.
     *
     * @param  array<string, mixed>  $atributos  sin `estado`: lo fija esta clase.
     */
    public function crear(array $atributos): Oferta
    {
        $oferta = new Oferta($atributos);
        $oferta->estado = EstadoOferta::Vigente;
        $oferta->save();

        return $oferta;
    }

    /** @throws NegociacionInvalida `oferta_no_vigente` (409) si la tabla no admite el paso. */
    public function transicionar(Oferta $oferta, EstadoOferta $hacia): Oferta
    {
        if (! TransicionesOferta::permitida($oferta->estado, $hacia)) {
            throw NegociacionInvalida::conflicto('oferta_no_vigente');
        }

        $oferta->forceFill([
            'estado' => $hacia,
            'respondida_en' => $hacia === EstadoOferta::Aceptada || $hacia === EstadoOferta::Rechazada || $hacia === EstadoOferta::Reemplazada
                ? Carbon::now()
                : $oferta->respondida_en,
        ])->save();

        return $oferta;
    }
}
