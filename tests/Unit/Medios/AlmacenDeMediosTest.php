<?php

use App\Dominios\Compartido\Aplicacion\Medios\AlmacenDeMedios;
use App\Dominios\Compartido\Aplicacion\Medios\ArchivoDeMedio;
use App\Dominios\Compartido\Aplicacion\Medios\GeneradorDeDerivados;
use App\Dominios\Compartido\Aplicacion\Medios\LimiteDeMedios;
use App\Dominios\Compartido\Aplicacion\Medios\RegistrarAccesoAMedio;
use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Dominio\VarianteDeMedio;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Soporte\Esquema;
use Tests\TestCase;

/*
 * El almacén central de medios: ruta relativa y nombre generado, derivados junto al original, borrado y movimiento
 * que arrastran a los derivados, degradación sin error cuando falta el archivo, límites y constancia de accesos.
 */

uses(TestCase::class);

beforeEach(function () {
    Storage::fake('local');
});

test('guardar deja la ruta relativa con un nombre generado que no lleva el nombre subido', function () {
    $almacen = app(AlmacenDeMedios::class);

    $ruta = $almacen->guardar(UploadedFile::fake()->image('carnet de Juan Perez.jpg', 40, 30), 'drones/7/fotos');
    $otra = $almacen->guardar(UploadedFile::fake()->image('carnet de Juan Perez.jpg', 40, 30), 'drones/7/fotos');

    expect($ruta)->toMatch('#^drones/7/fotos/[0-9a-f]{32}\.jpg$#')
        ->and($ruta)->not->toBe($otra)
        ->and($ruta)->not->toContain('Juan')
        ->and($almacen->existe($ruta))->toBeTrue();
    Storage::disk('local')->assertExists($ruta);
});

test('un archivo que falta degrada sin error: no existe, no se sirve y borrarlo no falla', function () {
    $almacen = app(AlmacenDeMedios::class);

    expect($almacen->existe('drones/7/fotos/no-esta.jpg'))->toBeFalse()
        ->and($almacen->existe(null))->toBeFalse()
        ->and($almacen->existe(''))->toBeFalse()
        ->and($almacen->rutaAbsoluta('drones/7/fotos/no-esta.jpg'))->toBeNull()
        ->and($almacen->respuesta('drones/7/fotos/no-esta.jpg'))->toBeNull()
        ->and($almacen->peso('drones/7/fotos/no-esta.jpg'))->toBeNull();

    $almacen->borrar('drones/7/fotos/no-esta.jpg');
    $almacen->borrar(null);
});

test('normalizar deja solo la ruta relativa dentro del disco', function () {
    $raiz = storage_path('app/private');

    expect(AlmacenDeMedios::normalizar('drones/7/fotos/a.jpg'))->toBe('drones/7/fotos/a.jpg')
        ->and(AlmacenDeMedios::normalizar('https://api.agrocom.test/storage/drones/7/fotos/a.jpg?x=1'))->toBe('drones/7/fotos/a.jpg')
        ->and(AlmacenDeMedios::normalizar('/storage/app/private/drones/7/fotos/a.jpg'))->toBe('drones/7/fotos/a.jpg')
        ->and(AlmacenDeMedios::normalizar($raiz.'/drones/7/fotos/a.jpg'))->toBe('drones/7/fotos/a.jpg')
        ->and(AlmacenDeMedios::normalizar('../etc/passwd'))->toBeNull()
        ->and(AlmacenDeMedios::normalizar('a/../../b.jpg'))->toBeNull()
        ->and(AlmacenDeMedios::normalizar('  '))->toBeNull()
        ->and(AlmacenDeMedios::normalizar(null))->toBeNull();
});

test('los derivados se generan junto al original, pesan menos y el original queda intacto', function () {
    $almacen = app(AlmacenDeMedios::class);
    $archivo = UploadedFile::fake()->image('lote.jpg', 1600, 1200);
    $bytesOriginal = filesize($archivo->getRealPath());
    $ruta = $almacen->guardar($archivo, 'terrenos/3/fotos');

    // En pruebas la cola es síncrona: el trabajo ya corrió al guardar.
    $miniatura = $almacen->derivado($ruta, VarianteDeMedio::Miniatura);
    $media = $almacen->derivado($ruta, VarianteDeMedio::Media);

    expect($miniatura)->not->toBeNull()->and($media)->not->toBeNull()
        ->and($miniatura)->toStartWith(substr($ruta, 0, -4).'.miniatura.')
        ->and($almacen->peso($miniatura))->toBeLessThan($almacen->peso($media))
        ->and($almacen->peso($media))->toBeLessThan($bytesOriginal)
        ->and($almacen->peso($ruta))->toBe($bytesOriginal);

    [$ancho] = getimagesizefromstring((string) Storage::disk('local')->get($miniatura));
    expect($ancho)->toBe((int) config('medios.derivados.miniatura.lado'));

    // La variante pedida sirve el derivado; el original, el original; sin derivado, cae al original.
    expect($almacen->rutaServible($ruta, VarianteDeMedio::Miniatura))->toBe($miniatura)
        ->and($almacen->rutaServible($ruta, VarianteDeMedio::Original))->toBe($ruta);

    Storage::disk('local')->delete($miniatura);
    expect($almacen->rutaServible($ruta, VarianteDeMedio::Miniatura))->toBe($ruta);
});

test('generar es idempotente y un PDF no se transforma', function () {
    $almacen = app(AlmacenDeMedios::class);
    $generador = app(GeneradorDeDerivados::class);
    $ruta = $almacen->guardar(UploadedFile::fake()->image('a.jpg', 800, 600), 'terrenos/3/fotos');

    expect($generador->generar($ruta))->toBe([])
        ->and($generador->generar($ruta, forzar: true))->toHaveCount(2);

    $pdf = $almacen->guardar(UploadedFile::fake()->createWithContent('licencia.pdf', "%PDF-1.4\n%fin"), 'proveedores/1/documentos');

    expect($pdf)->toEndWith('.pdf')
        ->and($generador->generar($pdf))->toBe([])
        ->and($almacen->derivado($pdf, VarianteDeMedio::Miniatura))->toBeNull();
});

test('una imagen ilegible se omite sin error y deja el original', function () {
    $almacen = app(AlmacenDeMedios::class);
    Storage::disk('local')->put('terrenos/9/fotos/rota.jpg', 'esto no es una imagen');

    expect(app(GeneradorDeDerivados::class)->generar('terrenos/9/fotos/rota.jpg'))->toBe([])
        ->and($almacen->existe('terrenos/9/fotos/rota.jpg'))->toBeTrue();
});

test('borrar y mover arrastran a los derivados', function () {
    $almacen = app(AlmacenDeMedios::class);
    $ruta = $almacen->guardar(UploadedFile::fake()->image('a.jpg', 800, 600), 'drones/1/fotos');
    $miniatura = $almacen->derivado($ruta, VarianteDeMedio::Miniatura);

    expect($almacen->mover($ruta, 'drones/2/fotos/movida.jpg'))->toBeTrue();
    Storage::disk('local')->assertMissing($ruta);
    Storage::disk('local')->assertMissing($miniatura);
    expect($almacen->existe('drones/2/fotos/movida.jpg'))->toBeTrue()
        ->and($almacen->derivado('drones/2/fotos/movida.jpg', VarianteDeMedio::Miniatura))->not->toBeNull()
        ->and($almacen->mover('drones/1/fotos/no-esta.jpg', 'drones/2/fotos/otra.jpg'))->toBeFalse();

    $almacen->borrar('drones/2/fotos/movida.jpg');

    expect(Storage::disk('local')->allFiles('drones'))->toBe([]);
});

test('el ViewModel de un archivo trae todo lo que una cuadrícula necesita y degrada si falta', function () {
    $almacen = app(AlmacenDeMedios::class);
    $ruta = $almacen->guardar(UploadedFile::fake()->image('a.jpg', 800, 600), 'drones/1/fotos');
    $url = fn (VarianteDeMedio $variante): string => "/x/archivo?variante={$variante->value}";

    $imagen = ArchivoDeMedio::de($ruta, null, 'Foto frontal', null, null, $url, '/x/descargar')->aArray();

    expect($imagen)->toMatchArray([
        'url_miniatura' => '/x/archivo?variante=miniatura',
        'url_media' => '/x/archivo?variante=media',
        'url_original' => '/x/archivo?variante=original',
        'existe' => true,
        'tipo' => 'imagen',
        'tiene_vista_previa' => true,
        'mime' => 'image/jpeg',
        'nombre_visible' => 'Foto frontal',
        'url_descarga' => '/x/descargar',
    ])->and($imagen['peso'])->toBeGreaterThan(0);

    $pdf = $almacen->guardar(UploadedFile::fake()->createWithContent('l.pdf', "%PDF-1.4\n%fin"), 'proveedores/1/documentos');
    $documento = ArchivoDeMedio::de($pdf, null, 'Licencia', 'application/pdf', 12, $url)->aArray();

    expect($documento['tipo'])->toBe('pdf')->and($documento['tiene_vista_previa'])->toBeFalse()
        ->and($documento['url_miniatura'])->toBeNull()->and($documento['url_original'])->not->toBeNull()
        ->and($documento['peso'])->toBe(12);

    $falta = ArchivoDeMedio::de('drones/1/fotos/borrada.jpg', null, 'Foto', 'image/jpeg', 500, $url, '/x/descargar')->aArray();

    expect($falta['existe'])->toBeFalse()->and($falta['url_original'])->toBeNull()->and($falta['url_descarga'])->toBeNull()
        ->and($falta['url_miniatura'])->toContain('medio-no-disponible')->and($falta['tipo'])->toBe('imagen');
});

test('los límites salen de la configuración y se exponen para las apps', function () {
    config(['medios.limites.por_subida' => 7, 'medios.limites.por_entidad.dron_fotos' => 3]);
    $limites = app(LimiteDeMedios::class);

    expect($limites->excede('dron_fotos', 2))->toBeFalse()
        ->and($limites->excede('dron_fotos', 3))->toBeTrue()
        ->and($limites->excede('dron_fotos', 1, 3))->toBeTrue()
        ->and($limites->resumen()['por_subida'])->toBe(7)
        ->and($limites->resumen()['por_entidad'])->toHaveKeys(LimiteDeMedios::CATEGORIAS)
        ->and($limites->resumen()['por_entidad']['dron_fotos'])->toBe(3);
});

test('abrir o descargar un original deja constancia en la bitácora sin contenido ni nombre de archivo', function () {
    Esquema::migrar(['create_plt_bitacoras_table', 'create_sec_user_table']);
    DB::table('sec_user')->delete();
    DB::table('sec_user')->insert(['id' => 5, 'name' => 'Ana', 'username' => 'ana', 'password' => 'x', 'type' => 'interno', 'state' => true, 'created_at' => now(), 'updated_at' => now()]);
    Esquema::migrar(['create_sec_user_preferencia_table']);

    app(RegistrarAccesoAMedio::class)->ejecutar(5, 'prv_documentos', 41, 'descargado');

    $fila = Bitacora::query()->latest('id')->first();

    expect($fila->accion)->toBe(AccionBitacora::Descargado)
        ->and($fila->user_id)->toBe(5)->and($fila->tabla)->toBe('prv_documentos')->and($fila->registro_id)->toBe(41)
        ->and($fila->despues)->toBe(['medio' => 'descargado'])->and($fila->antes)->toBeNull();
});
