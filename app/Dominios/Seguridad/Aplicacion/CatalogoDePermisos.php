<?php

namespace App\Dominios\Seguridad\Aplicacion;

use Illuminate\Support\Facades\DB;

/**
 * Lado de LECTURA del catálogo RBAC: `sec_permission`, el pivote
 * `sec_role_permission` y el árbol `sec_menu` que los ata a pantallas.
 *
 * Existe como pieza propia por dos razones.
 *
 * PRIMERA: "vivo" acá significa cuatro condiciones a la vez, y olvidar una da
 * una respuesta que parece correcta. Un otorgamiento cuenta solo si el pivote
 * no está dado de baja, el permiso no está dado de baja, el permiso está
 * habilitado (`state`), y —para las preguntas que hablan de roles— el rol
 * está vivo Y habilitado. La última es la que se olvida: un rol desactivado
 * conserva sus filas del pivote, así que contarlo como portador haría creer
 * que la llave del sistema está a salvo cuando no la tiene nadie que pueda
 * iniciar sesión.
 *
 * SEGUNDA: los 91 permisos NO son 91 cosas equivalentes, aunque la tabla los
 * guarde igual. 31 de ellos son el `permission_id` de un ítem de `sec_menu`:
 * otorgarlos es literalmente "este rol ve esta pantalla en el sidebar". Los
 * 58 siguientes son acciones DENTRO de una de esas pantallas, y se reconocen
 * porque comparten el prefijo `modulo.entidad` con el permiso de la pantalla
 * (`comercial.cliente.ver` es la pantalla; `comercial.cliente.crear`, su
 * acción). Los 2 que quedan —`operaciones.acta.generar` y `.firmar`— no
 * cuelgan de ninguna pantalla del panel porque se ejecutan desde
 * `agrocom-field`, y el árbol los devuelve aparte en vez de esconderlos.
 *
 * Esa partición es lo que hace administrable la matriz: 91 interruptores
 * sueltos son ilegibles; siete módulos de menú con sus pantallas, y las
 * acciones anidadas bajo cada una, se recorren. Y sale de los datos, no de
 * una lista escrita a mano que se desincronizaría con `sec_menu` en cuanto
 * se agregue una pantalla.
 *
 * Solo lectura: quien otorga o quita es {@see AsignarPermisosRol}.
 */
final class CatalogoDePermisos
{
    /**
     * IDs de permiso otorgados y vivos de un rol. No filtra por estado del
     * rol a propósito: es "qué tiene este rol", y la pantalla necesita poder
     * mostrar y editar la matriz de un rol desactivado.
     *
     * @return list<int>
     */
    public function idsPermisoDeRol(int $idRol): array
    {
        return DB::table('sec_role_permission as rp')
            ->join('sec_permission as p', 'p.id', '=', 'rp.id_permission')
            ->where('rp.id_role', $idRol)
            ->whereNull('rp.deleted_at')
            ->whereNull('p.deleted_at')
            ->where('p.state', true)
            ->pluck('rp.id_permission')
            ->map(static fn (int|string $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * IDs de rol VIVOS Y HABILITADOS que tienen otorgado un permiso, por su
     * código. Es la pregunta con la que se decide si queda alguna llave.
     *
     * @return list<int>
     */
    public function idsRolVivoConPermiso(string $codigoPermiso): array
    {
        return DB::table('sec_role_permission as rp')
            ->join('sec_permission as p', 'p.id', '=', 'rp.id_permission')
            ->join('sec_role as r', 'r.id', '=', 'rp.id_role')
            ->where('p.code', $codigoPermiso)
            ->whereNull('rp.deleted_at')
            ->whereNull('p.deleted_at')
            ->where('p.state', true)
            ->whereNull('r.deleted_at')
            ->where('r.state', true)
            ->pluck('rp.id_role')
            ->map(static fn (int|string $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * IDs de permiso cuyo ÚNICO portador vivo es este rol — los que, si se le
     * quitan, quedan huérfanos en el catálogo.
     *
     * La pantalla los pinta bloqueados, con su explicación, en vez de dejar
     * que el submit falle: un interruptor que se puede apagar y al guardar
     * vuelve encendido con un error es peor que uno que dice desde el
     * principio por qué no se toca.
     *
     * @return list<int>
     */
    public function idsPermisoConPortadorUnico(int $idRol): array
    {
        $delRol = DB::table('sec_role_permission as rp')
            ->join('sec_permission as p', 'p.id', '=', 'rp.id_permission')
            ->where('rp.id_role', $idRol)
            ->whereNull('rp.deleted_at')
            ->whereNull('p.deleted_at')
            ->where('p.state', true)
            ->pluck('p.id');

        if ($delRol->isEmpty()) {
            return [];
        }

        // Otros roles VIVOS que también los tienen: lo que quede afuera de
        // esta lista es lo que solo sostiene el rol en cuestión.
        $sostenidosPorOtro = DB::table('sec_role_permission as rp')
            ->join('sec_role as r', 'r.id', '=', 'rp.id_role')
            ->whereIn('rp.id_permission', $delRol)
            ->where('rp.id_role', '!=', $idRol)
            ->whereNull('rp.deleted_at')
            ->whereNull('r.deleted_at')
            ->where('r.state', true)
            ->pluck('rp.id_permission')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();

        return $delRol
            ->map(static fn (int|string $id): int => (int) $id)
            ->reject(static fn (int $id): bool => in_array($id, $sostenidosPorOtro, true))
            ->values()
            ->all();
    }

    /**
     * Catálogo completo listo para pintar: los módulos del MENÚ con sus
     * pantallas, las acciones anidadas bajo cada una, y aparte las acciones
     * que no cuelgan de ninguna pantalla del panel.
     *
     * Se agrupa por módulo de MENÚ (Operación, Comercial, Recursos…) y no por
     * módulo del código de permiso (`operaciones`, `comercial`, `personal`…)
     * porque son cosas distintas y la que importa acá es la primera: el
     * módulo "Recursos" del sidebar contiene `operaciones.dron.ver`,
     * `mantenimiento.bateria.ver` y `personal.base.ver`. Agrupar por el
     * prefijo del código daría una lista que no se parece a lo que el usuario
     * del rol va a ver, que es justamente lo que la pantalla promete mostrar.
     *
     * Las etiquetas viajan como CLAVES de idioma (`menu.operacion.label`), tal
     * como las guarda `sec_menu`; se resuelven con `__()` recién en la vista
     * (ADR 0013).
     *
     * @return array{
     *     modulos: list<array{clave: string, icono: string, pantallas: list<array{
     *         id: int, codigo: string, clave: string, icono: string, descripcion: string,
     *         acciones: list<array{id: int, codigo: string, accion: string, descripcion: string}>
     *     }>}>,
     *     sueltos: list<array{id: int, codigo: string, accion: string, descripcion: string}>
     * }
     */
    public function arbolDeConcesiones(): array
    {
        $permisos = DB::table('sec_permission')
            ->whereNull('deleted_at')
            ->where('state', true)
            ->orderBy('code')
            ->get(['id', 'code', 'description']);

        $items = DB::table('sec_menu as item')
            ->join('sec_menu as modulo', 'modulo.id', '=', 'item.padre_id')
            ->join('sec_permission as p', 'p.id', '=', 'item.permission_id')
            ->whereNull('item.deleted_at')
            ->whereNull('modulo.deleted_at')
            ->whereNull('p.deleted_at')
            ->where('p.state', true)
            ->orderBy('modulo.orden')
            ->orderBy('item.orden')
            ->get([
                'modulo.label as modulo_label',
                'modulo.icono as modulo_icono',
                'item.label as item_label',
                'item.icono as item_icono',
                'p.id as permiso_id',
                'p.code as permiso_code',
                'p.description as permiso_descripcion',
            ]);

        /** @var list<int> $idsPantalla */
        $idsPantalla = $items->pluck('permiso_id')->map(static fn (int|string $id): int => (int) $id)->all();

        // Prefijo `modulo.entidad` de cada pantalla → sus acciones. Es el
        // vínculo real entre una acción y la pantalla donde se ejerce:
        // `comercial.cliente.crear` se ejerce en `comercial.cliente.ver`.
        $accionesPorPrefijo = [];

        foreach ($permisos as $permiso) {
            $id = (int) $permiso->id;

            if (in_array($id, $idsPantalla, true)) {
                continue;
            }

            $accionesPorPrefijo[$this->prefijo((string) $permiso->code)][] = [
                'id' => $id,
                'codigo' => (string) $permiso->code,
                'accion' => $this->accion((string) $permiso->code),
                'descripcion' => (string) $permiso->description,
            ];
        }

        $modulos = [];

        foreach ($items->groupBy('modulo_label') as $claveModulo => $pantallasDelModulo) {
            $modulos[] = [
                'clave' => (string) $claveModulo,
                'icono' => (string) $pantallasDelModulo->first()->modulo_icono,
                'pantallas' => $pantallasDelModulo->map(function (object $item) use (&$accionesPorPrefijo): array {
                    $prefijo = $this->prefijo((string) $item->permiso_code);
                    $acciones = $accionesPorPrefijo[$prefijo] ?? [];
                    // Consumido: una acción pertenece a UNA pantalla. Si dos
                    // ítems de menú compartieran prefijo, la segunda no
                    // repetiría los mismos interruptores.
                    unset($accionesPorPrefijo[$prefijo]);

                    return [
                        'id' => (int) $item->permiso_id,
                        'codigo' => (string) $item->permiso_code,
                        'clave' => (string) $item->item_label,
                        'icono' => (string) $item->item_icono,
                        'descripcion' => (string) $item->permiso_descripcion,
                        'acciones' => $acciones,
                    ];
                })->values()->all(),
            ];
        }

        // Lo que sobra: acciones sin pantalla del panel donde ejercerse. Hoy
        // son las dos del acta, que se ejecutan desde `agrocom-field`. Van
        // visibles y aparte — esconderlas dejaría permisos inalcanzables
        // desde la única pantalla que administra permisos.
        $sueltos = [];

        foreach ($accionesPorPrefijo as $acciones) {
            foreach ($acciones as $accion) {
                $sueltos[] = $accion;
            }
        }

        usort($sueltos, static fn (array $a, array $b): int => $a['codigo'] <=> $b['codigo']);

        return ['modulos' => $modulos, 'sueltos' => $sueltos];
    }

    /** `comercial.cliente.crear` → `comercial.cliente`. */
    private function prefijo(string $codigo): string
    {
        return implode('.', array_slice(explode('.', $codigo), 0, 2));
    }

    /** `comercial.cliente.crear` → `crear`. */
    private function accion(string $codigo): string
    {
        $partes = explode('.', $codigo);

        return $partes[2] ?? $codigo;
    }
}
