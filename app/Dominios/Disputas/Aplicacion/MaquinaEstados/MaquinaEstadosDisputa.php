<?php

namespace App\Dominios\Disputas\Aplicacion\MaquinaEstados;

use App\Dominios\Disputas\Dominio\EstadoDisputa;
use App\Dominios\Disputas\Dominio\Excepciones\DisputaInvalida;
use App\Dominios\Disputas\Dominio\TransicionesDisputa;
use App\Dominios\Disputas\Infraestructura\Eloquent\Disputa;
use App\Dominios\Disputas\Infraestructura\Eloquent\HistorialDeDisputa;
use Illuminate\Support\Facades\DB;

/**
 * Única clase que crea y cambia el `estado` de una disputa (invariante 7 de
 * CLAUDE.md): consulta {@see TransicionesDisputa} antes de escribir y deja una fila
 * de historial por cada cambio, con autor, rol y motivo. Todo ocurre en una
 * transacción con la fila bloqueada: dos actores decidiendo a la vez no se pisan.
 */
final class MaquinaEstadosDisputa
{
    /**
     * Crea la disputa `abierta` con su fila inicial de historial.
     *
     * @param  array<string, mixed>  $atributos  sin `estado`: lo fija esta clase.
     */
    public function crear(array $atributos, int $actorUsuarioId, string $actorRol): Disputa
    {
        return DB::transaction(function () use ($atributos, $actorUsuarioId, $actorRol): Disputa {
            $disputa = new Disputa($atributos);
            $disputa->estado = EstadoDisputa::Abierta;
            $disputa->save();

            $this->registrar($disputa, null, EstadoDisputa::Abierta, $actorUsuarioId, $actorRol, null);

            return $disputa;
        });
    }

    /**
     * @param  array<string, mixed>  $cambios  atributos que se fijan junto con el estado (ronda, plazos, atendida_por).
     *
     * @throws DisputaInvalida si la tabla no admite el paso.
     */
    public function transicionar(Disputa $disputa, EstadoDisputa $hacia, ?int $actorUsuarioId, string $actorRol, ?string $motivo = null, array $cambios = []): Disputa
    {
        return DB::transaction(function () use ($disputa, $hacia, $actorUsuarioId, $actorRol, $motivo, $cambios): Disputa {
            /** @var Disputa $actual */
            $actual = Disputa::query()->lockForUpdate()->findOrFail($disputa->id);
            $desde = $actual->estado;

            if (! TransicionesDisputa::permitida($desde, $hacia)) {
                throw DisputaInvalida::transicionNoPermitida($desde->value, $hacia->value);
            }

            $actual->forceFill([...$cambios, 'estado' => $hacia])->save();
            $this->registrar($actual, $desde, $hacia, $actorUsuarioId, $actorRol, $motivo);

            return $actual;
        });
    }

    private function registrar(Disputa $disputa, ?EstadoDisputa $desde, EstadoDisputa $hacia, ?int $actorUsuarioId, string $actorRol, ?string $motivo): void
    {
        HistorialDeDisputa::query()->create([
            'disputa_id' => $disputa->id,
            'estado_anterior' => $desde,
            'estado_nuevo' => $hacia,
            'actor_usuario_id' => $actorUsuarioId,
            'actor_rol' => $actorRol,
            'motivo' => $motivo === null ? null : trim($motivo),
        ]);
    }
}
