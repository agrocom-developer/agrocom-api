<?php

namespace App\Dominios\Negociacion\Aplicacion;

use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Seguridad\Contratos\LecturaCuentas;

/**
 * Nadie se contrata a sí mismo (ADR 0046, punto 10). Una cuenta no ofrece sobre una solicitud publicada
 * por ella misma, y tampoco sobre la de OTRA cuenta de la misma persona: ahora que una persona real puede
 * tener una cuenta de cliente y una de proveedor (con el mismo teléfono), comparar solo el id de la cuenta
 * dejaría que «contratara» a su propio número. La identidad es `sec_user.persona_id` (el documento).
 *
 * Aplica al proveedor entero: sus cuentas de aviso son el titular y los pilotos con cuenta propia, y
 * cualquiera de ellas con la misma persona que el cliente cuenta como el mismo.
 */
final class SinAutocontratacion
{
    public function __construct(
        private readonly LecturaProveedores $proveedores,
        private readonly LecturaCuentas $cuentas,
    ) {}

    /**
     * Las cuentas y las personas del lado proveedor.
     *
     * @return array{usuarios: list<int>, personas: list<int>}
     */
    public function delProveedor(int $proveedorId): array
    {
        $usuarios = $this->proveedores->usuariosDeAviso($proveedorId);
        $personas = [];

        foreach ($this->cuentas->porIds($usuarios) as $cuenta) {
            if ($cuenta->personaId !== null) {
                $personas[$cuenta->personaId] = $cuenta->personaId;
            }
        }

        return ['usuarios' => $usuarios, 'personas' => array_values($personas)];
    }

    /**
     * De estas cuentas de cliente, las que son del mismo proveedor o de la misma persona.
     *
     * @param  list<int>  $clienteUsuarioIds
     * @param  array{usuarios: list<int>, personas: list<int>}  $delProveedor
     * @return list<int>
     */
    public function propios(array $clienteUsuarioIds, array $delProveedor): array
    {
        $propios = array_values(array_intersect($clienteUsuarioIds, $delProveedor['usuarios']));

        if ($delProveedor['personas'] === []) {
            return $propios;
        }

        foreach ($this->cuentas->porIds(array_values(array_diff($clienteUsuarioIds, $propios))) as $cuenta) {
            if ($cuenta->personaId !== null && in_array($cuenta->personaId, $delProveedor['personas'], true)) {
                $propios[] = $cuenta->id;
            }
        }

        return $propios;
    }

    public function esPropia(int $clienteUsuarioId, int $proveedorId): bool
    {
        return $this->propios([$clienteUsuarioId], $this->delProveedor($proveedorId)) !== [];
    }
}
