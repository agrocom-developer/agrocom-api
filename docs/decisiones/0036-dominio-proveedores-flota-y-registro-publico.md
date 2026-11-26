# ADR 0036 — Dominio de proveedores y flota: módulos, roles, registro público y aprobación

**Estado:** aceptada; el punto 4 queda sustituido por el ADR 0046 (la cuenta de app no tiene roles `cliente` ni `proveedor`: tiene un tipo de cuenta, y el token no lleva rol) · **Fecha:** 2026-11-23 · **Decide:** el dueño (autonomía delegada)

> **Nota de retiro (26/11/2026):** el auxiliar se eliminó del sistema (rol, permisos `proveedores.auxiliar.*`, página del panel, endpoints `/api/proveedores/auxiliares`, documentos y calificación del auxiliar). Donde este ADR lo menciona, ya no aplica; las tablas `prv_auxiliares` y la columna `flo_equipos.auxiliar_id` siguen en el esquema, sin uso, hasta la reconsolidación de migraciones. Un reemplazo (integrantes del equipo del servicio) se define aparte.

## Contexto

Los ADR 0028, 0029 y 0032 fijan el qué: proveedores propio, independiente y tercero; el dron como vehículo con dueño; el registro por cuenta propia con aprobación de un administrador. Falta el cómo en el código: en qué módulos vive, qué roles y permisos nacen, cómo se protege el registro público, cómo se mantiene la invariante 7 (transiciones de estado por máquina) y qué se reutiliza de lo existente.

## Decisión

1. **Tres módulos nuevos** bajo `app/Dominios/`, cada uno con sus tablas y comunicados por contratos y eventos (ADR 0003):
   - **`Proveedores`** (`prv_`): `prv_proveedores`, `prv_pilotos`, `prv_auxiliares`, `prv_documentos` y `prv_historial_estados`.
   - **`Flota`** (`flo_`): `flo_drones` (dueño, operador, ficha, `ciclos_bateria`), `flo_equipos` (piloto + dron + auxiliar opcional, con vigencia) y `flo_historial_estados`.
   - **`Solicitudes`** (`sol_`): por ahora `sol_clientes` y `sol_zonas`; el resto de la solicitud de servicio (ADR 0027) llega en su propia tarea.
2. **El ciclo de aprobación es uno solo para los cuatro sujetos** (`pendiente → aprobado ⇄ suspendido`, `pendiente → rechazado → pendiente`, cualquiera → `baja`). Vive en `Compartido` (`EstadoAprobacion`, `AccionAprobacion`, `TransicionesAprobacion`, `DecisionDeAprobacion`) porque lo usan `Proveedores` y `Flota` sin importarse entre sí. Cada módulo tiene su servicio en `Aplicacion/MaquinaEstados/` (`MaquinaEstadosAprobacion`, `MaquinaEstadosDocumento`, `MaquinaEstadosDron`) y es la única clase que escribe `estado` (invariante 7). Aprobar exige además los documentos obligatorios aprobados y vigentes; rechazar, suspender y dar de baja exigen motivo. Cada cambio inserta una fila de historial con autor, origen (`administrador` o `sistema`) y motivo, y anuncia `EstadoDeAprobacionCambiado`.
3. **El dron del marketplace reutiliza el catálogo operativo.** `flo_drones.ope_dron_id` enlaza con `ope_drones`, que es lo que usa la sesión de vuelo en el lote; no se duplica el catálogo. `ciclos_bateria` es el único dato de mantenimiento que se conserva (ADR 0029, punto 4): sube con un `UPDATE` atómico cuando el evento `RecargaRegistrada` (que ahora lleva el `dronId` de la sesión) llega de la sincronización. No cambia el contrato de la sincronización ni su idempotencia.
4. **Dos roles nuevos, `cliente` y `proveedor`, sin pantallas del panel.** Operan por la API con el token de dispositivo (`POST /api/auth/token`, un rol activo por sesión). El piloto y el auxiliar ganan los permisos de cargar documentos y, el piloto, registrar a su auxiliar. El encargado de operaciones y el dueño reciben los permisos de administración (`proveedores.*.ver|aprobar|suspender|dar_baja`, `proveedores.documento.revisar`, `flota.dron.*`, `flota.equipo.ver`). La puerta es el permiso del rol del token; el alcance (solo lo propio, un id ajeno responde 404) lo hacen los casos de uso (invariante 5 aplicada al proveedor).
5. **Registro público con límite.** `POST /api/proveedores/registro` y `POST /api/clientes/registro` crean la cuenta, su perfil y el estado inicial sin autenticación, bajo el limitador `registro-publico` (5 por minuto y 20 por hora por IP, compartido entre ambos). `GET /api/zonas` es lectura pública con su propio límite. El proveedor `propio` no se registra por la API: lo siembra `ProveedorPropioSeeder`. El independiente es su propio piloto y nace con su licencia `pendiente`.
6. **Las cuentas se crean por contrato.** `Seguridad` expone `RegistroCuentas` (crear una cuenta con roles, sin entregar `SecUser`) y `LecturaCuentas`; `Proveedores` y `Solicitudes` nunca escriben `sec_user`.
7. **Documentos en disco privado.** El archivo va a `config('proveedores.disco_documentos')` (nunca `public`) con la ruta por objeto del ADR 0026, y solo lo sirve el panel con el permiso `proveedores.documento.ver`. Un documento rechazado o vencido no se reescribe: se carga uno nuevo. El proceso diario `proveedores:vencimientos` (03:00, idempotente) avisa a los 30 y 7 días, marca los vencidos y suspende al sujeto con origen `sistema`; al aprobarse un documento vigente que cierra lo faltante, se levanta solo una suspensión puesta por el sistema, nunca una puesta por un administrador.
8. **Notificaciones.** El motor del ADR 0025 suma reglas para `EstadoDeAprobacionCambiado`, `ProveedorRegistrado`, `DocumentoPorVencer` y `DocumentoVencido`, con nueve tipos nuevos y cinco recursos (`proveedor`, `piloto`, `auxiliar`, `dron`, `documento`). Como las apps no tienen campana de panel, `GET /api/notificaciones` y `POST /api/notificaciones/{id}/leida` exponen los avisos in-app de la cuenta. Los catálogos cerrados (CHECK) se amplían en una migración propia, sin tocar el `create` consolidado.
9. **Panel.** Cola de aprobación en `panel/proveedores`, `panel/pilotos`, `panel/auxiliares`, `panel/drones-flota`, `panel/equipos-flota` y `panel/documentos`, con el patrón del panel (listado, ficha con línea de tiempo del historial, acciones con motivo en modal).
10. **Datos demo** (`ProveedoresDemoSeeder`, solo `local` y `staging`): Agrocom propio con sus pilotos, auxiliar, drones y equipos; un independiente aprobado; un tercero aprobado con un piloto y un dron pendientes y un dron suspendido por un seguro vencido; un independiente pendiente; un tercero rechazado; tres clientes.

## Alternativas descartadas

- **Un solo módulo `Marketplace`**: mezcla ciclos de vida distintos y rompe «un módulo escribe solo sus tablas».
- **Máquina de estados distinta por sujeto**: cuatro tablas idénticas que se desalinean; el ciclo es el mismo y la política también.
- **`ope_drones` como tabla del dron del marketplace**: no admite dueño, aprobación ni documentos sin ensuciar el catálogo que consume la sincronización.
- **Un rol por tipo de proveedor** (`independiente`, `tercero`): el tipo es un dato del proveedor, no una diferencia de permisos.

## Consecuencias

- Una migración por tabla nueva y una ampliación de `ntf_notificaciones`; ninguna destructiva.
- `RecargaRegistrada` suma un parámetro opcional: el listener de baterías existente no cambia.
- La negociación (ADR 0030) lee la aptitud de un equipo de `Flota` (`AptitudDeEquipo`: proveedor, piloto, dron y auxiliar aprobados, documentos vigentes y vigencia); no hay que duplicar esa regla.
- La revisión humana posterior a la integración cubre `MaquinaEstados*`, el proceso de vencimientos y el cambio en la sincronización de recargas.
