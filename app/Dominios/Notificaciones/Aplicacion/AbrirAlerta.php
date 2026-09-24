<?php

namespace App\Dominios\Notificaciones\Aplicacion;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModoSoloLectura;
use App\Dominios\Notificaciones\Infraestructura\Eloquent\AlertaVista;
use App\Dominios\Operaciones\Contratos\LecturaPanelOperaciones;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Abrir una alerta técnica desde la campana: la deja leída PARA ESTA CUENTA.
 * No la declara atendida — eso es de todos y se hace en su pantalla, con su
 * permiso —; solo es que esta cuenta ya la vio.
 *
 * La alerta tiene que existir (`ModelNotFoundException`, que el panel devuelve
 * como 404): un id inventado dejaría un estado huérfano que taparía una alerta
 * futura de esa cuenta. Quién puede ver las alertas (`operaciones.alerta.ver`,
 * contra el rol activo) lo decide quien llama: acá no hay rol.
 *
 * Con la vista «como otro usuario» activa (tarea 140) no se escribe, igual que
 * {@see AbrirNotificacion}: quedaría firmado por la cuenta observada.
 */
final class AbrirAlerta
{
    public function __construct(
        private readonly EstadoDeAlertaPorCuenta $estado,
        private readonly LecturaPanelOperaciones $operaciones,
    ) {}

    /**
     * @throws ModelNotFoundException si no existe una alerta con ese id.
     */
    public function ejecutar(int $usuarioId, int $alertaId): void
    {
        if ($this->operaciones->idsAlertasExistentes([$alertaId]) === []) {
            throw (new ModelNotFoundException)->setModel(AlertaVista::class, [$alertaId]);
        }

        if (ModoSoloLectura::activo()) {
            return;
        }

        $this->estado->marcarLeida($usuarioId, $alertaId);
    }
}
