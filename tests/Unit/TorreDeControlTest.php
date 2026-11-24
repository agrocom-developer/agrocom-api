<?php

use App\Dominios\Compartido\Dominio\Macrorregion;
use App\Dominios\Negociacion\Aplicacion\PreferenciasDeAviso;
use App\Dominios\Solicitudes\Dominio\EstadoCliente;
use App\Dominios\Solicitudes\Dominio\TransicionesCliente;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/*
 * La torre de control del panel: sus rutas exigen sesión interna y rol activo,
 * el menú apunta solo a pantallas y permisos que existen, el catálogo reparte los
 * permisos como dice la política (soporte atiende pero no aprueba ni modera) y lo
 * que se retiró del panel no vuelve a registrarse. Sin base de datos: lee las
 * rutas registradas y los archivos del catálogo.
 */

uses(TestCase::class);

const TORRE_RUTAS = [
    'panel.dashboard', 'panel.solicitudes.index', 'panel.solicitudes.show', 'panel.solicitudes.cancelar',
    'panel.solicitudes.evidencia', 'panel.clientes.index', 'panel.clientes.show', 'panel.clientes.estado',
    'panel.zonas.index', 'panel.zonas.edit', 'panel.zonas.update', 'panel.avisos.index',
    'panel.drones-flota.index', 'panel.drones-flota.show', 'panel.drones-flota.foto', 'panel.drones-flota.foto-revisar',
    'panel.proveedores.index', 'panel.pilotos.index', 'panel.auxiliares.index', 'panel.documentos.index',
    'panel.calificaciones.index', 'panel.disputas.index', 'panel.metricas.index',
];

function torreSemilla(string $archivo): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/'.$archivo);
}

test('toda pantalla de la torre de control exige sesión interna y rol activo', function () {
    foreach (TORRE_RUTAS as $nombre) {
        $ruta = Route::getRoutes()->getByName($nombre);

        expect($ruta)->not->toBeNull("Falta la ruta {$nombre}");
        expect($ruta->gatherMiddleware())->toContain('auth:interno')->toContain('rol.activo');
    }
});

test('lo que se retiró del panel no vuelve a registrarse', function () {
    $retirados = ['trabajos', 'sesiones', 'pausas', 'alertas', 'drones', 'bases', 'personas', 'baterias', 'cuadrillas', 'fichas-dron', 'reportes', 'evidencias'];

    foreach (Route::getRoutes()->getRoutesByName() as $nombre => $ruta) {
        foreach ($retirados as $modulo) {
            expect(str_starts_with($nombre, "panel.{$modulo}."))->toBeFalse("La ruta {$nombre} es de un módulo retirado del panel");
        }
    }
});

test('el menú solo apunta a rutas que existen y a permisos del catálogo', function () {
    $menu = torreSemilla('database/seeders/Catalogo/SecMenuSeeder.php');
    $catalogo = torreSemilla('database/seeders/Catalogo/SeguridadSeeder.php');

    preg_match_all("/ruta: '([^']+)', codigoPermiso: '([^']+)'/", $menu, $pares, PREG_SET_ORDER);

    expect(count($pares))->toBeGreaterThanOrEqual(20);

    foreach ($pares as [, $ruta, $permiso]) {
        expect(Route::has($ruta))->toBeTrue("El menú apunta a {$ruta}, que no existe");
        expect($catalogo)->toContain("'{$permiso}' =>");
    }
});

test('soporte atiende pero no aprueba, no resuelve y no modera', function () {
    $catalogo = torreSemilla('database/seeders/Catalogo/SeguridadSeeder.php');
    preg_match('/private const PERMISOS_SOPORTE = \[(.*?)\];/s', $catalogo, $m);
    $soporte = $m[1];

    foreach (['.aprobar', '.suspender', '.dar_baja', 'disputa.resolver', 'calificacion.moderar', 'seguridad.usuario', 'seguridad.rol', 'cliente.suspender'] as $prohibido) {
        expect($soporte)->not->toContain($prohibido);
    }

    expect($soporte)->toContain('solicitudes.solicitud.ver_seguimiento')->toContain('disputas.disputa.atender');
});

test('el chat, las fotos y la ubicación tienen su propio permiso y no los tiene ningún rol de la app', function () {
    $catalogo = torreSemilla('database/seeders/Catalogo/SeguridadSeeder.php');

    foreach (['PERMISOS_CLIENTE', 'PERMISOS_PROVEEDOR', 'PERMISOS_PILOTO', 'PERMISOS_AUXILIAR'] as $lista) {
        preg_match('/private const '.$lista.' = \[(.*?)\];/s', $catalogo, $m);
        expect($m[1])->not->toContain('ver_seguimiento')->not->toContain('seguridad.dashboard.ver');
    }
});

test('los permisos retirados no figuran en el catálogo vigente', function () {
    $catalogo = torreSemilla('database/seeders/Catalogo/SeguridadSeeder.php');
    preg_match('/private const PERMISOS = \[(.*?)\n    \];/s', $catalogo, $m);

    foreach (['operaciones.trabajo.', 'operaciones.sesion.', 'operaciones.alerta.', 'operaciones.pausa.', 'operaciones.dron.', 'personal.base.', 'personal.equipo_trabajo.', 'mantenimiento.'] as $retirado) {
        expect($m[1])->not->toContain("'{$retirado}");
    }
});

test('las macrorregiones del panel son las mismas que acepta el aviso de nuevas solicitudes', function () {
    expect(Macrorregion::valores())->toBe(PreferenciasDeAviso::MACRORREGIONES);

    foreach (Macrorregion::valores() as $region) {
        expect(__('solicitudes.macrorregion.'.$region))->not->toBe('solicitudes.macrorregion.'.$region);
    }
});

test('un cliente se suspende y se reactiva, y nada más', function () {
    expect(TransicionesCliente::permitida(EstadoCliente::Activo, EstadoCliente::Suspendido))->toBeTrue()
        ->and(TransicionesCliente::permitida(EstadoCliente::Suspendido, EstadoCliente::Activo))->toBeTrue()
        ->and(TransicionesCliente::permitida(EstadoCliente::Activo, EstadoCliente::Activo))->toBeFalse();
});

test('un dron no se aprueba sin las fotos obligatorias', function () {
    expect(config('flota.fotos_obligatorias'))->toBe(['frontal', 'lateral', 'placa_serie']);
    expect(torreSemilla('app/Dominios/Flota/Aplicacion/ResolverAprobacionDron.php'))->toContain('FotosDeDronIncompletas');
});
