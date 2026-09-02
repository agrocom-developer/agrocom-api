<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Dominio\Excepciones\ClienteDuplicado;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\ClienteContacto;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Edición de un cliente con sus contactos en una sola operación (HU-22, tarea
 * 33): mismo criterio que `CrearCliente` — una sola transacción para cliente
 * y contactos.
 *
 * El set de contactos recibido es el COMPLETO y definitivo, no un delta: los
 * que faltan respecto a los actuales se dan de baja (soft delete), los que
 * traen `id` se actualizan y los que no traen `id` se crean — así "editar"
 * nunca deja un contacto huérfano por omisión (mismo criterio que
 * `AsignarRolesUsuario::sincronizarRoles`).
 */
final class ActualizarCliente
{
    /**
     * @param  list<array{id: int|null, tipo: string, nombre: string, telefono: string|null, email: string|null, observaciones: string|null}>  $contactos
     *
     * @throws ClienteDuplicado si el NIT ya pertenece a otro cliente activo
     *                          (índice parcial `com_clientes_nit_unico`).
     */
    public function ejecutar(Cliente $cliente, string $razonSocial, ?string $nit, array $contactos): Cliente
    {
        return DB::transaction(function () use ($cliente, $razonSocial, $nit, $contactos): Cliente {
            $cliente->razon_social = $razonSocial;
            $cliente->nit = $nit;

            try {
                $cliente->save();
            } catch (QueryException $excepcion) {
                $this->relanzarComoDuplicado($excepcion, $nit);
            }

            $this->sincronizarContactos($cliente, $contactos);

            return $cliente->refresh();
        });
    }

    /** @param  list<array{id: int|null, tipo: string, nombre: string, telefono: string|null, email: string|null, observaciones: string|null}>  $contactos */
    private function sincronizarContactos(Cliente $cliente, array $contactos): void
    {
        $idsEnviados = array_values(array_filter(array_column($contactos, 'id')));

        // `whereNotIn` con un array vacío no excluye nada (Laravel lo resuelve
        // como "verdadero para toda fila"): si ningún contacto enviado trae
        // `id`, esto da de baja a todos los actuales — correcto, porque
        // significa que el set enviado los reemplaza por completo.
        $cliente->contactos()
            ->whereNotIn('id', $idsEnviados)
            ->get()
            ->each(function (ClienteContacto $contacto): void {
                $usuarioId = Auth::id();

                if ($usuarioId !== null) {
                    $contacto->updated_by = (int) $usuarioId;
                    $contacto->save();
                }

                $contacto->delete();
            });

        foreach ($contactos as $datos) {
            $id = $datos['id'];
            unset($datos['id']);

            $contacto = $id !== null
                ? $cliente->contactos()->whereKey($id)->firstOrFail()
                : new ClienteContacto(['cliente_id' => $cliente->id]);

            $contacto->fill($datos);
            $contacto->save();
        }
    }

    /**
     * @throws ClienteDuplicado si la violación corresponde al NIT.
     * @throws QueryException si la violación no es la contemplada.
     */
    private function relanzarComoDuplicado(QueryException $excepcion, ?string $nit): never
    {
        $mensaje = $excepcion->getMessage();

        if ($nit !== null && (str_contains($mensaje, 'com_clientes_nit_unico') || str_contains($mensaje, 'com_clientes.nit'))) {
            throw ClienteDuplicado::porNit($nit);
        }

        throw $excepcion;
    }
}
