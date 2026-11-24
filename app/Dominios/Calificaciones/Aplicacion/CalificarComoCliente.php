<?php

namespace App\Dominios\Calificaciones\Aplicacion;

use App\Dominios\Calificaciones\Dominio\AutorCalificacion;
use App\Dominios\Calificaciones\Dominio\Excepciones\CalificacionInvalida;
use App\Dominios\Calificaciones\Dominio\SujetoCalificacion;
use App\Dominios\Calificaciones\Infraestructura\Eloquent\Calificacion;
use App\Dominios\Solicitudes\Contratos\DatosServicio;
use App\Dominios\Solicitudes\Contratos\LecturaServicios;
use App\Dominios\Solicitudes\Contratos\ResolucionDeServicio;
use Illuminate\Support\Facades\DB;

/**
 * El cliente califica al proveedor, al piloto y al auxiliar de un servicio
 * `finalizada` (que pasa a `calificada`) (`POST /solicitudes/{id}/calificaciones`, especificación §10.1,
 * política de calificación). Parte del servicio del cliente de la cuenta
 * (invariante 5 de CLAUDE.md): el de otro cliente responde 404. Varias
 * calificaciones en una llamada se guardan juntas o ninguna; la reputación del
 * proveedor se recalcula una vez al final.
 */
final class CalificarComoCliente
{
    public function __construct(
        private readonly LecturaServicios $servicios,
        private readonly SujetosDelServicio $sujetos,
        private readonly EmitirCalificacion $emitir,
        private readonly RecalcularReputacion $reputacion,
        private readonly ResolucionDeServicio $solicitudes,
    ) {}

    /**
     * @param  list<array{uuid_cliente: string, sujeto_tipo: string, estrellas: int, etiquetas?: list<string>|null, comentario?: string|null}>  $calificaciones
     * @return list<array{calificacion: Calificacion, creada: bool}>
     *
     * @throws CalificacionInvalida
     */
    public function ejecutar(int $usuarioId, int $solicitudId, array $calificaciones): array
    {
        $servicio = $this->delCliente($usuarioId, $solicitudId);
        $this->emitir->exigirCalificable($servicio);

        $sujetos = $this->sujetos->delLadoProveedor($servicio);

        $resultado = DB::transaction(function () use ($calificaciones, $servicio, $usuarioId, $sujetos): array {
            $guardadas = [];

            foreach ($calificaciones as $datos) {
                $tipo = SujetoCalificacion::tryFrom($datos['sujeto_tipo']);

                if ($tipo === null || ! isset($sujetos[$tipo->value])) {
                    throw CalificacionInvalida::sujetoInvalido($datos['sujeto_tipo']);
                }

                [$calificacion, $creada] = $this->emitir->ejecutar($servicio, AutorCalificacion::Cliente, $usuarioId, $tipo, $sujetos[$tipo->value]['id'], $datos);

                $guardadas[] = ['calificacion' => $calificacion, 'creada' => $creada];
            }

            return $guardadas;
        });

        if ($servicio->proveedorId !== null && array_filter($resultado, fn (array $r): bool => $r['creada']) !== []) {
            $this->reputacion->deProveedor($servicio->proveedorId);
        }

        if (array_filter($resultado, fn (array $r): bool => $r['creada']) !== []) {
            $this->solicitudes->marcarCalificada($servicio->id, $usuarioId);
        }

        return $resultado;
    }

    /** @throws CalificacionInvalida `servicioAjeno` (404) si el servicio no es del cliente de la cuenta. */
    public function delCliente(int $usuarioId, int $solicitudId): DatosServicio
    {
        $servicio = $this->servicios->datos($solicitudId);

        if ($servicio === null || $servicio->clienteUsuarioId !== $usuarioId) {
            throw CalificacionInvalida::servicioAjeno();
        }

        return $servicio;
    }
}
