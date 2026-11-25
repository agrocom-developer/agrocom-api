<?php

/*
 * Genera los ejemplos reales de `docs/api/ejemplos/`: ejecuta el recorrido de
 * las apps (cliente, piloto, auxiliar) contra la API de verdad, request por
 * request, y guarda cada intercambio como un fixture JSON.
 *
 * No va dentro de `app/`: es una herramienta de contrato, no código de
 * producción. Lo invoca `bin/generar-ejemplos`, que apunta a una base
 * PostgreSQL DESECHABLE con `DatabaseSeeder` y `Demo\DemoSeeder` ya corridos
 * (el script escribe en esa base: solicitudes, ofertas, sesiones).
 *
 *   php bin/lib/generar-ejemplos.php <raíz del repo> <directorio de salida>
 *
 * Los tokens reales se sustituyen por un marcador: un fixture nunca guarda una
 * credencial viva.
 */

use Database\Seeders\Demo\PersonalDemoSeeder;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

$raiz = $argv[1] ?? dirname(__DIR__, 2);
$salida = $argv[2] ?? $raiz.'/docs/api/ejemplos';

require $raiz.'/vendor/autoload.php';

$app = require $raiz.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

if (! app()->environment(['local', 'testing'])) {
    fwrite(STDERR, "Se niega a correr fuera de local/testing: escribe en la base.\n");
    exit(1);
}

const MARCADOR_TOKEN = '12|<token-del-dispositivo>';
const UUID_DISPOSITIVO = '6f1d0a2e-1f34-4c9f-9a8b-2b7c1d5e0f31';

$clave = PersonalDemoSeeder::PASSWORD;

if (! is_dir($salida)) {
    mkdir($salida, 0775, true);
}

foreach (glob($salida.'/*.json') ?: [] as $viejo) {
    unlink($viejo);
}

$guardados = 0;
$fallos = [];

/**
 * @param  array<string, mixed>|null  $cuerpo
 * @param  array<string, UploadedFile>  $archivos
 * @return array{0: int, 1: mixed}
 */
function llamar(string $metodo, string $ruta, ?array $cuerpo = null, ?string $token = null, array $archivos = []): array
{
    global $kernel;

    app('auth')->forgetGuards();

    $servidor = ['HTTP_ACCEPT' => 'application/json'];
    if ($token !== null) {
        $servidor['HTTP_AUTHORIZATION'] = "Bearer {$token}";
    }

    if ($archivos === []) {
        $servidor['CONTENT_TYPE'] = 'application/json';
        $peticion = Request::create($ruta, $metodo, [], [], [], $servidor, $cuerpo === null ? null : json_encode($cuerpo));
    } else {
        $peticion = Request::create($ruta, $metodo, $cuerpo ?? [], [], $archivos, $servidor);
    }

    $respuesta = $kernel->handle($peticion);
    $texto = $respuesta->getContent();
    $json = json_decode((string) $texto, true);

    return [$respuesta->getStatusCode(), $json ?? ($texto === '' ? null : $texto)];
}

/**
 * Llama y guarda el intercambio como fixture. Devuelve [estado, cuerpo].
 *
 * @param  array<string, mixed>|null  $cuerpo
 * @return array{0: int, 1: mixed}
 */
function registrar(string $nombre, string $operacion, string $descripcion, string $metodo, string $ruta, ?array $cuerpo = null, ?string $token = null, ?int $esperado = null): array
{
    global $salida, $guardados, $fallos;

    [$estado, $respuesta] = llamar($metodo, $ruta, $cuerpo, $token);

    if ($esperado !== null && $estado !== $esperado) {
        $fallos[] = "{$nombre}: se esperaba {$esperado} y llegó {$estado} ".substr(json_encode($respuesta, JSON_UNESCAPED_UNICODE) ?: '', 0, 300);

        return [$estado, $respuesta];
    }

    $fixture = [
        'operationId' => $operacion,
        'descripcion' => $descripcion,
        'peticion' => array_filter([
            'metodo' => $metodo,
            'ruta' => $ruta,
            'autenticada' => $token !== null,
            'cuerpo' => $cuerpo,
        ], static fn ($valor) => $valor !== null),
        'respuesta' => [
            'estado' => $estado,
            'cuerpo' => sanear($respuesta),
        ],
    ];

    file_put_contents(
        "{$salida}/{$nombre}.json",
        json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)."\n",
    );
    $guardados++;

    return [$estado, $respuesta];
}

/**
 * Como {@see registrar()}, pero con un archivo de imagen (multipart). Guarda el intercambio sin el binario.
 *
 * @param  array<string, mixed>  $cuerpo
 * @return array{0: int, 1: mixed}
 */
function registrarFoto(string $nombre, string $operacion, string $descripcion, string $metodo, string $ruta, array $cuerpo, string $token, int $esperado, int $rojo = 90): array
{
    global $salida, $guardados, $fallos;

    $archivo = tempnam(sys_get_temp_dir(), 'foto');
    $lienzo = imagecreatetruecolor(320, 240);
    imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, $rojo, 140, 60));
    imagejpeg($lienzo, $archivo, 70);

    [$estado, $respuesta] = llamar($metodo, $ruta, $cuerpo, $token, ['archivo' => new UploadedFile($archivo, 'foto.jpg', 'image/jpeg', null, true)]);

    if ($estado !== $esperado) {
        $fallos[] = "{$nombre}: se esperaba {$esperado} y llegó {$estado} ".substr(json_encode($respuesta, JSON_UNESCAPED_UNICODE) ?: '', 0, 300);

        return [$estado, $respuesta];
    }

    file_put_contents("{$salida}/{$nombre}.json", json_encode([
        'operationId' => $operacion,
        'descripcion' => $descripcion,
        'peticion' => ['metodo' => $metodo, 'ruta' => $ruta, 'autenticada' => true, 'cuerpo' => $cuerpo === [] ? null : $cuerpo, 'multipart' => ['archivo' => 'foto.jpg (image/jpeg)']],
        'respuesta' => ['estado' => $estado, 'cuerpo' => sanear($respuesta)],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)."\n");
    $guardados++;

    return [$estado, $respuesta];
}

/** Sustituye toda credencial viva por el marcador. */
function sanear(mixed $valor): mixed
{
    if (is_array($valor)) {
        foreach ($valor as $clave => $interno) {
            $valor[$clave] = $clave === 'token' && is_string($interno) ? MARCADOR_TOKEN : sanear($interno);
        }
    }

    return $valor;
}

/** @return array{0: string, 1: array<string, mixed>} */
function ingresar(string $usuario, string $clave, ?string $nombreDispositivo = null): array
{
    [$estado, $cuerpo] = llamar('POST', '/api/auth/token', [
        'username' => $usuario,
        'password' => $clave,
        'uuid_dispositivo' => UUID_DISPOSITIVO,
        'nombre_dispositivo' => $nombreDispositivo,
    ]);

    if ($estado !== 201) {
        throw new RuntimeException("No se pudo ingresar como {$usuario}: {$estado}");
    }

    return [$cuerpo['token'], $cuerpo];
}

/**
 * @param  array<int, array<string, mixed>>  $lista
 * @param  callable(array<string, mixed>): bool  $criterio
 * @return array<string, mixed>|null
 */
function buscar(array $lista, callable $criterio): ?array
{
    foreach ($lista as $elemento) {
        if ($criterio($elemento)) {
            return $elemento;
        }
    }

    return null;
}

$desde = Carbon::now()->addDays(20)->toDateString();
$hasta = Carbon::now()->addDays(26)->toDateString();

try {
    // ------------------------------------------------------------------ acceso
    registrar('seguridad-credenciales-invalidas', 'emitirTokenDispositivo', 'Credenciales incorrectas.', 'POST', '/api/auth/token', [
        'username' => 'rodrigo.suarez', 'password' => 'incorrecta', 'uuid_dispositivo' => UUID_DISPOSITIVO,
    ], null, 422);

    registrar('seguridad-token-sin-datos', 'emitirTokenDispositivo', 'Falta el uuid del dispositivo y la contraseña.', 'POST', '/api/auth/token', [
        'username' => 'rodrigo.suarez',
    ], null, 422);

    registrar('seguridad-sin-token', 'verSesionCampo', 'Una ruta protegida sin token responde 401.', 'GET', '/api/auth/sesion', null, null, 401);

    registrar('distribucion-version', 'obtenerVersionVigente', 'Versión mínima y vigente del APK; no pide token.', 'GET', '/api/version', null, null, 200);

    // ----------------------------------------------------------------- cliente
    [$respuestaEmision, $emision] = (function () use ($clave) {
        [$estado, $cuerpo] = registrar('seguridad-token-cliente', 'emitirTokenDispositivo', 'El cliente ingresa; el token se muestra una sola vez.', 'POST', '/api/auth/token', [
            'username' => 'agro.sanrafael', 'password' => $clave, 'uuid_dispositivo' => UUID_DISPOSITIVO, 'nombre_dispositivo' => 'Celular del cliente',
        ], null, 201);

        return [$estado, $cuerpo];
    })();
    $tokenCliente = $emision['token'];

    registrar('seguridad-sesion-cliente', 'verSesionCampo', 'Quién es el dispositivo y con qué rol opera.', 'GET', '/api/auth/sesion', null, $tokenCliente, 200);
    registrar('clientes-mi', 'verMiCliente', 'Perfil del cliente de la cuenta.', 'GET', '/api/clientes/mi', null, $tokenCliente, 200);
    [, $contactos] = registrar('contactos-lista', 'listarMisContactos', 'Contactos de referencia vigentes del cliente, el principal primero.', 'GET', '/api/clientes/mi/contactos', null, $tokenCliente, 200);
    $contactoElegidoId = $contactos['contactos'][1]['id'] ?? ($contactos['contactos'][0]['id'] ?? null);
    [, $contactoNuevo] = registrar('contactos-agregar', 'agregarContacto', 'Agrega una persona de referencia; el primer contacto de un cliente queda como principal.', 'POST', '/api/clientes/mi/contactos', [
        'nombre' => 'Elena Chávez Soliz', 'telefono' => '+591 70011223', 'cargo' => 'Administradora',
    ], $tokenCliente, 201);
    registrar('contactos-agregar-invalido', 'agregarContacto', 'Validación: faltan el nombre y el teléfono.', 'POST', '/api/clientes/mi/contactos', [
        'cargo' => 'Administradora',
    ], $tokenCliente, 422);
    $contactoNuevoId = $contactoNuevo['contacto']['id'] ?? null;
    if ($contactoNuevoId !== null) {
        registrar('contactos-actualizar', 'actualizarContacto', 'Corrige un contacto propio; marcarlo principal releva al anterior.', 'PUT', "/api/clientes/mi/contactos/{$contactoNuevoId}", [
            'nombre' => 'Elena Chávez Soliz', 'telefono' => '+591 70011224', 'cargo' => 'Administradora general', 'es_principal' => false,
        ], $tokenCliente, 200);
        registrar('contactos-baja', 'darDeBajaContacto', 'Baja lógica de un contacto propio; las solicitudes ya creadas conservan a la persona elegida.', 'DELETE', "/api/clientes/mi/contactos/{$contactoNuevoId}", null, $tokenCliente, 204);
    }
    registrar('contactos-ajeno', 'actualizarContacto', 'Un id que no es del cliente responde 404, nunca 403 (invariante 5).', 'PUT', '/api/clientes/mi/contactos/999999', [
        'nombre' => 'Otra persona', 'telefono' => '+591 70011225',
    ], $tokenCliente, 404);
    registrar('clientes-zonas', 'listarZonas', 'Catálogo de zonas de operación.', 'GET', '/api/zonas', null, $tokenCliente, 200);
    registrar('terrenos-lista', 'listarTerrenos', 'Terrenos del cliente.', 'GET', '/api/terrenos', null, $tokenCliente, 200);

    [, $terreno] = registrar('terrenos-crear', 'crearTerreno', 'Alta de un terreno; las hectáreas viajan como decimal exacto en string.', 'POST', '/api/terrenos', [
        'nombre' => 'Lote Los Álamos',
        'hectareas' => '120.5000',
        'cultivo' => 'Soya',
        'referencias_acceso' => 'Camino vecinal, portón azul a 2 km de la ruta.',
        'acceso_dificil' => false,
    ], $tokenCliente, 201);
    $terrenoId = $terreno['terreno']['id'] ?? null;

    registrar('terrenos-sin-nombre', 'crearTerreno', 'Validación: faltan datos obligatorios.', 'POST', '/api/terrenos', [
        'hectareas' => '0',
    ], $tokenCliente, 422);

    if ($terrenoId !== null) {
        registrar('terrenos-ver', 'verTerreno', 'Detalle de un terreno propio.', 'GET', "/api/terrenos/{$terrenoId}", null, $tokenCliente, 200);
        registrar('terrenos-actualizar', 'actualizarTerreno', 'Corrige los datos de un terreno propio.', 'PUT', "/api/terrenos/{$terrenoId}", [
            'nombre' => 'Lote Los Álamos', 'hectareas' => '118.2500', 'cultivo' => 'Soya',
        ], $tokenCliente, 200);

        registrarFoto('terrenos-foto-subir', 'subirFotoDeTerreno', 'El cliente sube una foto del estado de su terreno (multipart, menos de 300 KB); el piloto la ve antes de ofertar.', 'POST', "/api/terrenos/{$terrenoId}/fotos", ['descripcion' => 'Borde norte con maleza alta'], $tokenCliente, 201);
        registrar('terrenos-ver-con-fotos', 'verTerreno', 'El terreno trae sus fotos con URL firmada de corta vida.', 'GET', "/api/terrenos/{$terrenoId}", null, $tokenCliente, 200);
    }

    registrar('referencias-precio', 'precioDeReferencia', 'Precio por hectárea de referencia para proponer: de la zona, la macrorregión o el general del tipo.', 'GET', '/api/referencias/precio?tipo_aplicacion=herbicida&macrorregion=chiquitania', null, $tokenCliente, 200);
    registrar('referencias-precio-invalido', 'precioDeReferencia', 'Validación: tipo de aplicación inexistente.', 'GET', '/api/referencias/precio?tipo_aplicacion=riego', null, $tokenCliente, 422);

    registrar('solicitudes-lista', 'listarSolicitudes', 'Solicitudes del cliente, la más reciente primero.', 'GET', '/api/solicitudes', null, $tokenCliente, 200);

    $uuidSolicitud = '5b0f6a52-0c1e-4b6c-9d57-7f9d3a1c2e01';
    $cuerpoSolicitud = [
        'uuid_cliente' => $uuidSolicitud,
        'terreno_id' => $terrenoId,
        'contacto_id' => $contactoElegidoId,
        'tipo_aplicacion' => 'herbicida',
        'hectareas_solicitadas' => '100.0000',
        'producto_indicado' => 'Glifosato 480 SL, 2 L/ha',
        'aporta_producto' => 'cliente',
        'ventana_desde' => $desde,
        'ventana_hasta' => $hasta,
        'urgente' => false,
        'precio_ha_propuesto' => '125.0000',
        'observaciones' => 'Entrar por el portón sur.',
    ];

    [, $solicitud] = registrar('solicitudes-crear', 'crearSolicitud', 'Crea una solicitud en borrador con su uuid_cliente generado en el dispositivo y el precio por hectárea que propone el cliente.', 'POST', '/api/solicitudes', $cuerpoSolicitud, $tokenCliente, 201);
    $solicitudId = $solicitud['solicitud']['id'] ?? null;

    registrar('solicitudes-crear-reintento', 'crearSolicitud', 'Reintento con el mismo uuid_cliente: no duplica la solicitud (idempotencia).', 'POST', '/api/solicitudes', $cuerpoSolicitud, $tokenCliente);

    registrar('solicitudes-crear-invalida', 'crearSolicitud', 'Validación: ventana de fechas invertida y hectáreas en cero.', 'POST', '/api/solicitudes', [
        'uuid_cliente' => '5b0f6a52-0c1e-4b6c-9d57-7f9d3a1c2e99',
        'terreno_id' => $terrenoId,
        'tipo_aplicacion' => 'herbicida',
        'hectareas_solicitadas' => '0',
        'producto_indicado' => 'Glifosato',
        'aporta_producto' => 'cliente',
        'ventana_desde' => $hasta,
        'ventana_hasta' => $desde,
    ], $tokenCliente, 422);

    if ($solicitudId !== null) {
        registrar('solicitudes-estimacion', 'estimarSolicitud', 'Monto estimado con el precio propuesto, antes de abrirla.', 'GET', "/api/solicitudes/{$solicitudId}/estimacion", null, $tokenCliente, 200);
        registrar('solicitudes-publicar', 'publicarSolicitud', 'La deja abierta a ofertas: los pilotos la ven en el mapa y reciben el aviso de su zona.', 'POST', "/api/solicitudes/{$solicitudId}/publicar", null, $tokenCliente, 200);
        registrar('solicitudes-precio', 'cambiarPrecioSolicitud', 'El cliente cambia el precio propuesto mientras la solicitud sigue abierta; las ofertas vigentes no cambian.', 'POST', "/api/solicitudes/{$solicitudId}/precio", ['precio_ha_propuesto' => '128.0000'], $tokenCliente, 200);
        registrar('solicitudes-ver', 'verSolicitud', 'Detalle con su historial de estados.', 'GET', "/api/solicitudes/{$solicitudId}", null, $tokenCliente, 200);
    }

    registrar('solicitudes-ajena', 'verSolicitud', 'Un id que no es del cliente responde 404, nunca 403 (invariante 5).', 'GET', '/api/solicitudes/999999', null, $tokenCliente, 404);

    // El cliente tiene servicios finalizados y calificados en la demo.
    [, $lista] = llamar('GET', '/api/solicitudes?per_page=50', null, $tokenCliente);
    $filas = $lista['solicitudes'] ?? [];
    $calificada = buscar($filas, fn ($fila) => ($fila['estado'] ?? '') === 'calificada');

    if ($calificada !== null) {
        registrar('calificaciones-servicio', 'verCalificacionesDelServicio', 'Calificaciones de un servicio calificado.', 'GET', "/api/solicitudes/{$calificada['id']}/calificaciones", null, $tokenCliente, 200);
    }

    registrar('disputas-lista', 'listarDisputas', 'Disputas del cliente.', 'GET', '/api/disputas', null, $tokenCliente, 200);
    registrar('notificaciones-lista', 'listarNotificaciones', 'Bandeja de notificaciones.', 'GET', '/api/notificaciones', null, $tokenCliente, 200);

    // ------------------------------------------------------------------ piloto
    [$tokenPiloto] = ingresar('rodrigo.suarez', $clave);
    registrar('seguridad-token-piloto', 'emitirTokenDispositivo', 'El piloto ingresa con su rol.', 'POST', '/api/auth/token', [
        'username' => 'rodrigo.suarez', 'password' => $clave, 'uuid_dispositivo' => UUID_DISPOSITIVO, 'nombre_dispositivo' => 'Control remoto del dron',
    ], null, 201);
    [$tokenPiloto, $emisionPiloto] = ingresar('rodrigo.suarez', $clave, 'Control remoto del dron');

    registrar('seguridad-sesion-piloto', 'verSesionCampo', 'Sesión vigente del piloto.', 'GET', '/api/auth/sesion', null, $tokenPiloto, 200);
    registrar('seguridad-dispositivos', 'listarDispositivosPropios', 'Dispositivos con token vivo de la cuenta.', 'GET', '/api/dispositivos', null, $tokenPiloto, 200);
    registrar('proveedores-mi-piloto', 'verMiProveedor', 'El piloto propio de Agrocom ve en solo lectura el proveedor al que pertenece.', 'GET', '/api/proveedores/mi', null, $tokenPiloto, 200);
    registrar('proveedores-drones-piloto', 'listarDrones', 'El piloto ve los drones de su equipo.', 'GET', '/api/proveedores/drones', null, $tokenPiloto, 200);
    registrar('dispositivos-push-registrar', 'registrarDispositivoPush', 'La app registra el token de push de su aparato al iniciar sesión.', 'POST', '/api/dispositivos', ['token' => 'fcm:ejemplo-token-de-push', 'plataforma' => 'android'], $tokenPiloto, 201);
    registrar('dispositivos-push-repetido', 'registrarDispositivoPush', 'Repetir el token no duplica: responde 200.', 'POST', '/api/dispositivos', ['token' => 'fcm:ejemplo-token-de-push', 'plataforma' => 'android'], $tokenPiloto, 200);
    registrar('dispositivos-push-plataforma-invalida', 'registrarDispositivoPush', 'Validación: plataforma desconocida.', 'POST', '/api/dispositivos', ['token' => 'fcm:otro', 'plataforma' => 'web'], $tokenPiloto, 422);
    registrar('dispositivos-push-retirar', 'retirarDispositivoPush', 'Al cerrar sesión la app retira el token de push.', 'DELETE', '/api/dispositivos/'.rawurlencode('fcm:ejemplo-token-de-push'), null, $tokenPiloto, 204);
    registrar('dispositivos-push-retirar-ajeno', 'retirarDispositivoPush', 'Un token que no existe o es de otra cuenta responde 404.', 'DELETE', '/api/dispositivos/'.rawurlencode('fcm:ejemplo-token-de-push'), null, $tokenPiloto, 404);
    registrarFoto('perfil-foto-subir', 'subirFotoDePerfil', 'La cuenta sube su foto de perfil (multipart, menos de 300 KB).', 'PUT', '/api/perfil/foto', [], $tokenPiloto, 200, 140);
    registrar('perfil-foto-quitar', 'quitarFotoDePerfil', 'Quita la foto de perfil.', 'DELETE', '/api/perfil/foto', null, $tokenPiloto, 204);

    [, $catalogo] = registrar('sync-catalogo-completo', 'obtenerCatalogoSincronizacion', 'Primer pull: todo lo vigente, con el cursor de continuación.', 'GET', '/api/sync/catalogo', null, $tokenPiloto, 200);

    if (is_string($catalogo['cursor'] ?? null)) {
        registrar('sync-catalogo-incremental', 'obtenerCatalogoSincronizacion', 'Pull incremental desde el cursor: sin cambios, listas vacías.', 'GET', '/api/sync/catalogo?desde='.urlencode($catalogo['cursor']), null, $tokenPiloto, 200);
    }

    [, $catalogo] = registrar('sync-catalogo-con-servicio', 'obtenerCatalogoSincronizacion', 'Pull completo: las solicitudes adjudicadas o en ejecución del equipo del operario aparecen en `servicios_asignados`.', 'GET', '/api/sync/catalogo', null, $tokenPiloto, 200);
    $servicio = buscar($catalogo['servicios_asignados'] ?? [], fn ($fila) => in_array($fila['estado'] ?? '', ['adjudicada', 'en_ejecucion'], true));
    // La persona del piloto autenticado: la sesión declara a quien opera, y el servidor lo verifica.
    $personaId = $emisionPiloto['usuario']['persona_id'] ?? null;
    $persona = $personaId === null ? null : ['id' => $personaId];

    // -------------------------------------------------------------- lote (sync)
    if ($servicio !== null && $persona !== null) {
        $uuidTrabajo = '0a1b2c3d-1111-4a11-8a11-000000000001';
        $uuidSesion = '0a1b2c3d-1111-4a11-8a11-000000000002';
        $uuidCondiciones = '0a1b2c3d-1111-4a11-8a11-000000000003';
        $uuidCierreSesion = '0a1b2c3d-1111-4a11-8a11-000000000004';

        $lote = ['registros' => [
            ['tipo' => 'cierre_sesion', 'uuid_cliente' => $uuidCierreSesion, 'sesion_uuid_cliente' => $uuidSesion, 'fin' => '2026-11-24T11:40:00-04:00', 'hectareas_declaradas' => '42.5000', 'litros_consumidos' => '510.000', 'motivo_cierre' => 'completado'],
            ['tipo' => 'trabajo', 'uuid_cliente' => $uuidTrabajo, 'solicitud_id' => $servicio['id'], 'inicio' => '2026-11-24T08:00:00-04:00'],
            ['tipo' => 'condiciones', 'uuid_cliente' => $uuidCondiciones, 'sesion_uuid_cliente' => $uuidSesion, 'momento' => 'inicio_sesion', 'viento_kmh' => '9.00', 'temperatura_c' => '24.00', 'humedad_pct' => '62.00'],
            ['tipo' => 'sesion', 'uuid_cliente' => $uuidSesion, 'trabajo_uuid_cliente' => $uuidTrabajo, 'secuencia' => 1, 'piloto_id' => $persona['id'], 'inicio' => '2026-11-24T08:05:00-04:00', 'hectareas_declaradas' => '0'],
        ]];

        registrar('sync-lote-primero', 'sincronizarLote', 'Lote en desorden: el servidor aplica en orden causal (trabajo, sesión, condiciones, cierre) sin importar el orden del arreglo.', 'POST', '/api/sync', $lote, $tokenPiloto, 200);
        registrar('sync-lote-replay', 'sincronizarLote', 'El mismo lote reenviado: cada registro responde `duplicado` y el estado final no cambia (invariante 1).', 'POST', '/api/sync', $lote, $tokenPiloto, 200);

        registrar('sync-lote-rechazo-parcial', 'sincronizarLote', 'Un registro inválido se rechaza con motivo sin frenar al resto del lote.', 'POST', '/api/sync', ['registros' => [
            ['tipo' => 'sesion', 'uuid_cliente' => '0a1b2c3d-1111-4a11-8a11-000000000009', 'trabajo_uuid_cliente' => '00000000-0000-4000-8000-000000000000', 'secuencia' => 1, 'piloto_id' => $persona['id'], 'inicio' => '2026-11-24T09:00:00-04:00'],
            ['tipo' => 'trabajo', 'uuid_cliente' => $uuidTrabajo, 'solicitud_id' => $servicio['id'], 'inicio' => '2026-11-24T08:00:00-04:00'],
        ]], $tokenPiloto, 200);
    } else {
        $fallos[] = 'No hay servicio asignado ni persona piloto en el catálogo: se omite el lote.';
    }

    registrar('sync-lote-sin-registros', 'sincronizarLote', 'Un lote sin `registros` es el único caso que responde 422.', 'POST', '/api/sync', [], $tokenPiloto, 422);
    registrar('sync-lote-sin-token', 'sincronizarLote', 'Sin token el lote no se procesa.', 'POST', '/api/sync', ['registros' => []], null, 401);

    // Evidencia: foto chica comprimida, en cola separada del lote.
    $imagen = tempnam(sys_get_temp_dir(), 'evid');
    $lienzo = imagecreatetruecolor(64, 64);
    imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 90, 140, 60));
    imagejpeg($lienzo, $imagen, 70);
    $archivo = new UploadedFile($imagen, 'campo.jpg', 'image/jpeg', null, true);

    $evidencia = (function () use ($archivo, $tokenPiloto, $salida, &$guardados, &$fallos) {
        $peticion = [
            'uuid_cliente' => '0a1b2c3d-1111-4a11-8a11-0000000000e1',
            'tipo' => 'imagen_campo',
            'fecha' => '2026-11-24T11:41:00-04:00',
        ];
        [$estado, $cuerpo] = llamar('POST', '/api/evidencias', $peticion, $tokenPiloto, ['archivo' => $archivo]);

        if ($estado === 200 && ($cuerpo['estado'] ?? '') === 'aplicado') {
            file_put_contents("{$salida}/operaciones-evidencia.json", json_encode([
                'operationId' => 'subirEvidencia',
                'descripcion' => 'Sube una evidencia comprimida (multipart); va en una cola separada del lote.',
                'peticion' => ['metodo' => 'POST', 'ruta' => '/api/evidencias', 'autenticada' => true, 'cuerpo' => $peticion, 'multipart' => ['archivo' => 'campo.jpg (image/jpeg)']],
                'respuesta' => ['estado' => $estado, 'cuerpo' => sanear($cuerpo)],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)."\n");
            $guardados++;
        } else {
            $fallos[] = "operaciones-evidencia: {$estado} ".substr(json_encode($cuerpo, JSON_UNESCAPED_UNICODE) ?: '', 0, 300);
        }

        return $estado;
    })();

    // --------------------------------------------------------------- proveedor
    $rolProveedor = (int) DB::table('sec_role')->where('name', 'proveedor')->value('id');
    $rolPiloto = (int) DB::table('sec_role')->where('name', 'piloto')->value('id');
    $emitir = function (string $usuario, int $rol, string $dispositivo, string $nombre) use ($clave): string {
        [$estado, $emision] = llamar('POST', '/api/auth/token', [
            'username' => $usuario, 'password' => $clave, 'uuid_dispositivo' => $dispositivo, 'nombre_dispositivo' => $nombre, 'role_id' => $rol,
        ]);
        if ($estado !== 201) {
            throw new RuntimeException("No se pudo ingresar como {$usuario}: {$estado}");
        }

        return $emision['token'];
    };
    $tokenProveedor = $emitir('carlos.mamani', $rolProveedor, UUID_DISPOSITIVO, 'Celular del proveedor');
    $tokenPilotoCarlos = $emitir('carlos.mamani', $rolPiloto, '7a2e1b3f-2a45-4d0a-8b9c-3c8d2e6f1a43', 'Control remoto del dron');
    registrar('seguridad-token-proveedor', 'emitirTokenDispositivo', 'El mismo usuario elige con qué rol opera mediante `role_id`.', 'POST', '/api/auth/token', [
        'username' => 'carlos.mamani', 'password' => $clave, 'uuid_dispositivo' => '7a2e1b3f-2a45-4d0a-8b9c-3c8d2e6f1a42', 'nombre_dispositivo' => 'Celular del proveedor', 'role_id' => $rolProveedor,
    ], null, 201);

    [, $mi] = registrar('proveedores-mi', 'verMiProveedor', 'El proveedor de la cuenta y su estado de aprobación.', 'GET', '/api/proveedores/mi', null, $tokenProveedor, 200);
    registrar('proveedores-documentos', 'listarDocumentosProveedor', 'Documentos del proveedor: se consultan por sujeto.', 'GET', '/api/proveedores/documentos?sujeto_tipo=proveedor&sujeto_id='.($mi['proveedor']['id'] ?? 0), null, $tokenProveedor, 200);
    [, $drones] = registrar('flota-drones', 'listarDrones', 'Drones del proveedor, con su modelo.', 'GET', '/api/proveedores/drones', null, $tokenProveedor, 200);
    registrar('flota-equipos', 'listarEquipos', 'Equipos del proveedor.', 'GET', '/api/proveedores/equipos', null, $tokenProveedor, 200);
    registrar('proveedores-pilotos', 'listarPilotos', 'Pilotos del proveedor.', 'GET', '/api/proveedores/pilotos', null, $tokenProveedor, 200);
    registrar('proveedores-auxiliares', 'listarAuxiliares', 'Auxiliares del proveedor.', 'GET', '/api/proveedores/auxiliares', null, $tokenProveedor, 200);
    registrar('ofertas-reputacion', 'verReputacion', 'Calificación, tasa de cancelación y alertas propias; sin sanciones automáticas.', 'GET', '/api/reputacion', null, $tokenProveedor, 200);
    registrar('calificaciones-recibidas', 'listarCalificacionesRecibidas', 'Calificaciones que recibe el proveedor.', 'GET', '/api/calificaciones/recibidas', null, $tokenProveedor, 200);
    registrar('notificaciones-proveedor', 'listarNotificaciones', 'Bandeja del proveedor: nuevas solicitudes en su zona, ofertas y mensajes.', 'GET', '/api/notificaciones', null, $tokenProveedor, 200);
    registrar('notificaciones-preferencias', 'verPreferenciasDeAviso', 'Qué avisos recibe el piloto: nuevas solicitudes por zona, región y hectáreas mínimas.', 'GET', '/api/notificaciones/preferencias', null, $tokenProveedor, 200);
    registrar('notificaciones-preferencias-guardar', 'guardarPreferenciasDeAviso', 'Activa o apaga los avisos de nuevas solicitudes y los acota a una región.', 'PUT', '/api/notificaciones/preferencias', [
        'nuevas_solicitudes' => ['activo' => true, 'zonas' => [], 'macrorregiones' => ['chiquitania', 'valles'], 'hectareas_minimas' => '50.0000'], 'ofertas' => true, 'mensajes' => true, 'avance' => true, 'calificaciones' => true,
    ], $tokenProveedor, 200);

    // Mapa de solicitudes abiertas: sin datos privados del cliente.
    [, $mapa] = registrar('mercado-mapa', 'listarSolicitudesAbiertas', 'Solicitudes abiertas para el mapa del piloto, con sus filtros; sin nombre ni teléfono del cliente.', 'GET', '/api/solicitudes-abiertas?macrorregion=chiquitania&hectareas_min=50', null, $tokenProveedor, 200);
    registrar('mercado-resumen-zonas', 'resumenZonasAbiertas', 'Cuántas solicitudes y hectáreas hay por zona y región (valles frente a Chiquitania).', 'GET', '/api/solicitudes-abiertas/resumen-zonas', null, $tokenProveedor, 200);
    $abierta = $solicitudId !== null ? ['id' => $solicitudId] : buscar($mapa['solicitudes'] ?? [], fn ($fila) => ($fila['mi_negociacion'] ?? null) === null);

    if ($abierta !== null) {
        registrar('mercado-detalle', 'verSolicitudAbierta', 'Detalle previo a ofertar: lotes (polígonos), estado del terreno y datos del servicio, sin datos del cliente.', 'GET', "/api/solicitudes-abiertas/{$abierta['id']}", null, $tokenProveedor, 200);

        [, $creada] = registrar('ofertas-ofertar', 'ofertarSolicitud', 'El proveedor contraoferta su precio por hectárea; idempotente por uuid_cliente.', 'POST', "/api/solicitudes/{$abierta['id']}/ofertas", [
            'uuid_cliente' => '9c1d7e40-5a2b-4f3c-8e61-0d4b7a2c9e11', 'tipo' => 'contraoferta', 'precio_ha' => '118.0000', 'nota' => 'Podemos ir la semana próxima.',
        ], $tokenProveedor, 201);
        registrar('ofertas-ofertar-reintento', 'ofertarSolicitud', 'Reintento con el mismo uuid_cliente: devuelve la misma oferta con 200.', 'POST', "/api/solicitudes/{$abierta['id']}/ofertas", [
            'uuid_cliente' => '9c1d7e40-5a2b-4f3c-8e61-0d4b7a2c9e11', 'tipo' => 'contraoferta', 'precio_ha' => '118.0000', 'nota' => 'Podemos ir la semana próxima.',
        ], $tokenProveedor, 200);
        $ofertaId = $creada['oferta']['id'] ?? null;

        registrar('ofertas-lista', 'listarOfertas', 'Las negociaciones del proveedor: cada oferta con su estado y su ronda.', 'GET', '/api/ofertas', null, $tokenProveedor, 200);

        if ($ofertaId !== null) {
            registrar('ofertas-ver', 'verOferta', 'Una oferta con su historial de rondas.', 'GET', "/api/ofertas/{$ofertaId}", null, $tokenProveedor, 200);
            registrar('ofertas-comparar', 'compararOfertas', 'El cliente compara las ofertas de una solicitud: precio, calificación, dron y distancia de cada proveedor.', 'GET', "/api/solicitudes/{$abierta['id']}/ofertas", null, $tokenCliente, 200);
            [, $respuesta] = registrar('ofertas-contraofertar', 'contraofertarOferta', 'El cliente responde con su precio: la oferta anterior queda reemplazada y la nueva es la vigente.', 'POST', "/api/ofertas/{$ofertaId}/contraofertar", [
                'precio_ha' => '121.0000', 'nota' => '¿Puedes acercarte a este precio?', 'uuid_cliente' => '9c1d7e40-5a2b-4f3c-8e61-0d4b7a2c9e12',
            ], $tokenCliente, 201);
            $vigenteId = $respuesta['oferta']['id'] ?? null;

            if ($vigenteId !== null) {
                registrar('ofertas-aceptar', 'aceptarOferta', 'El proveedor acepta la contraoferta del cliente: la solicitud queda adjudicada y las demás ofertas se cierran.', 'POST', "/api/ofertas/{$vigenteId}/aceptar", null, $tokenProveedor, 200);
                registrar('ofertas-aceptar-otra-vez', 'aceptarOferta', 'Aceptar dos veces es idempotente: devuelve la misma oferta ya aceptada.', 'POST', "/api/ofertas/{$vigenteId}/aceptar", null, $tokenProveedor, 200);
            }

            $solicitudNegociada = $abierta['id'];

            registrar('solicitudes-adjudicada', 'verSolicitud', 'Tras adjudicar, la solicitud trae el precio acordado y los datos del proveedor.', 'GET', "/api/solicitudes/{$solicitudNegociada}", null, $tokenCliente, 200);

            // Chat entre el cliente y el proveedor adjudicado.
            registrar('mensajes-enviar', 'enviarMensaje', 'El cliente escribe al proveedor adjudicado; idempotente por uuid_cliente.', 'POST', "/api/solicitudes/{$solicitudNegociada}/mensajes", [
                'uuid_cliente' => '9c1d7e40-5a2b-4f3c-8e61-0d4b7a2c9e21', 'texto' => 'Hola, ¿qué día pueden venir?',
            ], $tokenCliente, 201);
            registrar('mensajes-responder', 'enviarMensaje', 'El proveedor responde.', 'POST', "/api/solicitudes/{$solicitudNegociada}/mensajes", [
                'uuid_cliente' => '9c1d7e40-5a2b-4f3c-8e61-0d4b7a2c9e22', 'texto' => 'El jueves temprano, si el viento lo permite.',
            ], $tokenProveedor, 201);
            [, $conversacion] = registrar('mensajes-lista', 'listarMensajes', 'Mensajes en orden con cursor: el cliente consulta cada pocos segundos con `despues`.', 'GET', "/api/solicitudes/{$solicitudNegociada}/mensajes", null, $tokenCliente, 200);

            // Ejecución: inicio, avance con foto, última ubicación y cierre del piloto.
            registrar('seguimiento-inicio', 'iniciarServicio', 'El piloto marca el inicio: la solicitud pasa a en ejecución.', 'POST', "/api/solicitudes/{$solicitudNegociada}/inicio", [
                'uuid_cliente' => '9c1d7e40-5a2b-4f3c-8e61-0d4b7a2c9e31', 'secuencia' => 1,
            ], $tokenPilotoCarlos, 201);
            registrar('seguimiento-avance', 'registrarAvance', 'El piloto marca el avance en porcentaje (o en hectáreas).', 'POST', "/api/solicitudes/{$solicitudNegociada}/avance", [
                'uuid_cliente' => '9c1d7e40-5a2b-4f3c-8e61-0d4b7a2c9e32', 'secuencia' => 2, 'porcentaje' => '40.00', 'nota' => 'Primera franja cubierta.',
            ], $tokenPilotoCarlos, 201);

            $foto = tempnam(sys_get_temp_dir(), 'avance');
            $lienzoAvance = imagecreatetruecolor(320, 240);
            imagefill($lienzoAvance, 0, 0, imagecolorallocate($lienzoAvance, 90, 140, 60));
            imagejpeg($lienzoAvance, $foto, 70);
            $peticionFoto = ['uuid_cliente' => '9c1d7e40-5a2b-4f3c-8e61-0d4b7a2c9e33', 'tipo' => 'foto_avance'];
            [$estadoFoto, $cuerpoFoto] = llamar('POST', "/api/solicitudes/{$solicitudNegociada}/evidencias", $peticionFoto, $tokenPilotoCarlos, ['archivo' => new UploadedFile($foto, 'avance.jpg', 'image/jpeg', null, true)]);
            if ($estadoFoto === 201) {
                file_put_contents("{$salida}/seguimiento-evidencia.json", json_encode([
                    'operationId' => 'subirEvidenciaDeSolicitud',
                    'descripcion' => 'Sube una foto de avance comprimida (multipart); cola separada de los registros livianos.',
                    'peticion' => ['metodo' => 'POST', 'ruta' => "/api/solicitudes/{$solicitudNegociada}/evidencias", 'autenticada' => true, 'cuerpo' => $peticionFoto, 'multipart' => ['archivo' => 'avance.jpg (image/jpeg)']],
                    'respuesta' => ['estado' => $estadoFoto, 'cuerpo' => sanear($cuerpoFoto)],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)."\n");
                $guardados++;
            } else {
                $fallos[] = "seguimiento-evidencia: {$estadoFoto} ".substr(json_encode($cuerpoFoto, JSON_UNESCAPED_UNICODE) ?: '', 0, 300);
            }

            registrar('seguimiento-ubicacion-enviar', 'enviarUbicacion', 'La app del piloto envía su última posición mientras el servicio está en ejecución.', 'PUT', '/api/ubicacion', [
                'solicitud_id' => $solicitudNegociada, 'latitud' => -17.3395, 'longitud' => -63.2528, 'precision_m' => 12, 'capturado_en' => Carbon::now()->toIso8601String(),
            ], $tokenPilotoCarlos, 204);
            registrar('seguimiento-ubicacion-ver', 'verUbicacionDelPiloto', 'El cliente ve el último punto del piloto (sin ruta ni tiempo de llegada).', 'GET', "/api/solicitudes/{$solicitudNegociada}/ubicacion-piloto", null, $tokenCliente, 200);
            registrar('seguimiento-avance-ver', 'verAvance', 'El cliente ve el avance, el monto estimado y las fotos.', 'GET', "/api/solicitudes/{$solicitudNegociada}/avance", null, $tokenCliente, 200);
            registrar('seguimiento-cierre', 'cerrarServicio', 'El piloto cierra con las hectáreas ejecutadas: con su foto de avance la solicitud pasa a finalizada y se muestra el monto a pagar.', 'POST', "/api/solicitudes/{$solicitudNegociada}/cierre", [
                'uuid_cliente' => '9c1d7e40-5a2b-4f3c-8e61-0d4b7a2c9e34', 'secuencia' => 3, 'hectareas_ejecutadas' => '98.5000', 'nota' => 'Servicio terminado.',
            ], $tokenPilotoCarlos, 201);
            registrar('solicitudes-finalizada', 'verSolicitud', 'Finalizada: precio final = hectáreas ejecutadas por precio acordado; el pago se acuerda fuera de la app.', 'GET', "/api/solicitudes/{$solicitudNegociada}", null, $tokenCliente, 200);
            $uuidPago = '9c1d7e40-5a2b-4f3c-8e61-0d4b7a2c9e40';
            registrar('pago-confirmado-cliente-no-escribe', 'confirmarPagoDeSolicitud', 'El cliente no informa el pago: lo escribe solo el proveedor.', 'POST', "/api/solicitudes/{$solicitudNegociada}/pago-confirmado", ['uuid_cliente' => $uuidPago], $tokenCliente, 403);
            registrar('pago-confirmado-ver-vacio', 'verPagoConfirmadoDeSolicitud', 'Antes de que el proveedor lo informe, el pago es nulo.', 'GET', "/api/solicitudes/{$solicitudNegociada}/pago-confirmado", null, $tokenCliente, 200);
            registrar('pago-confirmado', 'confirmarPagoDeSolicitud', 'El proveedor informa que recibió el pago, que ocurre fuera de la aplicación; el monto es el final del servicio.', 'POST', "/api/solicitudes/{$solicitudNegociada}/pago-confirmado", ['uuid_cliente' => $uuidPago, 'metodo' => 'transferencia', 'nota' => 'Recibido en la cuenta del banco.'], $tokenPilotoCarlos, 201);
            registrar('pago-confirmado-reintento', 'confirmarPagoDeSolicitud', 'Repetir el mismo uuid devuelve el mismo registro (200).', 'POST', "/api/solicitudes/{$solicitudNegociada}/pago-confirmado", ['uuid_cliente' => $uuidPago, 'metodo' => 'transferencia'], $tokenPilotoCarlos, 200);
            registrar('pago-confirmado-otro-uuid', 'confirmarPagoDeSolicitud', 'Un segundo registro con otro uuid responde 409: lo confirmado no se reescribe.', 'POST', "/api/solicitudes/{$solicitudNegociada}/pago-confirmado", ['uuid_cliente' => '9c1d7e40-5a2b-4f3c-8e61-0d4b7a2c9e41'], $tokenPilotoCarlos, 409);
            registrar('pago-confirmado-ver', 'verPagoConfirmadoDeSolicitud', 'El cliente ve que el proveedor confirmó el pago.', 'GET', "/api/solicitudes/{$solicitudNegociada}/pago-confirmado", null, $tokenCliente, 200);
            registrar('seguimiento-ubicacion-sin-ejecucion', 'verUbicacionDelPiloto', 'Fuera de la ejecución no hay ubicación: la respuesta viene sin punto.', 'GET', "/api/solicitudes/{$solicitudNegociada}/ubicacion-piloto", null, $tokenCliente, 200);
        }
    }

    // Una segunda solicitud para mostrar la cancelación del cliente.
    if ($terrenoId !== null) {
        [, $otra] = llamar('POST', '/api/solicitudes', [...$cuerpoSolicitud, 'uuid_cliente' => '5b0f6a52-0c1e-4b6c-9d57-7f9d3a1c2e02', 'hectareas_solicitadas' => '40.0000'], $tokenCliente);
        $otraId = $otra['solicitud']['id'] ?? null;
        if ($otraId !== null) {
            llamar('POST', "/api/solicitudes/{$otraId}/publicar", null, $tokenCliente);
            registrar('solicitudes-cancelar', 'cancelarSolicitud', 'El cliente cancela una solicitud abierta con motivo; sin penalidad.', 'POST', "/api/solicitudes/{$otraId}/cancelar", ['motivo' => 'Se adelantó la lluvia y se pospone la aplicación.'], $tokenCliente, 200);
        }
    }

    // El proveedor no alcanza lo del cliente.
    registrar('terrenos-rol-equivocado', 'listarTerrenos', 'Un rol de proveedor no alcanza los terrenos del cliente.', 'GET', '/api/terrenos', null, $tokenProveedor, 403);

    // ---------------------------------------------------------------- cierre
    llamar('DELETE', '/api/auth/token', null, $tokenPiloto);
    registrar('seguridad-cerrar-sesion', 'cerrarSesionCampo', 'Cierra la sesión: el token deja de valer.', 'DELETE', '/api/auth/token', null, $tokenCliente, 204);
    registrar('seguridad-token-revocado', 'verSesionCampo', 'Con el token ya revocado, la sesión responde 401.', 'GET', '/api/auth/sesion', null, $tokenCliente, 401);
} catch (Throwable $excepcion) {
    $fallos[] = get_class($excepcion).': '.$excepcion->getMessage().' @ '.$excepcion->getFile().':'.$excepcion->getLine();
}

echo "Fixtures guardados: {$guardados}\n";
foreach ($fallos as $fallo) {
    echo "AVISO: {$fallo}\n";
}

exit($fallos === [] ? 0 : 1);
