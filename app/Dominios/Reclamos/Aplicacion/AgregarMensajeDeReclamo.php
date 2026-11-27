<?php

namespace App\Dominios\Reclamos\Aplicacion;

use App\Dominios\Reclamos\Dominio\Excepciones\ReclamoInvalido;
use App\Dominios\Reclamos\Dominio\ParteDeReclamo;
use App\Dominios\Reclamos\Infraestructura\Eloquent\MensajeDeReclamo;
use App\Dominios\Reclamos\Infraestructura\Eloquent\Reclamo;

/**
 * Un mensaje en la conversación de un reclamo: de una parte (`POST
 * /reclamos/{id}/mensajes`, en la app) o de soporte (en el panel, donde además
 * puede pedir información a la contraparte). Solo mientras el reclamo está viva:
 * una resuelta se reabre apelando, no escribiendo. Solo se inserta; nunca se edita.
 */
final class AgregarMensajeDeReclamo
{
    public function __construct(
        private readonly ParticipanteDeReclamo $participantes,
        private readonly ParametrosDeReclamo $parametros,
    ) {}

    /** @throws ReclamoInvalido */
    public function comoParte(int $usuarioId, ParteDeReclamo $parte, int $reclamoId, string $texto): MensajeDeReclamo
    {
        $reclamo = $this->participantes->reclamo($usuarioId, $parte, $reclamoId);

        return $this->agregar($reclamo, $usuarioId, $parte->value, 'mensaje', $texto);
    }

    /**
     * Soporte escribe a las partes; con `$pideInformacion` el mensaje queda marcado como
     * solicitud de información a la contraparte (que responde en 48 horas).
     *
     * @throws ReclamoInvalido
     */
    public function comoSoporte(int $actorUsuarioId, int $reclamoId, string $texto, bool $pideInformacion = false): MensajeDeReclamo
    {
        $reclamo = Reclamo::query()->find($reclamoId) ?? throw ReclamoInvalido::reclamoAjeno();

        return $this->agregar($reclamo, $actorUsuarioId, 'soporte', $pideInformacion ? 'informacion' : 'mensaje', $texto);
    }

    private function agregar(Reclamo $reclamo, int $usuarioId, string $rol, string $tipo, string $texto): MensajeDeReclamo
    {
        $texto = trim($texto);

        if ($texto === '') {
            throw ReclamoInvalido::textoRequerido();
        }

        if (! $reclamo->estado->estaViva()) {
            throw ReclamoInvalido::noAdmiteMensajes();
        }

        return MensajeDeReclamo::query()->create([
            'reclamo_id' => $reclamo->id,
            'autor_usuario_id' => $usuarioId,
            'autor_rol' => $rol,
            'tipo' => $tipo,
            'texto' => mb_substr($texto, 0, $this->parametros->textoMax()),
        ]);
    }
}
