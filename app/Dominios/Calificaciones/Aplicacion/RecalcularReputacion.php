<?php

namespace App\Dominios\Calificaciones\Aplicacion;

use App\Dominios\Calificaciones\Contratos\Eventos\ReputacionDeProveedorActualizada;
use App\Dominios\Calificaciones\Dominio\AutorCalificacion;
use App\Dominios\Calificaciones\Dominio\EstadoCalificacion;
use App\Dominios\Calificaciones\Dominio\PromedioDeCalificaciones;
use App\Dominios\Calificaciones\Dominio\SujetoCalificacion;
use App\Dominios\Calificaciones\Infraestructura\Eloquent\Calificacion;
use App\Dominios\Proveedores\Contratos\CalificacionDeProveedores;
use App\Dominios\Solicitudes\Contratos\ReputacionDeClientes;
use Illuminate\Support\Facades\DB;

/**
 * Recalcula la reputación de un proveedor o de un cliente desde sus calificaciones
 * visibles (política de calificación, «Promedio y umbrales»; ADR 0039) y la guarda
 * en la ficha del módulo dueño por contrato. Se llama tras cada calificación y
 * tras cada moderación. Idempotente: con las mismas calificaciones deja el mismo
 * resultado.
 *
 * Proveedor: sobre los últimos 100 servicios que recibieron alguna calificación
 * visible del cliente, el promedio ponderado por sujeto (proveedor y
 * piloto). Cliente: el promedio de las últimas 100 calificaciones visibles que
 * le dieron los proveedores. Una calificación `oculta` no cuenta; una reportada
 * (`en_moderacion`) sí, hasta que un administrador decide.
 */
final class RecalcularReputacion
{
    public function __construct(
        private readonly CalificacionDeProveedores $proveedores,
        private readonly ReputacionDeClientes $clientes,
        private readonly ParametrosDeCalificacion $parametros,
    ) {}

    /** @return array{promedio: string, conteo: int} */
    public function deProveedor(int $proveedorId): array
    {
        $contables = array_map(fn (EstadoCalificacion $estado): string => $estado->value, array_filter(EstadoCalificacion::cases(), fn (EstadoCalificacion $estado): bool => $estado->cuentaParaPromedio()));
        $sujetos = array_map(fn (SujetoCalificacion $sujeto): string => $sujeto->value, SujetoCalificacion::delLadoProveedor());

        $base = fn () => Calificacion::query()
            ->where('proveedor_id', $proveedorId)
            ->where('autor_rol', AutorCalificacion::Cliente->value)
            ->whereIn('sujeto_tipo', $sujetos)
            ->whereIn('estado', $contables);

        $solicitudes = $base()
            ->select('solicitud_id')
            ->groupBy('solicitud_id')
            ->orderByRaw('MAX(id) DESC')
            ->limit($this->parametros->ventana())
            ->pluck('solicitud_id')
            ->all();

        $calificaciones = $solicitudes === []
            ? []
            : $base()->whereIn('solicitud_id', $solicitudes)->get(['sujeto_tipo', 'estrellas'])
                ->map(fn (Calificacion $c): array => ['sujeto' => $c->sujeto_tipo->value, 'estrellas' => $c->estrellas])
                ->all();

        $promedio = PromedioDeCalificaciones::ponderado($calificaciones, $this->parametros->pesos());
        $conteo = count($solicitudes);

        DB::transaction(function () use ($proveedorId, $promedio, $conteo): void {
            $this->proveedores->actualizar($proveedorId, $promedio, $conteo);

            event(new ReputacionDeProveedorActualizada($proveedorId, $promedio, $conteo));
        });

        return ['promedio' => $promedio, 'conteo' => $conteo];
    }

    /** @return array{promedio: string, conteo: int} */
    public function deCliente(int $clienteId): array
    {
        $contables = array_map(fn (EstadoCalificacion $estado): string => $estado->value, array_filter(EstadoCalificacion::cases(), fn (EstadoCalificacion $estado): bool => $estado->cuentaParaPromedio()));

        $estrellas = Calificacion::query()
            ->where('cliente_id', $clienteId)
            ->where('autor_rol', AutorCalificacion::Proveedor->value)
            ->where('sujeto_tipo', SujetoCalificacion::Cliente->value)
            ->whereIn('estado', $contables)
            ->orderByDesc('id')
            ->limit($this->parametros->ventana())
            ->pluck('estrellas')
            ->map(fn ($valor): int => (int) $valor)
            ->all();

        $promedio = PromedioDeCalificaciones::simple($estrellas);

        $this->clientes->actualizar($clienteId, $promedio, count($estrellas));

        return ['promedio' => $promedio, 'conteo' => count($estrellas)];
    }
}
