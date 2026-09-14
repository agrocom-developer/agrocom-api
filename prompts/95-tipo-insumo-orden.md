<!-- ciclo: critica=si turno-noche=1 rama=feature/tipo-insumo-orden etapas=4 descongela=tests -->

# Tarea 95 — HU-79: tipo sólido/líquido en la orden de aplicación

## Prerrequisito obligatorio — comprobalo ANTES de escribir código

Esta tarea depende de que **HU-70 (tarea 85, rama `feature/asignacion-equipos`)**
y **HU-78 (tarea 94, módulo `Mezclas`, rama `feature/mezclas-caldo`)** estén
integradas a `develop` — HU-79 comparte tabla (`ope_ordenes_aplicacion`) con
la primera y catálogo de productos con la segunda, y no tiene sentido iterar
dos veces sobre lo mismo.

Al escribir este prompt (14/9/2026), HU-70 seguía **sin integrar**: su PR
#189 estaba abierto en modo borrador, sin mergear, esperando la revisión
línea por línea de una persona (es crítica: toca el motor de sync y la
máquina de estados de `Trabajo`). Ver la nota de deuda técnica del
14/9/2026 en `docs/gestion/cola_tareas.md` para el detalle de por qué quedó
así.

Antes de tocar nada, corré:

```
git fetch origin
git log origin/develop --oneline | grep -i "asignacion-equipos\|HU-70" | head -5
gh pr view 189 --json state,mergedAt   # o el número que corresponda si cambió
```

y lo mismo para la rama/PR de la tarea 94. **Si cualquiera de las dos sigue
sin mergear a `develop`**, no implementes nada: escribí `runs/95.estado =
BLOQUEADA`, y en `runs/95.md` explicá cuál falta y por qué (el PR sigue
abierto/en borrador, número de PR, motivo). No es tu decisión resolver el PR
trabado — eso es del usuario. El ciclo la saltea y sigue con la próxima
tarea de la cola sin que esto corte el bucle.

Si las dos están integradas, seguí con lo de abajo.

## Qué hacer

Como **encargado**, distinguir si una orden de aplicación es de producto
sólido (kilos por vuelo — fertilizante, semilla de pasto) o líquido (litros
por hectárea — insecticida/herbicida/fungicida/fertilizante
líquido/coadyuvante/antiespumante), para que la orden pida los datos
correctos según el insumo.

Cargá los skills `verificacion` y `dominio-backend` antes de tocar código.

1. **Migración `ALTER`** sobre `ope_ordenes_aplicacion`: columna
   `tipo_insumo` `string(10)` `NOT NULL DEFAULT 'liquido'` — mismo criterio
   que `tipo_aplicacion` (tarea 70): no hay nada que heredar, toda orden
   tiene un tipo de insumo aunque nadie lo haya elegido a propósito. `CHECK`
   solo pgsql (`solido`/`liquido`), mismo molde que
   `ope_ordenes_aplicacion_tipo_aplicacion_chk`. Si el insumo es sólido, la
   orden necesita kilos por vuelo en vez de litros por hectárea — decidí si
   eso es una columna nueva (`kilos_por_vuelo`, nullable, con
   `required_if:tipo_insumo,solido` en el Request) o si reusás `litros_ha`
   reinterpretada; la migración ya deja claro cuál elegiste.

2. **Enum de dominio** `TipoInsumo` en `Operaciones/Dominio/`, mismo
   criterio que `TipoAplicacion` (tarea 70).

3. **Validación cruzada con el catálogo de productos de Mezclas** (tarea
   94): un producto sólido no se puede cargar en una orden marcada líquida,
   y viceversa. Esto se valida donde el registro de mezcla se aplica contra
   la orden (motor de sync o caso de uso, según cómo haya quedado resuelta
   la tarea 94 — revisalo antes de decidir dónde enganchar la regla, no lo
   asumas).

4. **Request/vista** de la orden: campo `tipo_insumo` (select) +
   `kilos_por_vuelo` condicional al tipo. `lang/es/operaciones.php` suma las
   etiquetas.

## Cómo repartir las etapas

- **Etapa 1**: verificación de prerrequisitos (arriba) + migración + enum +
  modelo + Request.
- **Etapa 2**: validación cruzada con el catálogo de productos (rechazo de
  insumo sólido en orden líquida y viceversa) + su test.
- **Etapa 3**: vista del formulario de orden (tipo_insumo + kilos_por_vuelo
  condicional).
- **Etapa 4**: tests restantes + `./bin/verify` completo.

## Qué NO hacer

- No reabrir el alcance de HU-70 (asignación de equipos) ni tocar su lógica
  — esta tarea solo agrega una columna a una tabla que HU-70 ya usa.
- No anticipar `capacidad_kg` del dron (HU-81, Sprint 17, tarea 96 — todavía
  no le toca el turno): la validación de "cuánto puede llevar cada vuelo"
  queda para cuando esa tarea exista. No la simules ni la bloquees por eso.
- No avanzar con código si el prerrequisito de arriba no se cumple — es la
  tentación previsible acá: la fila de la cola ya trae el criterio escrito y
  es fácil asumir que "ya está integrado" sin comprobarlo.

## Criterio de aceptación

`./bin/verify` = 0, con:
- Test de que una orden sólida pide kilos por vuelo y una líquida litros por
  hectárea.
- Test de que un insumo sólido no se puede cargar en una orden marcada
  líquida (rechazo), y viceversa.

## Cierre de cada etapa

`runs/95.estado` con una palabra (`PARCIAL`/`OK`/`BLOQUEADA`). `runs/95.md`
con qué se hizo y qué falta. Al llegar a `OK`, `runs/95.pr.md` con título +
cuerpo.

Commits agrupados por función, en español, imperativo. Sin `Co-Authored-By`.
