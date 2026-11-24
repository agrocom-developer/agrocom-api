<?php

namespace App\Dominios\Disputas\Aplicacion;

use App\Dominios\Disputas\Aplicacion\MaquinaEstados\MaquinaEstadosDisputa;
use App\Dominios\Disputas\Contratos\Eventos\DisputaApelada;
use App\Dominios\Disputas\Dominio\EstadoDisputa;
use App\Dominios\Disputas\Dominio\Excepciones\DisputaInvalida;
use App\Dominios\Disputas\Dominio\ParteDeDisputa;
use App\Dominios\Disputas\Infraestructura\Eloquent\Disputa;
use App\Dominios\Disputas\Infraestructura\Eloquent\MensajeDeDisputa;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cualquiera de las partes apela una resolución, una sola vez y dentro de 7 días,
 * con pruebas nuevas (`POST /disputas/{id}/apelar`, política de disputas, «Proceso»,
 * punto 4): la disputa vuelve a `en_revision` en su segunda ronda y la apelación
 * queda como un mensaje con el texto de las pruebas. La primera resolución sigue
 * rigiendo hasta que la segunda la reemplace.
 */
final class ApelarResolucion
{
    public function __construct(
        private readonly ParticipanteDeDisputa $participantes,
        private readonly MaquinaEstadosDisputa $estados,
        private readonly ParametrosDeDisputa $parametros,
    ) {}

    /** @throws DisputaInvalida */
    public function ejecutar(int $usuarioId, ParteDeDisputa $parte, int $disputaId, string $pruebas): Disputa
    {
        $disputa = $this->participantes->disputa($usuarioId, $parte, $disputaId);
        $pruebas = trim($pruebas);

        if ($pruebas === '') {
            throw DisputaInvalida::textoRequerido();
        }

        if ($disputa->estado !== EstadoDisputa::Resuelta || $disputa->ronda >= 2) {
            throw DisputaInvalida::noSeApela();
        }

        if ($disputa->apelable_hasta === null || $disputa->apelable_hasta->lessThan(Carbon::now())) {
            throw DisputaInvalida::apelacionVencida();
        }

        return DB::transaction(function () use ($disputa, $usuarioId, $parte, $pruebas): Disputa {
            $apelada = $this->estados->transicionar($disputa, EstadoDisputa::EnRevision, $usuarioId, $parte->value, $pruebas, [
                'ronda' => 2,
                'apelable_hasta' => null,
                'responde_antes_de' => Carbon::now()->addHours($this->parametros->respuestaHoras()),
            ]);

            MensajeDeDisputa::query()->create([
                'disputa_id' => $apelada->id,
                'autor_usuario_id' => $usuarioId,
                'autor_rol' => $parte->value,
                'tipo' => 'apelacion',
                'texto' => mb_substr($pruebas, 0, $this->parametros->textoMax()),
            ]);

            event(new DisputaApelada($apelada->id, $apelada->solicitud_id, $parte->value, $this->participantes->usuariosDe($apelada, $parte->contraparte())));

            return $apelada;
        });
    }
}
