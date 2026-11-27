# ADR 0050 — Organización de los archivos multimedia: ruta relativa, derivados, límites y descarga auditada

**Estado:** Aceptada · **Fecha:** 2026-11-27 · **Relacionados:** ADR 0009 y 0026 (rutas por objeto), ADR 0040 (fotos de seguimiento), ADR 0046 (verificación), ADR 0047 (eliminación de cuenta). **Zona crítica:** archivos de personas y documentos de identidad.

## Contexto

Los usuarios de las apps suben documentos, fotos del dron, del terreno, de avance, del chat y de perfil. Cada módulo guardaba y servía su archivo por su cuenta: nombres con la fecha o el nombre subido, sin miniaturas (el panel y las apps descargaban el original para pintar una cuadrícula), sin un tope de archivos por entidad, sin constancia de quién descargaba un documento de identidad y con la eliminación de cuenta sin borrar todos los archivos.

## Decisión

1. **Un solo servicio: `AlmacenDeMedios` (`Compartido/Aplicacion/Medios`).** Guarda, mueve, sirve, comprueba y borra. Ningún módulo llama a `Storage` para un archivo de una persona.
2. **La base guarda solo la ruta relativa dentro del disco**, nunca una URL ni una ruta del servidor. La URL se compone al responder (ruta firmada, con token o del panel) y la raíz del disco puede cambiar. `AlmacenDeMedios::normalizar()` y la migración `normaliza_las_rutas_de_medios_a_relativas` dejan así cualquier valor antiguo que trajera una URL o un prefijo `storage/`.
3. **Estructura de carpetas por dominio y propietario (ADR 0026, objeto primero):**

   | Archivo | Ruta (disco privado) |
   |---|---|
   | Documentos del proveedor, piloto o dron | `proveedores/{id}/documentos/`, `pilotos/{id}/documentos/`, `drones/{id}/documentos/` |
   | Fotos del dron | `drones/{id}/fotos/` |
   | Fotos del terreno | `terrenos/{id}/fotos/` |
   | Fotos de avance del servicio | `servicios/{solicitud_id}/avance/{uuid_cliente}.{ext}` |
   | Fotos del chat del servicio | `servicios/{solicitud_id}/chat/{uuid_cliente}.{ext}` |
   | Foto de perfil | `perfiles/{usuario_id}/` |

   El nombre del archivo lo genera el servidor: 16 bytes aleatorios (`{hex32}.{ext}`) o el `uuid_cliente` que el dispositivo ya generó (avance y chat, para que reintentar caiga en el mismo archivo). Nunca el nombre que subió la persona ni un dato personal. Los archivos que ya estaban guardados no se mueven: su ruta relativa sigue valiendo (`mover()` queda para reorganizarlos con un comando propio).
4. **Derivados, original intacto.** Cada imagen tiene una miniatura (lado mayor 320 px) y un tamaño medio (960 px), en WebP (JPEG si GD no lo soporta), junto al original con un sufijo: `{nombre}.miniatura.webp`, `{nombre}.media.webp`. La ruta del derivado se calcula de la del original, así que no hay columnas nuevas. Se generan con la extensión GD que ya trae PHP (sin dependencia nueva) en la cola `medios`, que el planificador vacía cada minuto (no hay worker permanente en el despliegue), y con `php artisan medios:regenerar [--dominio=] [--forzar] [--en-cola]` para lo existente. Los PDF no se transforman: el ViewModel expone `tiene_vista_previa: false`. Pedir un derivado que aún no existe entrega el original, nunca un error.
5. **Las apps y el panel usan el derivado por defecto** y piden el original solo en el visor o la descarga: `?variante=miniatura|media|original` en cada endpoint de archivo (sin variante, el original, por compatibilidad con las apps publicadas). Los recursos JSON agregan `url_miniatura` y `url_media` junto a `url`.
6. **Límite de archivos: por entidad dueña y por categoría**, en `config/medios.php`, validado por el servidor con 422 y mensaje en español, y legible por las apps en `GET /api/medios/limites`. Hoy 10 en: fotos por dron, fotos por terreno, documentos EN REVISIÓN por sujeto (el historial aprobado no cuenta), fotos de avance por servicio y fotos del chat por servicio. `por_subida` (10) acota los archivos de una sola petición; hoy cada endpoint acepta uno. Reintentar una foto ya subida (mismo `uuid_cliente`) no cuenta.
7. **Archivo que falta: sin error.** `existe()` responde `false`; el recurso `ArchivoDeMedio` marca `existe: false` y apunta las miniaturas a `images/medio-no-disponible.svg`; los endpoints de archivo responden 404 sin 500.
8. **Descarga del original con permiso propio y bitácora.** `GET /panel/documentos/{id}/descargar` exige `proveedores.documento.descargar` (sensible: solo administrador, o el operador al que se le asigne con aviso), responde con `Content-Disposition: attachment` y un nombre armado por el servidor (`{tipo}-{id}.{ext}`), `X-Content-Type-Options: nosniff` y `Cache-Control: private, no-store`. El visor `/panel/documentos/{id}/archivo` sigue; abrir o descargar el ORIGINAL deja una fila `descargado` en `plt_bitacoras` (quién, cuándo, qué registro, sin contenido ni nombre de archivo). La miniatura de la cuadrícula no se registra.
9. **Eliminación de cuenta (ADR 0047):** borra original y derivados de los documentos (del proveedor, piloto y dron), las fotos del dron, las fotos del terreno y la foto de perfil. Las fotos de avance y del chat se conservan según el plazo de retención de la política (12 meses tras el cierre del plazo de reclamo); su purga programada no forma parte de este ADR.
10. **`storage/demo/` queda sin ignorar** durante el desarrollo para versionar medios de ejemplo; solo ficticios, nunca de personas reales. `storage/app/private` sigue ignorado.

## Contrato interno para las pantallas: `ArchivoDeMedio`

Un recurso por archivo (`ArchivoDeMedio::de(...)->aArray()`): `url_miniatura`, `url_media`, `url_original`, `existe`, `tipo` (`imagen`, `pdf` u `otro`), `tiene_vista_previa`, `mime`, `peso` (bytes), `nombre_visible` (nunca el nombre que subió la persona) y, extra, `url_descarga` (solo si quien mira puede descargar). Para documentos: `MedioDeDocumento::de($documento, $puedeDescargar)`.

## Consecuencias

- Migraciones: `permite_la_accion_descargado_en_la_bitacora` (solo ensancha el CHECK de `plt_bitacoras.accion`; `down()` lo vuelve a tres acciones como `NOT VALID` para no tocar las constancias) y `normaliza_las_rutas_de_medios_a_relativas` (datos, idempotente). Probadas arriba, abajo y arriba en PostgreSQL.
- Contrato aditivo: `GET /api/medios/limites`, `url_miniatura` y `url_media` en fotos de terreno, de dron, de avance y del chat, y el parámetro `variante` en los endpoints de archivo. Se retira `solicitudes.fotos_terreno.maximo_por_terreno` (8) a favor de `medios.limites.por_entidad.terreno_fotos` (10).
- Riesgo asumido: sin worker permanente, un derivado puede tardar hasta un minuto en existir; mientras tanto se sirve el original.
