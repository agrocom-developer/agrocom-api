<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaTexto;
use App\Dominios\Seguridad\Contratos\TipoCuenta;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado de cuentas del panel, con búsqueda opcional por nombre
 * o username, por estado de acceso y por rol (HU-45, tarea 39). Solo lectura con paginación.
 *
 * Solo cuentas de tipo `panel` (ADR 0046): el personal de Agrocom. Las cuentas
 * de app (cliente y proveedor) tienen su ficha en Clientes y Proveedores y no se
 * administran desde Usuarios.
 */
final class ListarUsuarios
{
    /** @return LengthAwarePaginator<int, SecUser> */
    public function ejecutar(?string $busqueda = null, int $porPagina = 15, ?string $estado = null, ?int $rolId = null): LengthAwarePaginator
    {
        return SecUser::query()
            ->where('tipo_cuenta', TipoCuenta::Panel->value)
            ->when(in_array($estado, ['activo', 'bloqueado'], true), fn ($consulta) => $consulta->where('state', $estado === 'activo'))
            ->when($rolId !== null, fn ($consulta) => $consulta->whereExists(
                fn ($sub) => $sub->selectRaw('1')
                    ->from('sec_user_role as ur')
                    ->whereColumn('ur.id_user', 'sec_user.id')
                    ->where('ur.id_role', $rolId)
                    ->whereNull('ur.deleted_at'),
            ))
            ->when(
                $busqueda !== null && $busqueda !== '',
                fn ($consulta) => BusquedaTexto::aplicar($consulta, ['name', 'username'], $busqueda),
            )
            ->orderBy('name')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
