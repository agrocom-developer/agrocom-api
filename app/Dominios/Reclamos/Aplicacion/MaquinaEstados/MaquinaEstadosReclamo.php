<?php

namespace App\Dominios\Reclamos\Aplicacion\MaquinaEstados;

use App\Dominios\Reclamos\Dominio\EstadoReclamo;
use App\Dominios\Reclamos\Dominio\Excepciones\ReclamoInvalido;
use App\Dominios\Reclamos\Dominio\TransicionesReclamo;
use App\Dominios\Reclamos\Infraestructura\Eloquent\HistorialDeReclamo;
use App\Dominios\Reclamos\Infraestructura\Eloquent\Reclamo;
use Illuminate\Support\Facades\DB;

/**
 * Única clase que crea y cambia el `estado` de un reclamo (invariante 7 de
 * CLAUDE.md): consulta {@see TransicionesReclamo} antes de escribir y deja una fila
 * de historial por cada cambio, con autor, rol y motivo. Todo ocurre en una
 * transacción con la fila bloqueada: dos actores decidiendo a la vez no se pisan.
 */
final class MaquinaEstadosReclamo
{
    /**
     * Crea el reclamo `abierta` con su fila inicial de historial.
     *
     * @param  array<string, mixed>  $atributos  sin `estado`: lo fija esta clase.
     */
    public function crear(array $atributos, int $actorUsuarioId, string $actorRol): Reclamo
    {
        return DB::transaction(function () use ($atributos, $actorUsuarioId, $actorRol): Reclamo {
            $reclamo = new Reclamo($atributos);
            $reclamo->estado = EstadoReclamo::Abierta;
            $reclamo->save();

            $this->registrar($reclamo, null, EstadoReclamo::Abierta, $actorUsuarioId, $actorRol, null);

            return $reclamo;
        });
    }

    /**
     * @param  array<string, mixed>  $cambios  atributos que se fijan junto con el estado (ronda, plazos, atendida_por).
     *
     * @throws ReclamoInvalido si la tabla no admite el paso.
     */
    public function transicionar(Reclamo $reclamo, EstadoReclamo $hacia, ?int $actorUsuarioId, string $actorRol, ?string $motivo = null, array $cambios = []): Reclamo
    {
        return DB::transaction(function () use ($reclamo, $hacia, $actorUsuarioId, $actorRol, $motivo, $cambios): Reclamo {
            /** @var Reclamo $actual */
            $actual = Reclamo::query()->lockForUpdate()->findOrFail($reclamo->id);
            $desde = $actual->estado;

            if (! TransicionesReclamo::permitida($desde, $hacia)) {
                throw ReclamoInvalido::transicionNoPermitida($desde->value, $hacia->value);
            }

            $actual->forceFill([...$cambios, 'estado' => $hacia])->save();
            $this->registrar($actual, $desde, $hacia, $actorUsuarioId, $actorRol, $motivo);

            return $actual;
        });
    }

    private function registrar(Reclamo $reclamo, ?EstadoReclamo $desde, EstadoReclamo $hacia, ?int $actorUsuarioId, string $actorRol, ?string $motivo): void
    {
        HistorialDeReclamo::query()->create([
            'reclamo_id' => $reclamo->id,
            'estado_anterior' => $desde,
            'estado_nuevo' => $hacia,
            'actor_usuario_id' => $actorUsuarioId,
            'actor_rol' => $actorRol,
            'motivo' => $motivo === null ? null : trim($motivo),
        ]);
    }
}
