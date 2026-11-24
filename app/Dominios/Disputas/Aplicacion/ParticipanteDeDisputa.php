<?php

namespace App\Dominios\Disputas\Aplicacion;

use App\Dominios\Disputas\Dominio\Excepciones\DisputaInvalida;
use App\Dominios\Disputas\Dominio\ParteDeDisputa;
use App\Dominios\Disputas\Infraestructura\Eloquent\Disputa;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;

/**
 * Quién es la cuenta dentro de una disputa: el cliente de la solicitud o el
 * proveedor que la prestó (titular o piloto). Todo lo que la API de disputas
 * devuelve parte de acá (invariante 5 de CLAUDE.md): una disputa de otro responde
 * 404. Resuelve el proveedor por el contrato de `Proveedores`.
 */
final class ParticipanteDeDisputa
{
    public function __construct(private readonly LecturaProveedores $proveedores) {}

    /**
     * El proveedor de la cuenta para el lado del proveedor; `null` para el cliente.
     *
     * @throws DisputaInvalida `sinProveedor` (404) si la cuenta del proveedor no pertenece a ninguno.
     */
    public function proveedorDe(int $usuarioId, ParteDeDisputa $parte): ?int
    {
        if ($parte === ParteDeDisputa::Cliente) {
            return null;
        }

        return ($this->proveedores->proveedorDeLaCuenta($usuarioId) ?? throw DisputaInvalida::sinProveedor())->id;
    }

    /** Si la disputa es de esta cuenta, del lado indicado. */
    public function esDeLaCuenta(Disputa $disputa, int $usuarioId, ParteDeDisputa $parte, ?int $proveedorId): bool
    {
        return $parte === ParteDeDisputa::Cliente
            ? $disputa->cliente_usuario_id === $usuarioId
            : ($proveedorId !== null && $disputa->proveedor_id === $proveedorId);
    }

    /**
     * Cuentas a las que se avisa del lado indicado de la disputa.
     *
     * @return list<int>
     */
    public function usuariosDe(Disputa $disputa, ParteDeDisputa $parte): array
    {
        if ($parte === ParteDeDisputa::Cliente) {
            return [$disputa->cliente_usuario_id];
        }

        return $disputa->proveedor_id === null ? [] : $this->proveedores->usuariosDeAviso($disputa->proveedor_id);
    }

    /**
     * La disputa de la cuenta, del lado indicado.
     *
     * @throws DisputaInvalida `disputaAjena` (404) si no es de la cuenta.
     */
    public function disputa(int $usuarioId, ParteDeDisputa $parte, int $disputaId): Disputa
    {
        $proveedorId = $this->proveedorDe($usuarioId, $parte);
        $disputa = Disputa::query()->find($disputaId);

        if ($disputa === null || ! $this->esDeLaCuenta($disputa, $usuarioId, $parte, $proveedorId)) {
            throw DisputaInvalida::disputaAjena();
        }

        return $disputa;
    }
}
