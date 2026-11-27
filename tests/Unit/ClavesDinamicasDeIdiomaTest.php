<?php

/*
 * Claves de idioma armadas en tiempo de ejecución (ADR 0013). `ClavesDeIdiomaTest` solo ve las
 * claves escritas como literal; acá se cubren las familias `__('a.b.'.$valor)` cuyo conjunto de
 * valores se puede enumerar con certeza (casos de un enum, constantes del controlador, tipos de
 * aviso). Una clave sin valor no da error: se vería cruda en pantalla (`seguridad.dashboard.pendiente.disputas`).
 *
 * Cada familia es `prefijo => valores`. Si un enum gana un caso y la etiqueta no se escribe,
 * esta prueba falla con la clave exacta.
 */

use App\Dominios\Calificaciones\Dominio\AutorCalificacion;
use App\Dominios\Calificaciones\Dominio\CausaModeracion;
use App\Dominios\Calificaciones\Dominio\EstadoCalificacion;
use App\Dominios\Calificaciones\Dominio\EtiquetaCalificacion;
use App\Dominios\Calificaciones\Dominio\SujetoCalificacion;
use App\Dominios\Compartido\Dominio\AccionAprobacion;
use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Compartido\Dominio\TipoDocumento;
use App\Dominios\Disputas\Dominio\EstadoDisputa;
use App\Dominios\Disputas\Dominio\MotivoDisputa;
use App\Dominios\Disputas\Dominio\ParteDeDisputa;
use App\Dominios\Disputas\Dominio\ResultadoDisputa;
use App\Dominios\Flota\Aplicacion\GestionarFotosDeDron;
use App\Dominios\Flota\Dominio\OrigenDron;
use App\Dominios\Mantenimiento\Dominio\EstadoBateria;
use App\Dominios\Negociacion\Aplicacion\Mensajeria;
use App\Dominios\Negociacion\Dominio\EstadoOferta;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use App\Dominios\Personal\Dominio\RecursoTipoEquipo;
use App\Dominios\Proveedores\Dominio\EstadoDocumento;
use App\Dominios\Proveedores\Dominio\TipoProveedor;
use App\Dominios\Proveedores\Dominio\TipoSujeto;
use App\Dominios\Seguridad\Contratos\PropositoDeCodigo;
use App\Dominios\Seguridad\Contratos\TipoCuenta;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\DashboardController;
use App\Dominios\Solicitudes\Dominio\ActorSolicitud;
use App\Dominios\Solicitudes\Dominio\AportaProducto;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Dominio\TipoAplicacionServicio;
use App\Dominios\Solicitudes\Dominio\TipoCliente;

/**
 * @param  class-string<BackedEnum>  $enum
 * @return list<string>
 */
function valoresDeEnumIdioma(string $enum): array
{
    return array_map(static fn (BackedEnum $caso): string => (string) $caso->value, $enum::cases());
}

/**
 * @param  array<array-key, mixed>  $catalogo
 * @return array<string, true>
 */
function clavesPlanasIdioma(array $catalogo, string $prefijo): array
{
    $plano = [$prefijo => true];

    foreach ($catalogo as $clave => $valor) {
        $plano += is_array($valor)
            ? clavesPlanasIdioma($valor, "{$prefijo}.{$clave}")
            : ["{$prefijo}.{$clave}" => true];
    }

    return $plano;
}

/**
 * @return array<string, true>
 */
function catalogoPlanoIdioma(): array
{
    $claves = [];

    foreach (glob(dirname(__DIR__, 2).'/lang/es/*.php') ?: [] as $archivo) {
        /** @var array<array-key, mixed> $catalogo */
        $catalogo = require $archivo;
        $claves += clavesPlanasIdioma($catalogo, basename($archivo, '.php'));
    }

    return $claves;
}

/**
 * Los códigos que el código de la aplicación le pasa a `NegociacionInvalida` (`ajeno`, `prohibido`,
 * `conflicto`, `dato`), leídos del propio código: cada uno es la cola de `negociacion.errores.*`.
 *
 * @return list<string>
 */
function clavesDeNegociacionInvalida(): array
{
    $codigos = [];
    $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app', FilesystemIterator::SKIP_DOTS));

    foreach ($iterador as $archivo) {
        if ($archivo->isFile() && str_ends_with($archivo->getPathname(), '.php')) {
            preg_match_all('/NegociacionInvalida::(?:ajeno|prohibido|conflicto|dato)\(\s*\'([a-z_]+)\'/', (string) file_get_contents($archivo->getPathname()), $c);
            $codigos = [...$codigos, ...$c[1]];
        }
    }

    return array_values(array_unique($codigos));
}

/**
 * @return array<string, array<string, mixed>>
 */
function clavesDeConfiguracion(): array
{
    /** @var array{claves: array<string, array<string, mixed>>} $configuracion */
    $configuracion = require dirname(__DIR__, 2).'/config/configuracion.php';

    return $configuracion['claves'];
}

/**
 * @return list<string>
 */
function gruposDeConfiguracion(): array
{
    return array_values(array_unique(array_map(static fn (array $clave): string => (string) $clave['grupo'], clavesDeConfiguracion())));
}

/**
 * @return array<string, list<string>> prefijo (con su punto final) → valores posibles
 */
function familiasDinamicasIdioma(): array
{
    $pendientes = array_keys((new ReflectionClassConstant(DashboardController::class, 'PENDIENTES'))->getValue());

    return [
        'seguridad.dashboard.pendiente.' => $pendientes,
        'notificaciones.titulo.' => valoresDeEnumIdioma(TipoNotificacion::class),
        'aprobacion.estado.' => valoresDeEnumIdioma(EstadoAprobacion::class),
        'aprobacion.accion.' => valoresDeEnumIdioma(AccionAprobacion::class),
        'aprobacion.sujeto.' => valoresDeEnumIdioma(TipoSujeto::class),
        'proveedores.aprobacion.resuelta.' => valoresDeEnumIdioma(AccionAprobacion::class),
        'proveedores.tipo.' => valoresDeEnumIdioma(TipoProveedor::class),
        'proveedores.documentos.tipo.' => valoresDeEnumIdioma(TipoDocumento::class),
        'proveedores.documentos.estado.' => valoresDeEnumIdioma(EstadoDocumento::class),
        'solicitudes.estado.' => valoresDeEnumIdioma(EstadoSolicitud::class),
        'solicitudes.tipo_aplicacion.' => valoresDeEnumIdioma(TipoAplicacionServicio::class),
        'solicitudes.aporta_producto.' => valoresDeEnumIdioma(AportaProducto::class),
        'solicitudes.clientes.tipo.' => valoresDeEnumIdioma(TipoCliente::class),
        'solicitudes.clientes.estado.' => valoresDeEnumIdioma(EstadoAprobacion::class),
        'solicitudes.origen.' => valoresDeEnumIdioma(ActorSolicitud::class),
        'solicitudes.panel.neg_estado.' => valoresDeEnumIdioma(EstadoOferta::class),
        'flota.origen.' => valoresDeEnumIdioma(OrigenDron::class),
        'personal.recurso_tipo.' => valoresDeEnumIdioma(RecursoTipoEquipo::class),
        'disputas.estado.' => valoresDeEnumIdioma(EstadoDisputa::class),
        'disputas.motivo.' => valoresDeEnumIdioma(MotivoDisputa::class),
        'disputas.resultado.' => valoresDeEnumIdioma(ResultadoDisputa::class),
        'disputas.panel.abierta_por_' => valoresDeEnumIdioma(ParteDeDisputa::class),
        'calificaciones.estado.' => valoresDeEnumIdioma(EstadoCalificacion::class),
        'calificaciones.sujeto.' => valoresDeEnumIdioma(SujetoCalificacion::class),
        'calificaciones.autor.' => valoresDeEnumIdioma(AutorCalificacion::class),
        'calificaciones.causa.' => valoresDeEnumIdioma(CausaModeracion::class),
        'calificaciones.etiqueta.' => valoresDeEnumIdioma(EtiquetaCalificacion::class),
        'seguridad.tipos_de_cuenta.' => valoresDeEnumIdioma(TipoCuenta::class),
        'seguridad.codigos.correo_motivo.' => valoresDeEnumIdioma(PropositoDeCodigo::class),
        'seguridad.bitacora.acciones.' => valoresDeEnumIdioma(AccionBitacora::class),
        'seguridad.usuarios.estado_' => ['activo', 'bloqueado'],
        'metricas.tipo.' => valoresDeEnumIdioma(TipoProveedor::class),
        'mantenimiento.estado_bateria.' => valoresDeEnumIdioma(EstadoBateria::class),
        'negociacion.chat_sistema.' => array_values(Mensajeria::EVENTOS_DE_SISTEMA),
        'negociacion.errores.' => clavesDeNegociacionInvalida(),
        'proveedores.aprobacion.modal.' => array_map(
            static fn (string $accion): string => $accion,
            array_values(array_diff(valoresDeEnumIdioma(AccionAprobacion::class), ['reenviar'])),
        ),
        'flota.fotos.tipo.' => GestionarFotosDeDron::TIPOS,
        // Valores de columnas de texto (no son enum): estados de la foto de dron, tipos de avance y de foto
        // del seguimiento, autores del chat y de la disputa, categorías del correo de prueba y causas del reembolso.
        'flota.fotos.estado.' => ['en_revision', 'aprobada', 'rechazada'],
        'flota.equipos.rol.' => ['piloto', 'dron'],
        'solicitudes.panel.seg_tipo.' => ['inicio', 'avance', 'cierre'],
        'solicitudes.panel.seg_foto_tipo.' => ['foto_avance', 'foto_final', 'foto_incidencia'],
        'solicitudes.panel.neg_autor.' => ['cliente', 'proveedor'],
        'solicitudes.panel.chat_autor.' => ['cliente', 'proveedor', 'sistema'],
        'disputas.panel.autor_' => ['cliente', 'proveedor', 'soporte'],
        'disputas.panel.tipo_' => ['informacion', 'apelacion'],
        'disputas.errores.reembolso.' => ['negativo', 'sin_reembolso', 'requiere_reembolso', 'excede_monto', 'parcial_no_total'],
        'calificaciones.panel.autor_' => ['administrador', 'proveedor'],
        'configuracion.correo_prueba.error.' => ['sin_configurar', 'sin_destino', 'autenticacion', 'conexion', 'tiempo', 'cifrado', 'desconocido'],
        'busqueda.bloques.' => ['clientes', 'proveedores', 'drones'],
        'proveedores.documentos.revisado.' => ['aprobar', 'rechazar'],
        'configuracion.grupo_' => gruposDeConfiguracion(),
        'configuracion.claves.' => array_keys(clavesDeConfiguracion()),
        'notificaciones.panel.lectura.' => ['sin_leer', 'leida'],
        'aprobacion.errores.cuenta_no_aprobada_' => valoresDeEnumIdioma(EstadoAprobacion::class),
    ];
}

it('toda familia de claves dinámicas tiene valor en lang/es para cada caso posible', function () {
    $catalogo = catalogoPlanoIdioma();
    $faltantes = [];

    foreach (familiasDinamicasIdioma() as $prefijo => $valores) {
        expect($valores)->not->toBeEmpty("La familia {$prefijo} no tiene valores que comprobar.");

        foreach ($valores as $valor) {
            if (! isset($catalogo[$prefijo.$valor])) {
                $faltantes[] = $prefijo.$valor;
            }
        }
    }

    expect($faltantes)->toBe([], "Claves dinámicas sin valor (se verían crudas):\n  ".implode("\n  ", $faltantes));
});

it('cada pendiente del tablero declara su pantalla y su permiso', function () {
    $pendientes = (new ReflectionClassConstant(DashboardController::class, 'PENDIENTES'))->getValue();

    foreach ($pendientes as $clave => [$ruta, $permiso]) {
        expect($ruta)->toStartWith('panel.', "El pendiente {$clave} no apunta a una ruta del panel.");
        expect($permiso)->toMatch('/^[a-z_]+\.[a-z_]+\.[a-z_]+$/');
    }
});

it('toda clave de menu.* que siembra SecMenuSeeder existe en lang/es', function () {
    $catalogo = catalogoPlanoIdioma();
    $codigo = (string) file_get_contents(dirname(__DIR__, 2).'/database/seeders/Catalogo/SecMenuSeeder.php');

    // Solo las claves vivas: las RETIRADAS ya no se muestran.
    $codigo = preg_replace('/private const RETIRADAS = \[.*?\n    \];/s', '', $codigo) ?? $codigo;
    // El primer argumento de `mover()` es la clave ANTERIOR, que ya no se muestra.
    $codigo = preg_replace('/mover\(\s*\'[^\']+\'\s*,/', 'mover(', $codigo) ?? $codigo;
    preg_match_all('/[\'"](menu\.[a-z0-9_]+(?:\.[a-z0-9_]+)*)[\'"]/', $codigo, $coincidencias);

    $faltantes = array_values(array_filter(array_unique($coincidencias[1]), fn (string $clave): bool => ! isset($catalogo[$clave])));

    expect($faltantes)->toBe([], "Claves de menú sin valor:\n  ".implode("\n  ", $faltantes));
});
