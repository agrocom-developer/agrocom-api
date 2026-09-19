<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Requests\Concerns;

use App\Dominios\Comercial\Contratos\LecturaContrato;
use Illuminate\Validation\Rule;

/**
 * Regla compartida por `CrearOrdenRequest` y `ActualizarOrdenRequest`: el
 * contacto que emite la orden (`emitida_por_contacto_id`) tiene que ser un
 * contacto DEL CLIENTE del contrato de la orden — no de cualquier cliente.
 *
 * El formulario ya solo ofrece los contactos de ese cliente, pero eso es
 * presentación: un pedido armado a mano con el id de un contacto de otro cliente
 * lo aceptaría. Acá se cierra del lado del servidor, con la misma pregunta que
 * hace la pantalla. El cliente del contrato se pregunta a Comercial por su
 * contrato de lectura ({@see LecturaContrato}), no por su tabla.
 *
 * Si el contrato no existe no se acota nada: ya lo rechaza la regla de
 * `contrato_id`, y ese es el error que el usuario tiene que ver.
 */
trait ValidaContactoDelCliente
{
    /** @return list<mixed> */
    private function reglasContactoDelCliente(?int $contratoId): array
    {
        $regla = Rule::exists('com_cliente_contactos', 'id')->whereNull('deleted_at');

        $clienteId = $contratoId === null
            ? null
            : app(LecturaContrato::class)->obtenerParaOrden($contratoId)?->clienteId;

        if ($clienteId !== null) {
            $regla->where('cliente_id', $clienteId);
        }

        return ['nullable', 'integer', $regla];
    }
}
