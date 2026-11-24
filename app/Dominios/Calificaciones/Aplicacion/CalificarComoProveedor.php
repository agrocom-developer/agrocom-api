<?php

namespace App\Dominios\Calificaciones\Aplicacion;

use App\Dominios\Calificaciones\Dominio\AutorCalificacion;
use App\Dominios\Calificaciones\Dominio\Excepciones\CalificacionInvalida;
use App\Dominios\Calificaciones\Dominio\SujetoCalificacion;
use App\Dominios\Calificaciones\Infraestructura\Eloquent\Calificacion;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Solicitudes\Contratos\DatosServicio;
use App\Dominios\Solicitudes\Contratos\LecturaServicios;

/**
 * El proveedor califica al cliente de un servicio `completada`, en privado
 * (`POST /solicitudes/{id}/calificar-cliente`, ADR 0031): no se publica, sirve a
 * moderación y a soporte, y alimenta la reputación del cliente. Parte del
 * proveedor de la cuenta (titular o piloto): un servicio de otro proveedor
 * responde 404 (invariante 5 de CLAUDE.md).
 */
final class CalificarComoProveedor
{
    public function __construct(
        private readonly LecturaServicios $servicios,
        private readonly LecturaProveedores $proveedores,
        private readonly EmitirCalificacion $emitir,
        private readonly RecalcularReputacion $reputacion,
    ) {}

    /**
     * @param  array{uuid_cliente: string, estrellas: int, etiquetas?: list<string>|null, comentario?: string|null}  $datos
     * @return array{calificacion: Calificacion, creada: bool}
     *
     * @throws CalificacionInvalida
     */
    public function ejecutar(int $usuarioId, int $solicitudId, array $datos): array
    {
        $servicio = $this->delProveedor($usuarioId, $solicitudId);
        $this->emitir->exigirCalificable($servicio);

        [$calificacion, $creada] = $this->emitir->ejecutar($servicio, AutorCalificacion::Proveedor, $usuarioId, SujetoCalificacion::Cliente, $servicio->clienteId, $datos);

        if ($creada) {
            $this->reputacion->deCliente($servicio->clienteId);
        }

        return ['calificacion' => $calificacion, 'creada' => $creada];
    }

    /** @throws CalificacionInvalida `servicioAjeno` (404) si el servicio no lo prestó el proveedor de la cuenta. */
    public function delProveedor(int $usuarioId, int $solicitudId): DatosServicio
    {
        $proveedor = $this->proveedores->proveedorDeLaCuenta($usuarioId) ?? throw CalificacionInvalida::sinProveedor();
        $servicio = $this->servicios->datos($solicitudId);

        if ($servicio === null || $servicio->proveedorId !== $proveedor->id) {
            throw CalificacionInvalida::servicioAjeno();
        }

        return $servicio;
    }
}
