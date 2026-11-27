<?php

namespace App\Dominios\Reclamos\Aplicacion;

use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Reclamos\Dominio\Excepciones\ReclamoInvalido;
use App\Dominios\Reclamos\Dominio\ParteDeReclamo;
use App\Dominios\Reclamos\Infraestructura\Eloquent\Reclamo;

/**
 * Quién es la cuenta dentro de un reclamo: el cliente de la solicitud o el
 * proveedor que la prestó (titular o piloto). Todo lo que la API de reclamos
 * devuelve parte de acá (invariante 5 de CLAUDE.md): un reclamo de otro responde
 * 404. Resuelve el proveedor por el contrato de `Proveedores`.
 */
final class ParticipanteDeReclamo
{
    public function __construct(private readonly LecturaProveedores $proveedores) {}

    /**
     * El proveedor de la cuenta para el lado del proveedor; `null` para el cliente.
     *
     * @throws ReclamoInvalido `sinProveedor` (404) si la cuenta del proveedor no pertenece a ninguno.
     */
    public function proveedorDe(int $usuarioId, ParteDeReclamo $parte): ?int
    {
        if ($parte === ParteDeReclamo::Cliente) {
            return null;
        }

        return ($this->proveedores->proveedorDeLaCuenta($usuarioId) ?? throw ReclamoInvalido::sinProveedor())->id;
    }

    /** Si el reclamo es de esta cuenta, del lado indicado. */
    public function esDeLaCuenta(Reclamo $reclamo, int $usuarioId, ParteDeReclamo $parte, ?int $proveedorId): bool
    {
        return $parte === ParteDeReclamo::Cliente
            ? $reclamo->cliente_usuario_id === $usuarioId
            : ($proveedorId !== null && $reclamo->proveedor_id === $proveedorId);
    }

    /**
     * Cuentas a las que se avisa del lado indicado del reclamo.
     *
     * @return list<int>
     */
    public function usuariosDe(Reclamo $reclamo, ParteDeReclamo $parte): array
    {
        if ($parte === ParteDeReclamo::Cliente) {
            return [$reclamo->cliente_usuario_id];
        }

        return $reclamo->proveedor_id === null ? [] : $this->proveedores->usuariosDeAviso($reclamo->proveedor_id);
    }

    /**
     * El reclamo de la cuenta, del lado indicado.
     *
     * @throws ReclamoInvalido `reclamoAjeno` (404) si no es de la cuenta.
     */
    public function reclamo(int $usuarioId, ParteDeReclamo $parte, int $reclamoId): Reclamo
    {
        $proveedorId = $this->proveedorDe($usuarioId, $parte);
        $reclamo = Reclamo::query()->find($reclamoId);

        if ($reclamo === null || ! $this->esDeLaCuenta($reclamo, $usuarioId, $parte, $proveedorId)) {
            throw ReclamoInvalido::reclamoAjeno();
        }

        return $reclamo;
    }
}
