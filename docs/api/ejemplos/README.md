# Ejemplos reales del contrato de API

Cada archivo `*.json` de esta carpeta es un intercambio real (petición y respuesta) capturado contra la API con `bin/generar-ejemplos`. Las apps móviles (`cliente` y `piloto` de `agrocom-field`) los usan como fixtures de sus pruebas de contrato: es lo que el servidor devuelve, no una maqueta.

Los archivos son generados: no se editan a mano. Se regeneran con `bin/generar-ejemplos` cada vez que cambia un endpoint, y el cambio entra en el mismo PR que el contrato (`docs/api/openapi.yaml`).

## Formato

```json
{
    "operationId": "crearSolicitud",
    "descripcion": "Qué intercambio es, en una frase.",
    "peticion": {
        "metodo": "POST",
        "ruta": "/api/solicitudes",
        "autenticada": true,
        "cuerpo": { }
    },
    "respuesta": {
        "estado": 201,
        "cuerpo": { }
    }
}
```

- `operationId` es el de la operación en `docs/api/openapi.yaml`; un mismo `operationId` puede tener varios ejemplos (éxito, validación, recurso ajeno, reintento).
- `ruta` lleva los identificadores ya resueltos (`/api/solicitudes/12/publicar`).
- Los tokens se sustituyen por el marcador `12|<token-del-dispositivo>`: un fixture nunca guarda una credencial viva.
- Hectáreas, litros y montos viajan como cadena decimal exacta (`"100.0000"`), nunca como número.
- Los instantes (`vence_en`, `creada_en`) cambian en cada regeneración; las apps no deben comparar su valor literal.

## Qué cubren

- Sesión por token de dispositivo: sesión de cliente y de piloto, credenciales inválidas, token ausente, revocado y de un rol equivocado, cierre de sesión y dispositivos.
- Cliente: zonas, terrenos y solicitudes (crear, reintentar con el mismo `uuid_cliente`, estimar, publicar, cambiar precio, cancelar, recurso ajeno).
- Proveedor y flota: perfil, pilotos, documentos, drones y equipos.
- Mercado y negociación: mapa de solicitudes abiertas con filtros, resumen por zona, detalle previo a ofertar, ofertar (y reintento idempotente), comparar, contraofertar, aceptar y aceptar otra vez.
- Ejecución: inicio, avance, foto de avance, ubicación del piloto (enviar y ver), cierre, chat y preferencias de aviso.
- Posventa y avisos: calificaciones, disputas y notificaciones.
- Sincronización del lote offline: catálogo completo e incremental, lote nuevo, replay del mismo lote (todos `duplicado`), rechazo parcial, lote sin registros y sin token; y la subida de evidencia.

## Cómo se validan

`tests/Unit/Contrato/EjemplosDeApiTest.php` comprueba que cada ejemplo corresponde a una operación del contrato con su método y su ruta, que su estado HTTP está documentado, que sus cuerpos cumplen los esquemas de `openapi.yaml` (sin campos sin documentar, con nullables y enums respetados), que ninguno guarda una credencial viva y que los decimales viajan como cadena. `tests/Feature/Contrato/RutasDelContratoTest.php` comprueba que las rutas registradas y las documentadas son la misma superficie.

## Regenerar

```bash
bin/generar-ejemplos
```

El script levanta un PostgreSQL desechable en Docker, migra, siembra el catálogo y los datos de demostración, recorre los flujos y destruye el contenedor. Para usar una base propia (siempre vacía y desechable, porque el script escribe en ella): `GENERAR_EJEMPLOS_EN_HOST=1 DB_HOST=... DB_PORT=... DB_DATABASE=... DB_USERNAME=... DB_PASSWORD=... bin/generar-ejemplos`.
