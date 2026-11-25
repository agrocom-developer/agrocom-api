<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Presentacion;

use App\Dominios\Personal\Contratos\LecturaFichaPersona;
use App\Dominios\Seguridad\Aplicacion\ConsultarRolesDeUsuarios;
use App\Dominios\Seguridad\Aplicacion\ListarBitacora;
use App\Dominios\Seguridad\Aplicacion\ListarDispositivosDeUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;

/**
 * Tarjetas del aside de la ficha de usuario (roles, persona, dispositivos y
 * bitácora de la cuenta): arma el arreglo que pintan las vistas. Lo de otro
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

    /** @return array{titulo: string, icono: string, tieneDatos: bool, items: list<array<string, mixed>>, vacioTitulo: string, vacioDetalle: string, acciones: list<array{label: string, href: string, icono?: string}>} */
    public function tarjetaPersona(SecUser $usuario, LecturaFichaPersona $lecturaPersona): array
    {
        $ficha = $usuario->persona_id !== null ? $lecturaPersona->dePersona($usuario->persona_id) : null;

        return [
            'titulo' => __('seguridad.usuarios.aside_persona_titulo'),
            'icono' => 'badge',
            'tieneDatos' => $ficha !== null,
            'items' => $ficha === null ? [] : [
                ['label' => __('seguridad.usuarios.aside_persona_nombre'), 'value' => $ficha->nombre],
                ['label' => __('seguridad.usuarios.aside_persona_rol'), 'value' => __('personal.roles.'.$ficha->rol)],
                ['label' => __('seguridad.usuarios.aside_persona_base'), 'value' => $ficha->baseNombre ?? __('seguridad.usuarios.aside_persona_sin_base')],
            ],
            // Sin persona_id, o con una persona dada de baja, la tarjeta dice cuál de las dos es.
            'vacioTitulo' => $usuario->persona_id === null
                ? __('seguridad.usuarios.aside_persona_vacio_titulo')
                : __('seguridad.usuarios.aside_persona_baja_titulo'),
            'vacioDetalle' => $usuario->persona_id === null
                ? __('seguridad.usuarios.aside_persona_vacio_detalle')
                : __('seguridad.usuarios.aside_persona_baja_detalle'),
            'acciones' => [],
        ];
    }

    /** @return array{titulo: string, icono: string, tieneDatos: bool, items: list<array<string, mixed>>, vacioTitulo: string, vacioDetalle: string, acciones: list<array{label: string, href: string, icono?: string}>} */
    public function tarjetaDispositivos(SecUser $usuario, ListarDispositivosDeUsuario $listarDispositivos): array
    {
        $dispositivos = $listarDispositivos->ejecutar($usuario);
        $ultimoUso = $dispositivos->max('last_used_at');

        return [
            'titulo' => __('seguridad.usuarios.aside_dispositivos_titulo'),
            'icono' => 'smartphone',
            'tieneDatos' => $dispositivos->isNotEmpty(),
            'items' => [
                ['label' => __('seguridad.usuarios.aside_dispositivos_total'), 'value' => (string) $dispositivos->count(), 'mono' => true],
                ['label' => __('seguridad.usuarios.aside_dispositivos_ultimo_uso'), 'value' => $ultimoUso !== null ? $ultimoUso->diffForHumans() : __('seguridad.dispositivos.sin_uso')],
            ],
            'vacioTitulo' => __('seguridad.usuarios.aside_dispositivos_vacio_titulo'),
            'vacioDetalle' => __('seguridad.usuarios.aside_dispositivos_vacio_detalle'),
            // La lista de dispositivos se acota a esta cuenta con su usuario en el buscador.
            'acciones' => $dispositivos->isNotEmpty()
                ? [['label' => __('seguridad.usuarios.aside_dispositivos_accion'), 'href' => route('panel.dispositivos.index', ['q' => $usuario->username]), 'icono' => 'list']]
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
