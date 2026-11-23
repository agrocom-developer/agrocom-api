<?php

/*
|--------------------------------------------------------------------------
| Sanctum — token por dispositivo para las apps de campo (HU-03)
|--------------------------------------------------------------------------
|
| Sanctum se usa acá en su modo más chico: tokens de API para
| `agrocom-field` (ADR 0008). NO se usa el modo SPA con cookies de sesión —
| el panel es Laravel full-stack con Blade/Livewire (ADR 0002), no una SPA,
| así que nada de este sistema necesita `/sanctum/csrf-cookie` ni el
| middleware `EnsureFrontendRequestsAreStateful`.
|
| Solo se declaran las claves que cambian respecto del default del paquete;
| el resto se resuelve por `mergeConfigFrom`.
|
*/

return [

    /*
    | Guards de sesión que Sanctum consulta ANTES de mirar el bearer token.
    |
    | Vacío a propósito. El default del paquete es `['web']`: con eso, un
    | request a `/api/*` hecho desde un navegador con la sesión del panel
    | abierta quedaría autenticado por esa cookie, sin token de dispositivo —
    | exactamente lo que el ADR 0008 prohíbe ("ningún middleware ni guard se
    | comparte entre ambos archivos de rutas"). Con la lista vacía, la API de
    | campo autentica ÚNICAMENTE por `Authorization: Bearer`.
    */
    'guard' => [],

    /*
    | Minutos de vigencia de un token, contados desde que se emite. `null` = no
    | caduca por tiempo.
    |
    | Por defecto 43200 (30 días): cubre una campaña larga sin señal en el lote
    | y limita la ventana de un token robado. Al vencer, la app pide el login
    | de nuevo en cuanto recupera conexión; lo registrado sin conexión queda en
    | el outbox y se envía con el token nuevo. Se ajusta con
    | `SANCTUM_EXPIRATION` (vacío o `null` desactiva la caducidad). Ojo: este
    | valor se aplica a cada token según su fecha de emisión, así que bajarlo
    | caduca de golpe los tokens ya emitidos más viejos que el nuevo plazo.
    */
    'expiration' => is_numeric(env('SANCTUM_EXPIRATION', 43200)) ? (int) env('SANCTUM_EXPIRATION', 43200) : null,

    /*
    | Prefijo de los tokens emitidos. Un prefijo reconocible permite que los
    | escáneres de secretos (GitHub, GitLab) detecten un token filtrado en un
    | repositorio y avisen. Vacío por default para no invalidar tokens ya
    | emitidos al activarlo.
    */
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    /*
    | Ruta `/sanctum/csrf-cookie`: solo la necesita el modo SPA, que este
    | proyecto no usa. Apagada para no publicar un endpoint sin consumidor.
    */
    'routes' => false,

    /*
    | Registro automático de `last_used_at` por parte de Sanctum: apagado, y
    | reemplazado por un listener propio en `SeguridadServiceProvider`.
    |
    | Sanctum lo escribe con un `save()` normal, que dispara los eventos de
    | Eloquent y con ellos el trait `RegistraAutoria` (ADR 0007), que llama a
    | `Auth::id()`. Dentro de `/api/*` el guard por defecto es `sanctum`, que
    | en ese momento TODAVÍA está resolviendo el usuario: pedirle el id lo
    | hace re-entrar, volver a buscar el token, volver a escribir
    | `last_used_at`... hasta agotar la memoria en el primer request de cada
    | token. El listener propio lo escribe con `saveQuietly()`, que además es
    | lo correcto en sí: usar la app no es una edición del token, y no debe
    | reescribir `updated_by` (que dice quién lo emitió o revocó).
    */
    'last_used_at' => false,

];
