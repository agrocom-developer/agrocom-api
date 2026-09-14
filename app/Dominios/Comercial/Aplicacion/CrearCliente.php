<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Dominio\Excepciones\ClienteDuplicado;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Alta de un cliente con sus contactos en una sola operación (HU-22, tarea
 * 33): el formulario es uno solo, así que el cliente y sus contactos nacen
 * en la misma transacción — nunca dos requests separados para una sola
 * acción de usuario.
 *
 * `logo` (HU-75, tarea 91): mismo criterio que `GuardarDatosEmpresa`
 * (ADR 0019) — disco `public`, nombre generado por el servidor, bajo
 * `logos/clientes/` (carpeta propia, distinta de `logos/empresa/`: son
 * dueños de dato distintos). `logo_path` nunca se asigna vía `fill()`.
 *
 * El archivo se sube DESPUÉS del `save()` inicial, no antes: a diferencia
 * de `SecDatosEmpresa` (fila única, sin unicidad que pueda fallar),
 * `com_clientes` sí tiene el índice parcial del NIT — subir el logo antes
 * dejaría un archivo huérfano en disco si el alta termina rechazada por
 * `ClienteDuplicado`.
 */
final class CrearCliente
{
    /**
     * @param  list<array{tipo: string, nombre: string, telefono: string|null, email: string|null, observaciones: string|null}>  $contactos
     *
     * @throws ClienteDuplicado si el NIT ya pertenece a otro cliente activo
     *                          (índice parcial `com_clientes_nit_unico`).
     */
    public function ejecutar(string $razonSocial, ?string $nit, string $tipoPersona, ?string $ubicacionOficina, array $contactos, ?UploadedFile $logo = null): Cliente
    {
        return DB::transaction(function () use ($razonSocial, $nit, $tipoPersona, $ubicacionOficina, $contactos, $logo): Cliente {
            $cliente = new Cliente([
                'razon_social' => $razonSocial,
                'nit' => $nit,
                'tipo_persona' => $tipoPersona,
                'ubicacion_oficina' => $ubicacionOficina,
            ]);

            try {
                $cliente->save();
            } catch (QueryException $excepcion) {
                $this->relanzarComoDuplicado($excepcion, $nit);
            }

            if ($logo !== null) {
                $this->reemplazarLogo($cliente, $logo);
                $cliente->save();
            }

            foreach ($contactos as $contacto) {
                $cliente->contactos()->create($contacto);
            }

            return $cliente->refresh();
        });
    }

    private function reemplazarLogo(Cliente $cliente, UploadedFile $logo): void
    {
        $extension = $logo->extension() ?: 'bin';
        $ruta = sprintf('logos/clientes/logo-%d.%s', now()->timestamp, $extension);

        Storage::disk('public')->put($ruta, (string) file_get_contents($logo->getRealPath()));

        $cliente->logo_path = $ruta;
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
