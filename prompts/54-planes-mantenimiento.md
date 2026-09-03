<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/planes-mantenimiento etapas=4 -->

# Tarea 54 — HU-38: planes de mantenimiento preventivo por horas de vuelo

## Por qué esta tarea

`plan_sprints.md` Sprint 11 (§236): "Como encargado, quiero planes de
mantenimiento preventivo por horas de vuelo, para que el sistema me
avise antes de la falla." CA esencial: plan por modelo de dron; alerta
cuando el acumulado de horas cruza el umbral.

Cierra Sprint 11 junto con las tareas 50-53, ya integradas o en curso.
**No depende de la tarea 53 (HU-37, órdenes)** — no hace falta que
exista `man_ordenes_mantenimiento` para calcular la alerta. Va después
en la cola solo porque sigue el orden literal de `plan_sprints.md`, no
por una dependencia de dato real.

**No es crítica**: ABM + una lectura cross-módulo de solo consulta, sin
dinero ni transición de estado gobernada (mismo criterio que la tarea 51,
baterías).

## De dónde salen las "horas de vuelo acumuladas" — no existe la columna, se derivan

`docs/gestion/cola_tareas.md` dejó esto pendiente de decidir "con el
módulo `Mantenimiento` ya en `develop`, para no adivinar la forma del
dato" — ya está: `ope_sesiones` (desde la tarea 09, con `dron_id` desde
la tarea 20) tiene `dron_id`, `inicio` y `fin` (`dateTime`, `fin` nulo
mientras la sesión sigue abierta). Las horas de vuelo de un dron son la
suma de `fin - inicio` de sus sesiones **cerradas** — recalculable
siempre desde los registros de origen, mismo espíritu que la invariante
6 de `CLAUDE.md` aunque esa invariante hable literalmente de plata y
hectáreas. **No agregues una columna `horas_vuelo` a `ope_drones`**: se
desincronizaría del dato real apenas alguien la editara a mano.

Calculá la suma **en PHP con Carbon** (`$fin->diffInSeconds($inicio)`,
sumado y dividido por 3600), no con aritmética de fechas en SQL cruda:
Postgres y SQLite (motor de los tests) difieren en sus funciones de
fecha, y varias migraciones de este repo ya dejaron nota de evitar SQL
específico de un solo driver para esto (buscá "Solo pgsql" en
`database/migrations/` si querés ver el patrón).

`man_planes_mantenimiento.modelo` correlaciona por **igualdad de texto**
contra `ope_drones.modelo` (columna `string(40)` nullable, sin catálogo
cerrado — ver `2026_09_02_100004_add_modelo_capacidad_a_ope_drones_table.php`),
no por FK: mismo criterio exacto que `man_baterias.identificador` contra
`ope_recargas.bateria_saliente_id` (tarea 51) — documentalo igual de
explícito en el docblock de la migración.

### Contrato de lectura nuevo en `Operaciones`

`Operaciones/Contratos/LecturaHorasVueloPorModelo` (mirá
`LecturaAlertasTemperaturaBateria` como plantilla exacta de forma —
interfaz chica, un método, doc explicando la frontera):

```php
interface LecturaHorasVueloPorModelo
{
    /**
     * Horas de vuelo acumuladas de cada dron (no borrado) cuyo `modelo`
     * coincide, como texto exacto, con $modelo — solo sesiones cerradas
     * (`fin` no nulo). Indexado por `dron_id`. Vacío si ningún dron tiene
     * ese modelo o ninguno tiene sesiones cerradas.
     *
     * @return array<int, float>
     */
    public function horasAcumuladasPorModelo(string $modelo): array;
}
```

Implementación en `Operaciones/Infraestructura/`, bindeada en
`OperacionesServiceProvider` (mismo patrón que
`LecturaAlertasTemperaturaBateria`/`...Eloquent`), consumida desde
`Mantenimiento` por la interfaz — nunca por el modelo Eloquent `Sesion`
ni `Dron` de `Operaciones`.

## Modelo de datos

`man_planes_mantenimiento`: `id, modelo` (`string(40)`, texto libre, ver
arriba), `tarea` (`string` o `text`, descripción de la tarea preventiva
— p. ej. "cambio de hélices"), `horas_umbral` (`decimal` o `unsignedInteger`,
`CHECK > 0`), auditoría, soft delete.

**Recortes frente al modelo completo de la especificación** (§4.5,
`planes_mantenimiento`), documentalos en el docblock:

- Sin `intervalo_unidad` (horas / km / hectáreas / días): el CA esencial
  solo pide horas de vuelo de dron. No generalices a km/hectáreas/días
  todavía — no hay HU que lo necesite (vehículos no llevan odómetro en
  este alcance, tarea 50).
- Sin `equipo_tipo` genérico (dron/vehículo/generador): esta tabla es
  solo de drones por ahora. Si más adelante se necesitan planes de
  vehículo o generador, es una HU nueva, no una ampliación silenciosa de
  esta.
- Sin `repuestos_previstos`: no aporta al CA esencial y depende de
  `Inventario` con una relación N:M que esta HU no pide.

## Caso de uso — alerta calculada al leer, sin tabla de alertas ni orden automática

`Mantenimiento/Aplicacion/ListarPlanesMantenimiento` (paginado): por
cada plan, llamá al contrato con `plan->modelo`, y marcá `alerta = true`
si **algún** dron de ese modelo tiene horas acumuladas `>= horas_umbral`
— mismo criterio de "alerta calculada en el listado, sin tabla propia"
que `ListarBaterias` (tarea 51). CRUD estándar
(`CrearPlanMantenimiento`, `ActualizarPlanMantenimiento`,
`EliminarPlanMantenimiento`) sin nada especial.

**No abras una orden de mantenimiento automáticamente** cuando se cruza
el umbral, aunque la especificación completa (§12) lo describa así
("el sistema avisa... y abre la orden"): el CA esencial de
`plan_sprints.md` es solo la alerta. Abrir la orden real queda como
acción manual del encargado en la pantalla de la tarea 53, ya
integrada — no la automatices por tu cuenta, es ampliar alcance sin que
el CA esencial lo pida.

## HTTP, permisos, menú

`PlanesMantenimientoController@index/create/store/edit/update/destroy`.
Permisos `mantenimiento.plan.ver/.crear/.editar/.eliminar` en
`PERMISOS_ENCARGADO_OPERACIONES`. El ítem `planes` ya está sembrado como
"botón sin link" bajo el grupo de menú `mantenimiento` en
`SecMenuSeeder.php` — activalo, no crees uno nuevo.

## Qué NO hacer

- No agregues `horas_vuelo` como columna persistida en `ope_drones` ni
  en ningún otro lado — es siempre derivada.
- No uses funciones de fecha específicas de Postgres en una migración o
  query que corra también contra SQLite.
- No conviertas `modelo` en FK real ni le agregues un catálogo cerrado.
- No dispares la apertura automática de una orden de mantenimiento.
- No toques `man_ordenes_mantenimiento` (tarea 53) más que para leerla
  si decidís mostrar, a título informativo, si un plan ya tiene una
  orden abierta — no es parte del CA esencial, no es obligatorio.

## Cómo repartir las etapas

- **Etapa 1**: contrato `LecturaHorasVueloPorModelo` + su implementación
  en `Operaciones`, migración `man_planes_mantenimiento`, modelo
  Eloquent.
- **Etapa 2**: casos de uso (`ListarPlanesMantenimiento` con la alerta
  calculada, CRUD).
- **Etapa 3**: controller, rutas, permisos, menú, vistas + copy.
- **Etapa 4**: tests Feature + spec visual + checklist §8.

## Criterio de aceptación

- `./bin/verify` = 0, con Playwright.
- Test Feature (`tests/Feature/Mantenimiento/PlanesMantenimientoPanelTest.php`)
  cubriendo: alta y edición de plan válidas; alerta activada cuando un
  dron de ese modelo tiene sesiones cerradas cuya suma de horas cruza
  `horas_umbral` (armá las sesiones con `inicio`/`fin` conocidos para que
  la suma sea exacta y verificable); sin alerta por debajo del umbral;
  sin alerta si ningún dron tiene ese modelo; bitácora completa; soft
  delete real; 403 sin permiso; ítem de menú publicado y gateado.
- Spec visual, claro y oscuro.
- Checklist de `docs/diseno/guia_pantalla_panel.md` §8 pasa.
- `tests/Unit/ArquitecturaModulosTest.php` sigue en verde (el contrato de
  lectura nuevo no rompe el aislamiento entre módulos).

## Puede tocar

`app/Dominios/Mantenimiento/**`, `app/Dominios/Operaciones/Contratos/**`
(el contrato de lectura nuevo), `app/Dominios/Operaciones/Infraestructura/**`
(su implementación y el binding en `OperacionesServiceProvider`),
migración nueva, `routes/web.php`,
`database/seeders/Catalogo/SeguridadSeeder.php`,
`database/seeders/Catalogo/SecMenuSeeder.php`,
`lang/es/mantenimiento.php`, `tests/**`.

Fuera de alcance: `ope_drones`, `ope_sesiones` (ninguna migración),
`man_ordenes_mantenimiento`, `Inventario`.

---

**Nota de la planificación**: última tarea escrita de esta tanda. Con
esta integrada, Sprint 11 queda cerrado (HU-36 a HU-40 completas). La
siguiente vuelta de planificación abre Sprint 12 (HU-41 a HU-44, TE-13,
TE-14) — la más grande es HU-41 (portal del cliente, invariante 5, con
su propio test obligatorio de scoping cruzado).
