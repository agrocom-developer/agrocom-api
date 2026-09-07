<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Dominio\Excepciones\PermisoDenegado;
use App\Dominios\Seguridad\Dominio\Excepciones\RolProtegido;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecPermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRolePermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Support\Facades\DB;

/**
 * Otorga y quita permisos a un rol. Es el caso de uso más peligroso del
 * sistema: quien lo ejecuta puede reescribir quién puede hacer qué, y —si no
 * se lo impide— dejar el panel en un estado del que no se vuelve sin acceso
 * al servidor. Hasta ahora esto solo se podía tocar por seeder, donde un
 * error se corrige editando el archivo y volviendo a sembrar; desde el panel
 * no hay tal salida.
 *
 * Recibe el set COMPLETO Y DEFINITIVO de permisos deseados, no un delta —
 * mismo criterio que {@see AsignarRolesUsuario}: los que falten respecto de
 * los actuales se revocan (soft delete) y los nuevos se otorgan, así "editar"
 * nunca deja un permiso huérfano por omisión.
 *
 * Cuatro guardas, en este orden:
 *
 * 1. PERMISO DE ENTRADA — `seguridad.rol.asignar_permiso` en el rol activo.
 * 2. ANTI-ESCALADA — el actor no puede otorgar NI quitar un permiso que él
 *    mismo no tiene en su rol activo. Sin esto, cualquiera que llegue a esta
 *    pantalla se concede el sistema entero en un submit; y aplicarla también
 *    al quite (no solo al otorgamiento) es lo que evita que un rol acotado
 *    sabotee permisos ajenos que no le incumben. Es el mismo criterio de
 *    delta completo que ya usa la guarda de `asignar_rol_dueno`.
 * 3. NO QUITARTE LA LLAVE A VOS MISMO — sobre tu propio rol activo no podés
 *    soltar `seguridad.rol.ver` ni `.asignar_permiso`. `ResolverRolActivo`
 *    revalida en CADA request, así que el efecto sería inmediato: el
 *    siguiente clic ya es un 403 sobre la pantalla que estás usando.
 * 4. NINGÚN PERMISO HUÉRFANO — no se puede quitar el último otorgamiento vivo
 *    de un permiso. Es consecuencia directa de la guarda 2: si un permiso se
 *    queda sin ningún rol, nadie lo tiene, y como nadie puede conceder lo que
 *    no tiene, no hay forma de devolvérselo a nadie desde el panel. El
 *    permiso queda muerto aunque el código lo siga exigiendo, y la pantalla
 *    que lo pide se vuelve inalcanzable para siempre.
 *
 * La 4 cubre a la 3 en el caso de un único rol con la llave, pero no cuando
 * hay dos: ahí quitártela a vos mismo dejaría el sistema administrable (por
 * el otro rol) y aun así te dejaría afuera a vos, sin aviso, en el próximo
 * clic. Por eso las dos existen y ninguna sobra.
 */
final class AsignarPermisosRol
{
    private const PERMISO_ASIGNAR = 'seguridad.rol.asignar_permiso';

    private const PERMISO_VER = 'seguridad.rol.ver';

    /** Los que, sobre el propio rol activo, no se pueden soltar (guarda 3). */
    private const IRRENUNCIABLES_SOBRE_EL_ROL_PROPIO = [
        self::PERMISO_VER,
        self::PERMISO_ASIGNAR,
    ];

    public function __construct(private readonly CatalogoDePermisos $catalogo) {}

    /**
     * @param  SecUser  $actor  Quién ejecuta: define el permiso exigido para
     *                          entrar, el techo de lo que puede conceder, y la
     *                          autoría de las filas que se crean/revocan.
     * @param  list<int>  $idsPermisoDeseados  Set completo y definitivo.
     * @param  int|null  $idRolActivo  Rol activo de la sesión del actor. Sin
     *                                 default: quien invoca desde el panel
     *                                 SIEMPRE lo pasa (evaluación por
     *                                 `tienePermisoEnRol()`, nunca la unión —
     *                                 invariante 10 de CLAUDE.md); `null` es
     *                                 la elección explícita de un llamador
     *                                 legítimamente sin sesión (seeders,
     *                                 comandos, tests directos).
     *
     * @throws PermisoDenegado guardas 1 y 2.
     * @throws RolProtegido guardas 3 y 4.
     */
    public function ejecutar(SecUser $actor, SecRole $rol, array $idsPermisoDeseados, ?int $idRolActivo): void
    {
        // (int) explícito: normaliza el payload sin importar si los IDs
        // llegaron de un form HTML (strings), de Postgres (numeric string) o
        // de SQLite (int nativo) — las comparaciones estrictas de abajo no
        // pueden depender de eso.
        /** @var list<int> $deseados */
        $deseados = array_values(array_unique(array_map('intval', $idsPermisoDeseados)));

        if (! $this->actorTienePermiso($actor, $idRolActivo, self::PERMISO_ASIGNAR)) {
            throw PermisoDenegado::porFaltaDePermiso(self::PERMISO_ASIGNAR);
        }

        $actuales = $this->catalogo->idsPermisoDeRol($rol->id);

        $aOtorgar = array_values(array_diff($deseados, $actuales));
        $aQuitar = array_values(array_diff($actuales, $deseados));

        if ($aOtorgar === [] && $aQuitar === []) {
            return;
        }

        $this->verificarAntiEscalada($actor, $idRolActivo, array_merge($aOtorgar, $aQuitar));
        $this->verificarQueNoSeSueltaLaLlavePropia($rol, $idRolActivo, $aQuitar);
        $this->verificarQueNingunPermisoQuedaHuerfano($rol, $aQuitar);

        DB::transaction(function () use ($rol, $aOtorgar, $aQuitar, $actor): void {
            foreach ($aOtorgar as $idPermiso) {
                $this->otorgar($rol->id, $idPermiso, $actor->id);
            }

            $this->quitar($rol->id, $aQuitar, $actor->id);
        });
    }

    /**
     * Guarda 2: nadie concede —ni retira— lo que no tiene.
     *
     * @param  list<int>  $idsAfectados
     *
     * @throws PermisoDenegado
     */
    private function verificarAntiEscalada(SecUser $actor, ?int $idRolActivo, array $idsAfectados): void
    {
        $codigos = SecPermission::query()
            ->whereIn('id', $idsAfectados)
            ->pluck('code')
            ->all();

        foreach ($codigos as $codigo) {
            if (! $this->actorTienePermiso($actor, $idRolActivo, (string) $codigo)) {
                throw PermisoDenegado::porFaltaDePermiso((string) $codigo);
            }
        }
    }

    /**
     * Guarda 3: sobre el rol con el que estás operando, `ver` y
     * `asignar_permiso` no se sueltan.
     *
     * @param  list<int>  $aQuitar
     *
     * @throws RolProtegido
     */
    private function verificarQueNoSeSueltaLaLlavePropia(SecRole $rol, ?int $idRolActivo, array $aQuitar): void
    {
        if ($idRolActivo === null || $rol->id !== $idRolActivo || $aQuitar === []) {
            return;
        }

        $codigos = SecPermission::query()
            ->whereIn('id', $aQuitar)
            ->whereIn('code', self::IRRENUNCIABLES_SOBRE_EL_ROL_PROPIO)
            ->pluck('code');

        if ($codigos->isNotEmpty()) {
            throw RolProtegido::porRolActivoPropio((string) $codigos->first());
        }
    }

    /**
     * Guarda 4: ningún permiso puede quedarse sin ningún rol vivo.
     *
     * @param  list<int>  $aQuitar
     *
     * @throws RolProtegido
     */
    private function verificarQueNingunPermisoQuedaHuerfano(SecRole $rol, array $aQuitar): void
    {
        if ($aQuitar === []) {
            return;
        }

        $soloEsteRolLosTiene = array_intersect(
            $aQuitar,
            $this->catalogo->idsPermisoConPortadorUnico($rol->id),
        );

        if ($soloEsteRolLosTiene === []) {
            return;
        }

        $codigo = SecPermission::query()
            ->whereIn('id', $soloEsteRolLosTiene)
            ->orderBy('code')
            ->value('code');

        throw RolProtegido::porUltimoPortadorDelPermiso((string) $codigo);
    }

    /**
     * Reactiva el otorgamiento anterior si existe en vez de insertar uno
     * nuevo: `sec_role_permission` acumula el historial por soft delete, y
     * devolverle un permiso a un rol que ya lo tuvo es el mismo hecho de
     * negocio, no uno nuevo. Sin esto la tabla junta una fila muerta por cada
     * vez que alguien duda con un interruptor.
     */
    private function otorgar(int $idRol, int $idPermiso, int $idActor): void
    {
        $previo = SecRolePermission::withTrashed()
            ->where('id_role', $idRol)
            ->where('id_permission', $idPermiso)
            ->first();

        if ($previo !== null) {
            $previo->updated_by = $idActor;
            $previo->deleted_at = null;
            $previo->save();

            return;
        }

        $pivote = new SecRolePermission(['id_role' => $idRol, 'id_permission' => $idPermiso]);
        $pivote->created_by = $idActor;
        $pivote->updated_by = $idActor;
        $pivote->save();
    }

    /**
     * @param  list<int>  $idsPermiso
     */
    private function quitar(int $idRol, array $idsPermiso, int $idActor): void
    {
        if ($idsPermiso === []) {
            return;
        }

        SecRolePermission::query()
            ->where('id_role', $idRol)
            ->whereIn('id_permission', $idsPermiso)
            ->get()
            ->each(function (SecRolePermission $pivote) use ($idActor): void {
                // Asignación directa + save() ANTES de delete(): `updated_by`
                // no es fillable (la autoría nunca es mass-assignable) y
                // `runSoftDelete()` solo persiste deleted_at/updated_at, así
                // que necesita su propio guardado para quedar auditado
                // (invariante 9) antes del soft delete. Mismo patrón que
                // AsignarRolesUsuario::sincronizarRoles().
                $pivote->updated_by = $idActor;
                $pivote->save();
                $pivote->delete();
            });
    }

    /**
     * Único punto de evaluación de permiso de este caso de uso: con rol
     * activo, SIEMPRE `tienePermisoEnRol()`; sin él (llamador legítimamente
     * sin sesión), la unión de `tienePermiso()` — nunca al revés por default
     * (ADR 0004, extensión 27/8/2026, punto 5).
     */
    private function actorTienePermiso(SecUser $actor, ?int $idRolActivo, string $codigo): bool
    {
        return $idRolActivo !== null
            ? $actor->tienePermisoEnRol($codigo, $idRolActivo)
            : $actor->tienePermiso($codigo);
    }
}
