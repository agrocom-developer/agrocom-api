<?php

declare(strict_types=1);

/**
 * Selector de pruebas por impacto: dado el conjunto de archivos que cambió un
 * diff, decide qué pruebas de `tests/` pueden verse afectadas.
 *
 * Principio rector: ante la duda el selector elige MÁS pruebas, nunca menos.
 * Un archivo que no reconoce corre toda la suite; una prueba cuyas
 * dependencias no puede inferir corre ante cualquier cambio de código.
 *
 * Cómo infiere dependencias (análisis estático, sin ejecutar nada):
 *   - Grafo de clases: cada clase de `app/`, `database/factories` y
 *     `database/seeders` apunta a las clases que nombra (FQN, `use` agrupados y
 *     clases hermanas del mismo namespace). Si cambia una clase, se calcula el
 *     cierre transitivo de las clases que la usan.
 *   - Una prueba depende de los nombres `App\…` / `Database\…` que escribe
 *     (una clase exacta, o un namespace entero si nombra solo el prefijo).
 *   - Literales de ruta (`'/lang/es/…'`, `database_path('migrations')`,
 *     `'/app/Dominios/X'`) marcan qué carpetas lee: las pruebas que escanean
 *     archivos dependen de ellas.
 *   - Una prueba que levanta la app (`TestCase`) o golpea HTTP depende además
 *     de rutas, vistas, idiomas y datos semilla.
 */
final class SelectorImpacto
{
    /** Pruebas que corren siempre, cualquiera sea el diff (humo mínimo). */
    public const HUMO = [
        'tests/Unit/ArquitecturaModulosTest.php',
        'tests/Unit/ContratoOpenApiTest.php',
        'tests/Unit/Contrato/EjemplosDeApiTest.php',
        'tests/Feature/Contrato/RutasDelContratoTest.php',
    ];

    /**
     * Cambios que invalidan cualquier selección: corre toda la suite.
     * Cada entrada es una expresión regular sobre la ruta relativa.
     */
    public const DISPARADORES_TODO = [
        '#^composer\.(json|lock)$#' => 'dependencias de Composer',
        '#^package(-lock)?\.json$#' => 'dependencias de npm',
        '#^config/#' => 'configuración de la app',
        '#^bootstrap/#' => 'arranque de la app',
        '#^app/Providers/#' => 'proveedores de servicios',
        '#^database/migrations/#' => 'migraciones',
        '#^database/(?!factories/|seeders/)#' => 'esquema de base de datos',
        '#^\.github/#' => 'CI y automatización',
        '#^bin/#' => 'scripts de verificación',
        '#^phpunit[^/]*\.xml$#' => 'configuración de PHPUnit',
        '#^phpstan#' => 'configuración de análisis estático',
        '#^tests/(?!.*Test\.php$)#' => 'archivos de pruebas compartidos',
        '#^(artisan|Dockerfile[^/]*|docker/.*|docker-compose[^/]*\.yml|\.env.*)$#' => 'entorno de ejecución',
    ];

    /** Cambios sin efecto sobre ninguna prueba (documentación y metadatos). */
    public const SIN_EFECTO = [
        '#^[^/]+\.md$#',
        '#^\.claude/#',
        '#^\.(gitignore|gitattributes|editorconfig)$#',
        '#^(LICENSE|CODEOWNERS)$#',
    ];

    /** Raíces cuyos archivos se mapean a pruebas por clase o por carpeta leída. */
    private const RAICES_MAPEADAS = '#^(app|routes|resources|lang|database/(factories|seeders)|docs|public)/#';

    /** Carpetas de código: un cambio ahí despierta a las pruebas de dependencias opacas. */
    private const RAICES_CODIGO = '#^(app|routes|resources|lang|database|config)/#';

    /** Partes de la app que una prueba que la levanta recorre sin nombrarlas. */
    private const RAICES_APP_LEVANTADA = '#^(routes|resources/views|lang|database/(factories|seeders))/#';

    /** Prefijos PSR-4 de las clases que entran al grafo. */
    private const PSR4 = [
        'App\\' => 'app',
        'Database\\Factories\\' => 'database/factories',
        'Database\\Seeders\\' => 'database/seeders',
    ];

    /** @var array<string, array{tokens: list<string>, areas: list<string>, levanta_app: bool, http: bool, opaca: bool}>|null */
    private ?array $perfiles = null;

    /** @var array<string, list<string>>|null FQN => FQN de las clases que lo usan */
    private ?array $dependientes = null;

    public function __construct(private readonly string $raiz) {}

    /**
     * @param  list<string>  $cambiados  rutas relativas a la raíz del repo
     * @return array{todo: bool, motivo: string|null, pruebas: list<string>, razones: array<string, list<string>>}
     */
    public function seleccionar(array $cambiados): array
    {
        $cambiados = array_values(array_unique(array_map(
            static fn (string $ruta): string => (string) preg_replace('#^(\./|/)+#', '', str_replace('\\', '/', $ruta)),
            array_filter($cambiados, static fn (string $ruta): bool => trim($ruta) !== ''),
        )));

        $razones = [];
        foreach (self::HUMO as $prueba) {
            $razones[$prueba][] = 'humo mínimo (corre siempre)';
        }

        $semillas = [];
        $pendientes = [];

        foreach ($cambiados as $archivo) {
            foreach (self::DISPARADORES_TODO as $patron => $motivo) {
                if (preg_match($patron, $archivo) === 1) {
                    return $this->todo("{$archivo}: {$motivo}");
                }
            }

            if (preg_match('#^tests/.+Test\.php$#', $archivo) === 1) {
                if (is_file("{$this->raiz}/{$archivo}")) {
                    $razones[$archivo][] = 'la prueba cambió';
                }

                continue;
            }

            if ($this->sinEfecto($archivo)) {
                continue;
            }

            if (preg_match(self::RAICES_MAPEADAS, $archivo) !== 1) {
                return $this->todo("{$archivo}: archivo no reconocido por el selector");
            }

            $clase = $this->claseDeArchivo($archivo);
            if ($clase !== null) {
                $semillas[] = $clase;
            }
            $pendientes[] = $archivo;
        }

        $afectadas = $this->cierreDeDependientes($semillas);

        foreach ($this->perfiles() as $prueba => $perfil) {
            foreach ($this->clasesQueNombra($perfil['tokens'], $afectadas) as $clase) {
                $razones[$prueba][] = in_array($clase, $semillas, true)
                    ? "usa {$clase} (cambió)"
                    : "usa {$clase} (afectada por el cambio)";
            }

            foreach ($pendientes as $archivo) {
                foreach ($perfil['areas'] as $area) {
                    if ($archivo === $area || str_starts_with($archivo, $area.'/')) {
                        $razones[$prueba][] = "lee {$area} ({$archivo})";
                    }
                }

                $recorreApp = preg_match(self::RAICES_APP_LEVANTADA, $archivo) === 1
                    || str_starts_with($archivo, 'app/');
                if (($perfil['levanta_app'] || $perfil['http']) && $recorreApp) {
                    $razones[$prueba][] = "levanta la app y {$archivo} forma parte de ella";
                }

                if ($perfil['opaca'] && preg_match(self::RAICES_CODIGO, $archivo) === 1) {
                    $razones[$prueba][] = "dependencias no inferibles; corre ante cambios de código ({$archivo})";
                }
            }
        }

        $pruebas = array_keys($razones);
        sort($pruebas);

        return [
            'todo' => false,
            'motivo' => null,
            'pruebas' => $pruebas,
            'razones' => array_map(static fn (array $r): array => array_values(array_unique($r)), $razones),
        ];
    }

    /** @return list<string> */
    public function todasLasPruebas(): array
    {
        return array_keys($this->perfiles());
    }

    /** @return array{todo: bool, motivo: string|null, pruebas: list<string>, razones: array<string, list<string>>} */
    private function todo(string $motivo): array
    {
        return ['todo' => true, 'motivo' => $motivo, 'pruebas' => $this->todasLasPruebas(), 'razones' => []];
    }

    private function sinEfecto(string $archivo): bool
    {
        foreach (self::SIN_EFECTO as $patron) {
            if (preg_match($patron, $archivo) === 1) {
                return true;
            }
        }

        return false;
    }

    /** FQN que corresponde a una ruta PSR-4, o `null` si no es una clase del grafo. */
    private function claseDeArchivo(string $archivo): ?string
    {
        if (! str_ends_with($archivo, '.php') || str_ends_with($archivo, '.blade.php')) {
            return null;
        }

        foreach (self::PSR4 as $prefijo => $carpeta) {
            if (str_starts_with($archivo, $carpeta.'/')) {
                $resto = substr($archivo, strlen($carpeta) + 1, -4);

                return $prefijo.str_replace('/', '\\', $resto);
            }
        }

        return null;
    }

    /**
     * Nombres `App\…`/`Database\…` que aparecen en un texto, normalizados a una
     * sola barra. Incluye los `use` agrupados (`use App\X\{A, B}` => App\X\A, App\X\B).
     * Un nombre que no es una clase completa actúa como prefijo de namespace.
     *
     * @return list<string>
     */
    private function tokensDe(string $texto): array
    {
        $tokens = [];

        if (preg_match_all('/\buse\s+((?:App|Database)(?:\\\\\w+)+)\\\\\{([^}]+)\}/', $texto, $grupos, PREG_SET_ORDER) > 0) {
            foreach ($grupos as $g) {
                foreach (explode(',', $g[2]) as $parte) {
                    $nombre = trim((string) preg_replace('/\s+as\s+\w+$/i', '', trim($parte)));
                    if ($nombre !== '') {
                        $tokens[] = $g[1].'\\'.$nombre;
                    }
                }
            }
        }

        if (preg_match_all('/\b(?:App|Database)(?:\\\\{1,2}\w+)+/', $texto, $m) > 0) {
            foreach ($m[0] as $nombre) {
                $tokens[] = (string) preg_replace('/\\\\{2,}/', '\\', $nombre);
            }
        }

        return array_values(array_unique($tokens));
    }

    /**
     * Cierre de dependientes: las clases semilla más las que las usan.
     *
     * Dentro de un módulo la propagación es transitiva. Entre módulos solo
     * cruza la frontera un paso: los consumidores directos de lo que cambió.
     * Por ADR 0003 los módulos se hablan a través de contratos, así que el
     * comportamiento interno de B no llega a A sin pasar por una clase que sí
     * cambió. Seguir cruzando de forma transitiva haría que, con el grafo tan
     * denso que tiene hoy el código, cualquier cambio alcanzara a casi toda la
     * suite. La integración entre módulos la cubren las pruebas que levantan la
     * app (corren ante cualquier cambio de `app/`) y la suite completa en
     * release y de noche.
     *
     * @param  list<string>  $semillas
     * @return array<string, true>
     */
    private function cierreDeDependientes(array $semillas): array
    {
        $grafo = $this->grafoDeDependientes();
        $esSemilla = array_fill_keys($semillas, true);
        $visitadas = [];
        $cola = $semillas;

        while ($cola !== []) {
            $clase = array_pop($cola);
            if (isset($visitadas[$clase])) {
                continue;
            }
            $visitadas[$clase] = true;
            $cruza = isset($esSemilla[$clase]);
            foreach ($grafo[$clase] ?? [] as $dependiente) {
                if ($cruza || $this->moduloDe($dependiente) === $this->moduloDe($clase)) {
                    $cola[] = $dependiente;
                }
            }
        }

        return $visitadas;
    }

    private function moduloDe(string $clase): string
    {
        return preg_match('/^App\\\\Dominios\\\\(\w+)\\\\/', $clase, $m) === 1 ? $m[1] : '';
    }

    /**
     * Clases afectadas que una prueba nombra, de forma exacta o a través de un
     * prefijo de namespace.
     *
     * @param  list<string>  $tokens
     * @param  array<string, true>  $afectadas
     * @return list<string>
     */
    private function clasesQueNombra(array $tokens, array $afectadas): array
    {
        if ($afectadas === []) {
            return [];
        }

        $nombradas = [];
        foreach ($tokens as $token) {
            if (isset($afectadas[$token])) {
                $nombradas[$token] = true;

                continue;
            }
            foreach (array_keys($afectadas) as $clase) {
                if (str_starts_with($clase, $token.'\\')) {
                    $nombradas[$clase] = true;
                    break;
                }
            }
        }

        return array_keys($nombradas);
    }

    /** @return array<string, list<string>> FQN => FQN de las clases que lo usan */
    private function grafoDeDependientes(): array
    {
        if ($this->dependientes !== null) {
            return $this->dependientes;
        }

        /** @var array<string, string> $textos FQN => contenido */
        $textos = [];
        $porNamespace = [];
        foreach ($this->archivosDeClases() as $archivo) {
            $fqn = $this->claseDeArchivo($archivo);
            if ($fqn === null) {
                continue;
            }
            $textos[$fqn] = $this->sinComentarios((string) file_get_contents("{$this->raiz}/{$archivo}"));
            $porNamespace[substr($fqn, 0, (int) strrpos($fqn, '\\'))][] = $fqn;
        }

        $grafo = [];
        foreach ($textos as $fqn => $texto) {
            $usadas = [];
            foreach ($this->tokensDe($texto) as $token) {
                if (isset($textos[$token])) {
                    $usadas[$token] = true;

                    continue;
                }
                foreach (array_keys($textos) as $otra) {
                    if (str_starts_with($otra, $token.'\\')) {
                        $usadas[$otra] = true;
                    }
                }
            }
            // Clases hermanas del mismo namespace: se usan por nombre corto, sin `use`.
            $namespace = substr($fqn, 0, (int) strrpos($fqn, '\\'));
            foreach ($porNamespace[$namespace] ?? [] as $hermana) {
                $corto = substr($hermana, strlen($namespace) + 1);
                if ($hermana !== $fqn && preg_match('/\b'.preg_quote($corto, '/').'\b/', $texto) === 1) {
                    $usadas[$hermana] = true;
                }
            }
            foreach (array_keys($usadas) as $usada) {
                if ($usada !== $fqn) {
                    $grafo[$usada][] = $fqn;
                }
            }
        }

        return $this->dependientes = $grafo;
    }

    /** Quita comentarios y docblocks: nombrar una clase en un comentario no es usarla. */
    private function sinComentarios(string $codigo): string
    {
        $salida = '';
        foreach (token_get_all($codigo) as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    continue;
                }
                $salida .= $token[1];
            } else {
                $salida .= $token;
            }
        }

        return $salida;
    }

    /** @return list<string> */
    private function archivosDeClases(): array
    {
        $archivos = [];
        foreach (self::PSR4 as $carpeta) {
            if (! is_dir("{$this->raiz}/{$carpeta}")) {
                continue;
            }
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$this->raiz}/{$carpeta}", FilesystemIterator::SKIP_DOTS));
            foreach ($it as $info) {
                if ($info->isFile() && str_ends_with($info->getFilename(), '.php')) {
                    $archivos[] = ltrim(substr($info->getPathname(), strlen($this->raiz)), '/');
                }
            }
        }

        return $archivos;
    }

    /** @return array<string, array{tokens: list<string>, areas: list<string>, levanta_app: bool, http: bool, opaca: bool}> */
    private function perfiles(): array
    {
        if ($this->perfiles !== null) {
            return $this->perfiles;
        }

        $perfiles = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$this->raiz}/tests", FilesystemIterator::SKIP_DOTS));
        foreach ($it as $info) {
            if (! $info->isFile() || ! str_ends_with($info->getFilename(), 'Test.php')) {
                continue;
            }
            $relativa = ltrim(substr($info->getPathname(), strlen($this->raiz)), '/');
            $perfiles[$relativa] = $this->perfilDe((string) file_get_contents($info->getPathname()), $relativa);
        }
        ksort($perfiles);

        return $this->perfiles = $perfiles;
    }

    /** @return array{tokens: list<string>, areas: list<string>, levanta_app: bool, http: bool, opaca: bool} */
    private function perfilDe(string $texto, string $relativa): array
    {
        $tokens = $this->tokensDe($texto);
        $areas = $this->areasLeidas($texto);
        $levantaApp = str_starts_with($relativa, 'tests/Feature/')
            || preg_match('/\buses\(\s*(?:\\\\?Tests\\\\)?TestCase::class|extends\s+(?:\\\\?Tests\\\\)?TestCase\b/', $texto) === 1;
        $http = preg_match('/->(?:get|post|put|patch|delete|getJson|postJson|putJson|patchJson|deleteJson|json|actingAs|withHeaders?|call)\(|\bcomoDispositivo\(|\bLivewire\b|\bRoute::|\broute\(/', $texto) === 1;

        return [
            'tokens' => $tokens,
            'areas' => $areas,
            'levanta_app' => $levantaApp,
            'http' => $http,
            // Sin dependencias detectables: no se puede afirmar que un cambio no la toque.
            'opaca' => ($tokens === [] && $areas === [] && ! $levantaApp)
                || ($this->leeArchivos($texto) && $areas === []),
        ];
    }

    private function leeArchivos(string $texto): bool
    {
        return preg_match('/\b(file_get_contents|glob|scandir|RecursiveDirectoryIterator|DirectoryIterator|file_exists|is_dir|is_file|require|include)\b|\b(?:base|app|database|lang|resource|public|config)_path\(/', $texto) === 1;
    }

    /**
     * Carpetas o archivos del repo que una prueba nombra de forma literal, como
     * prefijos (`lang/es`, `app/Dominios/Proveedores`). Un literal con comodín o
     * variable se corta en el primer segmento dinámico.
     *
     * @return list<string>
     */
    private function areasLeidas(string $texto): array
    {
        $areas = [];
        $raices = 'app|resources|lang|routes|database|docs|public|config|bootstrap';

        if (preg_match_all('#[\'"/]('.$raices.')((?:/[\w.\-]+)*)(?=[\'"/*{$]|$)#', $texto, $m, PREG_SET_ORDER) > 0) {
            foreach ($m as $c) {
                $areas[] = $c[1].$c[2];
            }
        }

        $ayudantes = ['app' => 'app', 'database' => 'database', 'lang' => 'lang', 'resource' => 'resources', 'public' => 'public', 'config' => 'config', 'base' => ''];
        foreach ($ayudantes as $funcion => $base) {
            if (preg_match_all('/\b'.$funcion.'_path\(\s*(?:[\'"]([^\'"]*)[\'"])?/', $texto, $m) > 0) {
                foreach ($m[1] as $literal) {
                    $segmentos = [];
                    foreach (explode('/', $literal) as $segmento) {
                        if ($segmento === '' || preg_match('/[*$\{]/', $segmento) === 1) {
                            break;
                        }
                        $segmentos[] = $segmento;
                    }
                    $area = trim($base.'/'.implode('/', $segmentos), '/');
                    if ($area !== '') {
                        $areas[] = $area;
                    }
                }
            }
        }

        $areas = array_values(array_unique($areas));
        sort($areas);

        return $areas;
    }
}
