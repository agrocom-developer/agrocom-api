# ADR 0017 — Despliegue en Proxmox: contenedor LXC dedicado + runner self-hosted disparado por tag

**Estado:** Aceptada · **Origen:** decisión del dueño del proyecto, formalizada en esta sesión (9/9/2026). **Cierra el alcance abierto** por ADR 0010 ("la decisión de cómo se despliega en el servidor... queda abierta... se crea uno nuevo cuando corresponda"). **Modifica** la cláusula de disparo de despliegue de ADR 0006 (ver nota al final de "Decisión").

## Contexto

ADR 0010 resolvió el entorno de desarrollo local con Docker Compose y dejó explícitamente afuera cómo se despliega en un servidor real. `docs/gestion/entornos.md` hoy describe staging y producción como "VPS" sin más precisión, y dice literalmente que ese servidor no está definido.

El dueño ya administra un Proxmox VE 9.1.7 (`server1`) que aloja, además de este proyecto, servicios de terceros ajenos a Agrocom: un ISP, redes, IoT, e institutos. Es un host **compartido**, no un servidor dedicado a `agrocom-api`. Dentro de ese Proxmox ya existe un contenedor LXC reservado para este proyecto: el **contenedor 119**, nombrado "Drones". Ese Proxmox corre además Nginx Proxy Manager para exponer HTTPS de otros servicios — reutilizable a futuro, no parte de esta decisión.

El proyecto todavía no tiene un release formal ni usuarios reales en producción: lo que se define acá es cómo se llega a una primera versión pública, no cómo se opera un sistema con campaña real corriendo.

## Decisión

### 1. Alcance: solo el contenedor 119, nunca el host ni otros contenedores

Toda automatización de despliegue de `agrocom-api` — el runner, los workflows, cualquier script de setup — actúa exclusivamente dentro del contenedor 119. Ningún workflow de este repositorio tiene ni necesita credenciales, acceso o alcance sobre el host Proxmox (`server1`) ni sobre ningún otro contenedor. Esto no es una preferencia de higiene: ese Proxmox sirve a clientes y proyectos que no tienen relación con Agrocom, y una fuga de alcance ahí es un incidente de terceros, no solo propio.

### 2. Mecanismo de CD: runner self-hosted de GitHub Actions dentro del contenedor 119

Se instala un **runner self-hosted de GitHub Actions** como servicio del sistema operativo dentro del contenedor 119, registrado contra este repositorio. El workflow de deploy corre `runs-on: [self-hosted, ...]` en vez de en la infraestructura de GitHub.

El runner es un proceso que abre conexión saliente hacia GitHub y hace *polling* de trabajos — no expone ningún puerto de entrada nuevo. Esto reemplaza el patrón alternativo (runner en la nube + SSH saliente hacia el contenedor), evitado por las razones que se detallan en "Alternativas descartadas".

El contenedor 119 no tiene Docker instalado todavía. Instalarlo es un paso de setup manual que hace el dueño directamente sobre el contenedor — no es una tarea de este ADR ni de ningún agente, se menciona solo como precondición operativa antes de que el primer workflow de deploy pueda correr.

### 3. Un solo ambiente: "producción beta"

No existe todavía un ambiente de staging separado. Todo lo que se despliega en el contenedor 119 es el único ambiente hoy: **producción beta**. No hay una segunda instancia a la que promover antes de esta — la promoción entre staging y producción, si se decide más adelante, es una decisión futura y separada, análoga a como ADR 0010 dejó esto mismo abierto para el servidor.

### 4. Disparo del despliegue: tag SemVer con prefijo `v`, sin sufijo de prerelease — no en cada push

El deploy **no es continuo**. No se dispara en cada push a `master` ni en cada merge de PR. Se dispara al pushear un tag Git con formato SemVer prefijado por `v` y **sin sufijo de prerelease**: `v1.0.0`, `v1.0.1`, `v1.1.0` — explícitamente **no** `v1.0.0-beta.1` ni similares. "Beta" describe la etapa del proyecto y del ambiente (ver punto 3), no un calificador dentro del número de versión: el ambiente es beta, la versión que corre en él es una versión final normal.

El workflow correspondiente escucha:

```yaml
on:
  push:
    tags:
      - 'v*.*.*'
```

(o patrón equivalente `v[0-9]+.[0-9]+.[0-9]+` si se necesita excluir sufijos de forma más estricta que el glob de tags de GitHub Actions). Empujar un tag es un acto deliberado del dueño — decide cuándo una versión pasa a producción beta, no lo decide el ritmo de los merges.

**Nota sobre ADR 0006:** ADR 0006 dice, heredado de `docs/legacy/definicion_tecnica_repos_modulos_stack.md`, que "cada merge a `master` dispara el deploy continuo (staging → producción)". Esa cláusula queda **reemplazada** por el trigger de tag descrito acá: el merge a `master` sigue siendo la integración estable del código, pero ya no dispara despliegue por sí solo. El resto de ADR 0006 (ramas, PRs, convención de commits) no cambia.

### 5. Sin dominio ni HTTPS por ahora

La beta es accesible por la IP pública del contenedor 119 y un puerto expuesto directamente, sin certificado ni dominio propio. Agregar dominio + HTTPS vía el Nginx Proxy Manager que ya corre en ese mismo Proxmox para otros servicios es una mejora futura, fuera de alcance de este ADR.

### 6. Imagen Docker: se adapta la de desarrollo local, no se diseña de cero

El `Dockerfile` y el `docker-compose.yml` de producción parten de los de ADR 0010 (servicios `app` y `db`), adaptados para este entorno — no se define una arquitectura de contenedores nueva. Diferencias explícitas respecto al compose local:

- El servicio `mail` (Mailpit) **se excluye** del compose de producción. Mailpit captura correo para no enviarlo de verdad, que es exactamente lo que no se quiere en producción beta. El envío real de correo usa SMTP configurado por variables de entorno (ver ADR 0016 para cómo se parametriza), no un contenedor propio.
- La implementación concreta del `Dockerfile`/`docker-compose.yml` de producción y del workflow de deploy la hace el agente `distribucion` a partir de este ADR — acá se fija el encaje arquitectónico, no el archivo final.

### 7. Secretos de GitHub Actions y permisos del repositorio

Configurar los *secrets* de GitHub Actions y cualquier protección de rama que dependa de este flujo requiere una cuenta de GitHub con permisos de administrador sobre el repositorio. Hoy esa cuenta (`agrocom-developer`) es distinta de la cuenta autenticada en la CLI de este entorno de trabajo (`Angello-27`, sin permisos de admin). Esto no es parte de la arquitectura de despliegue en sí — es una nota operativa: ese paso de configuración lo hace el dueño desde el navegador con la cuenta admin, ningún agente puede completarlo por su cuenta.

## Alternativas descartadas

- **Runner en la nube (GitHub-hosted) + SSH hacia el contenedor.** Exige dos cosas que se descartan juntas: abrir/exponer un puerto SSH del contenedor 119 hacia Internet, y guardar una clave privada SSH como secret de GitHub Actions. En un host **compartido** con clientes y proyectos ajenos a Agrocom, ampliar la superficie expuesta de un contenedor específico para que un servicio en la nube pueda entrar por SSH es un riesgo que no se justifica solo por evitar instalar un runner local. El runner self-hosted invierte la dirección de la conexión (el contenedor llama a GitHub, no al revés) y no necesita ninguna clave privada guardada como secret.
- **Deploy continuo en cada push/merge a `master`** (el patrón que describía ADR 0006, heredado de la definición legacy). Válido para un sistema ya en producción con usuarios reales, pero prematuro para una primera beta: dispararía un despliegue por cada integración de código sin que exista todavía una noción de "versión publicada", y no le da al dueño un punto explícito de "esto es lo que sale ahora". Se prefiere un trigger a demanda vía tag, reversible a deploy continuo el día que el proyecto lo justifique (sería, en ese caso, otro ADR).
- **Diseñar una arquitectura de contenedores de producción desde cero.** Descartada por continuidad con ADR 0010: ya existe un `Dockerfile`/`docker-compose.yml` que refleja las decisiones de stack (PHP 8.3, PostgreSQL 16), y remodelar la arquitectura de contenedores en paralelo a definir el despliegue arriesga resolver dos problemas a la vez y peor.
- **Dominio y HTTPS desde el día uno.** Descartada por ahora únicamente por orden de prioridades: la beta inicial prioriza tener el pipeline de deploy funcionando; dominio y certificado son un paso siguiente sobre infraestructura (Nginx Proxy Manager) que ya existe en ese Proxmox para otros servicios, no algo que haya que construir.

## Consecuencias

**A favor**

- Ningún puerto nuevo expuesto a Internet en el contenedor 119 ni en el host Proxmox como consecuencia de este pipeline.
- Ningún secreto de clave privada SSH almacenado en GitHub.
- El dueño controla exactamente cuándo se despliega (push de un tag), sin depender del ritmo de merges a `master`.
- Reutiliza el trabajo de ADR 0010 en vez de duplicar decisiones de stack de contenedores.

**En contra, y asumido**

- El runner self-hosted es un proceso de terceros (GitHub) corriendo dentro de un contenedor que convive, a nivel de host Proxmox, con servicios de otros clientes. El riesgo se acota — no se elimina — limitando el alcance del runner y de todo workflow exclusivamente al contenedor 119 (punto 1); el host y los demás contenedores quedan fuera de cualquier automatización de este repositorio.
- No hay staging: un cambio con bug llega directo a producción beta. Aceptado explícitamente porque hoy hay un solo ambiente (punto 3); introducir staging es una decisión futura, no un defecto de esta.
- Sin HTTPS, el tráfico hacia la beta viaja sin cifrar hasta que se configure dominio y certificado. Aceptado como estado temporal de una beta con acceso controlado, no como estado final.
- ADR 0006 queda desactualizado en su cláusula de disparo de despliegue; quien lea ADR 0006 sin leer este ADR se lleva una idea equivocada del trigger real. Se deja la nota cruzada en ambos sentidos (ver "Nota sobre ADR 0006" arriba) en vez de reescribir ADR 0006, para no perder el registro histórico de por qué existía esa cláusula.
- Configurar los secrets de GitHub Actions y la protección de rama para este workflow requiere la cuenta admin del repositorio (`agrocom-developer`) operando desde el navegador; ningún agente con la cuenta `Angello-27` puede completar ese paso.
- `docs/gestion/entornos.md` sigue describiendo hoy "staging/producción: VPS, sin definir" — ese documento se actualiza en un cambio aparte para reflejar este ADR; no se edita como parte de este trabajo.
