# ADR 0009 — Organización de recursos: evidencias, reportes y assets de marca

**Estado:** Aceptada.

## Contexto

El sistema produce tres tipos de archivo con reglas de vida muy distintas: fotos y firmas capturadas en el campo (evidencias, inmutables, sensibles), documentos generados por el propio sistema (actas y reportes en PDF, también inmutables una vez emitidos), y assets estáticos de la aplicación (logo, favicon, iconografía propia, que cambian por deploy). Tratarlos igual — todo en un mismo disco o bucket, sin convención de rutas — vuelve imposible razonar sobre backups, expiración de URLs, o qué se puede versionar en el repo.

## Decisión

Tres categorías, cada una con su regla:

1. **Evidencias** (`captura_rc`, `imagen_campo`, `foto_incidencia`, `comprobante`, `firma_acta` — especificación §4.3): bucket S3-compatible (Cloudflare R2) vía Flysystem, ruta `evidencias/{tipo}/{yyyy}/{mm}/{uuid_cliente}.{ext}`. URL siempre firmada con expiración, nunca pública. Hash SHA-256 calculado en el dispositivo y verificado al subir (especificación §7.1, §2). Nunca se sobrescriben ni se borran físicamente (ADR 0007).
2. **Reportes generados** (actas, reporte técnico, reporte comercial, recibos de planilla): mismo bucket, ruta `reportes/{tipo}/{entidad_id}/{uuid}.pdf`. Son inmutables una vez emitidos — una corrección o regeneración crea un archivo nuevo, referenciado desde el registro correspondiente (`actas.evidencia_firma_id`, etc.); nunca se sobrescribe el PDF ya entregado a un cliente.
3. **Assets de marca** (logo, favicon, iconografía propia del panel, plantillas base de PDF): versionados en el propio repositorio (`public/images/marca/` o `resources/`), no en el bucket. Cambian por deploy, no por operación — no tiene sentido tratarlos como datos generados en producción.

   **Nota (ADR 0026, 23/9/2026):** la regla de anidar por id del objeto dueño que este ADR ya aplica a evidencias y reportes queda explícita como criterio general para cualquier archivo con más de un objeto posible — ver ADR 0026 para el caso concreto que la motivó (logo de cliente) y la ruta prevista para futuras exportaciones.

   **Nota (ADR 0019, 11/9/2026):** esta categoría queda matizada, únicamente para el *logo de la propia empresa* (el campo "Logo de empresa" de `/panel/organizacion`, dato de negocio de quien opera el sistema — no el wordmark del panel `public/logo.png`, que sigue acá sin cambios): pensando en el pivot futuro a SaaS multi-tenant, ese archivo puntual pasa a ser subido por el usuario en runtime, en el disco `public` de Laravel (`storage/app/public`, no versionado en git), en vez de un asset fijo del repositorio. Favicon, iconografía propia del panel y plantillas base de PDF siguen exactamente como están escritos acá. Ver ADR 0019 para el detalle completo.

## Alternativas descartadas

- **Todo en `storage/app/public` del VPS**: no escala si algún día hay más de una instancia del backend, se pierde en un redeploy sin volumen persistente configurado, y no ofrece CDN/URL firmada nativa — descartado ya en `docs/legacy/definicion_tecnica_repos_modulos_stack.md` a favor de R2/B2.

## Consecuencias

- Flysystem se configura con el disco `r2` como default para evidencias y reportes; el disco `public` se reserva para assets de marca versionados en el repo (`public/logo.png`, favicon, iconografía del panel, plantillas PDF) **más el logo de la propia empresa** (excepción de runtime, ver nota de ADR 0019 arriba) — el resto del sistema no escribe en `public`.
- Ningún caso de uso genera una URL pública directa a una evidencia o reporte — siempre pasa por el mecanismo de firma con expiración.
- El costo de este bucket es marginal al volumen de la operación (`docs/negocio/ventana_al_negocio.md`, sección 2.2) y no requiere revisitarse en v1.
