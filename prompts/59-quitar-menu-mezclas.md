<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/quitar-menu-mezclas etapas=2 -->

# Tarea 59 — TE-13: quitar del menú el ítem `Operación › Mezclas`

## Por qué esta tarea

`plan_sprints.md` Sprint 12 (§254): "Quitar del menú el ítem `Operación ›
Mezclas`: no existe más por CR-01." Criterio: el ítem desaparece de
`SecMenuSeeder` y del árbol sembrado; ningún test lo referencia.

CR-01 (ya cerrada, ver `cola_tareas.md` "Fuera del ciclo automático": "HU-11,
HU-12... eliminadas del plan") decidió que Agrocom no prepara la mezcla ni
dosifica — el dato no existe y no va a existir. El ítem de menú
`operacion.mezclas` quedó sembrado como placeholder (`SecMenuSeeder.php`,
`$this->item($operacion, 'operacion', 'mezclas', 'science', 6);`, ícono
`science`, sin `ruta`) desde antes de que CR-01 se cerrara. Es la única tarea
de todo Sprint 12 que resta código en vez de agregar pantalla: chica y sin
dependencias, así que no hace falta que espere a las demás.

No es crítica: es un borrado de un placeholder sin lógica de negocio detrás.

## Qué hacer

Cargá las skills `dominio-backend` y `verificacion`.

1. Quitá la línea `$this->item($operacion, 'operacion', 'mezclas', ...)` de
   `database/seeders/Catalogo/SecMenuSeeder.php`.
2. Quitá la clave `'mezclas' => 'Mezclas'` de `lang/es/menu.php` (línea 34) y
   revisá la línea 27 (`descripcion` del módulo `operacion`, "Órdenes,
   trabajos, sesiones y mezclas de cada jornada de vuelo.") — ajustá el texto
   para que no siga nombrando algo que ya no existe.
3. El seeder es idempotente por `(label, padre_id)` (ver docblock de la
   clase): una fila ya sembrada en una base existente (dev/staging) con
   `label = 'menu.operacion.items.mezclas'` **no se borra sola** al sacar la
   línea del seeder — un `Seeder::run()` nunca hace `DELETE` de lo que ya no
   siembra. Agregá el `DELETE`/soft-delete explícito de esa fila (por
   `label`) al principio de `run()`, con un comentario corto de por qué
   (mismo criterio que la "migración del catálogo plano anterior" que ya
   hace este seeder al principio de `run()` — es borrado de catálogo, no de
   dato de negocio, así que un `delete()` físico sobre `sec_menu` es
   razonable si no hay ninguna FK apuntándole; verificalo antes).
4. Test que falle si el ítem existe: consultá el árbol de menú sembrado (o
   `sec_menu` directo) y confirmá que ningún registro tiene
   `label = 'menu.operacion.items.mezclas'`.

## Qué NO hacer

- No toques ningún otro ítem del menú ni reordenes las posiciones de los
  hermanos (`pausas` en 5, `evidencias` en 7) — dejalos con su número tal
  cual, aunque quede un hueco en la secuencia; renumerar es blast radius
  innecesario sobre algo que no pidió esta tarea.
- No reabras CR-01 ni HU-11/HU-12 — están eliminadas del plan, esta tarea
  solo limpia el residuo de menú que quedó de antes de esa decisión.
- No toques `docs/decisiones/**` (zona congelada, sin `descongela=decisiones`
  en esta tarea) — CR-01 ya está documentada donde corresponde.

## Criterio de aceptación

- `./bin/verify` = 0.
- Test que confirma que `sec_menu` no tiene ninguna fila con
  `label = 'menu.operacion.items.mezclas'` después de correr el seeder (ni
  en una base limpia ni reseedeando sobre una que ya tenía la fila vieja).

## Puede tocar

`database/seeders/Catalogo/SecMenuSeeder.php`, `lang/es/menu.php`,
`tests/**`.
