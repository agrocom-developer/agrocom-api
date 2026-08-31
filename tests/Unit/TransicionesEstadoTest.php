<?php

/*
 * Invariante 7 de CLAUDE.md ("toda transición de estado pasa por el servicio
 * de dominio de la máquina de estados correspondiente —tabla de transiciones
 * permitidas más guardas—, nunca un `estado = ...` suelto") con compuerta
 * automática, no por revisión.
 *
 * Se escribe ANTES que el dominio que vigila, a propósito. Hoy nada en `app/`
 * muta un estado: `EstadoContrato` y `EstadoOrdenAplicacion` son enums que
 * todavía nadie escribe. Puesta ahora, la aduana falla la PRIMERA vez que
 * alguien la cruce; puesta después, hay que salir a buscar qué se coló. Y como
 * el ciclo automático mergea con el verde de `bin/verify`, mientras la aduana
 * no exista ese verde no significa lo que parece.
 *
 * Este test NO implementa la máquina de estados: solo fija dónde puede vivir.
 *
 * ── La convención que este test establece (la que siguen las tareas del
 *    Sprint 2, cuando aparezcan `sesion`, `devengo` y compañía) ─────────────
 *
 *     app/Dominios/<Modulo>/Aplicacion/MaquinaEstados/MaquinaEstados<Entidad>.php
 *
 * Una carpeta por módulo, descubierta recorriendo el árbol igual que
 * `ArquitecturaModulosTest` descubre los módulos: el módulo que estrene su
 * máquina de estados queda habilitado sin editar este archivo, y el que no la
 * tenga no tiene dónde escribir un estado.
 *
 * Por qué `Aplicacion/` y no `Dominio/`, si la invariante dice "servicio de
 * dominio": por la regla 4 del ADR 0003, «Eloquent ES el modelo de dominio
 * dentro del módulo dueño». El servicio que aplica la transición escribe sobre
 * un modelo Eloquent, y `Dominio/` es justamente la capa que no depende de
 * Eloquent — lo verifica `ArquitecturaModulosTest`. El reparto queda así:
 *
 *   - `Dominio/`  → lo puro: el enum de estado, la tabla de transiciones
 *                   permitidas y las guardas. Sin Eloquent, testeable sola.
 *   - `Aplicacion/MaquinaEstados/` → la única clase que ESCRIBE el estado:
 *                   consulta la tabla de arriba, aplica la guarda y recién
 *                   entonces asigna.
 *
 * Que la excepción sea una carpeta y no un nombre de clase ni una anotación es
 * deliberado: meter un caso de uso ahí adentro para saltear la aduana es un
 * cambio de ruta visible en el diff del PR, no un `use` que pasa desapercibido.
 *
 * ── Alcance del escaneo ──────────────────────────────────────────────────
 *
 * Solo `app/`. Migraciones, seeders, factories y tests quedan afuera por
 * construcción (viven en `database/` y `tests/`): ahí un estado literal es
 * dato de arranque o armado de un caso de prueba, no una transición de
 * dominio. Los comentarios se blanquean antes de buscar — este repo documenta
 * la invariante citando el antipatrón textual ("nunca en un `estado = ...`
 * suelto") y esas citas son el valor del comentario, no la fuga.
 *
 * ── Lo que la aduana NO puede ver (por eso no reemplaza a la revisión) ────
 *
 *   - `$modelo->update($datos)` con el arreglo armado en otro lado.
 *   - SQL crudo (`DB::statement("UPDATE ... SET estado = ...")`), que además
 *     se saltea la bitácora — el riesgo que anota el ADR 0012.
 *   - Nombres de propiedad resueltos en runtime (`$modelo->{$campo} = ...`).
 *
 * Se queda con lo que se escribe literal, que es como se cuela en la práctica.
 */

$raizProyecto = dirname(__DIR__, 2);

/**
 * Nombre de un estado de dominio: `estado`, `estado_actual` y cualquier cosa
 * con sufijo `_estado` (`rc_estado`, `sesion_estado`). Fragmento reutilizado
 * por los dos patrones de abajo para que no se desincronicen.
 */
const NOMBRE_ESTADO_DOMINIO = '(?:estado(?:_actual)?|[A-Za-z0-9_]+_estado)';

/**
 * Asignación directa a la propiedad: `->estado = `, `->estado_actual = `,
 * `->rc_estado = `. El `(?!=)` final descarta las comparaciones (`==`, `===`),
 * y `\b` evita que `->estadoDelArte` cuente como estado.
 */
const PATRON_ASIGNACION_DIRECTA = '/->\s*'.NOMBRE_ESTADO_DOMINIO.'\b\s*(?:\?\?)?=(?!=)/';

/**
 * Escritura masiva por arreglo. Se exige `->` o `::` delante para no confundir
 * la llamada con la declaración del método (`public function create(...)`, que
 * existe hoy en `RolActivoController`). La lista incluye las variantes que
 * pasan por Eloquent y también `insert`/`upsert` del query builder: escriben
 * el estado igual y encima sin pasar por el modelo.
 */
const PATRON_ESCRITURA_MASIVA = '/(?:->|::)\s*(?:forceCreate|createOrFirst|create|forceFill|fill|updateOrCreate|updateQuietly|update|firstOrCreate|insertOrIgnore|insert|upsert)\s*\(/';

/** Clave de estado dentro del arreglo de una escritura masiva. */
const PATRON_CLAVE_ESTADO = '/([\'"])'.NOMBRE_ESTADO_DOMINIO.'\1\s*=>/';

/** La única excepción: el servicio de máquina de estados del módulo dueño. */
const PATRON_SERVICIO_ESTADOS = '~^app/Dominios/[^/]+/Aplicacion/MaquinaEstados/~';

/**
 * ¿Esta ruta es el servicio de máquina de estados de algún módulo? Es el único
 * lugar donde la invariante 7 admite que se escriba un estado.
 */
function estadosEsServicioDeEstados(string $rutaRelativa): bool
{
    return preg_match(PATRON_SERVICIO_ESTADOS, $rutaRelativa) === 1;
}

/**
 * Blanquea comentarios **conservando los saltos de línea**: el gate reporta
 * `archivo:línea`, y borrar de cuajo un docblock de diez líneas correría todo
 * lo de abajo y mandaría a leer la línea equivocada.
 *
 * Los comentarios de una línea se descartan solo cuando abren la línea, igual
 * que en `TokensColorTest`: así un `'https://…'` no se confunde con un `//`.
 * `#[` queda fuera del blanqueo porque es un atributo PHP 8, no un comentario.
 */
function estadosSinComentarios(string $codigo): string
{
    $blanquear = static fn (array $coincidencia): string => str_repeat("\n", substr_count($coincidencia[0], "\n"));

    $limpio = preg_replace_callback('~/\*.*?\*/~s', $blanquear, $codigo) ?? $codigo;
    $limpio = preg_replace_callback('~\{\{--.*?--\}\}~s', $blanquear, $limpio) ?? $limpio;
    $limpio = preg_replace_callback('~<!--.*?-->~s', $blanquear, $limpio) ?? $limpio;

    return preg_replace('~^[ \t]*(?://|#(?!\[)).*$~m', '', $limpio) ?? $limpio;
}

/**
 * Texto de los argumentos de una llamada, dado el offset de su `(`.
 *
 * Recorre equilibrando paréntesis y corchetes en vez de cortar en el primer
 * `]`: `updateOrCreate(['uuid_cliente' => $u], ['estado' => $e])` lleva la
 * clave de estado en el SEGUNDO arreglo, y un `[^\]]*` la perdería. Un
 * paréntesis dentro de un literal de texto desbalancea la cuenta y agranda la
 * ventana — hacia el falso positivo, que es ruidoso y se corrige, no hacia el
 * falso negativo, que es silencioso.
 */
function estadosArgumentosDeLlamada(string $codigo, int $offsetParentesis): string
{
    $profundidad = 0;
    $largo = strlen($codigo);

    for ($i = $offsetParentesis; $i < $largo; $i++) {
        if ($codigo[$i] === '(' || $codigo[$i] === '[') {
            $profundidad++;

            continue;
        }

        if ($codigo[$i] === ')' || $codigo[$i] === ']') {
            $profundidad--;

            if ($profundidad === 0) {
                return substr($codigo, $offsetParentesis + 1, $i - $offsetParentesis - 1);
            }
        }
    }

    return substr($codigo, $offsetParentesis + 1);
}

/** Número de línea (1-based) del offset dado. */
function estadosLineaDe(string $codigo, int $offset): int
{
    return substr_count(substr($codigo, 0, $offset), "\n") + 1;
}

/**
 * Asignaciones de estado que la invariante 7 no admite fuera del servicio de
 * máquina de estados, con el número de línea y el texto original para poder
 * ir directo a arreglarlas.
 *
 * @return list<array{linea: int, fragmento: string}>
 */
function estadosAsignacionesSueltas(string $codigo): array
{
    $lineasOriginales = explode("\n", $codigo);
    $limpio = estadosSinComentarios($codigo);

    /** @var array<int, true> $lineas */
    $lineas = [];

    if (preg_match_all(PATRON_ASIGNACION_DIRECTA, $limpio, $coincidencias, PREG_OFFSET_CAPTURE) > 0) {
        foreach ($coincidencias[0] as [, $offset]) {
            $lineas[estadosLineaDe($limpio, $offset)] = true;
        }
    }

    if (preg_match_all(PATRON_ESCRITURA_MASIVA, $limpio, $coincidencias, PREG_OFFSET_CAPTURE) > 0) {
        foreach ($coincidencias[0] as [$llamada, $offset]) {
            $offsetArgumentos = $offset + strlen($llamada);
            $argumentos = estadosArgumentosDeLlamada($limpio, $offsetArgumentos - 1);

            if (preg_match(PATRON_CLAVE_ESTADO, $argumentos, $clave, PREG_OFFSET_CAPTURE) === 1) {
                // Se reporta la línea de la CLAVE, no la de la llamada: en un
                // arreglo multilínea son distintas y la que hay que corregir
                // es la de la clave.
                $lineas[estadosLineaDe($limpio, $offsetArgumentos + $clave[0][1])] = true;
            }
        }
    }

    ksort($lineas);

    return array_map(
        fn (int $linea): array => ['linea' => $linea, 'fragmento' => trim($lineasOriginales[$linea - 1] ?? '')],
        array_keys($lineas),
    );
}

/**
 * Todo el PHP de `app/` —incluidas las vistas Blade de cada módulo (ADR
 * 0008)—, menos los servicios de máquina de estados. Descubierto recorriendo
 * el árbol: un módulo nuevo queda vigilado sin editar este archivo.
 *
 * @return list<string>
 */
function estadosArchivosVigilados(string $raizProyecto): array
{
    $rutaApp = $raizProyecto.'/app';

    if (! is_dir($rutaApp)) {
        return [];
    }

    $archivos = [];

    /** @var iterable<SplFileInfo> $iterador */
    $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rutaApp, FilesystemIterator::SKIP_DOTS));

    foreach ($iterador as $archivo) {
        $ruta = $archivo->getPathname();

        if (! $archivo->isFile() || ! str_ends_with($ruta, '.php')) {
            continue;
        }

        $relativa = substr($ruta, strlen($raizProyecto) + 1);

        if (estadosEsServicioDeEstados($relativa)) {
            continue;
        }

        $archivos[] = $relativa;
    }

    sort($archivos);

    return $archivos;
}

// Red de seguridad del descubrimiento: si esto falla, la regla de abajo no
// protege nada (p. ej. porque se movió app/Dominios).
test('el descubrimiento encuentra el código de dominio de app/', function () use ($raizProyecto) {
    expect(estadosArchivosVigilados($raizProyecto))
        ->toContain('app/Dominios/Comercial/Infraestructura/Eloquent/Contrato.php')
        ->toContain('app/Dominios/Operaciones/Aplicacion/ListarOrdenesAplicacion.php');
});

test('ninguna asignación de estado fuera del servicio de máquina de estados (CLAUDE.md invariante 7)', function () use ($raizProyecto) {
    $sueltas = [];

    foreach (estadosArchivosVigilados($raizProyecto) as $relativa) {
        $contenido = file_get_contents($raizProyecto.'/'.$relativa);

        if ($contenido === false) {
            continue;
        }

        foreach (estadosAsignacionesSueltas($contenido) as $hallazgo) {
            $sueltas[] = sprintf('%s:%d: %s', $relativa, $hallazgo['linea'], $hallazgo['fragmento']);
        }
    }

    expect($sueltas)->toBe([], "Una transición se le pide al servicio de máquina de estados del módulo (app/Dominios/<Modulo>/Aplicacion/MaquinaEstados/), que consulta la tabla de transiciones permitidas y sus guardas antes de escribir. Si ese servicio todavía no existe para la entidad, se crea ahí — no se asigna el estado a mano.\n".implode("\n", $sueltas));
});

test('la única excepción es el servicio de máquina de estados del módulo', function () {
    expect(estadosEsServicioDeEstados('app/Dominios/Operaciones/Aplicacion/MaquinaEstados/MaquinaEstadosOrdenAplicacion.php'))->toBeTrue()
        ->and(estadosEsServicioDeEstados('app/Dominios/Comercial/Aplicacion/MaquinaEstados/MaquinaEstadosContrato.php'))->toBeTrue()
        // Un caso de uso cualquiera no queda habilitado por estar en Aplicacion/…
        ->and(estadosEsServicioDeEstados('app/Dominios/Operaciones/Aplicacion/CerrarSesion.php'))->toBeFalse()
        // …ni la capa Dominio, que es donde va la tabla de transiciones, no la escritura…
        ->and(estadosEsServicioDeEstados('app/Dominios/Operaciones/Dominio/MaquinaEstados/TransicionesOrden.php'))->toBeFalse()
        // …ni nada que solo se llame parecido.
        ->and(estadosEsServicioDeEstados('app/Dominios/Operaciones/Infraestructura/Http/Controllers/Api/MaquinaEstadosController.php'))->toBeFalse();
});

/*
 * Los dos tests que siguen son la aduana probándose a sí misma. Un gate que
 * hoy solo pasa en verde no prueba nada: sin esto, un patrón mal escrito
 * (o desafinado en un refactor futuro) dejaría de detectar y la suite seguiría
 * igual de verde que cuando detectaba.
 */

test('el detector encuentra cada forma de asignar un estado a mano', function (string $codigo, int $lineaEsperada) {
    $hallazgos = estadosAsignacionesSueltas($codigo);

    expect($hallazgos)->toHaveCount(1)
        ->and($hallazgos[0]['linea'])->toBe($lineaEsperada);
})->with([
    'propiedad estado' => ["<?php\n\n\$orden->estado = EstadoOrdenAplicacion::Vigente;\n", 3],
    'propiedad estado_actual' => ["<?php\n\n\$sesion->estado_actual = 'validada';\n", 3],
    'propiedad con sufijo _estado' => ["<?php\n\n\$parte->rc_estado = 'capturado';\n", 3],
    'asignación con ??=' => ["<?php\n\n\$orden->estado ??= EstadoOrdenAplicacion::Emitida;\n", 3],
    'update en una línea' => ["<?php\n\n\$orden->update(['estado' => EstadoOrdenAplicacion::Consumida]);\n", 3],
    'update multilínea' => ["<?php\n\n\$orden->update([\n    'litros_ha' => 12,\n    'estado' => 'consumida',\n]);\n", 5],
    'create estático' => ["<?php\n\nContrato::create(['cliente_id' => 1, 'estado' => 'vigente']);\n", 3],
    'fill' => ["<?php\n\n\$contrato->fill(['estado' => EstadoContrato::Vigente])->save();\n", 3],
    'forceFill' => ["<?php\n\n\$contrato->forceFill(['estado' => 'cancelado'])->saveQuietly();\n", 3],
    'clave en el segundo arreglo de updateOrCreate' => ["<?php\n\nOrden::updateOrCreate(['uuid_cliente' => \$u], ['estado' => 'vigente']);\n", 3],
    'insert del query builder' => ["<?php\n\nDB::table('ope_ordenes')->insert(['estado' => 'emitida']);\n", 3],
    'línea correcta después de un docblock multilínea' => ["<?php\n\n/**\n * Un docblock\n * de varias líneas.\n */\n\$orden->estado = 'vencida';\n", 7],
]);

test('el detector no confunde con una asignación lo que solo lee o declara un estado', function (string $codigo) {
    expect(estadosAsignacionesSueltas($codigo))->toBe([]);
})->with([
    'lectura en un Resource' => "<?php\n\nreturn ['estado' => \$this->estado->value];\n",
    'declaración de fillable' => "<?php\n\nprotected \$fillable = ['estado'];\n",
    'declaración de casts' => "<?php\n\nreturn ['estado' => EstadoContrato::class];\n",
    'filtro de consulta' => "<?php\n\n\$consulta->where('estado', \$estado);\n",
    'comparación' => "<?php\n\nif (\$orden->estado === EstadoOrdenAplicacion::Vigente) {\n}\n",
    'escritura masiva sin clave de estado' => "<?php\n\n\$token->forceFill(['last_used_at' => now()])->saveQuietly();\n",
    'propiedad que solo empieza igual' => "<?php\n\n\$this->estadoDelArte = 'moderno';\n",
    'declaración de un método llamado create' => "<?php\n\npublic function create(array \$datos): void\n{\n}\n",
    'comentario de una línea que cita el antipatrón' => "<?php\n\n// \$orden->estado = 'vigente';\n",
    'docblock que cita el antipatrón' => "<?php\n\n/** Nunca un \$orden->estado = ... suelto. */\n",
]);
