<?php

namespace App\Dominios\Notificaciones\Infraestructura;

use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Infraestructura\Eloquent\Notificacion;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\Request;

/**
 * A dónde lleva el click de un aviso, según quién lo abre (ADR 0025 punto 6).
 * Al aviso solo se le guarda el recurso; la URL se resuelve acá, al abrir,
 * contra el ROL ACTIVO de la sesión (invariante 10): si ese rol puede ver el
 * recurso, va a él; si no, cae en la primera pantalla que sí puede ver
 * (`primerDestinoVisible`), nunca en un 403. Un piloto que recibe «tu
 * cuadrilla tiene una orden de trabajo nueva» no tiene
 * `operaciones.trabajo.ver`: aterriza en su tablero.
 *
 * Es el único lugar que conoce las rutas y los permisos de los recursos de los
 * demás módulos; el aviso mismo no sabe nada de ellos. El click nunca concede
 * acceso: el permiso lo sigue evaluando la propia pantalla de destino.
 */
final class DestinoDeNotificacion
{
    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function url(Request $request, Notificacion $notificacion): string
    {
        [$ruta, $permiso, $conId] = match ($notificacion->recurso_tipo) {
            RecursoNotificable::Contrato => ['panel.contratos.edit', 'comercial.contrato.editar', true],
            RecursoNotificable::OrdenTrabajo => ['panel.trabajos.show', 'operaciones.trabajo.ver', true],
            RecursoNotificable::Trabajo => ['panel.trabajos.detalle', 'operaciones.trabajo.ver', true],
            RecursoNotificable::Proveedor => ['panel.proveedores.show', 'proveedores.proveedor.ver', true],
            RecursoNotificable::Piloto => ['panel.pilotos.show', 'proveedores.piloto.ver', true],
            RecursoNotificable::Auxiliar => ['panel.auxiliares.show', 'proveedores.auxiliar.ver', true],
            RecursoNotificable::Dron => ['panel.drones-flota.show', 'flota.dron.ver', true],
            // La cola de documentos no tiene ficha por documento: lleva a la cola.
            RecursoNotificable::Documento => ['panel.documentos.index', 'proveedores.documento.ver', false],
        };

        if ($this->autorizacion->tienePermiso($request, $permiso)) {
            return $conId ? route($ruta, $notificacion->recurso_id) : route($ruta);
        }

        return $this->autorizacion->primerDestinoVisible($request);
    }
}
