# ADR 0044 — Alta manual en el panel

**Estado:** aceptada, revisable; los puntos 2 a 4 fueron modificados por el ADR 0046 · **Fecha:** 2026-11-25 · **Decide:** el dueño

## Contexto

Quien llega a la oficina (cliente, piloto, proveedor) no siempre se registra solo desde la app. El panel solo leía y cambiaba estados. El alta crea una cuenta de app (cliente o proveedor) y respeta la unicidad por persona; el panel lo usa solo el personal de Agrocom.

## Decisión

1. **Mismo caso de uso que la app.** El panel llama a `RegistrarCliente`, `RegistrarProveedor`, `RegistrarPiloto` y `RegistrarDron`; las reglas de validación son las del registro de la app (los `FormRequest` del panel las reutilizan). No hay reglas duplicadas.
2. **Mismo estado inicial.** El cliente, el proveedor, el piloto y el dron nacen `pendientes`, como los que se registran solos. El administrador puede aprobarlos en el acto si la verificación de identidad está aprobada; si no hay documento de la persona, la verificación queda marcada «cargado por administrador» (ADR 0046, punto 16).
3. **Nadie aprueba su propia cuenta.** `created_by` guarda a quien lo cargó. `SeparacionDeFunciones` impide que el actor apruebe su propia cuenta (el registro público no se autoaprueba); **no impide** que el administrador apruebe lo que él dio de alta desde el panel. `proveedores.separar_funciones` (`PROVEEDORES_SEPARAR_FUNCIONES`) conserva ese alcance. La bitácora registra al administrador como actor.
4. **Permisos propios:** `solicitudes.cliente.crear`, `proveedores.proveedor.crear`, `proveedores.piloto.crear` y `flota.dron.crear`, para el administrador y, si el administrador se los asigna, para un operador.
5. **Contraseña inicial.** La oficina la define y se la entrega a la persona.

## Consecuencias

Los documentos del proveedor y las fotos del dron se cargan aparte; sin ellos no se aprueba. El panel aún no tiene el formulario de carga de esos archivos.
