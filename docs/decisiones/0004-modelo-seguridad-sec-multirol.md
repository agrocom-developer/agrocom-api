# ADR 0004 — Modelo de seguridad `sec_*`: permiso abstracto + multi-rol

**Estado:** Aceptada · **Reemplaza:** el modelo `sec_*` de rol único de la spec v1.0 legacy. **Fuente:** `docs/legacy/decisiones_arquitectura_v2.md`, sección 5.

## Contexto

El modelo original (`action`, `module`, `menu`, `menu_action`, `role`, `permit`, `user`) es un RBAC clásico orientado a menús: el permiso depende de que exista un menú (`rol → permit → menu_action → menú → módulo`). Eso tiene dos problemas en este proyecto:

- **La app Flutter no tiene menús.** El piloto que cierra una sesión por API necesita autorización que en el modelo original no existe, porque no hay `sec_menu` para el RC — obligaría a inventar "menús fantasma".
- **Reglas que dependen del dato no caben en una tabla de permisos**: "el validador no puede ser el piloto de *esa* sesión", "el jefe solo saca stock de *su* base", "el portal solo ve *su* contrato". Ningún RBAC de pantallas expresa "¿puede validar ESTA sesión?", solo "¿puede validar sesiones?".

Además, la especificación funcional exige que un usuario tenga más de un rol (el jefe de campo también es piloto) con permisos por unión de roles — el modelo original solo admite `sec_user.id_role` único.

## Decisión

Inversión de dependencia: el permiso pasa a ser un concepto abstracto con código (`operaciones.sesion.validar`, `finanzas.gasto.crear`), y el menú lo referencia — no lo define.

```
sec_action            (id, name, description, icon, state)
sec_module            (id, name, path, id_module_parent, orden, state)
sec_menu              (id, id_module, name, path, icon, orden, state)
sec_permission        (id, code UNIQUE, description, id_module)      ← fuente de verdad del permiso
sec_menu_action       (id, id_menu, id_action, id_permission FK)     ← referencia, ya no define
sec_role              (id, name, description, state)
sec_role_permission   (id_role, id_permission)                        ← reemplaza a sec_permit
sec_user              (id, name, login, password[255, hashed], language, type,
                        persona_id FK NULL, contrato_id FK NULL,
                        profile_pic_url, initial_path, state)
sec_user_role         (id_user, id_role)                              ← multi-rol
sec_permission_log    (id, id_user, id_permission|id_role, accion, autor, fecha)
+ personal_access_tokens (Sanctum, token por dispositivo)
+ Policies por agregado (Sesion, Trabajo, Planilla, Stock, Usuario…) para las reglas por-registro
```

Dos capas de autorización, cada una responde una pregunta distinta:
- **`sec_*` responde "¿puede en general?"** — el permiso abstracto gobierna por igual el botón del panel, el endpoint de la API y (a futuro) la pantalla del RC.
- **Policies de Laravel responden "¿puede sobre este registro?"** — p. ej. `SesionPolicy::validar($usuario, $sesion)` verifica el permiso y que `$usuario->persona_id !== $sesion->piloto_id`.

`sec_user.persona_id` (FK nullable) enlaza el login con la persona operativa (`personas`, quien vuela y cobra por hectárea) — sin este enlace no se puede aplicar "nadie valida su propio trabajo a nivel de persona" ni calcular devengos desde el usuario autenticado. Los usuarios del portal del cliente usan `contrato_id` en su lugar. `sec_user.type` (`interno`/`cliente`) separa los dos guards de autenticación.

## Alternativas descartadas

- **Mantener `sec_user.id_role` único**: incompatible con el requisito explícito de multi-rol de la especificación funcional.
- **`spatie/laravel-permission`**: ya implementa roles + permisos + pivotes + caché y podría reemplazar media tabla de este esquema. Se descarta por preferencia de mantener el esquema `sec_*` propio, reutilizable entre proyectos de Agrocom — con la condición de implementar caché de permisos por usuario (memoria/Redis-de-array), porque la resolución rol→permiso corre en cada request.

## Consecuencias

- El mismo permiso gobierna panel web, API de campo y portal del cliente — sin duplicar reglas de autorización por superficie.
- El panel web (ADR 0002) renderiza el menú desde `sec_menu`/`sec_permission` directamente; ningún componente de navegación se define fuera de estas tablas.
- Ajustes menores heredados: `password` a 255 con hash Argon2id/bcrypt; `profile_pic` pasa a `profile_pic_url` (el archivo va al bucket de evidencias); `sec_menu_action.id_menu`/`id_action` corregidos a Integer FK (eran erratas del documento original); `sec_action` ya admite acciones no-CRUD (`validar`, `aprobar`, `firmar`, `anular`, `autorizar_version`).
