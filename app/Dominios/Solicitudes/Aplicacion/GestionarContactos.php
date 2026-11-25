<?php

namespace App\Dominios\Solicitudes\Aplicacion;

use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;
use App\Dominios\Solicitudes\Dominio\TipoCliente;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Cliente;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\ClienteContacto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Contactos de referencia de un cliente: las personas con quienes el proveedor
 * coordina el servicio. Todo parte del cliente recibido (invariante 5): un id
 * ajeno responde como si no existiera. Reglas:
 *
 * - Una empresa tiene siempre al menos un contacto vigente.
 * - Hay un solo contacto principal vigente; el primero que se crea lo es, y si
 *   se da de baja al principal lo releva el más antiguo que quede.
 * - Dar de baja es lógico: las solicitudes ya creadas conservan a la persona
 *   que se eligió.
 *
 * Lo usan la API del cliente, el registro (público y del panel) y la creación
 * de solicitudes; el panel no replica ninguna de estas reglas.
 */
final class GestionarContactos
{
    /** @return Collection<int, ClienteContacto> Vigentes, el principal primero. */
    public function listar(Cliente $cliente): Collection
    {
        return ClienteContacto::query()
            ->where('cliente_id', $cliente->id)
            ->orderByDesc('es_principal')
            ->orderBy('id')
            ->get();
    }

    /** @throws SolicitudInvalida `contactoAjeno` (404) si no es un contacto vigente del cliente. */
    public function obtener(Cliente $cliente, int $contactoId): ClienteContacto
    {
        $contacto = ClienteContacto::query()->where('cliente_id', $cliente->id)->find($contactoId);

        if ($contacto === null) {
            throw SolicitudInvalida::contactoAjeno();
        }

        return $contacto;
    }

    /** @param  array{nombre: string, telefono: string, cargo?: ?string, es_principal?: ?bool}  $datos */
    public function agregar(Cliente $cliente, array $datos): ClienteContacto
    {
        return DB::transaction(function () use ($cliente, $datos): ClienteContacto {
            $this->bloquear($cliente);

            $hayPrincipal = $this->principalDe($cliente) !== null;
            $esPrincipal = ! $hayPrincipal || (bool) ($datos['es_principal'] ?? false);

            if ($esPrincipal) {
                $this->quitarPrincipal($cliente);
            }

            return ClienteContacto::query()->create([
                'cliente_id' => $cliente->id,
                'nombre' => trim($datos['nombre']),
                'telefono' => trim($datos['telefono']),
                'cargo' => $this->limpiar($datos['cargo'] ?? null),
                'es_principal' => $esPrincipal,
            ])->refresh();
        });
    }

    /**
     * Corrige los datos de un contacto. `es_principal = false` sobre el principal
     * no hace nada: para cambiar de principal se marca a otro.
     *
     * @param  array{nombre?: string, telefono?: string, cargo?: ?string, es_principal?: ?bool}  $datos
     */
    public function actualizar(Cliente $cliente, int $contactoId, array $datos): ClienteContacto
    {
        return DB::transaction(function () use ($cliente, $contactoId, $datos): ClienteContacto {
            $this->bloquear($cliente);
            $contacto = $this->obtener($cliente, $contactoId);

            if (($datos['es_principal'] ?? false) === true && ! $contacto->es_principal) {
                $this->quitarPrincipal($cliente);
                $contacto->es_principal = true;
            }

            if (array_key_exists('nombre', $datos)) {
                $contacto->nombre = trim($datos['nombre']);
            }
            if (array_key_exists('telefono', $datos)) {
                $contacto->telefono = trim($datos['telefono']);
            }
            if (array_key_exists('cargo', $datos)) {
                $contacto->cargo = $this->limpiar($datos['cargo']);
            }

            $contacto->save();

            return $contacto->refresh();
        });
    }

    /**
     * Baja lógica.
     *
     * @throws SolicitudInvalida `ultimoContactoDeEmpresa` si es el único vigente de una empresa.
     */
    public function darDeBaja(Cliente $cliente, int $contactoId): void
    {
        DB::transaction(function () use ($cliente, $contactoId): void {
            $this->bloquear($cliente);
            $contacto = $this->obtener($cliente, $contactoId);

            $vigentes = ClienteContacto::query()->where('cliente_id', $cliente->id)->count();
            if ($cliente->tipo === TipoCliente::Empresa && $vigentes <= 1) {
                throw SolicitudInvalida::ultimoContactoDeEmpresa();
            }

            $eraPrincipal = $contacto->es_principal;
            // Primero se libera la marca: el índice único solo admite un principal vigente.
            $contacto->es_principal = false;
            $contacto->save();
            $contacto->delete();

            if ($eraPrincipal) {
                ClienteContacto::query()->where('cliente_id', $cliente->id)->orderBy('id')->first()?->update(['es_principal' => true]);
            }
        });
    }

    /**
     * Los contactos de un cliente recién registrado. Una empresa exige al menos
     * uno; si ninguno viene marcado como principal lo es el primero.
     *
     * @param  list<array{nombre: string, telefono: string, cargo?: ?string, es_principal?: ?bool}>  $contactos
     *
     * @throws SolicitudInvalida `contactoRequerido` si es una empresa y no hay ninguno.
     */
    public function registrarIniciales(Cliente $cliente, array $contactos): void
    {
        if ($contactos === []) {
            if ($cliente->tipo === TipoCliente::Empresa) {
                throw SolicitudInvalida::contactoRequerido();
            }

            return;
        }

        $marcado = null;
        foreach ($contactos as $indice => $contacto) {
            if (($contacto['es_principal'] ?? false) === true) {
                $marcado = $indice;
            }
        }
        $principal = $marcado ?? array_key_first($contactos);

        foreach ($contactos as $indice => $contacto) {
            $this->agregar($cliente, [...$contacto, 'es_principal' => $indice === $principal]);
        }
    }

    /**
     * El contacto de una solicitud nueva: el elegido (que debe ser vigente y del
     * cliente) o, si no se eligió, el principal de una empresa. Un cliente
     * persona puede no tener contacto.
     *
     * @throws SolicitudInvalida `contactoNoValido` (422) si el elegido no es del cliente;
     *                           `contactoRequerido` si una empresa no tiene ninguno.
     */
    public function paraSolicitud(Cliente $cliente, ?int $contactoId): ?ClienteContacto
    {
        if ($contactoId !== null) {
            $contacto = ClienteContacto::query()->where('cliente_id', $cliente->id)->find($contactoId);

            return $contacto ?? throw SolicitudInvalida::contactoNoValido();
        }

        if ($cliente->tipo !== TipoCliente::Empresa) {
            return null;
        }

        return $this->principalDe($cliente) ?? throw SolicitudInvalida::contactoRequerido();
    }

    private function principalDe(Cliente $cliente): ?ClienteContacto
    {
        return ClienteContacto::query()->where('cliente_id', $cliente->id)->where('es_principal', true)->first();
    }

    private function quitarPrincipal(Cliente $cliente): void
    {
        ClienteContacto::query()->where('cliente_id', $cliente->id)->where('es_principal', true)->get()
            ->each(fn (ClienteContacto $contacto): bool => $contacto->update(['es_principal' => false]));
    }

    /** Serializa las altas y bajas de un mismo cliente: dos a la vez no dejan dos principales ni una empresa sin contacto. */
    private function bloquear(Cliente $cliente): void
    {
        Cliente::query()->whereKey($cliente->id)->lockForUpdate()->first();
    }

    private function limpiar(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }
}
