<?php

use App\Dominios\Compartido\Aplicacion\Medios\AlmacenDeMedios;
use App\Dominios\Compartido\Aplicacion\Medios\RegistrarAccesoAMedio;
use App\Dominios\Proveedores\Aplicacion\ResolverSujeto;
use App\Dominios\Proveedores\Infraestructura\Http\Controllers\Web\DocumentosController;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Tests\Soporte\Esquema;
use Tests\TestCase;

/*
 * Paginación de los listados del panel. Dos compuertas comunes y una regresión:
 *
 * - todo listado que pagina conserva la consulta (`withQueryString()`): sin eso, los enlaces de la
 *   página 2 pierden los filtros y la búsqueda;
 * - la molécula `pagination` sirve la página 2 con la consulta intacta cuando hay más filas que el
 *   tamaño de página;
 * - regresión de /panel/documentos: `?estado=` (todos) llega vacío, el middleware lo vuelve null y
 *   `withQueryString()` lo descartaba; la página 2 reaplicaba «En revisión» y salía vacía.
 */

uses(TestCase::class);

it('todo listado paginado del panel conserva la consulta en los enlaces de página', function () {
    $raiz = dirname(__DIR__, 2).'/app';
    $sinConsulta = [];

    /** @var iterable<SplFileInfo> $archivos */
    $archivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS));

    foreach ($archivos as $archivo) {
        $ruta = $archivo->getPathname();
        $esListado = str_contains($ruta, '/Http/Controllers/Web/') || str_contains($ruta, '/Aplicacion/Listar');

        if (! $archivo->isFile() || ! str_ends_with($ruta, '.php') || ! $esListado) {
            continue;
        }

        $codigo = (string) file_get_contents($ruta);
        $paginaciones = preg_match_all('/->paginate\(/', $codigo);

        if ($paginaciones === 0) {
            continue;
        }

        // La API paginada por página explícita (`'page'` como argumento) no pinta enlaces: no aplica.
        $sinEnlaces = preg_match_all("/->paginate\([^;]*'page'/s", $codigo);
        $conConsulta = preg_match_all('/->withQueryString\(\)/', $codigo);

        if ($conConsulta < $paginaciones - $sinEnlaces) {
            $sinConsulta[] = substr($ruta, strlen($raiz) + 1);
        }
    }

    expect($sinConsulta)->toBe([]);
});

it('la molécula pagination sirve la página 2 con filtros y búsqueda intactos', function () {
    $filas = range(1, 31);
    $consulta = ['estado' => '', 'q' => 'carnet', 'tipo' => 'identidad'];
    $pagina2 = new LengthAwarePaginator(array_slice($filas, 15, 15), 31, 15, 2, ['path' => '/panel/x']);
    $pagina2->appends($consulta);

    $html = Blade::render('<x-molecules.pagination :paginator="$p" aria-label="Páginas" />', ['p' => $pagina2]);

    expect($pagina2->items())->toHaveCount(15)
        ->and($html)->toContain('rel="prev"')->toContain('rel="next"')
        ->and($html)->toContain('estado=&amp;q=carnet&amp;tipo=identidad&amp;page=3')
        ->and($html)->toContain('estado=&amp;q=carnet&amp;tipo=identidad&amp;page=1');
});

function paginaDeDocumentos(array $consulta): LengthAwarePaginator
{
    $peticion = Request::create('/panel/documentos', 'GET', $consulta);
    // Lo que hace el middleware ConvertEmptyStringsToNull en producción: `?estado=` llega como null.
    foreach ($consulta as $clave => $valor) {
        if ($valor === '') {
            $peticion->query->set($clave, null);
        }
    }
    app()->instance('request', $peticion);

    $autorizacion = new class implements AutorizacionPanelWeb
    {
        public function tienePermiso(Request $request, string $codigoPermiso): bool
        {
            return true;
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

    $controlador = new DocumentosController($autorizacion, app(AlmacenDeMedios::class), app(RegistrarAccesoAMedio::class));

    return $controlador->index($peticion, app(ResolverSujeto::class))->getData()['documentos'];
}

it('la cola de documentos sirve la página 2 con «todos» (?estado=) sin reaplicar En revisión', function () {
    Esquema::migrar(['create_plt_bitacoras_table', 'create_sec_user_table', 'create_sec_user_preferencia_table', 'create_sol_zonas_table', 'create_prv_proveedores_table', 'create_prv_documentos_table', 'retira_zonas']);

    foreach (['prv_documentos', 'prv_proveedores', 'plt_bitacoras', 'sec_user'] as $tabla) {
        DB::table($tabla)->delete();
    }

    $ahora = now();
    DB::table('sec_user')->insert(['id' => 9, 'name' => 'Rev', 'username' => 'rev', 'password' => 'x', 'type' => 'interno', 'state' => true, 'created_at' => $ahora, 'updated_at' => $ahora]);
    DB::table('prv_proveedores')->insert(['id' => 1, 'usuario_titular_id' => 9, 'tipo' => 'independiente', 'razon_social' => 'P', 'documento_fiscal' => '1', 'telefono' => '1', 'estado' => 'aprobado', 'created_at' => $ahora, 'updated_at' => $ahora]);

    foreach (range(1, 20) as $n) {
        DB::table('prv_documentos')->insert([
            'sujeto_tipo' => 'proveedor', 'sujeto_id' => 1, 'tipo' => 'identidad', 'archivo_ref' => "x/{$n}.jpg",
            'nombre_original' => "carnet-{$n}.jpg", 'mime' => 'image/jpeg', 'bytes' => 10,
            'estado' => $n <= 3 ? 'en_revision' : 'aprobado', 'subido_por' => 9, 'created_at' => $ahora, 'updated_at' => $ahora,
        ]);
    }

    // Todos: 20 documentos, la página 2 existe y trae los 5 restantes.
    $todos = paginaDeDocumentos(['estado' => '', 'page' => 2]);
    expect($todos->total())->toBe(20)->and($todos->items())->toHaveCount(5)
        ->and($todos->nextPageUrl())->toBeNull()
        ->and($todos->previousPageUrl())->toContain('estado=');

    // En la página 1 el enlace de «siguiente» conserva `estado=` (antes lo perdía).
    $primera = paginaDeDocumentos(['estado' => '']);
    expect($primera->nextPageUrl())->toContain('estado=&page=2');

    // Con búsqueda y otro estado, también.
    $aprobados = paginaDeDocumentos(['estado' => 'aprobado', 'q' => 'carnet']);
    expect($aprobados->total())->toBe(17)->and($aprobados->nextPageUrl())->toContain('estado=aprobado')->toContain('q=carnet')->toContain('page=2');

    // Sin parámetro, sigue valiendo «En revisión» por omisión y no hay página 2.
    $porOmision = paginaDeDocumentos([]);
    expect($porOmision->total())->toBe(3)->and($porOmision->hasMorePages())->toBeFalse();
});
