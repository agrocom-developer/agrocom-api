# ADR 0044 — Alta manual en el panel

**Estado:** aceptada, revisable · **Fecha:** 2026-11-25 · **Decide:** el dueño

## Contexto

Quien llega a la oficina (cliente, piloto, proveedor) no siempre se registra solo desde la app. El panel solo leía y cambiaba estados.

## Decisión

1. **Mismo caso de uso que la app.** El panel llama a `RegistrarCliente`, `RegistrarProveedor`, `RegistrarPiloto` y `RegistrarDron`; las reglas de validación son las del registro de la app (los `FormRequest` del panel las reutilizan). No hay reglas duplicadas.
2. **Mismo estado inicial.** El cliente nace activo, como el que se registra solo (no hay aprobación de clientes). El proveedor, el piloto y el dron nacen `pendientes` y los aprueba un administrador con sus documentos.
3. **Quién registra no aprueba.** `created_by` guarda a quien lo cargó y `SeparacionDeFunciones` impide que esa misma persona apruebe el registro; otra persona con permiso sí. `proveedores.separar_funciones` (`PROVEEDORES_SEPARAR_FUNCIONES`) lo apaga si el dueño decide lo contrario. La bitácora registra al administrador como actor.
4. **Permisos propios:** `solicitudes.cliente.crear`, `proveedores.proveedor.crear`, `proveedores.piloto.crear` y `flota.dron.crear`, para el administrador de operaciones, el dueño y el administrador de plataforma.
5. **Contraseña inicial.** La oficina la define y se la entrega a la persona.

## Consecuencias

Los documentos del proveedor y las fotos del dron se cargan aparte; sin ellos no se aprueba. El panel aún no tiene el formulario de carga de esos archivos.
