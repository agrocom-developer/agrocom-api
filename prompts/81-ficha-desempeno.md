<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/ficha-desempeno etapas=3 -->

# Tarea 81 — HU-58: ficha de desempeño de una persona

## Por qué esta tarea

El modelo ya sabe **quién aplicó qué, dónde y para qué cliente**, pero nadie lo
puede ver. La cadena existe entera y es de FKs reales, no de deducciones:

```
per_personas ←── ope_sesiones.piloto_id / auxiliar_id
                        │ trabajo_id
                 ope_trabajos ──→ com_lotes ──→ com_campos ──→ com_clientes
                        │ orden_id
             ope_ordenes_aplicacion ──→ com_contratos ──→ cpn_campanias
```

Hoy el único lugar donde aparece el nombre del piloto es el reporte técnico de
un lote (`ArmarContenidoReporteTecnico`). No hay forma de responder *"¿qué hizo
esta persona esta campaña?"*, que es lo que el dueño pidió el 8/9/2026: cuando
una aplicación sale mal, poder mirar quién la voló y decidir a futuro si se lo
vuelve a contratar.

**Por la sesión, no por el equipo.** El equipo de trabajo (ADR 0015 punto 3) es
una formación administrativa y su pertenencia **no es exclusiva**: una persona
puede integrar varios equipos a la vez porque se presta gente entre cuadrillas.
Atribuir una falla al equipo le carga la culpa a quien ese día estaba en otra
propiedad. La sesión dice exactamente quién voló ese lote, a qué hora y con qué
dron, y ya está firmada por el piloto y validada por el jefe de campo.

## Lo que ya existe

- `ope_sesiones` — `trabajo_id`, `piloto_id`, `auxiliar_id` (nullable), `inicio`,
  `fin`, `hectareas_declaradas`, `hectarea_inicial_acumulada`, `dron_id`,
  `litros_consumidos`, estado.
- `ope_sesion_rechazos` — `anula_a_id` (la sesión anulada), `motivo`,
  `rechazado_por`. Es la invariante 2 en acción: la corrección es un registro
  nuevo, no un `UPDATE`.
- `ope_incidencias` — `sesion_id`, `tipo`, `descripcion`, `hora`,
  `evidencia_foto_id`.
- `ope_trabajos` — `orden_id`, `lote_id`, `hectareas_declaradas`.
- La ficha de persona del panel (`/panel/personas`, tarea 37) y su
  `PersonasController` en `Personal`.
- El patrón de contrato de lectura inverso: `ArmarContenidoReporteTecnico` ya
  pide a `Comercial` el nombre del cliente sin tocar sus modelos. Copialo.

## Qué hacer

1. **Contrato de lectura nuevo en `Operaciones`**:
   `Operaciones/Contratos/LecturaDesempenioPersona` (interfaz + DTO primitivo,
   ADR 0003 regla 2). Dada una persona y un rango de fechas, devuelve:
   - sus sesiones, con fecha, rol en esa sesión (piloto o auxiliar), lote,
     campo, cliente, campaña, dron, hectáreas y estado;
   - las que fueron **rechazadas**, con motivo y quién las rechazó;
   - sus **incidencias**, por tipo.
   La implementación vive en `Operaciones/Infraestructura`; `Personal` **nunca**
   toca tablas `ope_*` ni sus modelos Eloquent.
2. **Cliente y campaña** salen del contrato del trabajo
   (`orden → contrato → campania_id`), pedidos a `Comercial` por contrato de
   lectura. Es la ruta exacta: **no** deduzcas la campaña por fechas ni por el
   campo, que con varias campañas abiertas del mismo cliente es ambiguo (ADR
   0015 punto 1).
3. **Pantalla**: pestaña "Desempeño" en la ficha de persona, o
   `/panel/personas/{persona}/desempeno` si la ficha hoy no tiene pestañas — mirá
   qué hay antes de decidir y seguí el arquetipo de ficha ya existente.
   - Filtros: rango de fechas (default: los últimos 12 meses), cliente y
     campaña (la lista de campañas se carga al elegir cliente).
   - Totales arriba: hectáreas aplicadas, sesiones validadas, sesiones
     rechazadas, incidencias.
   - Tabla de sesiones, y una lista aparte con los rechazos y sus motivos.
   - Estado vacío ilustrado para la persona sin sesiones en ese rango.
4. **Permiso** `personal.persona.desempenio` en `SeguridadSeeder`, y `@puede`
   en la pestaña. No lo ve cualquiera: es información sensible sobre una
   persona.
5. **Traducciones** en `lang/es/personal.php`.

## Qué NO hacer

- **Ningún puntaje, ranking ni semáforo de "buen/mal piloto".** La pantalla
  muestra hechos verificables y sus fuentes; la decisión de a quién contratar es
  de una persona, no de un número que el sistema se inventó. Un promedio con
  colores parece objetivo y no lo es: castiga a quien voló los lotes difíciles.
- No agregues `equipo_trabajo_id` a `ope_sesiones` ni deduzcas el equipo de la
  sesión: con pertenencia múltiple no es deducible sin ambigüedad (ADR 0015
  punto 3).
- No sumes con `SUM()` de SQL: en SQLite (motor de los tests) la agregación pasa
  por REAL/float y violaría la invariante 6. `Brick\Math\BigDecimal` en PHP
  (`bcmath` no está instalado en este entorno).
- No expongas nada de esto en el portal del cliente.

## Cómo repartir las etapas

- **Etapa 1**: contrato de lectura + implementación + tests unitarios de la
  agregación (hectáreas exactas, una sesión rechazada que no suma).
- **Etapa 2**: pantalla, filtros, permiso, traducciones, tests Feature.
- **Etapa 3**: estado vacío, pulido visual según `docs/diseno/guia_pantalla_panel.md`,
  `bin/verify`.

## Criterio de aceptación

- `./bin/verify` = 0 (en esta Mac la etapa de Playwright se saltea sola: las
  capturas de referencia son `-win32`. No generes capturas `-darwin`).
- Test: una persona que voló en dos clientes distintos ve las dos, y filtrando
  por uno ve solo ese.
- Test: una sesión **rechazada** aparece en la lista de rechazos con su motivo y
  **no** suma en las hectáreas aplicadas.
- Test: el total de hectáreas es exacto con decimales (`BigDecimal`, sin float).
- Test: la campaña que se muestra es la del contrato de esa orden, incluso si el
  cliente tiene dos campañas abiertas cuyos rangos contienen la fecha del vuelo.
- Test: un usuario sin `personal.persona.desempenio` recibe 403.
- Test de fronteras: `tests/Unit/ArquitecturaModulosTest.php` sigue en verde —
  `Personal` no importa nada de `Operaciones\Infraestructura`.

## Puede tocar

`app/Dominios/Operaciones/**` (contrato de lectura + implementación),
`app/Dominios/Personal/**` (pantalla, controlador, vistas),
`app/Dominios/Comercial/**` (solo si hace falta extender un contrato de lectura
existente para traer cliente + campaña por contrato), `app/Dominios/Seguridad/**`
(seeder de permisos), `lang/es/**`, `routes/web.php`, `tests/**`.

Fuera de alcance: migraciones (esta tarea no crea ni cambia tablas), el motor de
sync, el portal del cliente.

## Cierre obligatorio de cada etapa

`runs/81.estado`, `runs/81.md`, y al `OK` `runs/81.pr.md`. Commits agrupados por
función, en español, imperativo, sin `Co-Authored-By`.
