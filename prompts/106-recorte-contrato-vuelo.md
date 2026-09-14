<!-- ciclo: critica=no turno-noche=1 rama=feature/recorte-contrato-vuelo etapas=2 descongela=tests -->

# Tarea 106 — HU-91: Contrato sin `adelanto_pct` ni "Parámetros de vuelo"

## Qué hacer

Como **encargado**, dejar de pedir en el contrato un campo que nunca se
pidió (`adelanto_pct`) y toda la sección "Parámetros de vuelo" (7 campos):
esos límites pasan a heredar siempre de la Orden o del valor por defecto
del sistema (RF-60), nunca del contrato. Confirmado con el dueño el
14/9/2026 — ver `docs/negocio/observaciones_operaciones_comercial_2026-09-14.md`
§1 y §2. `com_contrato_ventanas` **no se toca**: quedó confirmado que la
mención a "ventana de aplicación" en el documento original era la misma
instrucción de sacar parámetros de vuelo, no una reubicación.

Cargá los skills `verificacion`, `dominio-backend` y `modelo-datos` antes de
tocar código.

1. **Migración `ALTER`** sobre `com_contratos`: `dropColumn` de
   `adelanto_pct`, `viento_max_kmh`, `temperatura_max_c`, `humedad_min_pct`,
   `humedad_max_pct`, `velocidad_max_kmh`, `umbral_reporte_avance_ha`,
   `altura_vuelo_m`. Antes de dropear las columnas (solo pgsql), `DROP
   CONSTRAINT` de cada `CHECK` asociado — nombres exactos en
   `database/migrations/2026_08_26_100003_create_com_contratos_table.php:64-89`:
   `com_contratos_adelanto_pct_chk`, `com_contratos_viento_max_chk`,
   `com_contratos_temperatura_max_chk`, `com_contratos_humedad_min_chk`,
   `com_contratos_humedad_max_chk`, `com_contratos_humedad_rango_chk`,
   `com_contratos_velocidad_max_chk`, `com_contratos_umbral_reporte_chk`,
   `com_contratos_altura_vuelo_chk`. No migres el valor de `adelanto_pct` a
   ningún otro lado: el dueño confirmó que ese campo nunca estuvo pedido, no
   hay nada que preservar.

2. **`Contrato` (Eloquent)**: sacá esas 8 columnas de `$fillable`,
   `casts()` y el docblock `@property`
   (`app/Dominios/Comercial/Infraestructura/Eloquent/Contrato.php`).

3. **`CrearContratoRequest`/`ActualizarContratoRequest`**: sacá las 8
   reglas correspondientes; en `CrearContratoRequest::withValidator()`
   (líneas 76-86), el validador cruzado de `humedad_min_pct`/`humedad_max_pct`
   deja de tener sentido — sacalo también.

4. **`_formulario.blade.php`** de contratos: sacá el campo `adelanto_pct`
   (líneas 157-166) y la sección completa `x-molecules.form-section
   :title="__('comercial.contratos.seccion_clima')"` (líneas 185-263,
   incluida su ayuda). Bajá el `campos_contador` de la primera sección de 9
   a 8 (línea 87 — perdió `adelanto_pct`).

5. **`lang/es/comercial.php`**: relabeleá `campo_adelanto_monto` de
   "Adelanto (monto)" a **"Adelanto Solicitado"** (mismo criterio de
   adopción de label sin migración que ya se usó con "Monto Estimado" para
   `monto_total`). Eliminá `campo_adelanto_pct`, `seccion_clima`,
   `seccion_clima_ayuda` y los 7 `campo_*` de clima/vuelo. Si
   `error_humedad_rango` queda sin ningún uso después de esto, eliminala
   también (comprobalo con grep antes de borrar).

6. **Demo**: `database/seeders/Demo/NucleoComercialSeeder.php` y
   `database/seeders/Demo/CarteraClientesDemoSeeder.php` siembran contratos
   con esas 8 claves — sacalas de los arrays de creación.

7. **Tests existentes a ajustar** (no son el foco de la tarea, pero dejan de
   compilar/pasar si no los tocás):
   - `tests/Feature/EsquemaNucleoComercialTest.php:35-37` — el test
     `com_contratos tiene la altura de vuelo pactada por contrato` pasa a
     comprobar que la columna **no** existe (o se elimina si ya no aporta
     nada, a tu criterio).
   - `tests/Feature/Comercial/GestionContratosPanelTest.php` — sacá el test
     de validación de `altura_vuelo_m` (líneas 180-188) y el assert de
     persistencia de `altura_vuelo_m` (línea 205); revisá cualquier payload
     de alta/edición de contrato del archivo que siga incluyendo alguna de
     las 8 claves eliminadas.

## Cómo repartir las etapas

- **Etapa 1**: migración + modelo + Requests + vista + lang.
- **Etapa 2**: demo + tests + `./bin/verify` completo.

## Qué NO hacer

- No toques `com_contrato_ventanas` ni su lógica de solapamiento — confirmado
  con el dueño que no se reubica ni cambia de cardinalidad.
- No toques `ope_ordenes_aplicacion`: esa tabla tiene sus PROPIAS columnas de
  clima/vuelo a nivel de orden (son otra cosa, ya existen, quedan igual —
  son justamente el nivel que ahora manda siempre).
- No cambies la fórmula de `monto_total` — ninguno de los 8 campos
  eliminados participaba de ese cálculo (`hectareas_contratadas ×
  aplicaciones_previstas × precio_ha`, sin cambios).
- No inventes una migración de datos para preservar `adelanto_pct`: el
  dueño confirmó que nunca estuvo pedido.

## Criterio de aceptación

`./bin/verify` = 0, con:
- Test de que `adelanto_pct` ya no existe como columna ni como campo del
  formulario.
- Test de regresión de que `monto_total` se calcula igual que antes (no
  depende de ningún campo eliminado).
- Test de que crear/editar un contrato sin los 7 campos de clima/vuelo
  sigue validando correcto.

## Cierre de cada etapa

`runs/106.estado` con una palabra (`PARCIAL`/`OK`/`BLOQUEADA`). `runs/106.md`
con qué se hizo y qué falta. Al llegar a `OK`, `runs/106.pr.md` con título +
cuerpo del PR.

Commits agrupados por función, en español, imperativo, explicando el
porqué. Sin `Co-Authored-By`.
