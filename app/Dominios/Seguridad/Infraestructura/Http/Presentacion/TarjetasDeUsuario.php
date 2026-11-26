<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Presentacion;

use App\Dominios\Seguridad\Aplicacion\ConsultarRolesDeUsuarios;
use App\Dominios\Seguridad\Aplicacion\ListarBitacora;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;

/**
 * Tarjetas del aside de la ficha de usuario (roles y bitácora de la
 * cuenta): arma el arreglo que pintan las vistas. Lo de otro
 * módulo llega por su `Contratos/`; los permisos de cada tarjeta los decide
 * el controlador.
 */
final class TarjetasDeUsuario
{
    public function __construct(private readonly ConsultarRolesDeUsuarios $roles) {}

    /**
     * Nombres legibles de los roles de cada usuario, para la columna «Roles».
     *
     * @param  list<int>  $idsUsuario
     * @return array<int, list<string>>
     */
    public function rolesPorUsuario(array $idsUsuario): array
    {
        return array_map(
            fn (array $slugs): array => array_map(NombreLegibleRol::de(...), $slugs),
            $this->roles->slugsPorUsuario($idsUsuario),
        );
    }

    /** @return array{titulo: string, icono: string, tieneDatos: bool, items: list<array<string, mixed>>, vacioTitulo: string, vacioDetalle: string, acciones: list<array{label: string, href: string, icono?: string}>} */
    public function tarjetaRoles(SecUser $usuario): array
    {
        $roles = $this->rolesPorUsuario([(int) $usuario->id])[(int) $usuario->id] ?? [];

        return [
            'titulo' => __('seguridad.usuarios.aside_roles_titulo'),
            'icono' => 'shield_person',
            'tieneDatos' => $roles !== [],
            'items' => [
                ['label' => __('seguridad.usuarios.aside_roles_total'), 'value' => (string) count($roles), 'mono' => true],
                ['label' => __('seguridad.usuarios.aside_roles_nombres'), 'value' => implode(', ', $roles)],
            ],
            'vacioTitulo' => __('seguridad.usuarios.aside_roles_vacio_titulo'),
            'vacioDetalle' => __('seguridad.usuarios.aside_roles_vacio_detalle'),
            'acciones' => $roles !== []
                ? [['label' => __('seguridad.usuarios.aside_roles_accion'), 'href' => route('panel.roles.index'), 'icono' => 'list']]
                : [],
        ];
    }

    /**
     * Últimos movimientos de la propia cuenta en la bitácora: lee con
     * {@see ListarBitacora} —el mismo caso de uso de `/panel/bitacora`, acotado
     * a `sec_user` y a esta cuenta— sin tocar la bitácora.
     *
     * @return array{titulo: string, icono: string, tieneDatos: bool, items: list<array<string, mixed>>, vacioTitulo: string, vacioDetalle: string, acciones: list<array{label: string, href: string, icono?: string}>}
     */
    public function tarjetaBitacora(SecUser $usuario, string $zonaQueVe, ListarBitacora $listarBitacora): array
    {
        $filas = $listarBitacora->ejecutar(
            zonaQueVe: $zonaQueVe,
            tabla: 'sec_user',
            registroId: (int) $usuario->id,
            porPagina: 3,
        )->items();

        return [
            'titulo' => __('seguridad.usuarios.aside_bitacora_titulo'),
            'icono' => 'history',
            'tieneDatos' => $filas !== [],
            // Acción y fecha: una línea por movimiento. Quién lo hizo y el detalle del cambio están en la bitácora.
            'items' => array_map(fn ($fila): array => [
                'label' => __('seguridad.bitacora.acciones.'.$fila->accion->value),
                'value' => $fila->instante->format('d/m/Y H:i'),
                'mono' => true,
            ], $filas),
            'vacioTitulo' => __('seguridad.usuarios.aside_bitacora_vacio_titulo'),
            'vacioDetalle' => __('seguridad.usuarios.aside_bitacora_vacio_detalle'),
            'acciones' => $filas !== []
                ? [['label' => __('seguridad.usuarios.aside_bitacora_accion'), 'href' => route('panel.bitacora.index', ['tabla' => 'sec_user', 'registro_id' => $usuario->id]), 'icono' => 'history']]
                : [],
        ];
    }
}
