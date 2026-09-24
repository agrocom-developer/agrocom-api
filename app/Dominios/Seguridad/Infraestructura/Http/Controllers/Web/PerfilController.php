<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Personal\Contratos\LecturaDatosPersonales;
use App\Dominios\Seguridad\Aplicacion\ActualizarPerfilPropio;
use App\Dominios\Seguridad\Aplicacion\ItemMenu;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Dominio\Excepciones\ContrasenaActualIncorrecta;
use App\Dominios\Seguridad\Dominio\Excepciones\UsuarioDuplicado;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Http\Presentacion\AccesosRapidos;
use App\Dominios\Seguridad\Infraestructura\Http\Presentacion\CascaraPanel;
use App\Dominios\Seguridad\Infraestructura\Http\Requests\ActualizarPerfilRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * `GET/PUT /panel/perfil` (tarea 66): autoservicio del guard `interno` —
 * cualquier usuario, con cualquier rol activo, sin permiso de grano fino:
 * el sujeto de la operación es siempre el propio usuario autenticado, nunca
 * un `{usuario}` de ruta (a diferencia de `UsuariosController`, que
 * administra cuentas AJENAS). Adaptador delgado (ADR 0008) sobre
 * {@see ActualizarPerfilPropio}.
 */
final class PerfilController
{
    private const PERMISO_EDITAR_PERSONA = 'personal.persona.editar';

    public function edit(
        Request $request,
        CascaraPanel $cascara,
        AutorizacionPanelWeb $autorizacion,
        LecturaDatosPersonales $lecturaPersona,
    ): View {
        $usuario = $request->user('interno');
        $idRolActivo = (int) $request->session()->get('sec_rol_activo_id');
        $cuerpo = $cascara->para($usuario, $idRolActivo);

        return view('seguridad::pages.perfil.index', array_merge($cuerpo, [
            'usuario' => $usuario,
            'accesos' => $this->accesosDelRol($idRolActivo, $cuerpo['menu']),
            'persona' => $this->tarjetaPersona(
                $usuario,
                $lecturaPersona,
                $autorizacion->tienePermiso($request, self::PERMISO_EDITAR_PERSONA),
            ),
        ]));
    }

    public function update(ActualizarPerfilRequest $request, ActualizarPerfilPropio $actualizar): RedirectResponse
    {
        /** @var SecUser $usuario */
        $usuario = $request->user('interno');
        $datos = $request->validated();

        try {
            $actualizar->ejecutar(
                usuario: $usuario,
                guard: 'interno',
                idSesionActual: $request->session()->getId(),
                // Siempre `null`: el nombre no es autoservicio en el panel
                // (ver ActualizarPerfilRequest). El portal sí lo manda.
                name: null,
                email: $this->cadenaONull($datos['email'] ?? null),
                passwordActual: $this->cadenaONull($datos['password_actual'] ?? null),
                passwordNueva: $this->cadenaONull($datos['password'] ?? null),
            );
        } catch (ContrasenaActualIncorrecta $excepcion) {
            throw ValidationException::withMessages(['password_actual' => [$excepcion->getMessage()]]);
        } catch (UsuarioDuplicado $excepcion) {
            return redirect()
                ->route('panel.perfil.edit')
                ->withErrors(['email' => $excepcion->getMessage()])
                ->withInput($request->except(['password', 'password_confirmation', 'password_actual']));
        }

        return redirect()
            ->route('panel.perfil.edit')
            ->with('estado', __('seguridad.perfil.actualizado'));
    }

    /**
     * Los accesos rápidos del rol activo, ya con el texto traducido y el destino resuelto.
     * Salen del menú de ese rol (ver {@see AccesosRapidos}): no hay otra lista de permisos.
     *
     * @param  list<ItemMenu>  $menu
     * @return list<array{href: string, icono: string, titulo: string, meta: string}>
     */
    private function accesosDelRol(int $idRolActivo, array $menu): array
    {
        $rolClave = SecRole::query()->whereKey($idRolActivo)->value('name');

        return array_map(static fn (array $acceso): array => [
            'href' => route($acceso['ruta']),
            'icono' => $acceso['icono'],
            'titulo' => __($acceso['etiqueta']),
            'meta' => __($acceso['modulo']),
        ], AccesosRapidos::para(is_string($rolClave) ? $rolClave : null, $menu));
    }

    /**
     * Los datos de la persona vinculada a la cuenta, en solo lectura. La persona sale SIEMPRE de
     * `persona_id` de la cuenta autenticada, nunca de la petición: nadie ve los datos de otra.
     * Sin persona vinculada, o con una dada de baja, la tarjeta dice cuál de las dos es.
     *
     * @return array{titulo: string, tieneDatos: bool, items: list<array<string, mixed>>, nota: string, editarHref: ?string, vacioTitulo: string, vacioDetalle: string}
     */
    private function tarjetaPersona(SecUser $usuario, LecturaDatosPersonales $lecturaPersona, bool $puedeEditar): array
    {
        $datos = $usuario->persona_id !== null ? $lecturaPersona->dePersona($usuario->persona_id) : null;
        $sinDato = __('seguridad.perfil.persona_sin_dato');

        return [
            'titulo' => __('seguridad.perfil.persona_titulo'),
            'tieneDatos' => $datos !== null,
            'items' => $datos === null ? [] : [
                ['label' => __('seguridad.perfil.persona_nombre'), 'value' => $datos->nombre],
                ['label' => __('seguridad.perfil.persona_rol'), 'value' => __('personal.roles.'.$datos->rol)],
                ['label' => __('seguridad.perfil.persona_base'), 'value' => $datos->baseNombre ?? __('seguridad.perfil.persona_sin_base')],
                ['label' => __('seguridad.perfil.persona_ci'), 'value' => $datos->ci ?? $sinDato, 'mono' => $datos->ci !== null],
                ['label' => __('seguridad.perfil.persona_celular'), 'value' => $datos->celular ?? $sinDato, 'mono' => $datos->celular !== null],
                ['label' => __('seguridad.perfil.persona_correo'), 'value' => $datos->correo ?? $sinDato],
                ['label' => __('seguridad.perfil.persona_direccion'), 'value' => $datos->direccion ?? $sinDato],
            ],
            'nota' => $puedeEditar ? __('seguridad.perfil.persona_nota_editable') : __('seguridad.perfil.persona_nota'),
            'editarHref' => $datos !== null && $puedeEditar ? route('panel.personas.edit', $datos->id) : null,
            'vacioTitulo' => $usuario->persona_id === null
                ? __('seguridad.perfil.persona_vacio_titulo')
                : __('seguridad.perfil.persona_baja_titulo'),
            'vacioDetalle' => $usuario->persona_id === null
                ? __('seguridad.perfil.persona_vacio_detalle')
                : __('seguridad.perfil.persona_baja_detalle'),
        ];
    }

    private function cadenaONull(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }
}
