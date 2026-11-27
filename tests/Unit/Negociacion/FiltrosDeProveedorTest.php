<?php

use App\Dominios\Negociacion\Aplicacion\Avisador;
use App\Dominios\Negociacion\Aplicacion\FiltrosDeProveedor\DepartamentosExcluidos;
use App\Dominios\Negociacion\Aplicacion\FiltrosDeProveedor\FiltroDeProveedor;
use App\Dominios\Negociacion\Aplicacion\FiltrosDeProveedor\FiltrosDeProveedor;
use App\Dominios\Negociacion\Aplicacion\FiltrosDeProveedor\HectareasMinimas;
use App\Dominios\Negociacion\Aplicacion\FiltrosDeProveedor\SolicitudParaAviso;
use App\Dominios\Negociacion\Aplicacion\FiltrosDeProveedor\TiposAplicacionExcluidos;
use App\Dominios\Negociacion\Aplicacion\PreferenciasDeAviso;
use App\Dominios\Negociacion\Contratos\Eventos\AvisoDeNegociacion;
use App\Dominios\Negociacion\Dominio\Excepciones\NegociacionInvalida;
use App\Dominios\Negociacion\Infraestructura\Oyentes\AvisarNuevaSolicitud;
use App\Dominios\Proveedores\Contratos\DatosPiloto;
use App\Dominios\Proveedores\Contratos\DatosProveedor;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Solicitudes\Contratos\Eventos\SolicitudAbierta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Soporte\Esquema;
use Tests\Soporte\ProveedoresFalsos;
use Tests\TestCase;

/*
 * Los filtros del proveedor (ADR 0045, puntos 5 a 7): un mecanismo extensible guardado en
 * `neg_preferencias.filtros` que gobierna solo los avisos. Cada filtro del catálogo tiene su prueba
 * acá; el registro rechaza lo desconocido al guardar y lo conserva e ignora al evaluar; y el aviso de
 * nueva solicitud respeta tanto los filtros como la disponibilidad (el ocupado no entra).
 */

uses(TestCase::class);

const FP_TITULAR = 801;
const FP_PILOTO = 811;
const FP_CLIENTE = 890;

function fpSolicitud(string $hectareas = '100', ?string $departamento = null, ?string $tipo = null): SolicitudParaAviso
{
    return new SolicitudParaAviso($hectareas, $departamento, $tipo);
}

function fpCodigo(callable $accion): string
{
    try {
        $accion();
    } catch (NegociacionInvalida $excepcion) {
        return $excepcion->codigo.':'.$excepcion->estatus.':'.$excepcion->campo;
    }

    return 'sin_error';
}

function fpMundo(): void
{
    Esquema::migrar(['create_neg_tablas_de_negociacion', 'agrega_disponibilidad_y_filtros_de_proveedor']);
    DB::table('neg_preferencias')->delete();
}

test('todo filtro del catálogo está registrado y tiene prueba propia en este archivo', function () {
    $registro = new FiltrosDeProveedor;
    $claves = array_column($registro->disponibles(), 'clave');
    $cuerpo = (string) file_get_contents(__FILE__);

    expect($claves)->toBe(['hectareas_minimas', 'departamentos_excluidos', 'tipos_aplicacion_excluidos']);

    foreach (FiltrosDeProveedor::CATALOGO as $clase) {
        expect(is_subclass_of($clase, FiltroDeProveedor::class))->toBeTrue()
            ->and(substr_count($cuerpo, "'".$clase::CLAVE."'"))->toBeGreaterThan(1);
    }
});

test('hectareas_minimas: decimal exacto, no avisa por debajo del mínimo y rechaza lo que no es un decimal', function () {
    $filtro = new HectareasMinimas;

    expect($filtro->normalizar('50'))->toBe('50.0000')
        ->and($filtro->normalizar(null))->toBeNull()
        ->and($filtro->normalizar(''))->toBeNull()
        ->and($filtro->admite('50.0000', fpSolicitud('49.9999')))->toBeFalse()
        ->and($filtro->admite('50.0000', fpSolicitud('50')))->toBeTrue()
        ->and(fpCodigo(fn () => $filtro->normalizar('abc')))->toBe('filtro_invalido:422:nuevas_solicitudes.hectareas_minimas')
        ->and(fpCodigo(fn () => $filtro->normalizar('-1')))->toBe('filtro_invalido:422:nuevas_solicitudes.hectareas_minimas')
        ->and(fpCodigo(fn () => $filtro->normalizar('1.00001')))->toBe('filtro_invalido:422:nuevas_solicitudes.hectareas_minimas')
        ->and(fpCodigo(fn () => $filtro->normalizar(['1'])))->toBe('filtro_invalido:422:nuevas_solicitudes.hectareas_minimas');
});

test('departamentos_excluidos: códigos de dos dígitos, no avisa del excluido y un dato desconocido no excluye', function () {
    $filtro = new DepartamentosExcluidos;

    expect($filtro->normalizar(['04', '09', '04']))->toBe(['04', '09'])
        ->and($filtro->normalizar([]))->toBeNull()
        ->and($filtro->admite(['09'], fpSolicitud('10', '09')))->toBeFalse()
        ->and($filtro->admite(['09'], fpSolicitud('10', '07')))->toBeTrue()
        ->and($filtro->admite(['09'], fpSolicitud('10', null)))->toBeTrue()
        ->and(fpCodigo(fn () => $filtro->normalizar(['Pando'])))->toBe('filtro_invalido:422:nuevas_solicitudes.departamentos_excluidos')
        ->and(fpCodigo(fn () => $filtro->normalizar('09')))->toBe('filtro_invalido:422:nuevas_solicitudes.departamentos_excluidos')
        ->and(fpCodigo(fn () => $filtro->normalizar([9])))->toBe('filtro_invalido:422:nuevas_solicitudes.departamentos_excluidos');
});

test('tipos_aplicacion_excluidos: no avisa del tipo excluido, uno nuevo que el servidor no conoce no coincide', function () {
    $filtro = new TiposAplicacionExcluidos;

    expect($filtro->normalizar(['desecante']))->toBe(['desecante'])
        ->and($filtro->admite(['desecante'], fpSolicitud('10', null, 'desecante')))->toBeFalse()
        ->and($filtro->admite(['desecante'], fpSolicitud('10', null, 'herbicida')))->toBeTrue()
        ->and($filtro->admite(['desecante'], fpSolicitud('10', null, null)))->toBeTrue()
        ->and(fpCodigo(fn () => $filtro->normalizar(['Herbicida!'])))->toBe('filtro_invalido:422:nuevas_solicitudes.tipos_aplicacion_excluidos');
});

test('el registro combina con Y, quita con null, conserva lo no enviado y rechaza una clave desconocida al guardar', function () {
    $registro = new FiltrosDeProveedor;

    $guardados = $registro->aplicar([], ['hectareas_minimas' => '50', 'departamentos_excluidos' => ['09'], 'tipos_aplicacion_excluidos' => ['desecante']]);

    expect($registro->admite($guardados, fpSolicitud('100', '07', 'herbicida')))->toBeTrue()
        ->and($registro->admite($guardados, fpSolicitud('10', '07', 'herbicida')))->toBeFalse()
        ->and($registro->admite($guardados, fpSolicitud('100', '09', 'herbicida')))->toBeFalse()
        ->and($registro->admite($guardados, fpSolicitud('100', '07', 'desecante')))->toBeFalse();

    $sinMinimo = $registro->aplicar($guardados, ['hectareas_minimas' => null]);

    expect($sinMinimo)->not->toHaveKey('hectareas_minimas')
        ->and($sinMinimo['departamentos_excluidos'])->toBe(['09'])
        ->and($registro->admite($sinMinimo, fpSolicitud('1', '07', 'herbicida')))->toBeTrue()
        ->and(fpCodigo(fn () => $registro->aplicar([], ['cultivo_excluido' => ['soya']])))->toBe('filtro_desconocido:422:nuevas_solicitudes.cultivo_excluido');
});

test('una clave desconocida ya guardada se ignora al evaluar y se conserva (un cliente nuevo no rompe a un servidor viejo)', function () {
    $registro = new FiltrosDeProveedor;
    $guardados = ['filtro_del_futuro' => ['x'], 'hectareas_minimas' => '50.0000'];

    expect($registro->admite($guardados, fpSolicitud('60')))->toBeTrue()
        ->and($registro->aplicar($guardados, ['departamentos_excluidos' => ['09']]))->toHaveKey('filtro_del_futuro');
});

test('el mecanismo es extensible: un filtro nuevo se suma sin tocar el registro ni la base', function () {
    $sinFinesDeSemana = new class implements FiltroDeProveedor
    {
        public function clave(): string
        {
            return 'solo_grandes';
        }

        public function tipo(): string
        {
            return 'booleano';
        }

        public function porDefecto(): mixed
        {
            return false;
        }

        public function normalizar(mixed $valor): mixed
        {
            return $valor === true ? true : null;
        }

        public function admite(mixed $valor, SolicitudParaAviso $solicitud): bool
        {
            return (float) $solicitud->hectareas >= 100;
        }
    };
    $registro = new FiltrosDeProveedor([new HectareasMinimas, $sinFinesDeSemana]);

    $guardados = $registro->aplicar([], ['solo_grandes' => true]);

    expect($registro->disponibles())->toBe([['clave' => 'hectareas_minimas', 'tipo' => 'decimal'], ['clave' => 'solo_grandes', 'tipo' => 'booleano']])
        ->and($registro->admite($guardados, fpSolicitud('99')))->toBeFalse()
        ->and($registro->admite($guardados, fpSolicitud('100')))->toBeTrue();
});

test('las preferencias guardan los filtros en `filtros`, los devuelven con sus por defecto y listan los disponibles', function () {
    fpMundo();
    $preferencias = app(PreferenciasDeAviso::class);

    $porDefecto = $preferencias->de(FP_TITULAR);
    expect($porDefecto['nuevas_solicitudes'])->toBe(['activo' => true, 'hectareas_minimas' => null, 'departamentos_excluidos' => [], 'tipos_aplicacion_excluidos' => []])
        ->and(array_column($porDefecto['filtros_disponibles'], 'tipo', 'clave'))->toBe(['hectareas_minimas' => 'decimal', 'departamentos_excluidos' => 'departamentos', 'tipos_aplicacion_excluidos' => 'tipos_aplicacion']);

    $guardadas = $preferencias->guardar(FP_TITULAR, ['nuevas_solicitudes' => ['hectareas_minimas' => '50', 'departamentos_excluidos' => ['04', '09']]]);

    expect($guardadas['nuevas_solicitudes'])->toBe(['activo' => true, 'hectareas_minimas' => '50.0000', 'departamentos_excluidos' => ['04', '09'], 'tipos_aplicacion_excluidos' => []])
        ->and(json_decode((string) DB::table('neg_preferencias')->where('usuario_id', FP_TITULAR)->value('filtros'), true))->toBe(['hectareas_minimas' => '50.0000', 'departamentos_excluidos' => ['04', '09']]);

    // Lo no enviado se conserva; null quita; los filtros no tocan las categorías.
    $luego = $preferencias->guardar(FP_TITULAR, ['nuevas_solicitudes' => ['tipos_aplicacion_excluidos' => ['desecante'], 'hectareas_minimas' => null], 'mensajes' => false]);

    expect($luego['nuevas_solicitudes'])->toBe(['activo' => true, 'hectareas_minimas' => null, 'departamentos_excluidos' => ['04', '09'], 'tipos_aplicacion_excluidos' => ['desecante']])
        ->and($luego['mensajes'])->toBeFalse()
        ->and(fpCodigo(fn () => $preferencias->guardar(FP_TITULAR, ['nuevas_solicitudes' => ['zonas' => [1]]])))->toBe('filtro_desconocido:422:nuevas_solicitudes.zonas');
});

test('el mínimo de hectáreas guardado antes del mecanismo sigue rigiendo hasta que se guarde de nuevo', function () {
    fpMundo();
    $ahora = now();
    DB::table('neg_preferencias')->insert(['usuario_id' => FP_TITULAR, 'nuevas_solicitudes' => true, 'hectareas_minimas' => '80.0000', 'created_at' => $ahora, 'updated_at' => $ahora]);
    $preferencias = app(PreferenciasDeAviso::class);

    expect($preferencias->coincide(FP_TITULAR, fpSolicitud('79')))->toBeFalse()
        ->and($preferencias->de(FP_TITULAR)['nuevas_solicitudes']['hectareas_minimas'])->toBe('80.0000');

    $preferencias->guardar(FP_TITULAR, ['nuevas_solicitudes' => ['hectareas_minimas' => null]]);

    expect($preferencias->coincide(FP_TITULAR, fpSolicitud('1')))->toBeTrue();
});

/** @return list<int> los destinatarios del aviso de nueva solicitud que emitió el oyente. */
function fpAvisados(ProveedoresFalsos $lectura, ?string $departamento = null, string $hectareas = '100', ?string $tipo = 'herbicida'): array
{
    app()->instance(LecturaProveedores::class, $lectura);
    $avisados = [];

    Event::fake([AvisoDeNegociacion::class]);
    app(AvisarNuevaSolicitud::class)->handle(new SolicitudAbierta(5, 1, FP_CLIENTE, $hectareas, '120.0000', $tipo, $departamento));
    Event::assertDispatched(AvisoDeNegociacion::class, function (AvisoDeNegociacion $aviso) use (&$avisados): bool {
        $avisados = $aviso->usuarios;

        return true;
    });

    return $avisados;
}

test('el aviso de nueva solicitud llega solo a cuentas disponibles que los filtros del proveedor dejan pasar', function () {
    fpMundo();
    $independiente = new DatosProveedor(1, FP_TITULAR, 'independiente', 'Uno', 'aprobado');
    $lectura = new ProveedoresFalsos([1 => $independiente]);

    expect(fpAvisados($lectura))->toBe([FP_TITULAR]);

    app(PreferenciasDeAviso::class)->guardar(FP_TITULAR, ['nuevas_solicitudes' => ['departamentos_excluidos' => ['09']]]);
    Event::fake([AvisoDeNegociacion::class]);
    app()->instance(LecturaProveedores::class, $lectura);
    app(AvisarNuevaSolicitud::class)->handle(new SolicitudAbierta(6, 1, FP_CLIENTE, '100', '120.0000', 'herbicida', '09'));
    Event::assertNotDispatched(AvisoDeNegociacion::class);

    // El filtro nunca se relaja: sin oferta y aunque pase el tiempo, quien excluyó Pando no recibe Pando.
    expect(app(PreferenciasDeAviso::class)->coincide(FP_TITULAR, fpSolicitud('100', '09')))->toBeFalse()
        ->and(app(PreferenciasDeAviso::class)->coincide(FP_TITULAR, fpSolicitud('100', '07')))->toBeTrue();
});

test('un proveedor ocupado o en trabajo no recibe el aviso; el piloto ocupado silencia solo a él', function () {
    fpMundo();
    $disponible = new DatosProveedor(1, FP_TITULAR, 'tercero', 'Empresa', 'aprobado');
    $piloto = new DatosPiloto(11, 1, FP_PILOTO, 'Piloto', 'aprobado');

    expect(fpAvisados(new ProveedoresFalsos([1 => $disponible], [11 => $piloto])))->toBe([FP_TITULAR, FP_PILOTO]);

    $pilotoOcupado = new DatosPiloto(11, 1, FP_PILOTO, 'Piloto', 'aprobado', 'ocupado');
    expect(fpAvisados(new ProveedoresFalsos([1 => $disponible], [11 => $pilotoOcupado])))->toBe([FP_TITULAR]);

    foreach (['ocupado', 'en_trabajo'] as $estado) {
        $empresa = new DatosProveedor(1, FP_TITULAR, 'tercero', 'Empresa', 'aprobado', disponibilidad: $estado);
        Event::fake([AvisoDeNegociacion::class]);
        app()->instance(LecturaProveedores::class, new ProveedoresFalsos([1 => $empresa], [11 => $piloto]));
        app(AvisarNuevaSolicitud::class)->handle(new SolicitudAbierta(7, 1, FP_CLIENTE, '100', '120.0000', 'herbicida'));

        // Con la empresa no disponible no avisa a ningún piloto suyo, aunque el piloto esté disponible.
        Event::assertNotDispatched(AvisoDeNegociacion::class);
    }
});

test('el Avisador sigue entregando las categorías de las preferencias (ofertas, mensajes) sin tocar los filtros', function () {
    fpMundo();
    app(PreferenciasDeAviso::class)->guardar(FP_TITULAR, ['nuevas_solicitudes' => ['departamentos_excluidos' => ['09']], 'ofertas' => false]);
    Event::fake([AvisoDeNegociacion::class]);

    app(Avisador::class)->avisar('oferta_recibida', 'k1', [], 5, [FP_TITULAR], 'ofertas');
    Event::assertNotDispatched(AvisoDeNegociacion::class);

    app(Avisador::class)->avisar('solicitud_adjudicada', 'k2', [], 5, [FP_TITULAR]);
    Event::assertDispatched(AvisoDeNegociacion::class);
});
