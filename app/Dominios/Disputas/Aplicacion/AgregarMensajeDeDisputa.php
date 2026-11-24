<?php

namespace App\Dominios\Disputas\Aplicacion;

use App\Dominios\Disputas\Dominio\Excepciones\DisputaInvalida;
use App\Dominios\Disputas\Dominio\ParteDeDisputa;
use App\Dominios\Disputas\Infraestructura\Eloquent\Disputa;
use App\Dominios\Disputas\Infraestructura\Eloquent\MensajeDeDisputa;

/**
 * Un mensaje en la conversación de una disputa: de una parte (`POST
 * /disputas/{id}/mensajes`, en la app) o de soporte (en el panel, donde además
 * puede pedir información a la contraparte). Solo mientras la disputa está viva:
 * una resuelta se reabre apelando, no escribiendo. Solo se inserta; nunca se edita.
 */
final class AgregarMensajeDeDisputa
{
    public function __construct(
        private readonly ParticipanteDeDisputa $participantes,
        private readonly ParametrosDeDisputa $parametros,
    ) {}

    /** @throws DisputaInvalida */
    public function comoParte(int $usuarioId, ParteDeDisputa $parte, int $disputaId, string $texto): MensajeDeDisputa
    {
        $disputa = $this->participantes->disputa($usuarioId, $parte, $disputaId);

        return $this->agregar($disputa, $usuarioId, $parte->value, 'mensaje', $texto);
    }

    /**
     * Soporte escribe a las partes; con `$pideInformacion` el mensaje queda marcado como
     * solicitud de información a la contraparte (que responde en 48 horas).
     *
     * @throws DisputaInvalida
     */
    public function comoSoporte(int $actorUsuarioId, int $disputaId, string $texto, bool $pideInformacion = false): MensajeDeDisputa
    {
        $disputa = Disputa::query()->find($disputaId) ?? throw DisputaInvalida::disputaAjena();

        return $this->agregar($disputa, $actorUsuarioId, 'soporte', $pideInformacion ? 'informacion' : 'mensaje', $texto);
    }

    private function agregar(Disputa $disputa, int $usuarioId, string $rol, string $tipo, string $texto): MensajeDeDisputa
    {
        $texto = trim($texto);

        if ($texto === '') {
            throw DisputaInvalida::textoRequerido();
        }

        if (! $disputa->estado->estaViva()) {
            throw DisputaInvalida::noAdmiteMensajes();
        }

        return MensajeDeDisputa::query()->create([
            'disputa_id' => $disputa->id,
            'autor_usuario_id' => $usuarioId,
            'autor_rol' => $rol,
            'tipo' => $tipo,
            'texto' => mb_substr($texto, 0, $this->parametros->textoMax()),
        ]);
    }
}
