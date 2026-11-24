<?php

namespace Database\Seeders\Catalogo;

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecMenu;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecPermission;
use Illuminate\Database\Seeder;

/**
 * Catálogo del menú de tres niveles del panel (quinta vuelta — el LAYOUT es
 * el de las maquetas aprobadas 4a/5a/5b/5c; los MÓDULOS y su vocabulario
 * salen de la especificación, no del mockup — pedido explícito del
 * 28/8/2026): 7 módulos raíz (riel de nivel 1) con sus ítems hijos (sidebar
 * de nivel 2), según §4 de
 * `docs/especificacion/especificacion_funcional_tecnica.md` — Comercial
 * (4.1), Recursos (4.2), Operación (4.3), Financiero (4.4), Seguridad (4.6) —
 * más Reportes (cap. 9/10).
 *
 * Corre en todos los entornos, producción incluida — igual que
 * `SeguridadSeeder`, del que depende (necesita `sec_permission` sembrado).
 * Sin autor explícito (`created_by`/`updated_by` NULL): dato de catálogo.
 *
 * `label`/`descripcion` guardan CLAVES de `lang/es/menu.php` (ADR 0013).
 * `ruta` guarda nombres de ruta Laravel; los ítems de pantallas todavía sin
 * construir la llevan NULL (menu-item los pinta como botón sin link). Las
 * tres pantallas que YA existen quedan en el árbol (pedido del 28/8/2026 —
 * nada se retira del menú):
 * - Operación › Programación → `panel.dashboard` (el viejo ítem "Inicio",
 *   migrado — es la misma pantalla, el "Operación de hoy"), gateado por
 *   `seguridad.dashboard.ver` desde la tarea 62 (fuga 2: antes sin permiso,
 *   visible para cualquier rol).
 * - Seguridad › Usuarios → `panel.usuarios.index`, gateado por
 *   `seguridad.usuario.ver` (el permiso de listado — los de mutación rigen
 *   acciones DENTRO de la pantalla, no la visibilidad del ítem).
 * - Seguridad › Organización → `panel.organizacion.index`, gateado por
 *   `seguridad.organizacion.ver` desde la tarea 62 (ídem — el engranaje del
 *   riel también la abre como atajo, condicionado al mismo permiso).
 *
 * Idempotente: primero MIGRA las filas del catálogo plano anterior a su
 * nueva identidad (mismo id, label/padre nuevos — así ediciones manuales y
 * bitácora sobreviven), después `firstOrCreate` sobre `(label, padre_id)`.
 */
class SecMenuSeeder extends Seeder
{
    public function run(): void
    {
        // TE-13 (tarea 59): CR-01 cerró HU-11/HU-12 — Agrocom no prepara la
        // mezcla ni dosifica, el dato no existe. El ítem quedó sembrado como
        // placeholder antes de que CR-01 se cerrara; una base ya sembrada no
        // lo pierde solo con sacar la línea de abajo (`Seeder::run()` nunca
        // hace `DELETE` de lo que ya no siembra), así que se borra acá.
        // `delete()` (soft, invariante 8) y no físico: `ModeloDominio`
        // bloquea `forceDelete()` (ADR 0007) y un DELETE crudo por fuera de
        // Eloquent está prohibido (ADR 0012) — mismo criterio que el resto
        // de `sec_*`. El ítem es hoja sin `ruta`: nada referencia su id.
        SecMenu::query()
            ->where('label', 'menu.operacion.items.mezclas')
            ->delete();

        // Tarea 62 (fuga 3): estos dos ítems se sembraron sin `ruta` ni
        // `permission_id` como placeholders y nunca llegaron a tener pantalla
        // propia — `operacion.evidencias` porque la galería de evidencias
        // (HU-42, tarea 56) terminó como sub-vista de un trabajo puntual
        // (`GET /panel/trabajos/{trabajo}/evidencias`, gateada por
        // `operaciones.trabajo.ver`, no un ítem de nivel 2 propio) y
        // `comercial.reportes_cliente` porque era para el portal del cliente
        // (HU-41) y quedó sin pantalla en el panel (`prompts/60-badges-menu-reales.md`
        // ya lo daba de baja). Por la regla de grupos de
        // `ObtenerMenuPorRolActivo`, un ítem sin permiso hacía visible a
        // "Operación"/"Comercial" completos para cualquier rol — mismo
        // criterio de borrado que `operacion.mezclas` arriba (soft delete
        // explícito de catálogo, `Seeder::run()` nunca lo hace solo).
        SecMenu::query()
            ->where('label', 'menu.operacion.items.evidencias')
            ->delete();
        SecMenu::query()
            ->where('label', 'menu.comercial.items.reportes_cliente')
            ->delete();

        // Refactor de menú de Operación (17/9/2026, pedido directo del
        // dueño): "Pausas" deja de tener ítem propio — es un hecho puntual
        // DENTRO de una sesión (`Pausa.sesion_id`, nunca tuvo máquina de
        // estados propia, ver su docblock), y el ítem "Sesiones" que se
        // renombra a "Seguimiento de vuelos" abajo ya es donde se sigue toda
        // la actividad del piloto. La pantalla (`panel.pausas.index`) y el
        // permiso (`operaciones.pausa.ver`) SIGUEN vigentes — solo se retira
        // la entrada de navegación, mismo criterio de baja que "mezclas"/
        // "evidencias"/"reportes_cliente" arriba.
        SecMenu::query()
            ->where('label', 'menu.operacion.items.pausas')
            ->delete();

        // El marketplace no lleva planilla, contabilidad interna, inventario
        // ni mantenimiento de flota (ADR 0029, punto 4): las entradas de
        // esos módulos salen del árbol. Soft delete (invariante 8), por la
        // misma razón que las bajas de arriba: `Seeder::run()` nunca borra lo
        // que ya no siembra. Idempotente: lo ya dado de baja no vuelve a
        // aparecer en `SecMenu::query()`.
        $this->retirarEntradas([
            'menu.recursos.items.vehiculos',
            'menu.recursos.items.generadores',
            'menu.mantenimiento.items.ordenes',
            'menu.mantenimiento.items.planes',
            'menu.mantenimiento.items.repuestos',
            'menu.mantenimiento.items.stock',
            'menu.financiero.items.gastos',
            'menu.financiero.items.combustible',
            'menu.financiero.items.rendiciones',
            'menu.financiero.items.devengos',
            'menu.financiero.items.planilla',
            'menu.financiero.items.anticipos',
            'menu.financiero.items.tarifas',
            'menu.mantenimiento.label',
        ]);

        // El marketplace tampoco lleva contratos, campañas, siembra por etapas,
        // facturas por contrato, órdenes de aplicación ni de trabajo, estadías,
        // ni la cartera comercial que los sostenía (ADR 0037): el cliente pide
        // un servicio y de ahí nace el trabajo. Los módulos «Comercial» y
        // «Financiero» salen enteros del árbol.
        $this->retirarEntradas([
            'menu.operacion.items.ordenes',
            'menu.operacion.items.estadias',
            'menu.comercial.items.campanias',
            'menu.comercial.items.clientes',
            'menu.comercial.items.contratos',
            'menu.comercial.items.propiedades',
            'menu.comercial.items.lotes',
            'menu.comercial.items.cultivos',
            'menu.comercial.label',
            'menu.financiero.items.facturas',
            'menu.financiero.label',
            'menu.reportes.items.comerciales',
        ]);

        $operacion = $this->modulo('operacion', 'flight_takeoff', 1);
        $solicitudes = $this->modulo('solicitudes', 'request_quote', 2);
        $recursos = $this->modulo('recursos', 'precision_manufacturing', 3);
        $proveedores = $this->modulo('proveedores', 'local_shipping', 4);
        $reportes = $this->modulo('reportes', 'lab_profile', 6);
        $seguridad = $this->modulo('seguridad', 'admin_panel_settings', 7);

        // Migración del catálogo plano anterior (raíces `seguridad.menu.*`):
        // las tres pantallas existen y siguen en el menú — misma fila, nueva
        // identidad bajo su módulo.
        $this->migrar('seguridad.menu.inicio', 'menu.operacion.items.programacion', $operacion, 'event_available', 1);
        $this->migrar('seguridad.menu.usuarios', 'menu.seguridad.items.usuarios', $seguridad, 'group', 1);
        $this->migrar('seguridad.menu.organizacion', 'menu.seguridad.items.organizacion', $seguridad, 'apartment', 3);

        // Operación (§4.3)
        //
        // El ítem del dashboard se llamaba "Programación" desde la maqueta,
        // cuando el tablero mostraba una agenda de sesiones programadas —
        // concepto que no existe en ninguna tabla. Desde la tarea 67 el
        // dashboard se compone por rol (un piloto ve sus sesiones y su
        // liquidación; un dueño, la operación entera), así que el nombre
        // honesto es "Tablero". Misma fila, mismo permiso: solo cambia la
        // etiqueta.
        $this->renombrar($operacion, 'menu.operacion.items.programacion', 'menu.operacion.items.tablero');
        $this->item($operacion, 'operacion', 'tablero', 'dashboard', 1, ruta: 'panel.dashboard', codigoPermiso: 'seguridad.dashboard.ver');
        // Refactor de menú (17/9/2026, pedido directo del dueño): "Trabajos"
        // pasaba por el mismo nombre que el Word del dueño usa para la orden
        // que el operador emite ("Orden de Trabajo" ya está confirmado como
        // columna del propio listado, HU-93) — acá se aclara que la
        // ejecución en sí, la que hace el piloto en base a la orden de
        // aplicación, se llama igual en el menú. `renombrar()` conserva la
        // fila (id, ruta, permiso, bitácora); solo cambia a qué clave de
        // `lang/es/menu.php` apunta.
        $this->renombrar($operacion, 'menu.operacion.items.trabajos', 'menu.operacion.items.orden_trabajo');
        // HU-05 (tarea 13): listado mínimo de trabajos/sesiones — el jefe ve
        // qué se cerró. Detalle con evidencias y filtros llegan con HU-15.
        // Orden 3: antes de "Asignación de equipos" y "Estadías" — primero
        // se crea el trabajo, después se le asigna el equipo y recién ahí
        // se sabe dónde se aloja (pedido directo del dueño, 17/9/2026).
        $this->item($operacion, 'operacion', 'orden_trabajo', 'work_history', 3, ruta: 'panel.trabajos.index', codigoPermiso: 'operaciones.trabajo.ver');

        // Cuadrillas (19/9/2026, pedido directo del dueño). El ítem que ocupaba
        // este lugar ("Asignación de equipos", después "Escuadras") abría el
        // reparto de hectáreas de una orden (tarea 85, HU-70/92), que el alta
        // de Orden de Trabajo ya cubre: sale del menú y esa pantalla queda
        // solo como ficha de cada orden (`panel.reparto-cuadrillas.*`, desde
        // la orden de aplicación). En su lugar entra la pantalla donde la
        // cuadrilla SE ARMA —piloto, ayudante y dron—, que vivía en Recursos
        // como "Equipos de trabajo": se mueve la MISMA fila (id, permiso y
        // bitácora intactos) y se reapunta a su ruta nueva. El backend sigue
        // en `Personal` (`per_equipos_trabajo`); agruparlo bajo Operación es
        // de layout, no una frontera de módulo (ADR 0011, extensión del
        // 26/8/2026, punto 3). "Cuadrilla" es el término del agro para el
        // grupo que sale junto al campo; en el código sigue siendo
        // `equipo_trabajo` (ADR 0015).
        $this->retirarItemReparto($operacion);
        $this->mover('menu.recursos.items.equipos_trabajo', 'menu.operacion.items.cuadrillas', $operacion, 4);
        $this->reapuntar('panel.equipos-trabajo.index', 'panel.cuadrillas.index');
        $this->item($operacion, 'operacion', 'cuadrillas', 'groups', 4, ruta: 'panel.cuadrillas.index', codigoPermiso: 'personal.equipo_trabajo.ver');
        // Refactor de menú (17/9/2026, pedido directo del dueño): "Sesiones"
        // apuntaba a la cola de validación, pero el uso real es más amplio —
        // seguimiento del avance de cada vuelo, con validación como una de
        // sus acciones, no la única razón de entrar. Absorbe también
        // "Pausas" (dado de baja arriba): un hecho puntual DENTRO de una
        // sesión, sin pantalla propia en el menú desde ahora. Baja del
        // orden 4 al 6 (último del grupo) en este refactor.
        $this->renombrar($operacion, 'menu.operacion.items.sesiones', 'menu.operacion.items.seguimiento_vuelos');
        // HU-14 (tarea 14): cola de validación de sesiones cerradas.
        $this->item($operacion, 'operacion', 'seguimiento_vuelos', 'flight', 6, ruta: 'panel.sesiones.validacion.index', codigoPermiso: 'operaciones.sesion.validar');

        // Solicitudes de servicio (ADR 0027): la unidad de negocio del
        // marketplace. Gateado por el permiso de LISTADO (`ver`); cancelar rige
        // el botón de la ficha, no la visibilidad del ítem.
        $this->item($solicitudes, 'solicitudes', 'solicitudes', 'request_quote', 1, ruta: 'panel.solicitudes.index', codigoPermiso: 'solicitudes.solicitud.ver');

        // Asignación (ADR 0030): la cola de solicitudes que esperan proveedor y sus
        // ofertas. Gateado por el permiso de LISTADO; asignar a mano rige el botón
        // de la ficha.
        $this->item($solicitudes, 'solicitudes', 'asignaciones', 'alt_route', 2, ruta: 'panel.asignaciones.index', codigoPermiso: 'asignacion.asignacion.ver');

        // Recursos (§4.2)
        // HU-27 (tarea 36): administración de la flota de drones — activa
        // el ítem que ya estaba sembrado como "botón sin link" (ver
        // docblock de `item()`).
        $this->item($recursos, 'recursos', 'drones', 'airplanemode_active', 1, ruta: 'panel.drones.index', codigoPermiso: 'operaciones.dron.ver');
        // HU-39 (tarea 51): seguimiento de baterías con ciclos y estado —
        // activa el ítem que ya estaba sembrado como "botón sin link" (ver
        // docblock de `item()`).
        $this->item($recursos, 'recursos', 'baterias', 'battery_charging_full', 2, ruta: 'panel.baterias.index', codigoPermiso: 'mantenimiento.bateria.ver');
        // Los equipos de trabajo (tarea 72, HU-49) ya no se siembran acá: el
        // ítem se movió a Operación como "Cuadrillas" (19/9/2026, ver arriba).

        // HU-82 (tarea 97): ficha de inventario del dron (serie, chasis,
        // versión de software, región, serie del control, accesorios). ABM
        // mínimo nuevo, sin placeholder previo. El backend vive en
        // `Mantenimiento` (`man_drones`) aunque el ítem quede agrupado bajo
        // "Recursos": misma agrupación de layout que el resto de este bloque
        // (ADR 0011, extensión 26/8/2026, punto 3). Icono `memory`, distinto
        // del `airplanemode_active` de `drones` (el catálogo operativo) para
        // no confundir los dos ítems en el sidebar.
        $this->item($recursos, 'recursos', 'fichas_dron', 'memory', 8, ruta: 'panel.fichas-dron.index', codigoPermiso: 'mantenimiento.ficha_dron.ver');

        // HU-26 (tarea 37): administración de personas y bases — activa los
        // dos ítems que ya estaban sembrados como "botón sin link" (ver
        // docblock de `item()`).
        $this->item($recursos, 'recursos', 'bases', 'home_work', 4, ruta: 'panel.bases.index', codigoPermiso: 'personal.base.ver');
        // Tarea 77 (HU-54, pedido del dueño 7/9/2026): "Personas" era el
        // nombre de la tabla filtrándose a la interfaz — el módulo es
        // Personal. `renombrar()` conserva la fila, su id, su ruta y su
        // permiso; solo cambia la etiqueta.
        $this->renombrar($recursos, 'menu.recursos.items.personas', 'menu.recursos.items.personal');
        $this->item($recursos, 'recursos', 'personal', 'badge', 5, ruta: 'panel.personas.index', codigoPermiso: 'personal.persona.ver');

        // Marketplace (ADR 0032): la cola de aprobación. Cada ítem va gateado por
        // el permiso de LISTADO (`.ver`); aprobar, suspender y dar de baja rigen
        // los botones de la ficha, no la visibilidad del ítem. Documentos va
        // primero: es la cola que más se mira a diario.
        $this->item($proveedores, 'proveedores', 'documentos', 'folder_open', 1, ruta: 'panel.documentos.index', codigoPermiso: 'proveedores.documento.ver');
        $this->item($proveedores, 'proveedores', 'proveedores', 'local_shipping', 2, ruta: 'panel.proveedores.index', codigoPermiso: 'proveedores.proveedor.ver');
        $this->item($proveedores, 'proveedores', 'pilotos', 'sports_esports', 3, ruta: 'panel.pilotos.index', codigoPermiso: 'proveedores.piloto.ver');
        $this->item($proveedores, 'proveedores', 'auxiliares', 'support_agent', 4, ruta: 'panel.auxiliares.index', codigoPermiso: 'proveedores.auxiliar.ver');
        $this->item($proveedores, 'proveedores', 'drones_flota', 'airplanemode_active', 5, ruta: 'panel.drones-flota.index', codigoPermiso: 'flota.dron.ver');
        $this->item($proveedores, 'proveedores', 'equipos_flota', 'groups', 6, ruta: 'panel.equipos-flota.index', codigoPermiso: 'flota.equipo.ver');
        $this->item($proveedores, 'proveedores', 'sanciones', 'gavel', 7, ruta: 'panel.sanciones.index', codigoPermiso: 'asignacion.sancion.ver');

        // Reportes (cap. 9 y 10)
        // HU-43 (tarea 57): "como encargado, quiero listar y descargar los
        // reportes técnicos generados, para reenviarlos al agrónomo" —
        // activa el ítem que ya estaba sembrado como "botón sin link" (ver
        // docblock de `item()`). Mismo permiso `operaciones.reporte.ver` que
        // ya gatea la descarga individual (misma acción de negocio).
        $this->item($reportes, 'reportes', 'tecnicos', 'summarize', 1, ruta: 'panel.reportes.tecnicos.index', codigoPermiso: 'operaciones.reporte.ver');
        // HU-19 (tarea 26): bandeja de alertas por excepción — activa el
        // ítem que ya estaba sembrado como "botón sin link" (ver docblock de
        // `item()`).
        $this->item($reportes, 'reportes', 'alertas', 'notifications_active', 3, ruta: 'panel.alertas.index', codigoPermiso: 'operaciones.alerta.ver');

        // Seguridad (§4.6 + cap. 14) — las dos pantallas existentes; si la
        // migración de arriba ya las convirtió, el firstOrCreate las
        // encuentra y no duplica.
        $this->item($seguridad, 'seguridad', 'usuarios', 'group', 1, ruta: 'panel.usuarios.index', codigoPermiso: 'seguridad.usuario.ver');
        // Administración del catálogo de roles y de la matriz rol↔permiso.
        // Va pegado a Usuarios porque son las dos mitades de la misma
        // pregunta ("quién es" / "qué puede"), y eso empuja un lugar a los
        // tres ítems de abajo — ver la nota de `orden` en `item()`. Gateado
        // por `seguridad.rol.ver`; los otros cuatro permisos rigen botones
        // dentro de la pantalla, no la visibilidad del ítem (mismo criterio
        // que Usuarios y Dispositivos).
        $this->item($seguridad, 'seguridad', 'roles', 'shield_person', 2, ruta: 'panel.roles.index', codigoPermiso: 'seguridad.rol.ver');
        // HU-03: revocación de sesiones de la app de campo. Gateado por el
        // permiso de LISTADO (`ver`); el de revocar rige el botón dentro de
        // la pantalla, no la visibilidad del ítem — mismo criterio que
        // Usuarios.
        $this->item($seguridad, 'seguridad', 'dispositivos', 'smartphone', 3, ruta: 'panel.dispositivos.index', codigoPermiso: 'seguridad.dispositivo.ver');
        $this->item($seguridad, 'seguridad', 'organizacion', 'apartment', 4, ruta: 'panel.organizacion.index', codigoPermiso: 'seguridad.organizacion.ver');
        // HU-20: sin módulo raíz propio en la espec §4 (runs/10-diseno.md) —
        // entra bajo Seguridad, mismo criterio que Organización.
        $this->item($seguridad, 'seguridad', 'versiones_apk', 'system_update', 5, ruta: 'panel.versiones-apk.index', codigoPermiso: 'distribucion.version.autorizar');
        // Orden 6 queda vacante a propósito: lo ocupaba "Campañas", que el
        // 9/9/2026 se movió a Comercial —ver el comentario allá para el
        // porqué, corregido el 15/9/2026—. No se renumeran los ítems que
        // siguen, mismo criterio que las otras vacantes del menú.
        // Tarea 78 (HU-55): llaves y tokens de infraestructura, exclusivo del
        // dueño — separado a propósito de "Organización" arriba (datos de la
        // empresa). Gateado por `seguridad.configuracion.ver`, que ningún
        // otro rol recibe (ver `SeguridadSeeder`), así que el ítem solo es
        // visible para `dueno`.
        $this->item($seguridad, 'seguridad', 'configuracion', 'tune', 7, ruta: 'panel.configuracion.index', codigoPermiso: 'seguridad.configuracion.ver');
        // Tarea 63 (invariante 9 de CLAUDE.md): bitácora de auditoría — quién
        // hizo qué, cuándo y en qué zona horaria. Gateado por
        // `seguridad.bitacora.ver` (dueño y encargado de operaciones, ver
        // `SeguridadSeeder`).
        $this->item($seguridad, 'seguridad', 'bitacora', 'history', 8, ruta: 'panel.bitacora.index', codigoPermiso: 'seguridad.bitacora.ver');
    }

    /**
     * Da de baja (soft delete) las entradas del menú con esas claves de
     * `lang/es/menu.php`, una por una para que cada `delete()` pase por el
     * observer de bitácora.
     *
     * @param  list<string>  $labels
     */
    private function retirarEntradas(array $labels): void
    {
        SecMenu::query()
            ->whereIn('label', $labels)
            ->get()
            ->each(fn (SecMenu $entrada) => $entrada->delete());
    }

    private function modulo(string $clave, string $icono, int $orden): SecMenu
    {
        return SecMenu::query()->firstOrCreate(
            ['label' => "menu.{$clave}.label", 'padre_id' => null],
            [
                'descripcion' => "menu.{$clave}.descripcion",
                'icono' => $icono,
                'ruta' => null,
                'orden' => $orden,
                'permission_id' => null,
            ],
        );
    }

    /**
     * Convierte una raíz del catálogo plano anterior en un ítem del árbol
     * nuevo — misma fila (id estable, bitácora intacta), identidad nueva.
     * `ruta`/`permission_id` no se tocan: ya apuntaban a la pantalla real.
     */
    /**
     * Renombra la etiqueta de un ítem YA COLGADO de su módulo, conservando la
     * fila (y por lo tanto su id, su permiso y su lugar en el orden).
     * Distinto de {@see migrar()}, que además mueve una raíz suelta bajo su
     * módulo: acá el ítem ya está en su sitio y solo cambia cómo se llama.
     */
    private function renombrar(SecMenu $padre, string $labelViejo, string $labelNuevo): void
    {
        SecMenu::query()
            ->where('padre_id', $padre->id)
            ->where('label', $labelViejo)
            ->update(['label' => $labelNuevo]);
    }

    /**
     * Saca del menú el reparto de hectáreas por orden (tarea 85, HU-70/92): el
     * alta de Orden de Trabajo ya lo cubre y la pantalla queda solo como ficha
     * de cada orden, a la que se llega desde la orden de aplicación.
     *
     * Filtra por `padre_id` + permiso y no por `label` ni por `ruta`: la fila
     * se llamó "Asignación de equipos" y después "Escuadras", y su ruta cambió
     * de nombre, pero el permiso fue siempre el mismo. `delete()` es soft
     * (invariante 8) y `SecMenu::query()` ya excluye lo borrado: correr esto
     * dos veces no hace nada. El permiso sigue vigente, gatea la ficha.
     */
    private function retirarItemReparto(SecMenu $padre): void
    {
        $permisoId = SecPermission::query()->where('code', 'operaciones.orden.asignar_equipos')->value('id');

        if ($permisoId === null) {
            return;
        }

        SecMenu::query()
            ->where('padre_id', $padre->id)
            ->where('permission_id', $permisoId)
            ->get()
            ->each(fn (SecMenu $fila) => $fila->delete());
    }

    /**
     * Cambia la ruta de los ítems que apuntaban a una ruta que se renombró.
     * Hace falta porque {@see item()} nunca pisa una `ruta` ya sembrada: sin
     * esto, una base existente seguiría enlazando a un nombre de ruta que ya
     * no existe. Se guarda con `save()` para que pase por la bitácora
     * (invariante 9); sin filas con la ruta vieja, no hace nada.
     */
    private function reapuntar(string $rutaVieja, string $rutaNueva): void
    {
        SecMenu::query()
            ->where('ruta', $rutaVieja)
            ->get()
            ->each(function (SecMenu $fila) use ($rutaNueva): void {
                $fila->ruta = $rutaNueva;
                $fila->save();
            });
    }

    /**
     * Mueve un ítem YA COLGADO de un módulo a otro, conservando la fila —su
     * id, su ruta, su permiso y su bitácora— y renombrando la etiqueta al
     * namespace del módulo nuevo. Distinto de {@see migrar()}, que sube una
     * raíz suelta (`padre_id` nulo) del catálogo plano viejo: acá el ítem ya
     * cuelga de un módulo y lo que cambia es de cuál.
     *
     * Busca por el label viejo sin filtrar por padre y sale sin hacer nada si
     * no lo encuentra: en una base ya movida —o recién sembrada— el `item()`
     * que sigue la resuelve por el label nuevo, así que correr el seeder dos
     * veces no duplica el ítem ni deja el viejo colgando. Se guarda con
     * `save()`, no con un `update()` masivo, para que la mutación pase por el
     * observer de bitácora (invariante 9).
     */
    private function mover(string $labelViejo, string $labelNuevo, SecMenu $padre, int $orden): void
    {
        $fila = SecMenu::query()->where('label', $labelViejo)->first();

        if ($fila === null) {
            return;
        }

        $fila->label = $labelNuevo;
        $fila->padre_id = $padre->id;
        $fila->orden = $orden;
        $fila->save();
    }

    private function migrar(string $labelViejo, string $labelNuevo, SecMenu $padre, string $icono, int $orden): void
    {
        $fila = SecMenu::query()
            ->whereNull('padre_id')
            ->where('label', $labelViejo)
            ->first();

        if ($fila === null) {
            return;
        }

        $fila->label = $labelNuevo;
        $fila->padre_id = $padre->id;
        $fila->icono = $icono;
        $fila->orden = $orden;
        $fila->save();
    }

    /**
     * `firstOrCreate` para la fila; además, si ya existía como "botón sin
     * link" (pantalla todavía no construida cuando se sembró por primera
     * vez) y esta vuelta SÍ trae `ruta`/`codigoPermiso`, completa esos dos
     * campos — nunca pisa un valor que ya estaba seteado (ni por una vuelta
     * anterior del seeder ni por una edición manual). Necesario porque el
     * catálogo de ítems se siembra completo desde el principio (quinta
     * vuelta) con placeholders para las pantallas que todavía no existían;
     * activarlas más tarde no puede depender de truncar `sec_menu` — los
     * datos demo no se borran.
     */
    private function item(
        SecMenu $padre,
        string $claveModulo,
        string $claveItem,
        string $icono,
        int $orden,
        ?string $ruta = null,
        ?string $codigoPermiso = null,
        bool $requierePersona = false,
    ): SecMenu {
        $permissionId = $codigoPermiso === null
            ? null
            : SecPermission::query()->where('code', $codigoPermiso)->value('id');

        $fila = SecMenu::query()->firstOrCreate(
            ['label' => "menu.{$claveModulo}.items.{$claveItem}", 'padre_id' => $padre->id],
            [
                'icono' => $icono,
                'ruta' => $ruta,
                'orden' => $orden,
                'permission_id' => $permissionId,
                'requiere_persona' => $requierePersona,
            ],
        );

        $activaRuta = $fila->ruta === null && $ruta !== null;
        $activaPermiso = $fila->permission_id === null && $permissionId !== null;
        // A diferencia de `ruta`/`permission_id`, estas dos SÍ se sincronizan
        // siempre: no son placeholders que el catálogo va completando, son
        // propiedades de la pantalla que el seeder define — una base ya
        // sembrada tiene que recibir el cambio sin que haya que truncar
        // `sec_menu` (los datos demo no se borran).
        //
        // `orden` se sumó a esa regla al insertar "Roles y permisos" en medio
        // del grupo Seguridad: sin sincronizarlo, un ítem nuevo solo podía ir
        // al final de su grupo, porque los ya sembrados conservaban su número
        // para siempre. Este seeder es la única fuente del orden del menú (no
        // hay pantalla que lo reordene), así que pisarlo es correcto y no
        // descarta ninguna edición de nadie.
        //
        // `icono` se sumó el 17/9/2026 (cambio de "Orden de Trabajo" de
        // fact_check a work_history): sin sincronizarlo, cambiar el ícono acá
        // no se reflejaba en una base ya sembrada — mismo motivo que `orden`.
        $ajustaRequisito = $fila->requiere_persona !== $requierePersona;
        $ajustaOrden = $fila->orden !== $orden;
        $ajustaIcono = $fila->icono !== $icono;

        if ($activaRuta || $activaPermiso || $ajustaRequisito || $ajustaOrden || $ajustaIcono) {
            $fila->ruta ??= $ruta;
            $fila->permission_id ??= $permissionId;
            $fila->requiere_persona = $requierePersona;
            $fila->orden = $orden;
            $fila->icono = $icono;
            $fila->save();
        }

        return $fila;
    }
}
