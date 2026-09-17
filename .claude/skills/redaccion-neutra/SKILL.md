---
name: redaccion-neutra
description: Registro de idioma del panel de agrocom-api — tuteo estándar, nunca voseo rioplatense ni "usted" formal, en todo texto visible al usuario. Usar antes de escribir o corregir cualquier clave de lang/es/*.php o cualquier texto en español visible en Blade/Livewire.
---

# Redacción neutra — agrocom-api

Todo texto visible al usuario (labels, placeholders, mensajes de error/ayuda,
botones, confirmaciones) va en **tuteo estándar**: segunda persona singular
neutra ("Selecciona", "Confirma", "Elige"), sin pronombre explícito salvo que
suene forzado sin él.

**Nunca:**
- Voseo rioplatense ("Seleccioná", "Confirmás", "¿Confirmás...?").
- La forma formal de "usted" ("Seleccione", "Elija", "Confirme").

**Por qué:** AGROCOM SRL opera en Bolivia (moneda Bs, timezone
America/La_Paz). Ni el voseo ni el "usted" formal son el registro esperado
para software de uso profesional ahí — el tuteo estándar es el término medio
neutro, "menos intrusivo" (palabras del dueño).

**Dónde vive:** `lang/es/*.php`, de todos los módulos. Nunca texto en español
hardcodeado — ni en `.blade.php`, ni en un controlador, ni en un FormRequest,
ni en una excepción, ni en JS. Si hace falta un string nuevo, es una clave
nueva en el archivo `lang/es/<dominio>.php` que corresponda. Ver
`dominio-backend` para saber a qué dominio pertenece un texto.

## Posesivos: lo institucional no es "tuyo"

No se le pone posesivo ("tu"/"mi") a un sustantivo INSTITUCIONAL o compartido
—portal, panel, sistema—: ahí va artículo neutro ("Ingresa al sistema", "al
panel"). Sí se deja cuando la cosa es genuinamente del usuario: tu acceso, tu
contraseña, tu correo, tu contrato, tu contacto en Agrocom.

## Dónde va cada texto y cómo se lee

| Qué | Sección en `lang/es/<modulo>.php` | Cómo se lee |
|---|---|---|
| Pantallas (labels, botones, ayudas) | la de la pantalla (`clientes`, `ordenes`…) | `__()` en Blade |
| Excepciones y mensajes de casos de uso | `errores` | `Texto::de()` |
| `messages()` de FormRequests | `validacion` | `__()` |
| Respuestas de controladores y API | `respuestas` | `__()` |
| Documentos PDF | `pdf.<documento>` | `__()` en la vista |
| Motivos de rechazo del sync | `sync` | `Texto::de()` |

- `Texto::de()` (`App\Dominios\Compartido\Infraestructura\Idioma\Texto`) es
  `__()` para las capas de adentro (`Dominio/`, `Aplicacion/`, servicios de
  `Infraestructura/`): si el framework no está levantado —un test unitario
  puro— devuelve la clave en vez de reventar.
- Validación, dos niveles (Laravel toma el primero que exista):
  1. `messages()` del FormRequest — **lo específico del formulario**:
     `'codigo.required' => __('campania.campanias.error_codigo_requerido')`
     → "Ingresa un código para la campaña.". Todo campo obligatorio de un
     formulario del panel lleva el suyo: "Ingresa …" (se escribe), "Elige …"
     (se elige), "Elige la fecha …", "Agrega al menos un …", nombrando el
     campo igual que su label. Modelo: `CrearCampaniaRequest`.
  2. `lang/es/validation.php` — el genérico de respaldo ("Este campo es
     obligatorio.", "Escribe como máximo :max caracteres."). Es lo que ven
     las reglas sin mensaje propio y los requests de la API.
  `required` ya cubre nulo, vacío y solo espacios (`TrimStrings` +
  `ConvertEmptyStringsToNull` corren antes de validar): no hace falta regla
  extra. No usar `validation.php › custom`: cuelga el mensaje del NOMBRE del
  campo en todo el sistema (`codigo` existe en campañas, lotes y repuestos).
- Datos variables con `:marcador` y array de reemplazos; nunca concatenar
  pedazos de frase traducidos.
- **JS no traduce.** El Blade que monta el componente le entrega los textos ya
  traducidos por `data-*` (ver `organisms/login-form.blade.php` +
  `js/pages/login.js`).

## Lo que responde el framework también es texto del sistema

Los errores que arma Laravel por su cuenta salen en inglés si nadie los
traduce, y no aparecen en ningún barrido de archivos — hay que probarlos
contra la API real (`curl` sin token, a una ruta que no existe, etc.):

- JSON (API de las apps y `fetch` del panel): 401, 403, 404, 405, 419, 429 y
  5xx los traduce `ErroresHttpEnEspanol` (`Compartido/Infraestructura/Http`,
  enganchado en `bootstrap/app.php`) con `lang/es/http.php`. Respeta el
  mensaje de toda excepción propia (`App\...`): un `PermisoDenegado` sigue
  diciendo lo suyo. De paso evita que un 404 de modelo filtre el nombre de la
  clase.
- Frases sueltas del framework (el "(y :count errores más)" del resumen de
  validación, los títulos de las páginas de error): `lang/es.json`.

## Pasar un texto a un componente Blade: siempre enlazado

`:label="__('x')"`, `:error="$errors->first('nit')"`, `:value="$nit"` — NUNCA
`label="{{ __('x') }}"`. Con `{{ }}` el valor se escapa al entrar a la prop y
el componente lo vuelve a escapar al pintarlo: una comilla sale como
`&#039;`, y un `&` en un `value` o en una URL se guarda o navega corrupto.

## Compuertas automáticas (corren en `bin/verify`)

- `tests/Unit/RedaccionNeutraTest.php`: falla si un valor de `lang/es/*.php`
  trae voseo o trato de usted. Si marca una palabra legítima, se agrega a su
  lista de excepciones con criterio — no se debilita el patrón.
- `tests/Unit/EscapePropsBladeTest.php`: falla si una prop de componente
  recibe su valor con `{{ }}`.

## Tabla de conversión voseo → tuteo

Verbos regulares (patrón `-á/-á(s)` → `-a/-as`, `-é(s)` → `-e/-es`, `-í(s)` →
`-e/-es` según conjugación):

| Voseo | Tuteo | | Voseo | Tuteo |
|---|---|---|---|---|
| Seleccioná | Selecciona | | Ingresá | Ingresa |
| Confirmá / Confirmás | Confirma / Confirmas | | Completá | Completa |
| Guardá | Guarda | | Agregá | Agrega |
| Cargá | Carga | | Eliminá | Elimina |
| Marcá | Marca | | Verificá | Verifica |
| Mirá | Mira | | Indicá | Indica |
| Buscá | Busca | | Cancelá | Cancela |
| Revisá | Revisa | | Corregí | Corrige |
| Actualizá | Actualiza | | Subí | Sube |
| Activá | Activa | | Desactivá | Desactiva |
| Adjuntá | Adjunta | | Repetí | Repite |
| Probá | Prueba | | Intentá | Intenta |
| Esperá | Espera | | Escribí | Escribe |
| Leé | Lee | | Elegí | Elige |
| Avisame | Avísame | | Fijate | Fíjate |
| Acordate | Acuérdate | | | |

Verbos irregulares (no siguen el patrón regular, ojo especial):

| Voseo | Tuteo |
|---|---|
| Volvé | Vuelve |
| Andá | Anda |
| Vení | Ven |
| Decí / Decime | Di / Dime |
| Salí | Sal |
| Hacé / Hacés | Haz / Haces |
| Poné | Pon |
| Tené / Tenés | Ten / Tienes |
| Sos | Eres |
| Podés | Puedes |
| Querés | Quieres |
| Necesitás | Necesitas |
| Sabés | Sabes |

**No tocar** — ya son correctas en tuteo, coinciden con voseo o son
impersonales/adverbios: "está" (3ra persona), "estás" (2da persona de
"estar", ya es tuteo), "quizá", "además", "acá", "allá". No son marcadores de
voseo aunque terminen en vocal acentuada.

Ver también [[comentarios-en-archivos-de-idioma]] (memoria): dentro de
`lang/es/*.php` los comentarios van todos o ninguno, cortos y sin jerga
técnica — este barrido de redacción no agrega comentarios nuevos explicando
el cambio.

## Caveat: mensajes nativos del navegador

Un `<select required>` (o `<input required>`) vacío al enviar el formulario
puede mostrar el aviso de validación **nativo del navegador** (el globito
HTML5), no un texto de la app. En Firefox en español ese mensaje es "Por
favor, elija un elemento de la lista." — con "elija" fijo, en usted. Ese
texto **no está en ningún `lang/es/*.php` ni en ningún Blade**: viene del
navegador y no se puede tocar editando strings del proyecto.

`atoms/select.blade.php` mantiene el `<select>` nativo real en el DOM
(oculto solo con `opacity`, no `display:none`) para progressive enhancement,
así que ese globito nativo puede aparecer. Si hace falta que el mensaje
respete el tuteo del resto del sistema, la corrección es agregar
`setCustomValidity()` en `resources/js/atoms/select.js` (evento `invalid` +
reset en `input`/`change`), con el texto propio como una clave nueva de
`lang/es/ui.php` — no una edición de contenido existente.
