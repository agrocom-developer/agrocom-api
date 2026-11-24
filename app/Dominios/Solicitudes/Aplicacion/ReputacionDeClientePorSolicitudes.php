<?php

namespace App\Dominios\Solicitudes\Aplicacion;

use App\Dominios\Solicitudes\Contratos\Eventos\ClienteAvisadoPorCalificacion;
use App\Dominios\Solicitudes\Contratos\Eventos\PublicacionDeClienteLimitada;
use App\Dominios\Solicitudes\Contratos\ReputacionDeClientes;
use App\Dominios\Solicitudes\Dominio\PoliticaDeCliente;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Cliente;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Guarda la calificación promedio del cliente y le aplica la política de
 * calificación (ADR 0039): bajo el umbral recibe un aviso y, si sigue bajo pasado
 * el plazo, se limita su publicación hasta una revisión; si el promedio se
 * recupera —porque un administrador moderó calificaciones o llegaron mejores—, el
 * aviso y la limitación se retiran. Idempotente: repetir el mismo promedio no
 * repite avisos.
 */
final class ReputacionDeClientePorSolicitudes implements ReputacionDeClientes
{
    public function __construct(private readonly ParametrosDeServicio $parametros) {}

    public function actualizar(int $clienteId, string $promedio, int $conteo): void
    {
        DB::transaction(function () use ($clienteId, $promedio, $conteo): void {
            $cliente = Cliente::query()->lockForUpdate()->find($clienteId);

            if ($cliente === null) {
                return;
            }

            $ahora = Carbon::now();
            $politica = $this->parametros->calificacionDeCliente();

            $accion = PoliticaDeCliente::evaluar(
                $promedio,
                $conteo,
                $cliente->publicacion_limitada_en !== null,
                $cliente->aviso_calificacion_en === null ? null : (int) $cliente->aviso_calificacion_en->diffInDays($ahora, true),
                $politica['umbral'],
                $politica['minimo_conteo'],
                $politica['plazo_limitacion_dias'],
            );

            $cambios = ['calificacion_promedio' => $promedio, 'calificaciones_conteo' => $conteo];

            if ($accion === PoliticaDeCliente::AVISAR) {
                $cambios['aviso_calificacion_en'] = $ahora;
            }

            if ($accion === PoliticaDeCliente::LIMITAR) {
                $cambios['publicacion_limitada_en'] = $ahora;
            }

            if ($accion === PoliticaDeCliente::RESTABLECER) {
                $cambios['aviso_calificacion_en'] = null;
                $cambios['publicacion_limitada_en'] = null;
            }

            $cliente->forceFill($cambios)->save();

            if ($accion === PoliticaDeCliente::AVISAR) {
                event(new ClienteAvisadoPorCalificacion($cliente->id, $cliente->usuario_id, $promedio));
            }

            if ($accion === PoliticaDeCliente::LIMITAR) {
                event(new PublicacionDeClienteLimitada($cliente->id, $cliente->usuario_id, $cliente->razon_social, $promedio));
            }
        });
    }
}
