<?php

use App\Dominios\Compartido\Aplicacion\Medios\AlmacenDeMedios;
use App\Dominios\Proveedores\Aplicacion\ItemsDeRevisionDeDocumentos;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Documento;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Soporte\Esquema;
use Tests\TestCase;

/*
 * La cuadrícula de medios y su modal de revisión: la miniatura no lleva acciones ni descarga (solo el modal las
 * trae) y cada acción llega decidida por permiso en el ítem.
 */

uses(TestCase::class);

function cuadriculaMundo(): void
{
    app()->setLocale('es');
    Esquema::migrar(['create_plt_bitacoras_table', 'create_sec_user_table', 'create_sec_user_preferencia_table', 'create_sol_zonas_table', 'create_prv_proveedores_table', 'create_prv_documentos_table', 'retira_zonas']);

    foreach (['prv_documentos', 'prv_proveedores', 'plt_bitacoras', 'sec_user'] as $tabla) {
        DB::table($tabla)->delete();
    }

    Storage::fake('local');
    $ahora = now();
    DB::table('sec_user')->insert(['id' => 9, 'name' => 'Rev', 'username' => 'rev', 'password' => 'x', 'type' => 'interno', 'state' => true, 'created_at' => $ahora, 'updated_at' => $ahora]);
    DB::table('prv_proveedores')->insert(['id' => 1, 'usuario_titular_id' => 9, 'tipo' => 'independiente', 'razon_social' => 'P', 'documento_fiscal' => '1', 'telefono' => '1', 'estado' => 'aprobado', 'created_at' => $ahora, 'updated_at' => $ahora]);

    $imagen = app(AlmacenDeMedios::class)->guardar(UploadedFile::fake()->image('carnet.jpg', 900, 600), 'proveedores/1/documentos');
    $pdf = app(AlmacenDeMedios::class)->guardar(UploadedFile::fake()->create('licencia.pdf', 20, 'application/pdf'), 'proveedores/1/documentos');

    DB::table('prv_documentos')->insert([
        ['sujeto_tipo' => 'proveedor', 'sujeto_id' => 1, 'tipo' => 'identidad', 'archivo_ref' => $imagen, 'nombre_original' => 'carnet.jpg', 'mime' => 'image/jpeg', 'bytes' => 900, 'estado' => 'en_revision', 'subido_por' => 9, 'created_at' => $ahora, 'updated_at' => $ahora],
        ['sujeto_tipo' => 'proveedor', 'sujeto_id' => 1, 'tipo' => 'licencia_piloto', 'archivo_ref' => $pdf, 'nombre_original' => 'licencia.pdf', 'mime' => 'application/pdf', 'bytes' => 20000, 'estado' => 'aprobado', 'subido_por' => 9, 'created_at' => $ahora, 'updated_at' => $ahora],
        ['sujeto_tipo' => 'proveedor', 'sujeto_id' => 1, 'tipo' => 'registro_dron', 'archivo_ref' => 'proveedores/1/documentos/no-existe.jpg', 'nombre_original' => 'x.jpg', 'mime' => 'image/jpeg', 'bytes' => 10, 'estado' => 'en_revision', 'subido_por' => 9, 'created_at' => $ahora, 'updated_at' => $ahora],
    ]);
}

/** @return array{0: string, 1: string} el HTML de la cuadrícula y el del modal. */
function cuadriculaHtml(array $items): array
{
    $html = Blade::render('<x-organisms.cuadricula-medios id="docs" :items="$items" />', ['items' => $items]);
    $corte = strpos($html, 'class="modal fade ag-modal-medio"');

    return [substr($html, 0, $corte), substr($html, $corte)];
}

test('la cuadrícula muestra la miniatura y el estado pero ninguna acción ni descarga', function () {
    cuadriculaMundo();
    $items = ItemsDeRevisionDeDocumentos::deModelos(Documento::query()->orderBy('id')->get(), true, true);

    [$cuadricula] = cuadriculaHtml($items);

    expect($cuadricula)->toContain('ag-cuadricula-medios__celda')
        ->and($cuadricula)->toContain('variante=miniatura')
        ->and($cuadricula)->toContain(__('revision_medios.pendiente'))
        ->and($cuadricula)->not->toContain('<form')
        ->and($cuadricula)->not->toContain('type="submit"')
        ->and($cuadricula)->not->toContain(__('revision_medios.modal.descargar'))
        ->and($cuadricula)->not->toContain(__('revision_medios.modal.aprobar'))
        ->and($cuadricula)->not->toContain(__('revision_medios.modal.rechazar'))
        ->and($cuadricula)->not->toContain('descargar');
});

test('un PDF sin miniatura muestra el ícono y la etiqueta de sin vista previa', function () {
    cuadriculaMundo();
    $items = ItemsDeRevisionDeDocumentos::deModelos(Documento::query()->orderBy('id')->get(), true, false);

    [$cuadricula] = cuadriculaHtml($items);

    expect($cuadricula)->toContain('picture_as_pdf')->and($cuadricula)->toContain(__('revision_medios.sin_vista_previa'));
});

test('sin el permiso de descargar el ítem no trae enlace de descarga; con él, sí', function () {
    cuadriculaMundo();
    $documentos = Documento::query()->orderBy('id')->get();

    $sin = ItemsDeRevisionDeDocumentos::deModelos($documentos, true, false);
    $con = ItemsDeRevisionDeDocumentos::deModelos($documentos, true, true);
    [, $modalSin] = cuadriculaHtml($sin);
    [, $modalCon] = cuadriculaHtml($con);

    expect($sin[0]['medio']['url_descarga'])->toBeNull()
        ->and($modalSin)->not->toContain('\/descargar')
        ->and($con[0]['medio']['url_descarga'])->toContain('/descargar')
        ->and($con[1]['medio']['url_descarga'])->toContain('/descargar')
        // El archivo que falta nunca ofrece descarga, aunque haya permiso.
        ->and($con[2]['medio']['existe'])->toBeFalse()
        ->and($con[2]['medio']['url_descarga'])->toBeNull()
        ->and($modalCon)->toContain('\/descargar');
});

test('solo lo que está en revisión se puede aprobar o rechazar, y solo con el permiso de revisar', function () {
    cuadriculaMundo();
    $documentos = Documento::query()->orderBy('id')->get();

    $reviso = ItemsDeRevisionDeDocumentos::deModelos($documentos, true, false);
    $noReviso = ItemsDeRevisionDeDocumentos::deModelos($documentos, false, false);

    expect($reviso[0]['puede_aprobar'])->toBeTrue()
        ->and($reviso[0]['puede_rechazar'])->toBeTrue()
        ->and($reviso[0]['url_revisar'])->toContain('/revisar')
        ->and($reviso[1]['puede_aprobar'])->toBeFalse()
        ->and($noReviso[0]['puede_aprobar'])->toBeFalse()
        ->and($noReviso[0]['puede_rechazar'])->toBeFalse()
        ->and($noReviso[0]['url_revisar'])->toBeNull();
});

test('un archivo que falta se marca como no existente y la cuadrícula lo dice', function () {
    cuadriculaMundo();
    $items = ItemsDeRevisionDeDocumentos::deModelos(Documento::query()->orderBy('id')->get(), true, true);

    [$cuadricula, $modal] = cuadriculaHtml($items);

    expect($items[2]['medio']['existe'])->toBeFalse()
        ->and($cuadricula)->toContain(__('revision_medios.no_disponible_miniatura'))
        ->and($modal)->toContain(__('revision_medios.modal.no_disponible'));
});

test('el modal es un diálogo accesible con anterior, siguiente y motivo obligatorio al rechazar', function () {
    cuadriculaMundo();
    [, $modal] = cuadriculaHtml(ItemsDeRevisionDeDocumentos::deModelos(Documento::query()->get(), true, true));

    expect($modal)->toContain('aria-labelledby')
        ->and($modal)->toContain('data-bs-dismiss="modal"')
        ->and($modal)->toContain('data-medio="anterior"')
        ->and($modal)->toContain('data-medio="siguiente"')
        ->and($modal)->toContain('name="motivo"')
        ->and($modal)->toContain('required');
});

test('sin ítems la cuadrícula no pinta nada', function () {
    expect(trim(Blade::render('<x-organisms.cuadricula-medios id="vacia" :items="[]" />')))->toBe('');
});
