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
 * (4.1), Recursos (4.2), Operación (4.3), Financiero (4.4), Mantenimiento e
 * inventario (4.5), Seguridad (4.6) — más Reportes (cap. 9/10).
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

        $operacion = $this->modulo('operacion', 'flight_takeoff', 1);
        $comercial = $this->modulo('comercial', 'handshake', 2);
        $recursos = $this->modulo('recursos', 'precision_manufacturing', 3);
        $mantenimiento = $this->modulo('mantenimiento', 'inventory_2', 4);
        $financiero = $this->modulo('financiero', 'payments', 5);
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
        // HU-25 (tarea 38): órdenes de aplicación con su propia máquina de
        // estados (emitida → vigente).
        $this->item($operacion, 'operacion', 'ordenes', 'assignment', 2, ruta: 'panel.ordenes.index', codigoPermiso: 'operaciones.orden.ver');

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
        // HU-51 (tarea 74): entrada y salida del equipo en cada hacienda. Se
        // mantiene como ítem PROPIO, separado de "Asignación de equipos"
        // (evaluado y descartado fusionarlos, 17/9/2026): el equipo se forma
        // una sola vez por campaña, pero la estadía es por propiedad — un
        // mismo equipo puede alojarse en varias propiedades de la misma
        // campaña, cardinalidad distinta a la de la asignación. Baja del
        // orden 6 al 5 en este refactor.
        $this->item($operacion, 'operacion', 'estadias', 'holiday_village', 5, ruta: 'panel.estadias.index', codigoPermiso: 'operaciones.estadia.ver');

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

        // Comercial (§4.1 + cap. 9)
        //
        // HU-46 (tarea 69, ADR 0015 punto 1) — reubicado el 9/9/2026 por
        // pedido del dueño, mirando el panel andando. El ítem había nacido
        // bajo Seguridad leyendo la campaña como "configuración de toda la
        // operación", y eso contradecía el propio ADR corregido el 8/9: la
        // campaña era del **cliente**. Agrocom es una empresa de servicio —
        // *"si fuera por nosotros daríamos servicio todo el año, no tuviéramos
        // que abrir campaña propia"*—: el cliente habilitaba su campaña, con
        // sus tiempos de riego y siembra, y recién ahí entraba la fumigación.
        // Por eso se agrupó con Clientes, Contratos y Propiedades, no con la
        // configuración de la casa.
        //
        // Corrección del 15/9/2026 (ADR 0015): esa lectura de "la campaña es
        // del cliente" quedó reemplazada — la campaña pasa a ser catálogo
        // compartido, sin dueño; el vínculo con el cliente vive en
        // `com_contratos`, no en `cpn_campanias`. Sigue agrupada bajo
        // Comercial (agrupación de layout, no una frontera de módulo — mismo
        // caso que `vehiculos`, que vive en `Mantenimiento` y se muestra bajo
        // Recursos, ADR 0011 extensión del 26/8/2026 punto 3), pero pasa a la
        // CABEZA del grupo: en el flujo de alta la campaña ya existe antes
        // que el contrato que la referencia. El permiso no cambia
        // (`campania.campania.ver`, módulo `Campania`). El resto del grupo
        // corre un lugar: Clientes 2, Contratos 3, Propiedades 4, Lotes 5,
        // Cultivos 6.
        $this->mover('menu.seguridad.items.campanias', 'menu.comercial.items.campanias', $comercial, 1);
        $this->item($comercial, 'comercial', 'campanias', 'calendar_month', 1, ruta: 'panel.campanias.index', codigoPermiso: 'campania.campania.ver');

        // HU-22 (tarea 33): alta y mantenimiento de clientes — activa el
        // ítem que ya estaba sembrado como "botón sin link" (ver docblock
        // de `item()`).
        $this->item($comercial, 'comercial', 'clientes', 'contact_page', 2, ruta: 'panel.clientes.index', codigoPermiso: 'comercial.cliente.ver');
        // HU-23 (tarea 34): administración de contratos — activa el ítem que
        // ya estaba sembrado como "botón sin link" (ver docblock de `item()`).
        $this->item($comercial, 'comercial', 'contratos', 'description', 3, ruta: 'panel.contratos.index', codigoPermiso: 'comercial.contrato.ver');
        // HU-24 (tarea 35): administración de campos y sus lotes — activa
        // el ítem que ya estaba sembrado como "botón sin link" (ver
        // docblock de `item()`).
        //
        // Tarea 77 (HU-54, pedido del dueño 7/9/2026): "Campos y lotes" se
        // separa en dos ítems. `renombrar()` conserva la fila y su id (la
        // propiedad sigue siendo la misma pantalla, mismo permiso); "Lotes"
        // es un ítem nuevo (etapa 1 lo sembró como "botón sin link" — la
        // pantalla llegó recién en la etapa 2, que es la que agrega
        // `ruta: 'panel.lotes.index'` acá; `item()` activa la ruta de una
        // instalación que ya tenía el ítem sembrado sin necesitar una
        // migración de datos nueva, ver su docblock).
        //
        // ADR 0018 (10/9/2026): "Propiedad" pasa a ser una entidad real,
        // distinta de "Campo" (antes eran la misma fila — ver el ADR, punto
        // 1, para el caso "Gamelera" que rompió ese supuesto). El vocabulario
        // que la tarea 77 le había dado a esta pantalla ("Propiedades", para
        // lo que hoy era `Campo`) quedó incorrecto un tiempo y se revertía con
        // un método dedicado — ver ADR 0020 justo abajo, que reemplaza esa
        // reversión: ahora el ítem legado se retira directamente.
        //
        // ADR 0020 (15/9/2026): `Campo` se elimina como entidad — `Lote`
        // cuelga directo de `Propiedad`, sin nivel intermedio. El ítem legado
        // "campos" (la MISMA fila de arriba, que pasó de "Campos y lotes" a
        // "Propiedades" y de vuelta a "Campos") ya no tiene pantalla
        // (`panel.campos.index` no existe) ni permiso vigente
        // (`comercial.campo.ver` se retira en `SeguridadSeeder`): se retira
        // del árbol con `retirarItemCampos()` (soft delete explícito, mismo
        // criterio que las bajas de catálogo al principio de este método).
        // "Propiedades" y "Lotes" quedan como ítems independientes: orden 4
        // y 5 desde la corrección del 15/9/2026 de "Campañas" de arriba.
        $this->retirarItemCampos($comercial);
        $this->item($comercial, 'comercial', 'propiedades', 'domain', 4, ruta: 'panel.propiedades.index', codigoPermiso: 'comercial.propiedad.ver');
        $this->item($comercial, 'comercial', 'lotes', 'grid_view', 5, ruta: 'panel.lotes.index', codigoPermiso: 'comercial.lote.ver');

        // HU-48 (tarea 71, ADR 0015 punto 4): catálogo de cultivos. Ítem
        // creado directo con ruta y permiso, no "botón sin link": el
        // catálogo no formaba parte de la siembra original de `sec_menu`
        // (ver docblock de `item()`). Último del grupo (orden 6) desde la
        // corrección del 15/9/2026 de "Campañas" de arriba.
        $this->item($comercial, 'comercial', 'cultivos', 'grass', 6, ruta: 'panel.cultivos.index', codigoPermiso: 'comercial.cultivo.ver');

        // Recursos (§4.2)
        // HU-27 (tarea 36): administración de la flota de drones — activa
        // el ítem que ya estaba sembrado como "botón sin link" (ver
        // docblock de `item()`).
        $this->item($recursos, 'recursos', 'drones', 'airplanemode_active', 1, ruta: 'panel.drones.index', codigoPermiso: 'operaciones.dron.ver');
        // HU-39 (tarea 51): seguimiento de baterías con ciclos y estado —
        // activa el ítem que ya estaba sembrado como "botón sin link" (ver
        // docblock de `item()`).
        $this->item($recursos, 'recursos', 'baterias', 'battery_charging_full', 2, ruta: 'panel.baterias.index', codigoPermiso: 'mantenimiento.bateria.ver');
        // HU-40 (tarea 50): administración de la flota de vehículos — activa
        // el ítem que ya estaba sembrado como "botón sin link" (ver
        // docblock de `item()`). El backend vive en `Mantenimiento`
        // (`man_vehiculos`) aunque el ítem quede agrupado bajo "Recursos" en
        // el sidebar: esa agrupación es solo layout (ADR 0011, extensión
        // 26/8/2026, punto 3), no una frontera de módulo.
        $this->item($recursos, 'recursos', 'vehiculos', 'local_shipping', 3, ruta: 'panel.vehiculos.index', codigoPermiso: 'mantenimiento.vehiculo.ver');

        // Tarea 72 (HU-49, ADR 0015 punto 3): catálogo de generadores. ABM
        // mínimo nuevo, sin placeholder previo — a diferencia de
        // drones/baterías/vehículos arriba (que activan un ítem ya sembrado
        // como "botón sin link"), acá se siembra completo desde el vamos. El
        // backend vive en `Mantenimiento` (`man_generadores`) aunque el ítem
        // quede agrupado bajo "Recursos": misma agrupación de layout que
        // `vehiculos` (ADR 0011, extensión 26/8/2026, punto 3).
        $this->item($recursos, 'recursos', 'generadores', 'bolt', 6, ruta: 'panel.generadores.index', codigoPermiso: 'mantenimiento.generador.ver');

        // Los equipos de trabajo (tarea 72, HU-49) ya no se siembran acá: el
        // ítem se movió a Operación como "Cuadrillas" (19/9/2026, ver arriba).

        // HU-82 (tarea 97): ficha de inventario del dron (serie, chasis,
        // versión de software, región, serie del control, accesorios). ABM
        // mínimo nuevo, sin placeholder previo, mismo criterio que
        // `generadores`/`equipos_trabajo` arriba. El backend vive en
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

        // Mantenimiento e inventario (§4.5)
        // HU-37 (tarea 53): órdenes de mantenimiento con su propia máquina de
        // estados (abierta → cerrada) — activa el ítem que ya estaba
        // sembrado como "botón sin link" (ver docblock de `item()`).
        $this->item($mantenimiento, 'mantenimiento', 'ordenes', 'build', 1, ruta: 'panel.ordenes-mantenimiento.index', codigoPermiso: 'mantenimiento.orden.ver');
        // HU-38 (tarea 54): planes de mantenimiento preventivo por horas de
        // vuelo — activa el ítem que ya estaba sembrado como "botón sin
        // link" (ver docblock de `item()`).
        $this->item($mantenimiento, 'mantenimiento', 'planes', 'checklist', 2, ruta: 'panel.planes-mantenimiento.index', codigoPermiso: 'mantenimiento.plan.ver');
        // HU-36 (tarea 52): catálogo de repuestos con stock por base y
        // alerta de mínimo — activa los dos ítems que ya estaban sembrados
        // como "botón sin link" (ver docblock de `item()`). El backend vive
        // en el módulo nuevo `Inventario` (`inv_*`) aunque el ítem quede
        // agrupado bajo "Mantenimiento" en el sidebar: esa agrupación es
        // solo layout (ADR 0011, extensión 3/9/2026, punto 15), no una
        // frontera de módulo — mismo criterio que `vehiculos`/`baterias`
        // bajo "Recursos" arriba.
        $this->item($mantenimiento, 'mantenimiento', 'repuestos', 'construction', 3, ruta: 'panel.repuestos.index', codigoPermiso: 'inventario.repuesto.ver');
        $this->item($mantenimiento, 'mantenimiento', 'stock', 'warehouse', 4, ruta: 'panel.stock.index', codigoPermiso: 'inventario.movimiento.ver');

        // Financiero (§4.4 + cap. 11)
        // HU-33 (tarea 47): "como encargado, quiero cargar gastos con su
        // categoría y comprobante" — activa el ítem que ya estaba sembrado
        // como "botón sin link" (ver docblock de `item()`).
        $this->item($financiero, 'financiero', 'gastos', 'receipt_long', 1, ruta: 'panel.gastos.index', codigoPermiso: 'finanzas.gasto.ver');
        // HU-35 (tarea 49): "como encargado, quiero registrar el
        // combustible del generador y de los vehículos, para imputarlo a la
        // campaña" — activa el ítem que ya estaba sembrado como "botón sin
        // link" (ver docblock de `item()`).
        $this->item($financiero, 'financiero', 'combustible', 'local_gas_station', 2, ruta: 'panel.combustible.index', codigoPermiso: 'finanzas.combustible.ver');
        // HU-34 (tarea 48): "como jefe de campo, quiero rendir los gastos que
        // hice en campo; el encargado los aprueba para reponer el fondo" —
        // activa el ítem que ya estaba sembrado como "botón sin link" (ver
        // docblock de `item()`). Icono y orden se mantienen tal cual estaban.
        $this->item($financiero, 'financiero', 'rendiciones', 'fact_check', 3, ruta: 'panel.rendiciones.index', codigoPermiso: 'finanzas.rendicion.ver');
        // HU-28 (tarea 40): "como piloto o auxiliar, quiero ver mis devengos
        // por período" — activa el ítem que ya estaba sembrado como "botón
        // sin link" (ver docblock de `item()`).
        // `requierePersona`: el permiso lo tienen `piloto`, `auxiliar` y
        // `dueno`, pero la pantalla es de «lo mío» — resuelve por PERSONA, no
        // por rol, y hace 404 si el usuario no tiene una vinculada. Único
        // ítem del catálogo con la bandera; ver el docblock de
        // `ObtenerMenuPorRolActivo`, sección `requiere_persona`.
        $this->item($financiero, 'financiero', 'devengos', 'request_quote', 4, ruta: 'panel.devengos.index', codigoPermiso: 'finanzas.devengo.ver', requierePersona: true);
        // HU-30 (tarea 44): "como dueño, quiero generar la planilla del
        // período desde los devengos y aprobarla" — activa el ítem que ya
        // estaba sembrado como "botón sin link" (ver docblock de `item()`).
        $this->item($financiero, 'financiero', 'planilla', 'event_note', 5, ruta: 'panel.planillas.index', codigoPermiso: 'finanzas.planilla.ver');
        // HU-31 (tarea 45): "como encargado, quiero emitir la factura de un
        // trabajo desde su acta conformada" — activa el ítem que ya estaba
        // sembrado como "botón sin link" (ver docblock de `item()`).
        $this->item($financiero, 'financiero', 'facturas', 'receipt', 6, ruta: 'panel.facturas.index', codigoPermiso: 'comercial.factura.ver');
        // HU-29 (tarea 41): "como encargado, quiero registrar anticipos
        // validando el tope" — a diferencia de los demás ítems de este
        // grupo, no había ítem "anticipos" sembrado como "botón sin link":
        // esta tarea lo crea y lo activa en el mismo paso. Orden 7 (al
        // final del grupo) para no reordenar los ítems ya sembrados.
        $this->item($financiero, 'financiero', 'anticipos', 'payments', 7, ruta: 'panel.anticipos.index', codigoPermiso: 'finanzas.anticipo.ver');

        // Reportes (cap. 9 y 10)
        // HU-43 (tarea 57): "como encargado, quiero listar y descargar los
        // reportes técnicos generados, para reenviarlos al agrónomo" —
        // activa el ítem que ya estaba sembrado como "botón sin link" (ver
        // docblock de `item()`). Mismo permiso `operaciones.reporte.ver` que
        // ya gatea la descarga individual (misma acción de negocio).
        $this->item($reportes, 'reportes', 'tecnicos', 'summarize', 1, ruta: 'panel.reportes.tecnicos.index', codigoPermiso: 'operaciones.reporte.ver');
        // HU-32 (tarea 46): "como dueño, quiero un reporte comercial de
        // avance por cliente, contrato y campaña" — activa el ítem que ya
        // estaba sembrado como "botón sin link" (ver docblock de `item()`).
        $this->item($reportes, 'reportes', 'comerciales', 'insert_chart', 2, ruta: 'panel.reportes.comercial.index', codigoPermiso: 'comercial.reporte.ver');
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
     * ADR 0020: `Campo` se elimina como entidad — `Lote` cuelga directo de
     * `Propiedad`, sin nivel intermedio. El ítem legado "campos" (que en su
     * momento supo llamarse "Campos y lotes", después "Propiedades" bajo la
     * tarea 77, y de vuelta a "Campos" con ADR 0018 — siempre la MISMA fila,
     * ver el historial en `run()` justo arriba de donde se llama a este
     * método) ya no tiene pantalla (`panel.campos.index` no existe) ni
     * permiso vigente (`comercial.campo.ver` se retira en `SeguridadSeeder`):
     * se retira del árbol en vez de renombrarlo.
     *
     * Filtra por `padre_id` + `ruta` (no por `label`, que solo puede haber
     * cambiado con el tiempo — mismo criterio que ya documentaba el método
     * que este reemplaza). `delete()` es soft (invariante 8 de CLAUDE.md:
     * `ModeloDominio` trae `SoftDeletes` de fábrica) y `SecMenu::query()` ya
     * excluye lo soft-deleteado, así que correr esto dos veces es no-op.
     */
    private function retirarItemCampos(SecMenu $padre): void
    {
        SecMenu::query()
            ->where('padre_id', $padre->id)
            ->where('ruta', 'panel.campos.index')
            ->delete();
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
