<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Comercial\Contratos\LecturaContrato;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;

/**
 * Caso de uso de lectura: candidatos para el selector de «Ver como» del
 * dashboard — mismo criterio de elegibilidad que ya aplica el modal por fila
 * de `usuarios/index.blade.php` (cuenta habilitada, ajena, con algo bajo lo
 * cual mirarla), pero agrupados por rol para un selector con buscador en vez
 * de una fila de tabla por cuenta.
 *
 * Solo arma la lista — no valida el permiso `seguridad.usuario.ver_como` (lo
 * hace el controlador, como en el resto del panel) ni abre la vista (eso
 * sigue siendo `IniciarVistaComo`, sin tocar).
 */
final class ListarCandidatosVerComo
{
    public function __construct(
        private readonly LecturaContrato $lecturaContrato,
    ) {}

    /**
     * @return array{
     *     grupos: list<array{clave: string, nombre: string, idRol: int, usuarioIds: list<int>}>,
     *     portalIds: list<int>,
     *     usuarios: array<int, array{nombre: string, username: string, esPortal: bool, roles: array<int, string>, detalle: string|null}>,
     * }
     */
    public function ejecutar(SecUser $admin): array
    {
        $internos = SecUser::query()
            ->where('type', TipoUsuario::Interno)
            ->where('state', true)
            ->where('id', '!=', $admin->id)
            ->orderBy('name')
            ->get(['id', 'name', 'username']);

        $portal = SecUser::query()
            ->where('type', TipoUsuario::Cliente)
            ->where('state', true)
            ->whereNotNull('contrato_id')
            ->where('id', '!=', $admin->id)
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'contrato_id']);

        $rolesPorUsuario = $this->rolesVivosPorUsuario($internos->pluck('id')->all());

        $usuarios = [];

        foreach ($internos as $usuario) {
            $roles = $rolesPorUsuario[$usuario->id] ?? [];

            // Sin rol vivo no hay nada bajo lo cual verla (mismo criterio
            // que IniciarVistaComo::rolAObservar()) — no entra al selector.
            if ($roles === []) {
                continue;
            }

            $usuarios[$usuario->id] = [
                'nombre' => $usuario->name,
                'username' => $usuario->username,
                'esPortal' => false,
                'roles' => $roles,
                'detalle' => null,
            ];
        }

        $grupos = $this->gruposDeRoles($rolesPorUsuario);

        foreach ($portal as $cuenta) {
            $usuarios[$cuenta->id] = [
                'nombre' => $cuenta->name,
                'username' => $cuenta->username,
                'esPortal' => true,
                'roles' => [],
                'detalle' => $cuenta->contrato_id !== null
                    ? $this->lecturaContrato->obtenerResumen($cuenta->contrato_id)?->clienteNombre
                    : null,
            ];
        }

        return [
            'grupos' => $grupos,
            'portalIds' => $portal->pluck('id')->all(),
            'usuarios' => $usuarios,
        ];
    }

    /**
     * Roles VIVOS y habilitados de cada cuenta, con su id — mismo criterio y
     * misma consulta que `UsuariosController::rolesVivosPorUsuario()` (no se
     * extrae a un único lugar porque una devuelve `id => nombre` por fila de
     * tabla y esta además necesita el slug para agrupar por rol; el costo de
     * las 15 líneas repetidas es menor que el de una firma genérica que
     * sirva a ambos casos).
     *
     * @param  list<int>  $idsUsuario
     * @return array<int, array<int, string>> `id de usuario => [id de rol => nombre legible]`
     */
    private function rolesVivosPorUsuario(array $idsUsuario): array
    {
        if ($idsUsuario === []) {
            return [];
        }

        return DB::table('sec_user_role as ur')
            ->join('sec_role as r', 'r.id', '=', 'ur.id_role')
            ->whereIn('ur.id_user', $idsUsuario)
            ->whereNull('ur.deleted_at')
            ->whereNull('r.deleted_at')
            ->where('r.state', true)
            ->orderBy('r.id')
            ->get(['ur.id_user', 'r.id', 'r.name'])
            ->groupBy(fn (object $fila): int => (int) $fila->id_user)
            ->map(fn (Collection $filas) => $filas
                ->mapWithKeys(fn (object $fila): array => [(int) $fila->id => $this->nombreLegibleRol((string) $fila->name)])
                ->all())
            ->all();
    }

    /**
     * Un grupo por rol, en el orden del catálogo (`sec_role.id`), cada uno
     * con los ids de las cuentas que lo tienen vivo — una cuenta con varios
     * roles aparece en más de un grupo a propósito (mismo criterio que
     * "Usuarios activos por rol" del propio dashboard: una cuenta con varios
     * roles cuenta en cada uno).
     *
     * @param  array<int, array<int, string>>  $rolesPorUsuario  `id de usuario => [id de rol => nombre legible]`
     * @return list<array{clave: string, nombre: string, idRol: int, usuarioIds: list<int>}>
     */
    private function gruposDeRoles(array $rolesPorUsuario): array
    {
        if ($rolesPorUsuario === []) {
            return [];
        }

        $idsUsuario = array_keys($rolesPorUsuario);

        $catalogo = DB::table('sec_role')
            ->whereIn('id', collect($rolesPorUsuario)->flatMap(fn (array $roles) => array_keys($roles))->unique()->all())
            ->orderBy('id')
            ->get(['id', 'name']);

        $grupos = [];

        foreach ($catalogo as $rol) {
            $usuarioIds = [];

            foreach ($idsUsuario as $idUsuario) {
                if (array_key_exists((int) $rol->id, $rolesPorUsuario[$idUsuario])) {
                    $usuarioIds[] = $idUsuario;
                }
            }

            if ($usuarioIds === []) {
                continue;
            }

            $grupos[] = [
                'clave' => $rol->name,
                'nombre' => $this->nombreLegibleRol($rol->name),
                'idRol' => (int) $rol->id,
                'usuarioIds' => $usuarioIds,
            ];
        }

        return $grupos;
    }

    private function nombreLegibleRol(string $slug): string
    {
        $clave = "seguridad.rol.meta.{$slug}.nombre";

        return Lang::has($clave) ? __($clave) : $slug;
    }
}
