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

use App\Dominios\Asignacion\Aplicacion\ResponderOferta;
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

$desde = Carbon::now()->addDays(4)->toDateString();
$hasta = Carbon::now()->addDays(12)->toDateString();

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
    }

    registrar('solicitudes-lista', 'listarSolicitudes', 'Solicitudes del cliente, la más reciente primero.', 'GET', '/api/solicitudes', null, $tokenCliente, 200);

    $uuidSolicitud = '5b0f6a52-0c1e-4b6c-9d57-7f9d3a1c2e01';
    $cuerpoSolicitud = [
        'uuid_cliente' => $uuidSolicitud,
        'terreno_id' => $terrenoId,
        'tipo_aplicacion' => 'herbicida',
        'hectareas_solicitadas' => '100.0000',
        'producto_indicado' => 'Glifosato 480 SL, 2 L/ha',
        'aporta_producto' => 'cliente',
        'ventana_desde' => $desde,
        'ventana_hasta' => $hasta,
        'urgente' => false,
        'observaciones' => 'Entrar por el portón sur.',
    ];

    [, $solicitud] = registrar('solicitudes-crear', 'crearSolicitud', 'Crea una solicitud en borrador con su uuid_cliente generado en el dispositivo.', 'POST', '/api/solicitudes', $cuerpoSolicitud, $tokenCliente, 201);
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
        registrar('solicitudes-estimacion', 'estimarSolicitud', 'Tarifa estimada antes de publicar.', 'GET', "/api/solicitudes/{$solicitudId}/estimacion", null, $tokenCliente, 200);
        registrar('solicitudes-publicar', 'publicarSolicitud', 'Congela la tarifa y la deja publicada; el motor de asignación toma el trabajo.', 'POST', "/api/solicitudes/{$solicitudId}/publicar", null, $tokenCliente);
        registrar('solicitudes-ver', 'verSolicitud', 'Detalle con su historial de estados.', 'GET', "/api/solicitudes/{$solicitudId}", null, $tokenCliente, 200);
        registrar('solicitudes-cancelar', 'cancelarSolicitud', 'El cliente cancela con motivo.', 'POST', "/api/solicitudes/{$solicitudId}/cancelar", ['motivo' => 'Se adelantó la lluvia y se pospone la aplicación.'], $tokenCliente);
    }

    registrar('solicitudes-ajena', 'verSolicitud', 'Un id que no es del cliente responde 404, nunca 403 (invariante 5).', 'GET', '/api/solicitudes/999999', null, $tokenCliente, 404);

    // El cliente 1 tiene servicios ejecutados con posventa sembrada.
    [, $lista] = llamar('GET', '/api/solicitudes?per_page=50', null, $tokenCliente);
    $filas = $lista['solicitudes'] ?? [];
    $completada = buscar($filas, fn ($fila) => ($fila['estado'] ?? '') === 'completada');
    $enDisputa = buscar($filas, fn ($fila) => in_array($fila['estado'] ?? '', ['en_disputa', 'completada'], true) && ($fila['id'] ?? 0) !== ($completada['id'] ?? 0));

    if ($completada !== null) {
        registrar('calificaciones-servicio', 'verCalificacionesDelServicio', 'Calificaciones de un servicio completado.', 'GET', "/api/solicitudes/{$completada['id']}/calificaciones", null, $tokenCliente, 200);
    }

    registrar('disputas-lista', 'listarDisputas', 'Disputas del cliente.', 'GET', '/api/disputas', null, $tokenCliente, 200);
    registrar('notificaciones-lista', 'listarNotificaciones', 'Bandeja de notificaciones.', 'GET', '/api/notificaciones', null, $tokenCliente, 200);

    // ------------------------------------------------------------------ piloto
    [$tokenPiloto] = ingresar('rodrigo.suarez', $clave);
    registrar('seguridad-token-piloto', 'emitirTokenDispositivo', 'El piloto ingresa con su rol.', 'POST', '/api/auth/token', [
        'username' => 'rodrigo.suarez', 'password' => $clave, 'uuid_dispositivo' => UUID_DISPOSITIVO, 'nombre_dispositivo' => 'Control remoto del dron',
    ], null, 201);
    [$tokenPiloto] = ingresar('rodrigo.suarez', $clave, 'Control remoto del dron');

    registrar('seguridad-sesion-piloto', 'verSesionCampo', 'Sesión vigente del piloto.', 'GET', '/api/auth/sesion', null, $tokenPiloto, 200);
    registrar('seguridad-dispositivos', 'listarDispositivosPropios', 'Dispositivos con token vivo de la cuenta.', 'GET', '/api/dispositivos', null, $tokenPiloto, 200);

    [, $catalogo] = registrar('sync-catalogo-completo', 'obtenerCatalogoSincronizacion', 'Primer pull: todo lo vigente, con el cursor de continuación.', 'GET', '/api/sync/catalogo', null, $tokenPiloto, 200);

    if (is_string($catalogo['cursor'] ?? null)) {
        registrar('sync-catalogo-incremental', 'obtenerCatalogoSincronizacion', 'Pull incremental desde el cursor: sin cambios, listas vacías.', 'GET', '/api/sync/catalogo?desde='.urlencode($catalogo['cursor']), null, $tokenPiloto, 200);
    }

    [, $catalogo] = registrar('sync-catalogo-con-servicio', 'obtenerCatalogoSincronizacion', 'Pull completo: los servicios aceptados por el equipo del operario aparecen en `servicios_asignados`.', 'GET', '/api/sync/catalogo', null, $tokenPiloto, 200);
    $servicio = buscar($catalogo['servicios_asignados'] ?? [], fn ($fila) => in_array($fila['estado'] ?? '', ['asignada', 'en_camino', 'en_ejecucion'], true));
    $persona = buscar($catalogo['personas'] ?? [], fn ($fila) => ($fila['rol'] ?? '') === 'piloto');

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
    // Los propios de Agrocom ofrecen primero: para que la oferta llegue a un
    // proveedor independiente, el titular propio rechaza la suya y el motor
    // pasa a la ronda de terceros (ADR 0030).
    $titularPropio = (int) DB::table('prv_proveedores')->where('tipo', 'propio')->value('usuario_titular_id');
    $ofertasPropias = DB::table('asg_ofertas')->where('estado', 'ofrecida')->pluck('id');
    foreach ($ofertasPropias as $idOferta) {
        app(ResponderOferta::class)->rechazar($titularPropio, (int) $idOferta, 'Sin cupo esta semana.');
    }

    $rolProveedor = (int) DB::table('sec_role')->where('name', 'proveedor')->value('id');
    [$estadoToken, $emisionProveedor] = llamar('POST', '/api/auth/token', [
        'username' => 'carlos.mamani', 'password' => $clave, 'uuid_dispositivo' => UUID_DISPOSITIVO, 'nombre_dispositivo' => 'Celular del proveedor', 'role_id' => $rolProveedor,
    ]);
    if ($estadoToken !== 201) {
        throw new RuntimeException("No se pudo ingresar como proveedor: {$estadoToken}");
    }
    $tokenProveedor = $emisionProveedor['token'];
    registrar('seguridad-token-proveedor', 'emitirTokenDispositivo', 'El mismo usuario elige con qué rol opera mediante `role_id`.', 'POST', '/api/auth/token', [
        'username' => 'carlos.mamani', 'password' => $clave, 'uuid_dispositivo' => '7a2e1b3f-2a45-4d0a-8b9c-3c8d2e6f1a42', 'nombre_dispositivo' => 'Celular del proveedor', 'role_id' => $rolProveedor,
    ], null, 201);

    [, $mi] = registrar('proveedores-mi', 'verMiProveedor', 'El proveedor de la cuenta y su estado de aprobación.', 'GET', '/api/proveedores/mi', null, $tokenProveedor, 200);
    registrar('proveedores-documentos', 'listarDocumentosProveedor', 'Documentos del proveedor: se consultan por sujeto.', 'GET', '/api/proveedores/documentos?sujeto_tipo=proveedor&sujeto_id='.($mi['proveedor']['id'] ?? 0), null, $tokenProveedor, 200);
    registrar('flota-drones', 'listarDrones', 'Drones del proveedor.', 'GET', '/api/proveedores/drones', null, $tokenProveedor, 200);
    registrar('flota-equipos', 'listarEquipos', 'Equipos del proveedor.', 'GET', '/api/proveedores/equipos', null, $tokenProveedor, 200);
    registrar('proveedores-pilotos', 'listarPilotos', 'Pilotos del proveedor.', 'GET', '/api/proveedores/pilotos', null, $tokenProveedor, 200);
    registrar('proveedores-auxiliares', 'listarAuxiliares', 'Auxiliares del proveedor.', 'GET', '/api/proveedores/auxiliares', null, $tokenProveedor, 200);
    registrar('flota-disponibilidad-declarar', 'declararDisponibilidad', 'El proveedor se pone en línea para recibir ofertas (requiere red).', 'PUT', '/api/disponibilidad', ['en_linea' => true], $tokenProveedor, 200);
    registrar('flota-disponibilidad', 'consultarDisponibilidad', 'Disponibilidad de los equipos del proveedor.', 'GET', '/api/disponibilidad', null, $tokenProveedor, 200);
    registrar('ofertas-reputacion', 'verReputacion', 'Calificación, tasas y sanciones propias.', 'GET', '/api/reputacion', null, $tokenProveedor, 200);
    registrar('calificaciones-recibidas', 'listarCalificacionesRecibidas', 'Calificaciones que recibe el proveedor.', 'GET', '/api/calificaciones/recibidas', null, $tokenProveedor, 200);
    registrar('notificaciones-proveedor', 'listarNotificaciones', 'Bandeja del proveedor.', 'GET', '/api/notificaciones', null, $tokenProveedor, 200);

    [, $ofertas] = registrar('ofertas-lista', 'listarOfertas', 'Ofertas vivas del proveedor.', 'GET', '/api/ofertas', null, $tokenProveedor, 200);
    $oferta = buscar($ofertas['ofertas'] ?? [], fn ($fila) => ($fila['estado'] ?? '') === 'ofrecida');

    if ($oferta !== null) {
        registrar('ofertas-ver', 'verOferta', 'Una oferta tal como la ve el proveedor: sin nombre del terreno ni contacto del cliente.', 'GET', "/api/ofertas/{$oferta['id']}", null, $tokenProveedor, 200);
        registrar('ofertas-aceptar', 'aceptarOferta', 'Acepta la oferta: la aceptación es atómica y requiere red.', 'POST', "/api/ofertas/{$oferta['id']}/aceptar", null, $tokenProveedor);
        registrar('ofertas-aceptar-otra-vez', 'aceptarOferta', 'Aceptar dos veces es idempotente: devuelve la misma oferta ya aceptada.', 'POST', "/api/ofertas/{$oferta['id']}/aceptar", null, $tokenProveedor);
    } else {
        $fallos[] = 'El proveedor independiente no recibió ninguna oferta viva tras el rechazo del propio.';
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
