# ADR 0012 — Lecturas complejas: vistas SQL de PostgreSQL expuestas como modelos Eloquent de solo lectura

**Estado:** Aceptada.

## Contexto

Las tablas transaccionales se mantienen en 3FN (`docs/especificacion/especificacion_funcional_tecnica.md`, sección 4), pero el sistema tiene un lado de lectura pesado: reportes técnico/comercial/de avance, dashboard/estado de resultados y mapa de avance — el módulo Reportes, que es de solo lectura ("Sistema (lectura)", `docs/especificacion/insumos_modelo_datos.md`, sección 1) — y el Portal, que son vistas de solo lectura sobre Comercial/Operaciones/Reportes. Son consultas anidadas multi-tabla que cruzan módulos; armarlas hidratando grafos de modelos Eloquent en memoria paga un costo de objetos que nadie va a mutar, y desnormalizar para evitarlo rompería la 3FN.

A la vez, cualquier estrategia que acerque lógica a la base de datos abre una puerta peligrosa: SQL que escribe se salta la bitácora por observer (invariante 9 de `CLAUDE.md`) y los servicios de máquina de estados (invariante 7), y queda fuera de Pest/Larastan y del diff de los PR.

## Decisión

- **Las lecturas complejas** (reportes, tableros, consultas anidadas multi-tabla) **se resuelven con vistas SQL de PostgreSQL** — y vistas materializadas cuando el costo lo justifique, p. ej. dashboards — creadas en migraciones vía `DB::statement`, con nombre `vw_*` (ADR 0011), y expuestas como **modelos Eloquent de solo lectura**. Una vista puede leer tablas de varios módulos: la regla 1 del ADR 0003 prohíbe las escrituras cruzadas, no la lectura declarativa para reporting; el modelo de la vista vive en el módulo lector (típicamente `Reportes/` o `Portal/`).
- **Las lecturas simples sin necesidad de hidratar modelos** van por query builder (`DB::table()`).
- **Regla dura: toda escritura pasa por Eloquent y los servicios de dominio.** Nunca por procedimientos almacenados ni SQL directo de mutación — se saltarían los invariantes 7 y 9 y el tooling completo. Los procedimientos almacenados de escritura quedan **prohibidos**; las funciones de solo lectura se admiten solo con justificación explícita en el PR.

## Alternativas descartadas

- **Desnormalizar las tablas transaccionales** (columnas agregadas, tablas resumen mantenidas en escritura): rompe la 3FN y la regla de que todo monto derivado debe poder recalcularse desde los registros de origen (invariante 6).
- **Armar los reportes en PHP hidratando agregados Eloquent**: costo de memoria y latencia sin retorno — en un reporte nadie muta nada, no hay razón para pagar el modelo rico.
- **Procedimientos almacenados como capa de lectura/escritura**: la parte de escritura contradice los invariantes 7 y 9, y toda la lógica quedaría invisible para Pest, Larastan y la revisión por diff.

## Consecuencias

- Las vistas se versionan en migraciones (`DB::statement` con `CREATE OR REPLACE VIEW` o `DROP`+`CREATE`); todo cambio de vista es una migración nueva y aparece en el diff del PR. El SQL crudo debe interpolar `DB::getTablePrefix()` por el prefijo global opcional (ADR 0011).
- Los modelos de vista son de solo lectura con guarda común (clase base o trait en `Compartido/`) que lance excepción ante cualquier intento de escritura — la protección no depende de la disciplina de cada modelo (mismo criterio que ADR 0007).
- Las vistas no pasan por los global scopes de Eloquent: cada definición debe excluir explícitamente los registros con `deleted_at` poblado de sus tablas base (ADR 0007), salvo las vistas de auditoría/historial, que los incluyen a propósito.
- Cada vista materializada define su estrategia de refresco (scheduler o comando tras eventos de dominio) y el desfase tolerable en el PR que la crea — un dashboard puede mostrar datos con retraso; un saldo, no.
- El scoping del portal no cambia: aun sirviendo desde vistas, todo endpoint de `/api/portal/*` consulta desde el `contrato` del usuario autenticado y lleva su test cliente A → recurso de cliente B → 404 (invariante 5).
