<?php

namespace App\Dominios\Notificaciones\Aplicacion;

use App\Dominios\Notificaciones\Dominio\NotificacionArmada;
use App\Dominios\Notificaciones\Infraestructura\Eloquent\Notificacion;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Reparte el aviso de un evento de dominio: aplica la regla del evento,
 * resuelve las cuentas destinatarias y crea una fila por cuenta (ADR 0025).
 * Un solo caso de uso atiende a todos los eventos; lo que cambia de un evento
 * a otro vive en su regla.
 *
 * Idempotente por identidad del hecho: `UNIQUE (usuario_id, clave_evento)`.
 * Correr esto dos veces con el mismo evento deja las mismas filas — el
 * segundo pase ve que ya existen y no crea nada, y si dos procesos se cruzan,
 * la violación de unicidad de la base se traduce en «ya estaba». La búsqueda
 * previa incluye las filas dadas de baja (`withTrashed`), igual que el
 * índice: una cuenta que descartó un aviso no lo recibe de nuevo por un
 * reintento.
 *
 * Cada fila es su propia escritura atómica, no una transacción de todo el
 * reparto: si el proceso muere a mitad, repetir el evento completa las que
 * faltan sin duplicar las hechas.
 */
final class EntregarNotificacion
{
    public function __construct(
        private readonly ReglasDeNotificacion $reglas,
        private readonly ResolverDestinatarios $destinatarios,
    ) {}

    /**
     * @param  int|null  $excluirUsuarioId  quien disparó el hecho: no se avisa a sí mismo
     * @return int cantidad de avisos NUEVOS creados (0 si el evento ya estaba repartido o no tiene regla)
     */
    public function ejecutar(object $evento, ?int $excluirUsuarioId = null): int
    {
        $regla = $this->reglas->para($evento);

        if ($regla === null) {
            return 0;
        }

        $armada = $regla->armar($evento);
        $creadas = 0;

        foreach ($this->destinatarios->ejecutar($armada->destinatarios) as $usuarioId) {
            if ($usuarioId === $excluirUsuarioId) {
                continue;
            }

            if ($this->entregar($armada, $usuarioId)) {
                $creadas++;
            }
        }

        return $creadas;
    }

    private function entregar(NotificacionArmada $armada, int $usuarioId): bool
    {
        $yaEntregada = Notificacion::withTrashed()
            ->deUsuario($usuarioId)
            ->where('clave_evento', $armada->claveEvento)
            ->exists();

        if ($yaEntregada) {
            return false;
        }

        try {
            // Transacción propia: dentro de una mayor es un SAVEPOINT, así una
            // violación de unicidad atrapada no deja abortada la de afuera en
            // Postgres (mismo recurso que el devengo, ADR 0023).
            DB::transaction(static fn (): Notificacion => Notificacion::create([
                'usuario_id' => $usuarioId,
                'tipo' => $armada->tipo,
                'clave_evento' => $armada->claveEvento,
                'parametros' => $armada->parametros,
                'recurso_tipo' => $armada->recurso,
                'recurso_id' => $armada->recursoId,
            ]));
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }
}
