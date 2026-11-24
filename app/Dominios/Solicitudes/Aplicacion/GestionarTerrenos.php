<?php

namespace App\Dominios\Solicitudes\Aplicacion;

use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Terreno;
use Illuminate\Support\Collection;

/**
 * Los terrenos del cliente de la cuenta (`GET/POST/PUT /terrenos`,
 * especificación §10.1): se registran una vez y se reutilizan en cada
 * solicitud. Todo parte de `cliente_id` (invariante 5): un id ajeno responde
 * como si no existiera.
 */
final class GestionarTerrenos
{
    public function __construct(private readonly ClienteDeLaCuenta $clientes) {}

    /** @return Collection<int, Terreno> */
    public function listar(int $usuarioId): Collection
    {
        $cliente = $this->clientes->resolver($usuarioId);

        return Terreno::query()->where('cliente_id', $cliente->id)->orderBy('nombre')->get();
    }

    /** @throws SolicitudInvalida `terrenoAjeno` (404) si no es del cliente. */
    public function obtener(int $usuarioId, int $terrenoId): Terreno
    {
        $cliente = $this->clientes->resolver($usuarioId);

        $terreno = Terreno::query()->where('cliente_id', $cliente->id)->find($terrenoId);

        if ($terreno === null) {
            throw SolicitudInvalida::terrenoAjeno();
        }

        return $terreno;
    }

    /** @param  array<string, mixed>  $datos */
    public function crear(int $usuarioId, array $datos): Terreno
    {
        $cliente = $this->clientes->activo($usuarioId);

        return Terreno::query()->create([...$datos, 'cliente_id' => $cliente->id])->refresh();
    }

    /** @param  array<string, mixed>  $datos */
    public function actualizar(int $usuarioId, int $terrenoId, array $datos): Terreno
    {
        $this->clientes->activo($usuarioId);
        $terreno = $this->obtener($usuarioId, $terrenoId);

        $terreno->fill($datos)->save();

        return $terreno->refresh();
    }
}
