<?php

use App\Dominios\Compartido\Aplicacion\Medios\AlmacenDeMedios;
use App\Dominios\Compartido\Aplicacion\Medios\RegistrarAccesoAMedio;
use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Dominio\Excepciones\MotivoRequerido;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Proveedores\Aplicacion\EliminarDocumento;
use App\Dominios\Proveedores\Dominio\Excepciones\RegistroInvalido;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Documento;
use App\Dominios\Proveedores\Infraestructura\Http\Controllers\Web\DocumentosController;
use App\Dominios\Proveedores\Infraestructura\Http\Requests\EliminarDocumentoRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Dominio\PermisosReservados;
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
 * Eliminar un documento de verificación (zona crítica: archivos de personas): permiso propio y sensible,
 * motivo obligatorio, borrado lógico con constancia en la bitácora (sin contenido), regla de dominio para el
 * documento aprobado y vigente de un sujeto aprobado, y retiro del original y sus derivados del almacén.
 */

uses(TestCase::class);

/** Panel de mentira: concede solo los permisos que se le pasan. */
function eliminarPanel(array $permisos): AutorizacionPanelWeb
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

/** @return array{0: Documento, 1: string} el documento y la ruta de su archivo */
function eliminarMundo(string $estadoDocumento = 'en_revision', string $estadoProveedor = 'aprobado', ?string $vence = null): array
{
    Esquema::migrar(['create_plt_bitacoras_table', 'create_sec_user_table', 'create_sec_user_preferencia_table', 'create_sol_zonas_table', 'create_prv_proveedores_table', 'create_prv_documentos_table', 'retira_zonas']);

    foreach (['prv_documentos', 'prv_proveedores', 'plt_bitacoras', 'sec_user'] as $tabla) {
        DB::table($tabla)->delete();
    }

    Storage::fake('local');
    $ahora = now();
    DB::table('sec_user')->insert(['id' => 9, 'name' => 'Rev', 'username' => 'rev', 'password' => 'x', 'type' => 'interno', 'state' => true, 'created_at' => $ahora, 'updated_at' => $ahora]);
    DB::table('prv_proveedores')->insert(['id' => 1, 'usuario_titular_id' => 9, 'tipo' => 'independiente', 'razon_social' => 'P', 'documento_fiscal' => '1', 'telefono' => '1', 'estado' => $estadoProveedor, 'created_at' => $ahora, 'updated_at' => $ahora]);

    $ruta = app(AlmacenDeMedios::class)->guardar(UploadedFile::fake()->image('Carnet de Maria Lopez.jpg', 900, 600), 'proveedores/1/documentos');
    DB::table('prv_documentos')->insert(['sujeto_tipo' => 'proveedor', 'sujeto_id' => 1, 'tipo' => 'identidad', 'archivo_ref' => $ruta, 'nombre_original' => 'Carnet de Maria Lopez.jpg', 'mime' => 'image/jpeg', 'bytes' => 900, 'estado' => $estadoDocumento, 'vence_en' => $vence, 'subido_por' => 9, 'created_at' => $ahora, 'updated_at' => $ahora]);

    return [Documento::query()->firstOrFail(), $ruta];
}

test('eliminar borra lógicamente, deja constancia sin contenido y retira el archivo del almacén', function () {
    [$documento, $ruta] = eliminarMundo();
    $almacen = app(AlmacenDeMedios::class);
    expect($almacen->existe($ruta))->toBeTrue();

    Auth::guard('interno')->setUser(SecUser::query()->findOrFail(9));
    app(EliminarDocumento::class)->ejecutar($documento->id, 9, 'Subido por error');

    expect(Documento::query()->find($documento->id))->toBeNull()
        ->and(Documento::withTrashed()->find($documento->id)?->deleted_at)->not->toBeNull()
        ->and($almacen->existe($ruta))->toBeFalse();

    $filas = Bitacora::query()->where('tabla', 'prv_documentos')->where('accion', AccionBitacora::Eliminado->value)->get();
    expect($filas)->not->toBeEmpty()
        ->and($filas->every(fn ($fila) => $fila->user_id === 9 && $fila->registro_id === $documento->id))->toBeTrue();

    $textoBitacora = json_encode($filas->map(fn ($fila) => [$fila->antes, $fila->despues])->all());
    expect($textoBitacora)->toContain('Subido por error')
        ->not->toContain('Carnet de Maria Lopez')
        ->not->toContain($ruta);
});

test('el motivo es obligatorio y sin él no se borra nada', function () {
    [$documento, $ruta] = eliminarMundo();

    expect(fn () => app(EliminarDocumento::class)->ejecutar($documento->id, 9, '   '))->toThrow(MotivoRequerido::class)
        ->and(Documento::query()->find($documento->id))->not->toBeNull()
        ->and(app(AlmacenDeMedios::class)->existe($ruta))->toBeTrue();
});

test('no se elimina el documento aprobado y vigente de un sujeto aprobado', function () {
    [$documento, $ruta] = eliminarMundo('aprobado', 'aprobado', now()->addYear()->toDateString());

    expect(fn () => app(EliminarDocumento::class)->ejecutar($documento->id, 9, 'Quiero'))->toThrow(RegistroInvalido::class)
        ->and(Documento::query()->find($documento->id))->not->toBeNull()
        ->and(app(AlmacenDeMedios::class)->existe($ruta))->toBeTrue();
});

test('sí se elimina un aprobado si su sujeto está suspendido o si el documento ya venció', function () {
    [$documento] = eliminarMundo('aprobado', 'suspendido', now()->addYear()->toDateString());
    app(EliminarDocumento::class)->ejecutar($documento->id, 9, 'Sujeto suspendido');
    expect(Documento::query()->find($documento->id))->toBeNull();

    [$vencido] = eliminarMundo('aprobado', 'aprobado', now()->subDay()->toDateString());
    app(EliminarDocumento::class)->ejecutar($vencido->id, 9, 'Vencido');
    expect(Documento::query()->find($vencido->id))->toBeNull();
});

test('eliminar exige su propio permiso: revisar y descargar no alcanzan', function () {
    [$documento] = eliminarMundo();
    $controlador = new DocumentosController(eliminarPanel(['proveedores.documento.ver', 'proveedores.documento.revisar', 'proveedores.documento.descargar']), app(AlmacenDeMedios::class), app(RegistrarAccesoAMedio::class));
    $peticion = EliminarDocumentoRequest::create('/panel/documentos/1', 'DELETE', ['motivo' => 'x']);

    expect(fn () => $controlador->destroy($peticion, $documento, app(EliminarDocumento::class)))->toThrow(HttpException::class)
        ->and(Documento::query()->find($documento->id))->not->toBeNull();
});

test('el permiso de eliminar es sensible: el operador no lo recibe en su preset', function () {
    expect(PermisosReservados::esSensible('proveedores.documento.eliminar'))->toBeTrue()
        ->and(PermisosReservados::PRESET_OPERADOR)->not->toContain('proveedores.documento.eliminar');
});
