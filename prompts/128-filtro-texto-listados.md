<!-- ciclo: critica=no turno-noche=1 rama=feature/filtro-texto-listados etapas=2 descongela=tests -->

# Tarea 128 — `?q[]=x` ya no da 500 en ningún listado

Deuda anotada por las tareas 121 y 122 (`docs/gestion/cola_tareas.md`, «Pendiente
al 20/9/2026»; medición en `runs/122-sondas.cjs` y `runs/123-sondas.log`) y
convertida en tarea por el dueño el 22/9/2026: `$request->string('q')` con un
arreglo (`?q[]=x`) lanza y responde 500 en Clientes, Personas, Bases, Contratos,
Cultivos, Repuestos, Campañas, Lotes y, según la 121, unos 22 controladores más
con el mismo patrón. Usuarios y Dispositivos ya lo arreglaron (tarea 121, PR
#265): ese es el patrón a seguir, no uno nuevo.

Cargá los skills `verificacion` y `dominio-backend`.

## Qué hacer

1. Medí primero: `grep -rn "string('q')\|string(\"q\")\|->string('estado')" app/Dominios --include='*Controller.php'`
   y todo filtro que lea un parámetro de query con `string()`/`input()` y lo use
   como texto. Anotá la lista en `runs/128.md`.
2. Una sola pieza de plataforma, no 22 copias: mirá cómo lo resolvió la 121 en
   `UsuariosController` y `DispositivosController` y extraé eso a un helper de
   `app/Dominios/Plataforma/` (por ejemplo `Infraestructura/Http/TextoDeFiltro`:
   `de(Request $request, string $clave, int $largoMaximo = 200): string` que
   devuelve `''` ante un arreglo, `null` o un valor no escalar, y recorta). Los
   dos controladores de la 121 pasan a usarlo también.
3. Aplicalo en todos los listados encontrados, sin cambiar la consulta que
   arman (solo cómo leen el parámetro). Los filtros de `estado`/enum siguen
   siendo lista blanca; si alguno acepta un arreglo hoy y da 500, mismo helper.
4. Test en `tests/Unit` del helper (arreglo → `''`, texto → texto recortado,
   ausente → `''`).
5. Sondas: copiá `runs/122-sondas.cjs` a `runs/128-sondas.cjs` y ampliala a
   **todos** los `index` del panel (la lista de rutas sale de
   `php artisan route:list --path=panel --method=GET`), con `?q[]=x` y
   `?estado[]=x`: todo tiene que responder 200 (o 302 al login si el rol no
   ve esa pantalla, nunca 500).

## Qué NO hacer

No toques casos de uso ni consultas; no agregues validación de formulario que
cambie mensajes visibles. No renombres parámetros de query (`q`, `estado`):
los enlaces guardados y el memento los usan.

## Criterio de aceptación

- `./bin/verify` = 0.
- `runs/128-sondas.log`: 0 respuestas 500 en todos los `index` del panel con
  `?q[]=x` y `?estado[]=x`.
- `grep -rn "string('q')" app/Dominios --include='*Controller.php'` vacío.

## Cierre obligatorio de cada etapa

`runs/128.estado`, `runs/128.md`, y al `OK` `runs/128.pr.md`. Commits por
función, en español, imperativo, sin `Co-Authored-By`.
