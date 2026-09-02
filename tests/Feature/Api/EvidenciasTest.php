<?php

use App\Dominios\Operaciones\Infraestructura\Eloquent\Evidencia;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * POST /api/evidencias (espec §2.1 punto 7, TE-07 parte servidor, tarea 19):
 * cola separada de evidencias, fuera del lote de /api/sync — sube el archivo
 * al disco r2, calcula el hash SHA-256 en el servidor y responde con el mismo
 * vocabulario que ResultadoSync (aplicado/duplicado/rechazado).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('r2');
    $this->actingAs(SecUser::factory()->create(), 'sanctum');
});

/** @return array<string, mixed> */
function camposEvidencia(string $uuidCliente, array $sobrescribir = []): array
{
    return array_merge([
        'uuid_cliente' => $uuidCliente,
        'tipo' => 'imagen_campo',
        'fecha' => '2026-09-01T10:00:00-04:00',
    ], $sobrescribir);
}

it('una subida nueva persiste el archivo en el disco r2 y la fila en la base', function () {
    $archivo = UploadedFile::fake()->createWithContent('campo.jpg', 'contenido-de-prueba-1');

    $respuesta = $this->post('/api/evidencias', [
        ...camposEvidencia('uuid-ev-1'),
        'archivo' => $archivo,
    ])->assertOk();

    expect($respuesta->json())->toBe(['uuid_cliente' => 'uuid-ev-1', 'estado' => 'aplicado']);

    $evidencia = Evidencia::query()->where('uuid_cliente', 'uuid-ev-1')->firstOrFail();

    expect($evidencia->tipo->value)->toBe('imagen_campo')
        ->and($evidencia->hash)->toBe(hash('sha256', 'contenido-de-prueba-1'))
        // Ruta ADR 0009: evidencias/{tipo}/{yyyy}/{mm}/{uuid_cliente}-{id}.{ext}
        ->and($evidencia->archivo_url)->toBe("evidencias/imagen_campo/2026/09/uuid-ev-1-{$evidencia->id}.jpg");

    Storage::disk('r2')->assertExists($evidencia->archivo_url);
    expect(Storage::disk('r2')->get($evidencia->archivo_url))->toBe('contenido-de-prueba-1');
});

it('reintentar el mismo uuid_cliente responde duplicado, sin crear una segunda fila ni reescribir el archivo', function () {
    $primero = UploadedFile::fake()->createWithContent('campo.jpg', 'contenido-original');
    $this->post('/api/evidencias', [
        ...camposEvidencia('uuid-ev-2'),
        'archivo' => $primero,
    ])->assertOk();

    $segundo = UploadedFile::fake()->createWithContent('campo.jpg', 'contenido-distinto-en-el-reintento');
    $respuesta = $this->post('/api/evidencias', [
        ...camposEvidencia('uuid-ev-2'),
        'archivo' => $segundo,
    ])->assertOk();

    expect($respuesta->json())->toBe(['uuid_cliente' => 'uuid-ev-2', 'estado' => 'duplicado'])
        ->and(Evidencia::query()->where('uuid_cliente', 'uuid-ev-2')->count())->toBe(1);

    $evidencia = Evidencia::query()->where('uuid_cliente', 'uuid-ev-2')->firstOrFail();

    // El contenido en disco sigue siendo el del primer request: el reintento
    // nunca llega a Storage::put() (ver RegistrarEvidencia).
    expect(Storage::disk('r2')->get($evidencia->archivo_url))->toBe('contenido-original')
        ->and($evidencia->hash)->toBe(hash('sha256', 'contenido-original'));
});

it('un tipo fuera del catálogo se rechaza sin persistir fila ni archivo', function () {
    $archivo = UploadedFile::fake()->createWithContent('campo.jpg', 'contenido-tipo-invalido');

    $respuesta = $this->post('/api/evidencias', [
        ...camposEvidencia('uuid-ev-3', ['tipo' => 'tipo_que_no_existe']),
        'archivo' => $archivo,
    ])->assertOk();

    expect($respuesta->json('estado'))->toBe('rechazado')
        ->and($respuesta->json('motivo'))->not->toBeNull()
        ->and(Evidencia::query()->where('uuid_cliente', 'uuid-ev-3')->exists())->toBeFalse()
        ->and(Storage::disk('r2')->allFiles())->toBeEmpty();
});

it('un archivo ausente se rechaza sin persistir fila', function () {
    $respuesta = $this->post('/api/evidencias', camposEvidencia('uuid-ev-4'))->assertOk();

    expect($respuesta->json())->toBe([
        'uuid_cliente' => 'uuid-ev-4',
        'estado' => 'rechazado',
        'motivo' => 'archivo ausente o vacío',
    ]);

    expect(Evidencia::query()->where('uuid_cliente', 'uuid-ev-4')->exists())->toBeFalse();
});

it('un archivo vacío se rechaza sin persistir fila', function () {
    $vacio = UploadedFile::fake()->createWithContent('vacio.jpg', '');

    $respuesta = $this->post('/api/evidencias', [
        ...camposEvidencia('uuid-ev-5'),
        'archivo' => $vacio,
    ])->assertOk();

    expect($respuesta->json())->toBe([
        'uuid_cliente' => 'uuid-ev-5',
        'estado' => 'rechazado',
        'motivo' => 'archivo ausente o vacío',
    ]);

    expect(Evidencia::query()->where('uuid_cliente', 'uuid-ev-5')->exists())->toBeFalse();
});

it('el hash persistido coincide con el contenido subido para cada tipo del catálogo', function (string $tipo) {
    $contenido = "contenido-{$tipo}";
    $archivo = UploadedFile::fake()->createWithContent("{$tipo}.jpg", $contenido);

    $this->post('/api/evidencias', [
        ...camposEvidencia("uuid-ev-tipo-{$tipo}", ['tipo' => $tipo]),
        'archivo' => $archivo,
    ])->assertOk()->assertJson(['estado' => 'aplicado']);

    $evidencia = Evidencia::query()->where('uuid_cliente', "uuid-ev-tipo-{$tipo}")->firstOrFail();

    expect($evidencia->hash)->toBe(hash('sha256', $contenido))
        ->and(Storage::disk('r2')->get($evidencia->archivo_url))->toBe($contenido);
})->with(['captura_rc', 'imagen_campo', 'foto_incidencia', 'comprobante', 'firma_acta']);

it('un hash_dispositivo que coincide con el contenido recibido aplica normalmente', function () {
    $contenido = 'contenido-con-hash-verificado';
    $archivo = UploadedFile::fake()->createWithContent('campo.jpg', $contenido);

    $respuesta = $this->post('/api/evidencias', [
        ...camposEvidencia('uuid-ev-6', ['hash_dispositivo' => hash('sha256', $contenido)]),
        'archivo' => $archivo,
    ])->assertOk();

    expect($respuesta->json('estado'))->toBe('aplicado');

    $evidencia = Evidencia::query()->where('uuid_cliente', 'uuid-ev-6')->firstOrFail();
    expect($evidencia->hash)->toBe(hash('sha256', $contenido));
});

it('un hash_dispositivo que NO coincide con el contenido recibido se rechaza sin persistir fila ni archivo', function () {
    $archivo = UploadedFile::fake()->createWithContent('campo.jpg', 'contenido-real');

    $respuesta = $this->post('/api/evidencias', [
        ...camposEvidencia('uuid-ev-7', ['hash_dispositivo' => hash('sha256', 'otro-contenido-distinto')]),
        'archivo' => $archivo,
    ])->assertOk();

    expect($respuesta->json())->toBe([
        'uuid_cliente' => 'uuid-ev-7',
        'estado' => 'rechazado',
        'motivo' => 'el hash declarado no coincide con el contenido recibido',
    ]);

    expect(Evidencia::query()->where('uuid_cliente', 'uuid-ev-7')->exists())->toBeFalse()
        ->and(Storage::disk('r2')->allFiles())->toBeEmpty();
});

it('un uuid_cliente con una barra se rechaza sin persistir fila ni archivo', function () {
    $archivo = UploadedFile::fake()->createWithContent('campo.jpg', 'contenido-uuid-con-barra');

    $respuesta = $this->post('/api/evidencias', [
        ...camposEvidencia('uuid/con-barra'),
        'archivo' => $archivo,
    ])->assertOk();

    expect($respuesta->json('estado'))->toBe('rechazado')
        ->and(Evidencia::query()->where('uuid_cliente', 'uuid/con-barra')->exists())->toBeFalse()
        ->and(Storage::disk('r2')->allFiles())->toBeEmpty();
});

it('una fecha inválida se rechaza sin persistir fila ni archivo', function () {
    $archivo = UploadedFile::fake()->createWithContent('campo.jpg', 'contenido-fecha-invalida');

    $respuesta = $this->post('/api/evidencias', [
        ...camposEvidencia('uuid-ev-8', ['fecha' => 'no-es-una-fecha']),
        'archivo' => $archivo,
    ])->assertOk();

    expect($respuesta->json())->toBe([
        'uuid_cliente' => 'uuid-ev-8',
        'estado' => 'rechazado',
        'motivo' => 'fecha inválida',
    ]);

    expect(Evidencia::query()->where('uuid_cliente', 'uuid-ev-8')->exists())->toBeFalse()
        ->and(Storage::disk('r2')->allFiles())->toBeEmpty();
});
