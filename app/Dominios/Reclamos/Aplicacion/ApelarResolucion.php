<?php

namespace App\Dominios\Reclamos\Aplicacion;

use App\Dominios\Reclamos\Aplicacion\MaquinaEstados\MaquinaEstadosReclamo;
use App\Dominios\Reclamos\Contratos\Eventos\ReclamoApelado;
use App\Dominios\Reclamos\Dominio\EstadoReclamo;
use App\Dominios\Reclamos\Dominio\Excepciones\ReclamoInvalido;
use App\Dominios\Reclamos\Dominio\ParteDeReclamo;
use App\Dominios\Reclamos\Infraestructura\Eloquent\MensajeDeReclamo;
use App\Dominios\Reclamos\Infraestructura\Eloquent\Reclamo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cualquiera de las partes apela una resolución, una sola vez y dentro de 7 días,
 * con pruebas nuevas (`POST /reclamos/{id}/apelar`, política de reclamos, «Proceso»,
 * punto 4): el reclamo vuelve a `en_revision` en su segunda ronda y la apelación
 * queda como un mensaje con el texto de las pruebas. La primera resolución sigue
 * rigiendo hasta que la segunda la reemplace.
 */
final class ApelarResolucion
{
    public function __construct(
        private readonly ParticipanteDeReclamo $participantes,
        private readonly MaquinaEstadosReclamo $estados,
        private readonly ParametrosDeReclamo $parametros,
    ) {}

    /** @throws ReclamoInvalido */
    public function ejecutar(int $usuarioId, ParteDeReclamo $parte, int $reclamoId, string $pruebas): Reclamo
    {
        $reclamo = $this->participantes->reclamo($usuarioId, $parte, $reclamoId);
        $pruebas = trim($pruebas);

        if ($pruebas === '') {
            throw ReclamoInvalido::textoRequerido();
        }

        if ($reclamo->estado !== EstadoReclamo::Resuelta || $reclamo->ronda >= 2) {
            throw ReclamoInvalido::noSeApela();
        }

        if ($reclamo->apelable_hasta === null || $reclamo->apelable_hasta->lessThan(Carbon::now())) {
            throw ReclamoInvalido::apelacionVencida();
        }

        return DB::transaction(function () use ($reclamo, $usuarioId, $parte, $pruebas): Reclamo {
            $apelada = $this->estados->transicionar($reclamo, EstadoReclamo::EnRevision, $usuarioId, $parte->value, $pruebas, [
                'ronda' => 2,
                'apelable_hasta' => null,
                'responde_antes_de' => Carbon::now()->addHours($this->parametros->respuestaHoras()),
            ]);

            MensajeDeReclamo::query()->create([
                'reclamo_id' => $apelada->id,
                'autor_usuario_id' => $usuarioId,
                'autor_rol' => $parte->value,
                'tipo' => 'apelacion',
                'texto' => mb_substr($pruebas, 0, $this->parametros->textoMax()),
            ]);

            event(new ReclamoApelado($apelada->id, $apelada->solicitud_id, $parte->value, $this->participantes->usuariosDe($apelada, $parte->contraparte())));

            return $apelada;
        });
    }
}
