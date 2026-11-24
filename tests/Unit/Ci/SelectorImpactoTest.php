<?php

/*
 * Pruebas del selector de pruebas por impacto (bin/lib/SelectorImpacto.php,
 * docs/gestion/ci-por-impacto.md). Cubren el contrato central: dado un diff,
 * qué pruebas corren — incluidos los casos en que corre todo — y que ante la
 * duda el selector elige más, nunca menos.
 *
 * La lógica se prueba sobre un proyecto mínimo armado en un directorio
 * temporal (rápido y estable aunque el código real cambie); al final unos
 * pocos casos corren contra el repo real para los disparadores de "todo".
 */

require_once dirname(__DIR__, 3).'/bin/lib/SelectorImpacto.php';

/** Arma un proyecto de juguete y devuelve su ruta. */
function proyectoDeJuguete(): string
{
    $raiz = sys_get_temp_dir().'/selector-impacto-'.bin2hex(random_bytes(4));
    $archivos = [
        // Módulo Alfa: Estado (hoja), Servicio usa Estado, Contratos/Lectura es su API pública.
        'app/Dominios/Alfa/Dominio/Estado.php' => "<?php\nnamespace App\\Dominios\\Alfa\\Dominio;\nenum Estado {}\n",
        'app/Dominios/Alfa/Aplicacion/Servicio.php' => "<?php\nnamespace App\\Dominios\\Alfa\\Aplicacion;\nuse App\\Dominios\\Alfa\\Dominio\\Estado;\nfinal class Servicio { public function f(): string { return Estado::class; } }\n",
        'app/Dominios/Alfa/Contratos/Lectura.php' => "<?php\nnamespace App\\Dominios\\Alfa\\Contratos;\ninterface Lectura {}\n",
        // Módulo Beta consume Alfa a través de su contrato; Beta\Interno usa a Beta\Base (mismo namespace, sin use).
        'app/Dominios/Beta/Aplicacion/Consumidor.php' => "<?php\nnamespace App\\Dominios\\Beta\\Aplicacion;\nuse App\\Dominios\\Alfa\\Contratos\\Lectura;\nfinal class Consumidor { public function __construct(private Lectura \$l) {} }\n",
        'app/Dominios/Beta/Aplicacion/Base.php' => "<?php\nnamespace App\\Dominios\\Beta\\Aplicacion;\nclass Base {}\n",
        'app/Dominios/Beta/Aplicacion/Interno.php' => "<?php\nnamespace App\\Dominios\\Beta\\Aplicacion;\nfinal class Interno extends Base {}\n",
        // Módulo Gamma, sin relación con los demás.
        'app/Dominios/Gamma/Dominio/Regla.php' => "<?php\nnamespace App\\Dominios\\Gamma\\Dominio;\nfinal class Regla {}\n",
        'routes/web.php' => "<?php\n",
        'lang/es/alfa.php' => "<?php\nreturn [];\n",
        'lang/es/gamma.php' => "<?php\nreturn [];\n",
        'resources/views/panel.blade.php' => "panel\n",
        // Pruebas.
        'tests/Unit/EstadoTest.php' => "<?php\nuse App\\Dominios\\Alfa\\Dominio\\Estado;\ntest('x', fn () => Estado::class);\n",
        'tests/Unit/ServicioTest.php' => "<?php\nuse App\\Dominios\\Alfa\\Aplicacion\\Servicio;\ntest('x', fn () => Servicio::class);\n",
        'tests/Unit/ConsumidorTest.php' => "<?php\nuse App\\Dominios\\Beta\\Aplicacion\\Consumidor;\ntest('x', fn () => Consumidor::class);\n",
        'tests/Unit/InternoTest.php' => "<?php\nuse App\\Dominios\\Beta\\Aplicacion\\Interno;\ntest('x', fn () => Interno::class);\n",
        'tests/Unit/ReglaTest.php' => "<?php\nuse App\\Dominios\\Gamma\\Dominio\\Regla;\ntest('x', fn () => Regla::class);\n",
        'tests/Unit/IdiomaAlfaTest.php' => "<?php\ntest('x', fn () => require dirname(__DIR__, 2).'/lang/es/alfa.php');\n",
        'tests/Unit/EscanerDeAppTest.php' => "<?php\ntest('x', fn () => glob(dirname(__DIR__, 2).'/app/Dominios/Gamma/*'));\n",
        'tests/Unit/OpacoTest.php' => "<?php\ntest('x', fn () => expect(1)->toBe(1));\n",
        'tests/Unit/LevantaAppTest.php' => "<?php\nuses(\\Tests\\TestCase::class);\ntest('x', fn () => 1);\n",
        'tests/Unit/ArquitecturaModulosTest.php' => "<?php\ntest('x', fn () => 1);\n",
        'tests/Unit/ContratoOpenApiTest.php' => "<?php\ntest('x', fn () => 1);\n",
        'tests/Unit/Contrato/EjemplosDeApiTest.php' => "<?php\ntest('x', fn () => 1);\n",
        'tests/Feature/Contrato/RutasDelContratoTest.php' => "<?php\ntest('x', fn () => 1);\n",
        'tests/Unit/Sub/AnidadaTest.php' => "<?php\nuse App\\Dominios\\Gamma\\Dominio\\Regla;\ntest('x', fn () => Regla::class);\n",
    ];
    foreach ($archivos as $ruta => $contenido) {
        if (! is_dir(dirname("{$raiz}/{$ruta}"))) {
            mkdir(dirname("{$raiz}/{$ruta}"), 0777, true);
        }
        file_put_contents("{$raiz}/{$ruta}", $contenido);
    }

    return $raiz;
}

function borrarProyecto(string $raiz): void
{
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $info) {
        $info->isDir() ? rmdir($info->getPathname()) : unlink($info->getPathname());
    }
    rmdir($raiz);
}

/** @return list<string> nombres de archivo (sin carpeta) de las pruebas elegidas */
function elegidas(string $raiz, array $diff): array
{
    $r = (new SelectorImpacto($raiz))->seleccionar($diff);
    expect($r['todo'])->toBeFalse();

    return array_map('basename', $r['pruebas']);
}

beforeEach(function () {
    $this->raiz = proyectoDeJuguete();
});

afterEach(function () {
    borrarProyecto($this->raiz);
});

test('un diff vacío corre solo el humo mínimo', function () {
    expect(elegidas($this->raiz, []))->toEqualCanonicalizing(['ArquitecturaModulosTest.php', 'ContratoOpenApiTest.php', 'EjemplosDeApiTest.php', 'RutasDelContratoTest.php']);
});

test('documentación y metadatos no despiertan ninguna prueba más', function () {
    expect(elegidas($this->raiz, ['README.md', 'CLAUDE.md', '.claude/skills/x/SKILL.md', '.gitignore']))
        ->toEqualCanonicalizing(['ArquitecturaModulosTest.php', 'ContratoOpenApiTest.php', 'EjemplosDeApiTest.php', 'RutasDelContratoTest.php']);
});

test('una clase hoja elige la prueba que la nombra y las pruebas que levantan la app', function () {
    $elegidas = elegidas($this->raiz, ['app/Dominios/Gamma/Dominio/Regla.php']);

    expect($elegidas)->toContain('ReglaTest.php', 'AnidadaTest.php', 'EscanerDeAppTest.php', 'LevantaAppTest.php', 'OpacoTest.php')
        ->not->toContain('EstadoTest.php', 'ServicioTest.php', 'ConsumidorTest.php', 'IdiomaAlfaTest.php');
});

test('dentro de un módulo la propagación a las clases que usan la modificada es transitiva', function () {
    $elegidas = elegidas($this->raiz, ['app/Dominios/Alfa/Dominio/Estado.php']);

    expect($elegidas)->toContain('EstadoTest.php', 'ServicioTest.php')
        ->not->toContain('ReglaTest.php', 'InternoTest.php');
});

test('un cambio en el contrato de un módulo llega a sus consumidores directos de otro módulo', function () {
    $elegidas = elegidas($this->raiz, ['app/Dominios/Alfa/Contratos/Lectura.php']);

    expect($elegidas)->toContain('ConsumidorTest.php')
        ->not->toContain('ReglaTest.php');
});

test('un cambio interno de un módulo no cruza a los consumidores de otro por más de un paso', function () {
    // Estado -> Servicio (mismo módulo); ningún módulo ajeno nombra a Estado ni a Servicio.
    $elegidas = elegidas($this->raiz, ['app/Dominios/Alfa/Dominio/Estado.php']);

    expect($elegidas)->not->toContain('ConsumidorTest.php');
});

test('las clases del mismo namespace se detectan sin use', function () {
    $elegidas = elegidas($this->raiz, ['app/Dominios/Beta/Aplicacion/Base.php']);

    expect($elegidas)->toContain('InternoTest.php');
});

test('un archivo de idioma elige las pruebas que lo leen y las que levantan la app', function () {
    $elegidas = elegidas($this->raiz, ['lang/es/alfa.php']);

    expect($elegidas)->toContain('IdiomaAlfaTest.php', 'LevantaAppTest.php')
        ->not->toContain('ReglaTest.php', 'EscanerDeAppTest.php');
});

test('rutas y vistas eligen a las pruebas que levantan la app', function () {
    expect(elegidas($this->raiz, ['routes/web.php']))->toContain('LevantaAppTest.php')
        ->and(elegidas($this->raiz, ['resources/views/panel.blade.php']))->toContain('LevantaAppTest.php');
});

test('una carpeta escaneada por una prueba la elige cuando algo dentro cambia', function () {
    $elegidas = elegidas($this->raiz, ['app/Dominios/Gamma/Dominio/Otra.php']);

    expect($elegidas)->toContain('EscanerDeAppTest.php');
});

test('una prueba modificada corre aunque no cambie código', function () {
    expect(elegidas($this->raiz, ['tests/Unit/ReglaTest.php']))->toContain('ReglaTest.php')
        ->not->toContain('EstadoTest.php');
});

test('una prueba borrada no se pide a Pest', function () {
    expect(elegidas($this->raiz, ['tests/Unit/YaNoExisteTest.php']))->not->toContain('YaNoExisteTest.php');
});

test('un cambio cuyo efecto no se puede acotar corre toda la suite', function (string $archivo, string $fragmentoDelMotivo) {
    $r = (new SelectorImpacto($this->raiz))->seleccionar([$archivo]);

    expect($r['todo'])->toBeTrue()
        ->and($r['motivo'])->toContain($fragmentoDelMotivo)
        ->and($r['pruebas'])->toHaveCount(count((new SelectorImpacto($this->raiz))->todasLasPruebas()));
})->with([
    'lockfile de Composer' => ['composer.lock', 'Composer'],
    'manifiesto de Composer' => ['composer.json', 'Composer'],
    'lockfile de npm' => ['package-lock.json', 'npm'],
    'configuración' => ['config/database.php', 'configuración'],
    'migración' => ['database/migrations/2026_01_01_000000_crea_algo.php', 'migraciones'],
    'esquema' => ['database/schema/pgsql-schema.sql', 'esquema'],
    'workflows de CI' => ['.github/workflows/ci.yml', 'CI'],
    'scripts de verificación' => ['bin/verify', 'scripts'],
    'el propio selector' => ['bin/lib/SelectorImpacto.php', 'scripts'],
    'configuración de PHPUnit' => ['phpunit.xml', 'PHPUnit'],
    'configuración de PHPStan' => ['phpstan.neon', 'análisis'],
    'bootstrap de pruebas' => ['tests/bootstrap.php', 'compartidos'],
    'Pest.php' => ['tests/Pest.php', 'compartidos'],
    'TestCase base' => ['tests/TestCase.php', 'compartidos'],
    'ayudante de pruebas' => ['tests/Soporte/Fabrica.php', 'compartidos'],
    'proveedor de servicios' => ['app/Providers/AppServiceProvider.php', 'proveedores'],
    'arranque' => ['bootstrap/app.php', 'arranque'],
    'Dockerfile' => ['Dockerfile', 'entorno'],
    'archivo desconocido' => ['algo-raro.xyz', 'no reconocido'],
]);

test('un solo disparador de todo entre muchos cambios basta para correr todo', function () {
    $r = (new SelectorImpacto($this->raiz))->seleccionar(['README.md', 'app/Dominios/Gamma/Dominio/Regla.php', 'composer.lock']);

    expect($r['todo'])->toBeTrue();
});

test('cada prueba elegida explica por qué', function () {
    $r = (new SelectorImpacto($this->raiz))->seleccionar(['app/Dominios/Gamma/Dominio/Regla.php']);

    foreach ($r['pruebas'] as $prueba) {
        expect($r['razones'][$prueba] ?? [])->not->toBeEmpty();
    }
});

test('las rutas con barras invertidas o prefijo ./ se normalizan', function () {
    expect(elegidas($this->raiz, ['./app\\Dominios\\Gamma\\Dominio\\Regla.php']))->toContain('ReglaTest.php');
});

// --- Contra el repo real: los disparadores de "todo" no pueden perderse. ---

test('en el repo real, un cambio de dependencias, migraciones o CI corre todo', function (string $archivo) {
    $r = (new SelectorImpacto(dirname(__DIR__, 3)))->seleccionar([$archivo]);

    expect($r['todo'])->toBeTrue()
        ->and($r['pruebas'])->toContain('tests/Unit/ArquitecturaModulosTest.php', 'tests/Unit/Ci/SelectorImpactoTest.php');
})->with(['composer.lock', 'config/app.php', 'database/migrations/2026_09_23_000101_create_sec_vistas_como_table.php', '.github/workflows/ci.yml', 'bin/verify', 'tests/Pest.php']);

test('en el repo real, el humo mínimo existe y todo archivo mapeado a pruebas apunta a pruebas que existen', function () {
    $selector = new SelectorImpacto(dirname(__DIR__, 3));
    $todas = $selector->todasLasPruebas();

    expect($todas)->toContain(...SelectorImpacto::HUMO);

    $r = $selector->seleccionar(['lang/es/operaciones.php', 'app/Dominios/Operaciones/Dominio/EstadoTrabajo.php']);
    foreach ($r['pruebas'] as $prueba) {
        expect($todas)->toContain($prueba);
    }
});
