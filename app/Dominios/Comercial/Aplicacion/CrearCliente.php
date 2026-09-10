<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Dominio\Excepciones\ClienteDuplicado;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Alta de un cliente con sus contactos en una sola operación (HU-22, tarea
 * 33): el formulario es uno solo, así que el cliente y sus contactos nacen
 * en la misma transacción — nunca dos requests separados para una sola
 * acción de usuario.
 */
final class CrearCliente
{
    /**
     * @param  list<array{tipo: string, nombre: string, telefono: string|null, email: string|null, observaciones: string|null}>  $contactos
     *
     * @throws ClienteDuplicado si el NIT ya pertenece a otro cliente activo
     *                          (índice parcial `com_clientes_nit_unico`).
     */
    public function ejecutar(string $razonSocial, ?string $nit, string $tipoPersona, array $contactos): Cliente
    {
        return DB::transaction(function () use ($razonSocial, $nit, $tipoPersona, $contactos): Cliente {
            $cliente = new Cliente([
                'razon_social' => $razonSocial,
                'nit' => $nit,
                'tipo_persona' => $tipoPersona,
            ]);

            try {
                $cliente->save();
            } catch (QueryException $excepcion) {
                $this->relanzarComoDuplicado($excepcion, $nit);
            }

            foreach ($contactos as $contacto) {
                $cliente->contactos()->create($contacto);
            }

            return $cliente->refresh();
        });
    }

    /**
     * Traduce la violación del índice único parcial a una excepción de
     * dominio legible — nunca deja propagarse el 500 crudo del motor de base
     * de datos. El formato del mensaje difiere por driver: Postgres nombra
     * el índice (`com_clientes_nit_unico`); SQLite (motor de los tests)
     * nombra tabla.columna (`com_clientes.nit`) — mismo criterio que
     * `AsignarRolesUsuario::relanzarComoDuplicado`.
     *
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
