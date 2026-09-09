<?php

use App\Dominios\Compartido\Contratos\LecturaConfiguracion;
use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\Configuracion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/*
 * Tarea 78 (HU-55) — "el corazón de la tarea": el valor de una configuración
 * SECRETA nunca vuelve al navegador ni se guarda en claro en ningún lado
 * (columna, bitácora). Cubre el modelo `Configuracion` (cifrado en reposo,
 * exclusión de la bitácora) y `ResolutorConfiguracion` (cascada base → .env,
 * nunca al revés, nunca revienta si falta).
 */

uses(RefreshDatabase::class);

function bitacoraDeConfiguracion(int $registroId, AccionBitacora $accion): ?Bitacora
{
    return Bitacora::query()
        ->where('tabla', 'plt_configuraciones')
        ->where('registro_id', $registroId)
        ->where('accion', $accion)
        ->latest('id')
        ->first();
}

it('guarda el valor cifrado en la columna: leer la fila cruda no devuelve el texto plano', function () {
    $configuracion = Configuracion::query()->create([
        'clave' => 'mapas.google_maps_api_key',
        'valor' => 'AIzaSyD-super-secreta-0000',
        'grupo' => 'mapas',
        'es_secreto' => true,
    ]);

    $crudo = DB::table('plt_configuraciones')->where('id', $configuracion->id)->value('valor');

    expect($crudo)->not->toBeNull()
        ->and($crudo)->not->toContain('AIzaSyD-super-secreta-0000')
        ->and(Configuracion::query()->find($configuracion->id)->valor)->toBe('AIzaSyD-super-secreta-0000');
});

it('la bitácora registra el alta de una configuración con la clave, nunca con el valor', function () {
    $configuracion = Configuracion::query()->create([
        'clave' => 'correo.password',
        'valor' => 'una-contrasena-smtp',
        'grupo' => 'correo',
        'es_secreto' => true,
    ]);

    $fila = bitacoraDeConfiguracion($configuracion->id, AccionBitacora::Creado);

    expect($fila)->not->toBeNull()
        ->and($fila->despues)->toHaveKey('clave', 'correo.password')
        ->and($fila->despues)->not->toHaveKey('valor');
});

it('la bitácora registra que un valor cambió, sin exponerlo ni cifrado, cuando es la única columna que cambia', function () {
    $configuracion = Configuracion::query()->create([
        'clave' => 'correo.password',
        'valor' => 'valor-viejo',
        'grupo' => 'correo',
        'es_secreto' => true,
    ]);

    $configuracion->valor = 'valor-nuevo';
    $configuracion->save();

    $fila = bitacoraDeConfiguracion($configuracion->id, AccionBitacora::Actualizado);

    expect($fila)->not->toBeNull()
        ->and($fila->antes)->toBeNull()
        ->and($fila->despues)->toBeNull();
});

it('la bitácora sigue registrando otras columnas no secretas junto al cambio de valor', function () {
    $configuracion = Configuracion::query()->create([
        'clave' => 'correo.password',
        'valor' => 'valor-viejo',
        'grupo' => 'correo',
        'descripcion' => 'Contraseña SMTP',
        'es_secreto' => true,
    ]);

    $configuracion->valor = 'valor-nuevo';
    $configuracion->descripcion = 'Contraseña SMTP (renovada)';
    $configuracion->save();

    $fila = bitacoraDeConfiguracion($configuracion->id, AccionBitacora::Actualizado);

    expect($fila)->not->toBeNull()
        ->and($fila->antes)->toBe(['descripcion' => 'Contraseña SMTP'])
        ->and($fila->despues)->toBe(['descripcion' => 'Contraseña SMTP (renovada)']);
});

function configurarRespaldoDePrueba(string $clave, string $ruta): void
{
    $claves = config('configuracion.claves');
    $claves[$clave] = ['respaldo' => $ruta];
    Config::set('configuracion.claves', $claves);
}

it('sin fila en la base, resuelve al respaldo de config()', function () {
    Config::set('app.name', 'Agrocom de prueba');
    configurarRespaldoDePrueba('prueba.clave', 'app.name');

    $resolutor = app(LecturaConfiguracion::class);

    expect($resolutor->valor('prueba.clave'))->toBe('Agrocom de prueba');
});

it('con fila en la base, la base gana sobre el respaldo de config()', function () {
    Config::set('app.name', 'Agrocom de prueba');
    configurarRespaldoDePrueba('prueba.clave', 'app.name');

    Configuracion::query()->create([
        'clave' => 'prueba.clave',
        'valor' => 'valor-de-la-base',
        'grupo' => 'integraciones',
    ]);

    $resolutor = app(LecturaConfiguracion::class);

    expect($resolutor->valor('prueba.clave'))->toBe('valor-de-la-base');
});

it('sin fila y sin respaldo configurado, resuelve a null sin excepción', function () {
    $resolutor = app(LecturaConfiguracion::class);

    expect($resolutor->valor('mapas.google_maps_api_key'))->toBeNull();
});

it('un valor explícitamente borrado (null) cae de nuevo al respaldo de config()', function () {
    Config::set('app.name', 'Agrocom de prueba');
    configurarRespaldoDePrueba('prueba.clave', 'app.name');

    $configuracion = Configuracion::query()->create([
        'clave' => 'prueba.clave',
        'valor' => 'valor-de-la-base',
        'grupo' => 'integraciones',
    ]);

    $configuracion->valor = null;
    $configuracion->save();

    $resolutor = app(LecturaConfiguracion::class);

    expect($resolutor->valor('prueba.clave'))->toBe('Agrocom de prueba');
});
