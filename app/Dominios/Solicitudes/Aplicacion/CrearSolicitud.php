<?php

namespace App\Dominios\Solicitudes\Aplicacion;

use App\Dominios\Solicitudes\Aplicacion\MaquinaEstados\MaquinaEstadosSolicitud;
use App\Dominios\Solicitudes\Dominio\AportaProducto;
use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;
use App\Dominios\Solicitudes\Dominio\TipoAplicacionServicio;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Terreno;
use Brick\Math\BigDecimal;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

/**
 * Crea una solicitud de servicio en `borrador` (especificación §10.1,
 * `POST /solicitudes`). Es idempotente por `uuid_cliente` (invariante 1 de
 * CLAUDE.md): reintentar el mismo uuid devuelve la solicitud ya creada y nunca
 * una segunda. El precio por hectárea propuesto puede venir al crearla o fijarse
 * antes de publicar; es obligatorio para abrirla al mercado.
 */
final class CrearSolicitud
{
    public function __construct(
        private readonly ClienteDeLaCuenta $clientes,
        private readonly MaquinaEstadosSolicitud $estados,
    ) {}

    /**
     * @param  array{uuid_cliente: string, terreno_id: int, tipo_aplicacion: string, hectareas_solicitadas: string|int|float, cultivo?: ?string, producto_indicado: string, aporta_producto?: ?string, ventana_desde: string, ventana_hasta: string, urgente?: ?bool, observaciones?: ?string, precio_ha_propuesto?: string|int|float|null}  $datos
     * @return array{0: SolicitudServicio, 1: bool} la solicitud y si se creó ahora (`false` = reintento).
     *
     * @throws SolicitudInvalida
     */
    public function ejecutar(int $usuarioId, array $datos): array
    {
        $cliente = $this->clientes->activo($usuarioId);

        $existente = SolicitudServicio::query()->where('uuid_cliente', $datos['uuid_cliente'])->first();
        if ($existente !== null) {
            return [$this->deLaCuenta($existente, $cliente->id), false];
        }

        /** @var Terreno|null $terreno */
        $terreno = Terreno::query()->where('cliente_id', $cliente->id)->find($datos['terreno_id']);
        if ($terreno === null) {
            throw SolicitudInvalida::terrenoAjeno();
        }

        $hectareas = BigDecimal::of((string) $datos['hectareas_solicitadas']);
        if ($hectareas->isGreaterThan(BigDecimal::of($terreno->hectareas))) {
            throw SolicitudInvalida::hectareasExcedenTerreno((string) $hectareas, $terreno->hectareas);
        }

        if (Carbon::parse($datos['ventana_hasta'])->startOfDay()->lt(Carbon::today())) {
            throw SolicitudInvalida::ventanaVencida();
        }

        $precio = isset($datos['precio_ha_propuesto']) ? (string) $datos['precio_ha_propuesto'] : null;
        if ($precio !== null) {
            PublicarSolicitud::validarPrecioDe($precio);
        }

        $atributos = [
            'uuid_cliente' => $datos['uuid_cliente'],
            'cliente_id' => $cliente->id,
            'terreno_id' => $terreno->id,
            'tipo_aplicacion' => TipoAplicacionServicio::from($datos['tipo_aplicacion']),
            'hectareas_solicitadas' => (string) $hectareas,
            'cultivo' => $datos['cultivo'] ?? $terreno->cultivo,
            'producto_indicado' => trim($datos['producto_indicado']),
            'aporta_producto' => AportaProducto::from($datos['aporta_producto'] ?? AportaProducto::Cliente->value),
            'ventana_desde' => $datos['ventana_desde'],
            'ventana_hasta' => $datos['ventana_hasta'],
            'urgente' => (bool) ($datos['urgente'] ?? false),
            'observaciones' => $datos['observaciones'] ?? null,
            'precio_ha_propuesto' => $precio,
        ];

        try {
            $solicitud = $this->estados->crear($atributos, $usuarioId);
        } catch (QueryException $excepcion) {
            // Dos reintentos en paralelo: gana el primero y el segundo recibe esa misma fila.
            $ganadora = SolicitudServicio::query()->where('uuid_cliente', $datos['uuid_cliente'])->first();
            if ($ganadora === null) {
                throw $excepcion;
            }

            return [$this->deLaCuenta($ganadora, $cliente->id), false];
        }

        return [$solicitud->refresh(), true];
    }

    /** Un `uuid_cliente` ya usado por OTRO cliente no se revela: se rechaza como en uso. */
    private function deLaCuenta(SolicitudServicio $solicitud, int $clienteId): SolicitudServicio
    {
        if ($solicitud->cliente_id !== $clienteId) {
            throw SolicitudInvalida::uuidEnUso();
        }

        return $solicitud;
    }
}
