---
name: contrato-api
description: Cómo se genera y publica el contrato de API (docs/api/openapi.yaml, code-first con atributos OA) y cómo lo consume agrocom-field. Usar antes de agregar, cambiar o retirar un endpoint, o al revisar un PR que toca controllers, form requests o resources de la API.
---

# Contrato de API — del servidor a las apps

## Quién escribe qué

- **El código es la fuente de verdad** (ADR 0014): los atributos `OA\...` de swagger-php en controllers, form requests y resources de cada módulo (`app/Dominios/<Módulo>/Infraestructura/Http/`) generan el spec con `darkaonline/l5-swagger`.
- **El YAML versionado es el contrato** que consume `agrocom-field`: `docs/api/openapi.yaml`. Nunca se edita a mano.
- **La app lo consume a mano** (ADR 0001 de `agrocom-field`): cliente `dio`, DTOs con `freezed`, errores mapeados a excepciones propias. No hay cliente generado.

## Regenerar

```
composer openapi
```

Ejecuta `php artisan l5-swagger:generate` y copia `storage/api-docs/api-docs.yaml` a `docs/api/openapi.yaml`. Corrélo dentro del contenedor del compose y commiteá el YAML en el mismo PR que cambia el endpoint. La UI de Swagger (`/api/documentation`) existe solo en local y staging.

## Cambiar un endpoint

1. Anotá el endpoint con `OA\...`: tag por módulo, parámetros, esquema de request, todas las respuestas (200, 401, 403, 404, 422) y el vocabulario del dominio tal como viaja (`hectareas_declaradas`, `uuid_cliente`). Dinero y hectáreas como cadenas decimales.
2. Escribí el caso de uso en `Aplicacion/`; el controller es un adaptador delgado.
3. Regenerá el spec y revisá el diff del YAML: debe contener exactamente lo que querías.
4. Si el endpoint lo usan las apps, suma sus intercambios a `bin/lib/generar-ejemplos.php` y regenera `docs/api/ejemplos/` con `bin/generar-ejemplos`: éxito, validación, autorización (404 si el recurso es ajeno) e idempotencia si recibe `uuid_cliente`.
5. Corre `vendor/bin/pest tests/Unit/Contrato tests/Feature/Contrato`: cada ruta debe estar en el contrato y cada ejemplo debe cumplir su esquema.

## Ejemplos reales y pruebas de contrato

- `docs/api/ejemplos/*.json` son intercambios reales (request y response) capturados contra la API con `bin/generar-ejemplos`, que levanta un PostgreSQL desechable, siembra el catálogo y los datos de demostración y recorre los flujos del cliente, el proveedor y el piloto. Las apps los usan como fixtures de sus propias pruebas de contrato. Nunca se editan a mano; el formato está en `docs/api/ejemplos/README.md`.
- `tests/Unit/Contrato/EjemplosDeApiTest.php` valida cada ejemplo contra el esquema de `docs/api/openapi.yaml` (estado documentado, campos sin documentar, nullables, enums, decimales como string, ninguna credencial viva).
- `tests/Feature/Contrato/RutasDelContratoTest.php` compara las rutas registradas con el contrato (sin endpoint sin documentar ni documentado de más, y la seguridad declarada coincide con la exigida).
- Un campo `nullable` que apunta a un esquema (`$ref`) se declara con `allOf: [new OA\Schema(ref: ...)]` junto a `nullable: true`: swagger-php descarta `nullable` cuando va al lado de un `ref`.

## Compatibilidad con las apps

Las apps de campo quedan instaladas durante semanas sin actualizar. Por eso:
- Cambio compatible (campo opcional nuevo, endpoint nuevo): se publica sin más.
- Cambio incompatible (renombrar, cambiar tipo, retirar): primero se agrega lo nuevo y se mantiene lo viejo, luego se espera a que la app nueva esté publicada en las tiendas y recién después se retira lo viejo (el servidor no puede forzar una versión mínima) (patrón expandir y contraer, coordinado con `migracion-esquema` si hay cambio de tablas).
- El PR avisa que cambió el contrato; `agrocom-field` adopta el cambio en su propio PR, siguiendo su skill `contrato-api`.

## Qué nunca

- Editar `docs/api/openapi.yaml` a mano.
- Dejar un endpoint sin atributos `OA\...`.
- Devolver campos internos en un resource (calificaciones sin moderar, precios de ofertas de otros proveedores, datos personales de otros): revisión de `confianza-seguridad`.
- Habilitar `/api/documentation` en producción sin actualizar el ADR 0014.
