<?php

use App\Dominios\Compartido\Aplicacion\Medios\AlmacenDeMedios;
use App\Dominios\Compartido\Aplicacion\Medios\RegistrarAccesoAMedio;
use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Dominio\VarianteDeMedio;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Proveedores\Aplicacion\CargarDocumento;
use App\Dominios\Proveedores\Aplicacion\MedioDeDocumento;
use App\Dominios\Proveedores\Contratos\DocumentoInvalido;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Documento;
use App\Dominios\Proveedores\Infraestructura\Http\Controllers\Web\DocumentosController;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Soporte\Esquema;
use Tests\TestCase;

/*
 * El visor y la descarga del original de un documento en el panel (zona crítica: documentos de identidad):
 * cada permiso por separado, encabezados seguros, constancia en la bitácora solo del original y el tope de
 * documentos en revisión por sujeto.
 */

uses(TestCase::class);

/** Panel de mentira: concede solo los permisos que se le pasan. */
function docPanel(array $permisos): AutorizacionPanelWeb
{
    return new class($permisos) implements AutorizacionPanelWeb
    {
        /** @param  list<string>  $permisos */
        public function __construct(private readonly array $permisos) {}

        public function tienePermiso(Request $request, string $codigoPermiso): bool
        {
            return in_array($codigoPermiso, $this->permisos, true);
        }

        public function cascara(Request $request): array
        {
            return [];
        }

        public function personaId(Request $request): ?int
        {
            return null;
        }

        public function primerDestinoVisible(Request $request): string
        {
            return '/';
        }
    };
}

function docMundo(): Documento
{
    Esquema::migrar(['create_plt_bitacoras_table', 'create_sec_user_table', 'create_sec_user_preferencia_table', 'create_sol_zonas_table', 'create_prv_proveedores_table', 'create_prv_documentos_table', 'retira_zonas']);

    foreach (['prv_documentos', 'prv_proveedores', 'plt_bitacoras', 'sec_user'] as $tabla) {
        DB::table($tabla)->delete();
    }

    Storage::fake('local');
    $ahora = now();
    DB::table('sec_user')->insert(['id' => 9, 'name' => 'Rev', 'username' => 'rev', 'password' => 'x', 'type' => 'interno', 'state' => true, 'created_at' => $ahora, 'updated_at' => $ahora]);
    DB::table('prv_proveedores')->insert(['id' => 1, 'usuario_titular_id' => 9, 'tipo' => 'independiente', 'razon_social' => 'P', 'documento_fiscal' => '1', 'telefono' => '1', 'estado' => 'aprobado', 'created_at' => $ahora, 'updated_at' => $ahora]);

    $ruta = app(AlmacenDeMedios::class)->guardar(UploadedFile::fake()->image('Carnet de Maria Lopez.jpg', 900, 600), 'proveedores/1/documentos');
    DB::table('prv_documentos')->insert(['sujeto_tipo' => 'proveedor', 'sujeto_id' => 1, 'tipo' => 'identidad', 'archivo_ref' => $ruta, 'nombre_original' => 'Carnet de Maria Lopez.jpg', 'mime' => 'image/jpeg', 'bytes' => 900, 'estado' => 'en_revision', 'subido_por' => 9, 'created_at' => $ahora, 'updated_at' => $ahora]);

    return Documento::query()->firstOrFail();
}

function docControlador(array $permisos): DocumentosController
{
    return new DocumentosController(docPanel($permisos), app(AlmacenDeMedios::class), app(RegistrarAccesoAMedio::class));
}

function docPeticion(string $variante = ''): Request
{
    return Request::create('/panel/documentos/1/archivo', 'GET', $variante === '' ? [] : ['variante' => $variante]);
}

test('el visor sirve el original con encabezados seguros y deja constancia; la miniatura no la deja', function () {
    $documento = docMundo();
    Auth::guard('interno')->setUser(SecUser::query()->findOrFail(9));
    $controlador = docControlador(['proveedores.documento.ver']);

    $original = $controlador->archivo(docPeticion(), $documento);
    $miniatura = $controlador->archivo(docPeticion('miniatura'), $documento);

    expect($original->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($original->headers->get('Cache-Control'))->toContain('no-store')
        ->and($miniatura->headers->get('X-Content-Type-Options'))->toBe('nosniff');

    $filas = Bitacora::query()->where('accion', AccionBitacora::Descargado->value)->get();
    expect($filas)->toHaveCount(1)
        ->and($filas[0]->user_id)->toBe(9)->and($filas[0]->tabla)->toBe('prv_documentos')->and($filas[0]->registro_id)->toBe($documento->id)
        ->and($filas[0]->despues)->toBe(['medio' => 'visto']);
});

test('descargar exige su propio permiso: ver no alcanza', function () {
    $documento = docMundo();

    expect(fn () => docControlador(['proveedores.documento.ver'])->descargar(docPeticion(), $documento))->toThrow(HttpException::class)
        ->and(fn () => docControlador([])->archivo(docPeticion(), $documento))->toThrow(HttpException::class)
        ->and(Bitacora::query()->where('accion', AccionBitacora::Descargado->value)->count())->toBe(0);
});

test('la descarga del original sale como adjunto con un nombre armado por el servidor y queda en la bitácora', function () {
    $documento = docMundo();
    Auth::guard('interno')->setUser(SecUser::query()->findOrFail(9));

    $respuesta = docControlador(['proveedores.documento.descargar'])->descargar(docPeticion(), $documento);
    $disposicion = (string) $respuesta->headers->get('Content-Disposition');

    expect($disposicion)->toStartWith('attachment')->toContain("identidad-{$documento->id}.jpg")
        ->and($disposicion)->not->toContain('Maria')->not->toContain('Carnet')
        ->and($respuesta->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($respuesta->headers->get('Cache-Control'))->toContain('no-store');

    $fila = Bitacora::query()->where('accion', AccionBitacora::Descargado->value)->sole();
    expect($fila->despues)->toBe(['medio' => 'descargado'])->and($fila->user_id)->toBe(9);
});

test('si el archivo ya no está, el visor y la descarga responden 404 sin error y sin constancia', function () {
    $documento = docMundo();
    Storage::disk('local')->delete($documento->archivo_ref);
    Auth::guard('interno')->setUser(SecUser::query()->findOrFail(9));

    foreach ([fn () => docControlador(['proveedores.documento.ver'])->archivo(docPeticion(), $documento), fn () => docControlador(['proveedores.documento.descargar'])->descargar(docPeticion(), $documento)] as $intento) {
        try {
            $intento();
            $codigo = 200;
        } catch (HttpException $excepcion) {
            $codigo = $excepcion->getStatusCode();
        }

        expect($codigo)->toBe(404);
    }

    expect(Bitacora::query()->where('accion', AccionBitacora::Descargado->value)->count())->toBe(0)
        ->and(MedioDeDocumento::de($documento)->existe)->toBeFalse()
        ->and(MedioDeDocumento::de($documento)->urlOriginal)->toBeNull();
});

test('MedioDeDocumento arma el contrato con las rutas del panel y la descarga solo si se puede', function () {
    $documento = docMundo();
    $sin = MedioDeDocumento::de($documento)->aArray();
    $con = MedioDeDocumento::de($documento, true)->aArray();

    expect($sin['url_original'])->toBe("/panel/documentos/{$documento->id}/archivo")
        ->and($sin['url_miniatura'])->toBe("/panel/documentos/{$documento->id}/archivo?variante=miniatura")
        ->and($sin['url_media'])->toBe("/panel/documentos/{$documento->id}/archivo?variante=media")
        ->and($sin['url_descarga'])->toBeNull()
        ->and($con['url_descarga'])->toBe("/panel/documentos/{$documento->id}/descargar")
        ->and($sin)->toMatchArray(['existe' => true, 'tipo' => 'imagen', 'tiene_vista_previa' => true, 'mime' => 'image/jpeg', 'peso' => 900, 'nombre_visible' => 'Documento de identidad']);
    expect(VarianteDeMedio::desde('media'))->toBe(VarianteDeMedio::Media)->and(VarianteDeMedio::desde('cualquier cosa'))->toBe(VarianteDeMedio::Original);
});

test('un sujeto no puede tener más documentos en revisión que el límite', function () {
    docMundo();
    config(['medios.limites.por_entidad.documentos' => 2, 'proveedores.disco_documentos' => 'local']);
    // El del mundo ya está en revisión: queda un cupo.
    $cargar = app(CargarDocumento::class);

    $cargar->cargar('proveedor', 1, 'licencia_piloto', UploadedFile::fake()->image('a.jpg'), null, 9);

    expect(fn () => $cargar->cargar('proveedor', 1, 'seguro_responsabilidad', UploadedFile::fake()->image('b.jpg'), null, 9))
        ->toThrow(DocumentoInvalido::class, 'esperando revisión');
});
