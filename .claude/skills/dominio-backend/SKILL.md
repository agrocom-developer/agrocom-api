---
name: dominio-backend
description: Mapa de la arquitectura modular de agrocom-api (app/Dominios/, capas, qué módulos existen y qué escribe cada uno, modelo base de plataforma, casos de uso). Usar antes de escribir código PHP de negocio — para saber dónde va lo nuevo sin releer los ADRs enteros.
---

# Backend de dominio — agrocom-api

Monolito modular Laravel: un módulo de dominio = una carpeta bajo `app/Dominios/`.
Este skill dice dónde va cada cosa; el porqué está en `docs/decisiones/0003-arquitectura-modular-clean-por-feature.md`.

## Módulos que existen hoy

| Módulo | Prefijo de tabla | Qué escribe |
|---|---|---|
| `Comercial` | `com_` | clientes, contactos, contratos, ventanas, campos, lotes |
| `Operaciones` | `ope_` | órdenes de aplicación |
| `Personal` | `per_` | personas, bases |
| `Seguridad` | `sec_` | usuarios, roles, permisos, menú, preferencias |
| `Compartido` | — | plataforma: `ModeloDominio`, `RegistraAutoria`, middleware transversal |

Prefijos ya reservados para módulos que aún no existen (ADR 0011): `syn_` Sync,
`mez_` Mezclas, `fin_` Finanzas, `inv_` Inventario, `man_` Mantenimiento,
`vw_` vistas de solo lectura. Un módulo nuevo registra su prefijo en el ADR 0011
**antes** de su primera migración.

## Las cuatro capas

```
app/Dominios/<Modulo>/
  Contratos/          lo que otros módulos pueden invocar (interfaces, DTOs)
  Aplicacion/         casos de uso: una clase = una acción del negocio
  Dominio/            reglas puras: enums de estado, excepciones, value objects
                      (no depende de Eloquent — hay test arch que lo verifica)
  Infraestructura/
    Eloquent/         modelos (extienden ModeloDominio — test arch lo verifica)
    Http/             Controllers, Requests, Resources, Middleware, Views
```

No todos los módulos tienen las cuatro: se crean cuando hacen falta. `Comercial`
y `Personal` hoy son solo `Dominio` + `Infraestructura/Eloquent`.

## Reglas de frontera (las defiende la suite, no la disciplina)

- **Un módulo solo escribe sus propias tablas.** Entre módulos se viaja por
  contratos o eventos de dominio.
- **Cero relaciones Eloquent cruzando módulos.** La referencia se guarda como FK
  de base de datos + atributo entero plano. Ejemplo vigente: `sec_user.persona_id`
  apunta a `per_personas.id` por FK, sin `belongsTo`.
- **Eventos de dominio vigentes entre módulos** (viven en `Operaciones/Contratos/Eventos/`,
  el oyente en el módulo que reacciona): `SesionValidada` (Operaciones → Finanzas,
  genera el devengo) y `AplicacionCerrada` (Operaciones → Comercial, finaliza el
  contrato al cerrarse su última aplicación).
- `tests/Unit/ArquitecturaModulosTest.php` descubre los módulos recorriendo
  `app/Dominios/` — un módulo nuevo queda protegido sin editar el test.

## El modelo base: `ModeloDominio`

Todo modelo de dominio extiende `App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio`,
que empaqueta las obligaciones del ADR 0007 para que no se puedan adoptar a medias:

- `SoftDeletes` (columna `deleted_at`).
- `forceDelete()` lanza `BorradoFisicoNoPermitido` — el borrado físico está cerrado.
- Trait `RegistraAutoria`: completa `created_by` / `updated_by` desde el usuario
  autenticado por eventos de Eloquent. Ningún caso de uso tiene que acordarse.

**Pendiente conocido**: la bitácora antes/después del ADR 0007 (invariante 9) no
existe todavía; hoy solo hay autoría por fila.

## Casos de uso

Una clase por acción, en `Aplicacion/`, con un único método público. Los que ya
existen sirven de molde: `AsignarRolesUsuario`, `IniciarSesionPanel`,
`ElegirRolActivo`, `ObtenerMenuPorRolActivo`, `ListarOrdenesAplicacion`.

Patrón vigente: el caso de uso traduce violaciones de la base (unicidad, FK) a
excepciones de dominio del propio módulo (`Dominio/Excepciones/`); el controlador
queda delgado y sin reglas de negocio.

## Vocabulario

Dominio en español, infraestructura en inglés: `Trabajo`, `Sesion`, `Mezcla`,
`hectareas_declaradas`; pero `SyncController`, `Repository`, `Middleware`.

## Antes de cerrar

Correr la cascada — ver el skill [verificacion]. Para el esquema y las
migraciones, [modelo-datos]. Para pantallas, [panel-design-ui].
