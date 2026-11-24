<?php

use App\Dominios\Seguridad\Aplicacion\GestionarFotoDePerfil;
use App\Dominios\Seguridad\Contratos\FotosDePerfil;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Soporte\Esquema;
use Tests\TestCase;

/*
 * La foto de perfil de la persona de la cuenta: cada cuenta escribe solo la suya, el
 * archivo vive en el disco privado y se entrega por una URL firmada de corta vida.
 */

uses(TestCase::class);

function perfilFotoMundo(): void
{
    Esquema::migrar(['create_plt_bitacoras_table', 'create_sec_user_table', 'agrega_foto_perfil_a_sec_user']);
    DB::table('sec_user')->delete();
    Storage::fake('local');

    foreach ([21 => 'ana', 22 => 'beto'] as $id => $username) {
        DB::table('sec_user')->insert(['id' => $id, 'name' => ucfirst($username), 'username' => $username, 'password' => 'x', 'type' => 'interno', 'state' => true, 'created_at' => now(), 'updated_at' => now()]);
    }
}

test('subir la foto la guarda en el disco privado y devuelve una URL firmada de corta vida', function () {
    perfilFotoMundo();
    $fotos = app(GestionarFotoDePerfil::class);

    $url = $fotos->subir(21, UploadedFile::fake()->image('yo.jpg')->size(80));
    $ref = DB::table('sec_user')->where('id', 21)->value('foto_perfil_ref');

    expect($url)->toStartWith('/api/personas/21/foto?')->toContain('expires=')->toContain('signature=')
        ->and($ref)->toStartWith('perfiles/21/')
        ->and($fotos->ruta(21))->not->toBeNull()
        ->and(DB::table('sec_user')->where('id', 22)->value('foto_perfil_ref'))->toBeNull()
        ->and($fotos->ruta(22))->toBeNull();
    Storage::disk('local')->assertExists($ref);
});

test('reemplazar la foto borra el archivo anterior y quitarla deja la cuenta sin foto', function () {
    perfilFotoMundo();
    $fotos = app(GestionarFotoDePerfil::class);

    $fotos->subir(21, UploadedFile::fake()->image('uno.jpg')->size(60));
    $primera = DB::table('sec_user')->where('id', 21)->value('foto_perfil_ref');
    $fotos->subir(21, UploadedFile::fake()->image('dos.jpg')->size(60));
    $segunda = DB::table('sec_user')->where('id', 21)->value('foto_perfil_ref');

    Storage::disk('local')->assertMissing($primera);
    Storage::disk('local')->assertExists($segunda);

    $fotos->quitar(21);
    $fotos->quitar(21);

    Storage::disk('local')->assertMissing($segunda);
    expect(DB::table('sec_user')->where('id', 21)->value('foto_perfil_ref'))->toBeNull()
        ->and(app(FotosDePerfil::class)->urlDe(21))->toBeNull();
});

test('subir la foto de una cuenta no toca la de otra', function () {
    perfilFotoMundo();
    $fotos = app(GestionarFotoDePerfil::class);

    $fotos->subir(21, UploadedFile::fake()->image('ana.jpg')->size(60));
    $fotos->subir(22, UploadedFile::fake()->image('beto.jpg')->size(60));
    $fotos->quitar(22);

    expect(app(FotosDePerfil::class)->urlDe(21))->not->toBeNull()
        ->and(app(FotosDePerfil::class)->urlDe(22))->toBeNull();
});
