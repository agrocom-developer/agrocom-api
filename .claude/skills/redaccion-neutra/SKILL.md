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
hardcodeado en `.blade.php` — si hace falta un string nuevo, es una clave
nueva en el archivo `lang/es/<dominio>.php` que corresponda, no un literal en
la vista. Ver `dominio-backend` para saber a qué dominio pertenece un texto.

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
