# Definición técnica — Repositorios, módulos y tecnologías

**Agrocom SRL · Sistema de Gestión de Operaciones de Fumigación · Complementa la Especificación Técnica v1.0**

Decisiones ya tomadas que este documento formaliza: **panel web administrativo fullstack en Laravel** (sin SPA separada), **app de campo en Flutter** corriendo en el RC del Agras y en celular Android (auxiliar), **dos repositorios** con prefijo `agrocom`.

---

## 1. Repositorios

| Repositorio | Contenido | Despliegue |
|---|---|---|
| **`agrocom-api`** | Backend Laravel: API REST para las apps, panel web administrativo (Filament), portal del cliente, generación de PDFs, colas, migraciones, seeders. Incluye `/docs` con las especificaciones y `/deploy` con scripts del servidor. | Continuo al VPS (staging → producción) en cada merge a `main` |
| **`agrocom-field`** | App Flutter de campo: un código, dos flavors (`piloto`, `auxiliar`). Motor de sync offline, base local, evidencias. | Deliberado: APK por versión etiquetada, autorizada desde el panel antes de llegar a los RC |

Por qué dos repos y no monorepo: los ritmos de release son opuestos. El servidor se corrige y despliega a diario; la app de campo se libera pocas veces y bajo autorización expresa (tu requisito de control de versiones del RC). Separarlos hace que el CI de cada uno sea trivial y que un tag en `agrocom-field` signifique exactamente "un APK candidato".

**El contrato entre ambos vive en `agrocom-api`** como fuente única: `docs/api/openapi.yaml` (endpoints, payloads de ejemplo, códigos de respuesta del protocolo de sync). `agrocom-field` lo consume; ante cualquier duda, el YAML gana. Ambos repos llevan su propio `CLAUDE.md` con las invariantes del proyecto para los agentes de IA.

**Convenciones comunes a ambos repos:**

- Ramas: trunk-based — `main` siempre desplegable, ramas cortas `feat/...`, `fix/...`, merge por PR aunque trabajes solo (el PR es donde el agente de IA y tú revisan el diff).
- Versionado: `agrocom-api` no versiona (despliegue continuo); `agrocom-field` usa SemVer + `versionCode` Android incremental (`1.4.0+17`). El endpoint `/api/version` publica la versión mínima y la autorizada.
- Commits en español, imperativo: `agrega validación de solape en sesiones`.
- Idioma del código: **dominio en español** (`Trabajo`, `Sesion`, `Mezcla`, `hectareas_declaradas`) — el vocabulario del negocio es en español y traducirlo crea dos idiomas para la misma cosa; palabras técnicas de infraestructura quedan en inglés (`SyncController`, `Repository`).

---

## 2. Módulos de `agrocom-api`

Monolito modular Laravel. Un módulo = una carpeta de dominio con sus modelos, servicios, eventos y policies. Los límites coinciden con las fases del plan (permite congelar fases 3–8 sin contaminar la ruta crítica).

```
agrocom-api/
├── CLAUDE.md                  # invariantes para agentes de IA
├── docs/
│   ├── api/openapi.yaml       # contrato API (fuente única)
│   ├── especificacion/        # spec funcional y técnica versionadas
│   └── decisiones/            # ADRs: una página por decisión de arquitectura
├── app/
│   ├── Dominios/
│   │   ├── Identidad/         # usuarios, roles múltiples, bases, bloqueo (Fase 1)
│   │   ├── Comercial/         # clientes, contratos, campos, lotes, órdenes de aplicación (F1)
│   │   ├── Mezclas/           # productos, recetas, mezclas, checklist, sobrantes (F1)
│   │   ├── Operaciones/       # trabajos, sesiones, condiciones, recargas, incidencias,
│   │   │                      #   validación cruzada, actas (F1–F2)
│   │   ├── Sync/              # protocolo offline: /api/sync, idempotencia, resolución de
│   │   │                      #   UUIDs, catálogo descargable, cola de evidencias (F1)
│   │   ├── Finanzas/          # gastos, rendiciones, combustible, devengos, anticipos,
│   │   │                      #   planillas, facturas, cobranzas (F3–F4)
│   │   ├── Mantenimiento/     # equipos, planes, órdenes, repuestos, stock, costeo PP (F5–F6)
│   │   ├── Portal/            # consultas solo-lectura scopeadas por contrato (F7)
│   │   └── Reportes/          # reporte técnico, comercial, dashboard, alertas (F2, F8)
│   └── Compartido/            # máquina de estados, auditoría, evidencias (S3), PDF,
│                              #   dinero (Decimal), enums globales
├── database/  routes/  tests/
└── deploy/                    # Caddyfile, supervisord, script de respaldo
```

Reglas entre módulos: un dominio no toca las tablas de otro — se comunica por **eventos de dominio** (`SesionValidada` → `Finanzas` genera devengos; `OrdenMantenimientoCerrada` → `Finanzas` genera gasto) o por servicios públicos del otro dominio. `Compartido` no depende de nadie; todos dependen de `Compartido`. `Portal` solo lee.

**Las tres superficies web salen del mismo repo:**

| Superficie | Implementación | Ruta |
|---|---|---|
| API para apps de campo | Controllers REST + Sanctum (token por dispositivo) | `/api/*` |
| Panel administrativo | **Filament** — panel "Admin" (jefe de campo, encargado, dueño; recursos visibles según rol) | `/admin` |
| Portal del cliente | **Filament** — segundo panel "Portal" con guard propio, solo lectura, todo scopeado por contrato | `/portal` |

---

## 3. Módulos de `agrocom-field`

```
agrocom-field/
├── CLAUDE.md
├── lib/
│   ├── nucleo/                # todo lo compartido (~70% del código)
│   │   ├── db/                # drift (SQLite): espejo local de tablas operativas
│   │   ├── sync/              # outbox, cola, push/pull, estados de registro
│   │   ├── auth/              # login, token por dispositivo, sesión persistente
│   │   ├── evidencias/        # captura, compresión <300KB, hash, cola de subida
│   │   ├── catalogo/          # órdenes, recetas, productos, lotes descargados
│   │   └── ui/                # tema de campo: botones grandes, alto contraste
│   ├── piloto/                # flavor piloto: órdenes → trabajo → sesión → condiciones
│   │   │                      #   → incidencias → cierre con captura RC → acta
│   ├── auxiliar/              # flavor auxiliar: mezclas (cálculo + checklist bloqueante,
│   │                          #   EPP, sobrantes) → recargas/batería → combustible → incidencias
│   └── main_piloto.dart / main_auxiliar.dart
├── android/                   # flavors Gradle: applicationId .piloto / .auxiliar
└── test/                      # el test de replay del sync vive aquí y en la API
```

Regla espejo de la del backend: `piloto/` y `auxiliar/` no se importan entre sí; solo importan `nucleo/`.

---

## 4. Stack tecnológico

### Backend (`agrocom-api`)

| Capa | Tecnología | Notas |
|---|---|---|
| Lenguaje/framework | PHP 8.3 + **Laravel 12** | |
| Base de datos | **PostgreSQL 16** | UNIQUE por `uuid_cliente`, CHECK, índices parciales, JSONB para GeoJSON. Sin PostGIS en v1 |
| Panel y portal | **Filament 4** | Dos paneles sobre los mismos modelos |
| Autenticación | Sanctum (apps, token por dispositivo) + sesión web (paneles) | |
| Permisos | Policies de Laravel + roles múltiples propios | La regla "nadie valida lo suyo" es por persona, en la policy |
| Colas | Driver `database` + supervisord | Redis solo si algún día hace falta; a esta escala no |
| Almacén de evidencias | S3-compatible: **Cloudflare R2** (o Backblaze B2) vía Flysystem | URLs firmadas con expiración |
| PDF | **Browsershot** (Chrome headless) para actas/reportes con imágenes; plantillas Blade | Si el VPS queda corto, fallback DomPDF |
| Testing | **Pest** + Larastan (nivel 6+) + Pint | El replay de sync y la matemática de devengos son los tests sagrados |
| Monitoreo | **Sentry** (errores) + logs estructurados | El mismo proyecto Sentry recibe la app Flutter |
| Respaldos | spatie/laravel-backup → bucket, diario; restauración probada mensual | |

### App de campo (`agrocom-field`)

| Capa | Tecnología | Notas |
|---|---|---|
| Framework | **Flutter 3.x** (Dart 3) | `minSdkVersion` según el spike en el RC real (Android ~10 → API 29) |
| Base local | **drift** (SQLite tipado) | Espejo de tablas operativas + tabla outbox |
| Estado | **Riverpod** | Simple, testeable, sin ceremonia |
| HTTP | dio + retry con backoff | Sync disparado por conectividad, apertura de app y botón manual. **Sin Firebase/FCM** (no hay Google Play Services garantizados en el RC): polling, no push |
| Imágenes | flutter_image_compress | Objetivo <300 KB; hash SHA-256 antes de subir |
| Flavors | Gradle productFlavors + entrypoints Dart | Dos APKs instalables por separado |
| Distribución | APK autohospedado + `GET /api/version` | Versión mínima y autorizada controladas desde el panel |
| Testing | flutter_test + integration_test | Replay de sync contra un servidor de mentira; golden tests de pantallas críticas |

### Infraestructura

| Pieza | Elección |
|---|---|
| Servidor | 1 VPS (4 vCPU / 8 GB): Laravel + PostgreSQL + supervisord. Staging chico aparte o como segundo sitio del mismo VPS |
| Proxy/TLS | Caddy (HTTPS automático) |
| CI/CD | GitHub Actions: `agrocom-api` → tests + deploy por SSH; `agrocom-field` → tests + build de ambos APKs en cada tag |
| Secretos | `.env` por entorno; nunca en el repo |

### Qué queda explícitamente afuera (y por qué)

Microservicios, Redis, Kubernetes/Docker Swarm, event sourcing formal, PostGIS, Firebase, React/SPA separada: cada uno agrega una pieza móvil que un equipo de una persona paga en mantenimiento, y ninguno resuelve un problema que este sistema tenga hoy. La arquitectura ya deja la puerta abierta (la API REST existe y es la misma que consumirían otras superficies) si el negocio escala a multi-cliente SaaS.

---

## 5. Primer commit de cada repo (arranque concreto)

**`agrocom-api`**: esqueleto Laravel 12 + Filament, migraciones de `Identidad` y `Comercial`, `CLAUDE.md` con las invariantes, `docs/api/openapi.yaml` con los endpoints de sync especificados, CI corriendo Pest en verde.

**`agrocom-field`**: esqueleto Flutter con los dos flavors compilando, drift configurado con una tabla, pantalla de login contra la API, y el APK de prueba instalado en el RC real (el spike de hardware de la semana 1 del plan).
