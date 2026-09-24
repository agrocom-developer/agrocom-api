<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Dominio\Excepciones\ClienteDuplicado;
use App\Dominios\Comercial\Dominio\TipoPersonaCliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\ClienteContacto;
use App\Dominios\Compartido\Aplicacion\OptimizarImagenSubida;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
 *
 * `logo`/`eliminarLogo` (HU-75, tarea 91; ruta actualizada por ADR 0026):
 * mismo criterio que `GuardarDatosEmpresa` — `eliminarLogo` solo actúa si no vino `$logo`
 * nuevo (subir y tildar "eliminar" a la vez no tiene sentido; el nuevo
 * archivo gana), y el archivo viejo se borra del disco al reemplazar o
 * eliminar. El archivo se toca DESPUÉS del `save()` de los campos de texto,
 * no antes: a diferencia de `SecDatosEmpresa`, `com_clientes` sí tiene el
 * índice parcial del NIT — tocar el archivo antes dejaría el disco
 * desincronizado (archivo borrado o huérfano) si el `save()` termina
 * rechazado por `ClienteDuplicado`.
 *
 * El contenido pasa por {@see OptimizarImagenSubida} antes de guardarse
 * (15/9/2026): el Form Request solo valida tipo y un tope técnico de subida,
 * el peso final liviano lo garantiza esta conversión, no un rechazo al
 * usuario.
 */
final class ActualizarCliente
{
    public function __construct(private readonly OptimizarImagenSubida $optimizarImagen) {}

    /**
     * @param  list<array{id: int|null, tipo: string, tipo_otro: string|null, nombre: string, telefono: string|null, email: string|null, observaciones: string|null}>  $contactos
     *
     * @throws ClienteDuplicado si el NIT ya pertenece a otro cliente activo
     *                          (índice parcial `com_clientes_nit_unico`).
     */
    public function ejecutar(Cliente $cliente, string $razonSocial, ?string $nombreComercial, ?string $nit, string $tipoPersona, ?string $ubicacionOficina, array $contactos, ?UploadedFile $logo = null, bool $eliminarLogo = false): Cliente
    {
        return DB::transaction(function () use ($cliente, $razonSocial, $nombreComercial, $nit, $tipoPersona, $ubicacionOficina, $contactos, $logo, $eliminarLogo): Cliente {
            $cliente->razon_social = $razonSocial;
            $cliente->nombre_comercial = $nombreComercial;
            $cliente->nit = $nit;
            $cliente->tipo_persona = TipoPersonaCliente::from($tipoPersona);
            $cliente->ubicacion_oficina = $ubicacionOficina;

            try {
                $cliente->save();
            } catch (QueryException $excepcion) {
                $this->relanzarComoDuplicado($excepcion, $nit);
            }

            if ($logo !== null) {
                $this->reemplazarLogo($cliente, $logo);
                $cliente->save();
            } elseif ($eliminarLogo && $cliente->logo_path !== null) {
                $this->borrarLogo($cliente);
                $cliente->save();
            }

            $this->sincronizarContactos($cliente, $contactos);

            return $cliente->refresh();
        });
    }

    private function reemplazarLogo(Cliente $cliente, UploadedFile $logo): void
    {
        if ($cliente->logo_path !== null) {
            Storage::disk('public')->delete($cliente->logo_path);
        }

        ['contenido' => $contenido, 'extension' => $extension] = $this->optimizarImagen->ejecutar($logo);
        $ruta = sprintf('logos/clientes/%d/logo-%d.%s', $cliente->id, now()->timestamp, $extension);

        Storage::disk('public')->put($ruta, $contenido);

        $cliente->logo_path = $ruta;
    }

    private function borrarLogo(Cliente $cliente): void
    {
        Storage::disk('public')->delete((string) $cliente->logo_path);

        $cliente->logo_path = null;
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
