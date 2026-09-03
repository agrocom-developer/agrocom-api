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
 * Esa excepción vale con una salvedad, y conviene conocerla antes de escribir
 * la cita: un comentario de una línea cuenta como comentario solo cuando ABRE
 * la línea. Pegado al final de una línea de código —`$x = 5; // ojo, nunca
 * $orden->estado = ...`— no se blanquea, y la cita se reporta. Es deliberado:
 * en el HTML de una vista Blade un `//` a mitad de línea es texto corriente
 * (`https://…` fuera de comillas), y tratarlo como comentario blanquearía
 * hasta el fin de esa línea, con el código que hubiera en el medio. El precio
 * de la salvedad es un hallazgo de más —ruidoso, visible, se corrige—; el de
 * levantarla sería uno de menos, silencioso. La aduana elige el ruido. Para
 * citar el antipatrón, el comentario va en su propia línea.
 *
 * ── Lo que la aduana NO puede ver (por eso no reemplaza a la revisión) ────
 *
 *   - `$modelo->update($datos)` con el arreglo armado en otro lado.
 *   - SQL crudo (`DB::statement("UPDATE ... SET estado = ...")`), que además
 *     se saltea la bitácora — el riesgo que anota el ADR 0012.
 *   - Nombres de propiedad resueltos en runtime (`$modelo->{$campo} = ...`).
 *   - Texto que no sea PHP válido: para no confundir un comentario con la cita
 *     de un comentario, el archivo se recorre como lo lee PHP, dando por hecho
 *     que las comillas cierran. En el HTML de una vista Blade un apóstrofe
 *     suelto puede dejar un comentario sin blanquear, y eso cae del lado del
 *     hallazgo de más: lo que se saltea se sigue buscando igual.
 *
 * Se queda con lo que se escribe literal, que es como se cuela en la práctica
 * — incluidas las dos formas que son la misma escritura con otra sintaxis:
 * `$orden['estado'] = ...` (Eloquent implementa ArrayAccess, así que por debajo
 * es el mismo `setAttribute` que la flecha) y el `setAttribute('estado', ...)`
 * explícito. Si solo se vigilara `->estado =`, quien viera fallar el gate no
 * tendría que cambiar de carpeta para pasar: le alcanzaría con cambiar de
 * sintaxis, sin que el diff lo delate. Las tres se vigilan juntas.
 *
 * ── Segunda excepción: estado DESCRIPTIVO sin máquina, no gobernado (HU-40,
 *    tarea 50) ───────────────────────────────────────────────────────────
 *
 * Hasta la tarea 50, toda columna literalmente llamada `estado`/`*_estado` en
 * `app/` correspondía a una máquina gobernada (Contrato, OrdenAplicacion,
 * Sesion, Trabajo, Acta, Alerta, VersionApk) — la invariante 7 aplicaba sin
 * matices. `man_vehiculos.estado` (`activo`/`taller`/`de_baja`) es el primer
 * caso real de lo contrario: un campo descriptivo libre, sin tabla de
 * transiciones ni guarda de dominio — cualquier valor puede pasar a cualquier
 * otro. La invariante 7 ("toda TRANSICIÓN de estado pasa por el servicio de
 * la máquina") no aplica por definición cuando no hay transición gobernada
 * que vigilar, no por excepción a la regla.
 *
 * La opción descartada fue meter un wrapper vacío en
 * `Aplicacion/MaquinaEstados/` solo para que el patrón de carpeta lo
 * blanqueara: eso contradice la instrucción explícita del prompt de la tarea
 * ("no construyas una máquina de estados para esto") y diluye lo que esa
 * carpeta significa para quien la lea después (hoy es sinónimo de "máquina
 * real, con guardas, de las que CLAUDE.md marca como no delegable sin
 * revisión línea por línea"). En su lugar, la excepción es una lista de
 * RUTAS EXACTAS de archivo (`RUTAS_ESTADO_DESCRIPTIVO_SIN_MAQUINA`, abajo),
 * angosta a propósito: cada entrada nueva es, igual que mover código a
 * `MaquinaEstados/`, una línea visible en el diff — no una vía silenciosa. No
 * es un patrón de nombre ni una heurística por reflection (este test es un
 * escáner de texto, no un analizador semántico): quien agregue una entrada
 * nueva certifica a mano, leyendo el caso de uso, que de verdad no hay
 * transición gobernada detrás.
 *
 * Zona gris para HU-37/38 (planes/órdenes de mantenimiento) y HU-39
 * (baterías): antes de sumar una ruta nueva a esa lista hay que clasificar el
 * campo contra el mismo criterio — un `estado` de orden de mantenimiento
 * (abierta → cerrada, con costo asociado) probablemente SÍ sea gobernado, a
 * diferencia de `man_vehiculos.estado`. Esta clasificación quedó anotada para
 * revisión posterior en `runs/revision-pendiente.txt` (mismo patrón que
 * CLAUDE.md fija para "el servicio de estados": revisión posterior a la
 * integración, no un PR retenido esperándola — ver la sección "Qué no
 * delegar sin revisión línea por línea" de CLAUDE.md).
 */

$raizProyecto = dirname(__DIR__, 2);

/**
 * Nombre de un estado de dominio: `estado`, `estado_actual` y cualquier cosa
 * con sufijo `_estado` (`rc_estado`, `sesion_estado`). Fragmento reutilizado
 * por los patrones de abajo para que no se desincronicen.
 */
const NOMBRE_ESTADO_DOMINIO = '(?:estado(?:_actual)?|[A-Za-z0-9_]+_estado)';

/**
 * Asignación directa a la propiedad: `->estado = `, `->estado_actual = `,
 * `->rc_estado = `. El `(?!=)` final descarta las comparaciones (`==`, `===`),
 * y `\b` evita que `->estadoDelArte` cuente como estado.
 */
const PATRON_ASIGNACION_DIRECTA = '/->\s*'.NOMBRE_ESTADO_DOMINIO.'\b\s*(?:\?\?)?=(?!=)/';

/**
 * La misma asignación por índice: `$orden['estado'] = ...`. Eloquent implementa
 * ArrayAccess, así que termina en el mismo `setAttribute` que la flecha y tiene
 * el mismo efecto sobre el modelo.
 */
const PATRON_ASIGNACION_INDICE = '/\[\s*([\'"])'.NOMBRE_ESTADO_DOMINIO.'\1\s*\]\s*(?:\?\?)?=(?!=)/';

/** Y por la API del modelo: `->setAttribute('estado', ...)`, `->offsetSet(...)`. */
const PATRON_ASIGNACION_POR_METODO = '/->\s*(?:setAttribute|offsetSet)\s*\(\s*([\'"])'.NOMBRE_ESTADO_DOMINIO.'\1\s*,/';

/**
 * Escritura masiva por arreglo. Se exige `->` o `::` delante para no confundir
 * la llamada con la declaración del método (`public function create(...)`, que
 * existe hoy en `RolActivoController`). La lista incluye las variantes que
 * pasan por Eloquent y también las del query builder —`insert`, `insertGetId`,
 * `insertOrIgnore`, `upsert`, `updateOrInsert`—: escriben el estado igual y
 * encima sin pasar por el modelo. `firstOrNew` entra por el mismo criterio que
 * su gemela `firstOrCreate`: asigna el atributo aunque no persista sola.
 *
 * Las variantes largas van antes que las cortas (`updateOrInsert` antes de
 * `update`) para que la alternancia no corte corto.
 */
const PATRON_ESCRITURA_MASIVA = '/(?:->|::)\s*(?:forceCreate|createOrFirst|create|forceFill|fill|updateOrCreate|updateOrInsert|updateQuietly|update|firstOrCreate|firstOrNew|insertOrIgnore|insertGetId|insert|upsert)\s*\(/';

/** Clave de estado dentro del arreglo de una escritura masiva. */
const PATRON_CLAVE_ESTADO = '/([\'"])'.NOMBRE_ESTADO_DOMINIO.'\1\s*=>/';

/** La primera excepción: el servicio de máquina de estados del módulo dueño. */
const PATRON_SERVICIO_ESTADOS = '~^app/Dominios/[^/]+/Aplicacion/MaquinaEstados/~';

/**
 * ¿Esta ruta es el servicio de máquina de estados de algún módulo? Es el
 * primer lugar donde la invariante 7 admite que se escriba un estado.
 */
function estadosEsServicioDeEstados(string $rutaRelativa): bool
{
    return preg_match(PATRON_SERVICIO_ESTADOS, $rutaRelativa) === 1;
}

/**
 * La segunda excepción (HU-40, tarea 50): rutas exactas donde `estado` es un
 * campo descriptivo libre, sin tabla de transiciones ni guarda de dominio —
 * ver el bloque "Segunda excepción" en el docblock de arriba. Lista angosta
 * a propósito, no un patrón de nombre: cada entrada certifica a mano que ese
 * caso de uso concreto no gobierna ninguna transición.
 *
 * @var list<string>
 */
const RUTAS_ESTADO_DESCRIPTIVO_SIN_MAQUINA = [
    'app/Dominios/Mantenimiento/Aplicacion/CrearVehiculo.php',
    'app/Dominios/Mantenimiento/Aplicacion/ActualizarVehiculo.php',
    // HU-39, tarea 51: mismo criterio que Vehiculo arriba — `man_baterias.estado`
    // es descriptivo (`activa`/`retirada`), sin tabla de transiciones ni guarda
    // de dominio (ver docblock de la migración y `EstadoBateria`).
    'app/Dominios/Mantenimiento/Aplicacion/CrearBateria.php',
    'app/Dominios/Mantenimiento/Aplicacion/ActualizarBateria.php',
];

/**
 * ¿Esta ruta es una de las excepciones angostas de estado descriptivo sin
 * máquina? Es el segundo (y último) lugar donde la invariante 7 admite que
 * se escriba un estado.
 */
function estadosEsExcepcionDescriptiva(string $rutaRelativa): bool
{
    return in_array($rutaRelativa, RUTAS_ESTADO_DESCRIPTIVO_SIN_MAQUINA, true);
}

/**
 * Fin de un literal de texto que arranca en `$offset` —comilla simple, doble,
 * heredoc o nowdoc—, o `null` si ahí no arranca ninguno.
 *
 * Adentro de un literal no hay comentarios que blanquear ni paréntesis que
 * contar: `'ver inciso b) del contrato'` es texto, no el cierre de una llamada.
 * Saltearlos es lo que evita que el detector se equivoque en silencio.
 *
 * @return int|null offset del primer carácter DESPUÉS del literal
 */
function estadosFinDeLiteral(string $codigo, int $offset): ?int
{
    $largo = strlen($codigo);
    $comilla = $codigo[$offset] ?? '';

    if ($comilla === "'" || $comilla === '"') {
        for ($i = $offset + 1; $i < $largo; $i++) {
            if ($codigo[$i] === '\\') {
                $i++;

                continue;
            }

            if ($codigo[$i] === $comilla) {
                return $i + 1;
            }
        }

        return $largo;
    }

    if (preg_match('~<<<[ \t]*([\'"]?)([A-Za-z_][A-Za-z0-9_]*)\1\R~A', $codigo, $apertura, 0, $offset) !== 1) {
        return null;
    }

    $inicioCuerpo = $offset + strlen($apertura[0]);
    $cierre = '~^[ \t]*'.preg_quote($apertura[2], '~').'(?![A-Za-z0-9_])~m';

    if (preg_match($cierre, $codigo, $coincidencia, PREG_OFFSET_CAPTURE, $inicioCuerpo) !== 1) {
        return $largo;
    }

    return $coincidencia[0][1] + strlen($coincidencia[0][0]);
}

/** ¿Del principio de la línea hasta `$offset` hay solo espacios? */
function estadosAbreLaLinea(string $codigo, int $offset): bool
{
    for ($i = $offset - 1; $i >= 0; $i--) {
        if ($codigo[$i] === "\n") {
            return true;
        }

        if ($codigo[$i] !== ' ' && $codigo[$i] !== "\t") {
            return false;
        }
    }

    return true;
}

/**
 * Fin del comentario que arranca en `$offset`, o `null` si ahí no arranca uno.
 * `#[` queda afuera: es un atributo PHP 8, no un comentario.
 *
 * Los de una línea se reconocen solo cuando abren la línea, igual que en
 * `TokensColorTest` — salvo cuando se pide lo contrario, que es dentro de los
 * argumentos de una llamada: ahí ya se sabe que se está mirando código PHP, y
 * un `)` comentado al final de la línea desbalancea la cuenta.
 */
function estadosFinDeComentario(string $codigo, int $offset, bool $soloAlAbrirLaLinea = true): ?int
{
    $largo = strlen($codigo);

    foreach (['/*' => '*/', '{{--' => '--}}', '<!--' => '-->'] as $abre => $cierra) {
        if (! str_starts_with(substr($codigo, $offset, strlen($abre)), $abre)) {
            continue;
        }

        $fin = strpos($codigo, $cierra, $offset + strlen($abre));

        return $fin === false ? $largo : $fin + strlen($cierra);
    }

    $dos = substr($codigo, $offset, 2);

    if ($dos !== '//' && ! (($codigo[$offset] ?? '') === '#' && $dos !== '#[')) {
        return null;
    }

    if ($soloAlAbrirLaLinea && ! estadosAbreLaLinea($codigo, $offset)) {
        return null;
    }

    $fin = strpos($codigo, "\n", $offset);

    // El salto de línea queda afuera a propósito: se conserva.
    return $fin === false ? $largo : $fin;
}

/**
 * Blanquea comentarios **conservando la longitud y los saltos de línea**: el
 * gate reporta `archivo:línea`, y borrar de cuajo un docblock de diez líneas
 * correría todo lo de abajo y mandaría a leer la línea equivocada.
 *
 * Recorre en vez de reemplazar con una expresión regular sobre todo el archivo
 * porque hay que distinguir el comentario de la cita del comentario: una
 * apertura de bloque escrita dentro de un literal de texto no abre nada, y con
 * un reemplazo global se blanqueaba desde ahí hasta el próximo cierre —
 * llevándose por delante, en silencio, las asignaciones del medio.
 */
function estadosSinComentarios(string $codigo): string
{
    $limpio = $codigo;
    $largo = strlen($codigo);
    $i = 0;

    while ($i < $largo) {
        $finLiteral = estadosFinDeLiteral($codigo, $i);

        if ($finLiteral !== null) {
            $i = $finLiteral;

            continue;
        }

        $finComentario = estadosFinDeComentario($codigo, $i);

        if ($finComentario === null) {
            $i++;

            continue;
        }

        for ($j = $i; $j < $finComentario; $j++) {
            if ($limpio[$j] !== "\n") {
                $limpio[$j] = ' ';
            }
        }

        $i = $finComentario;
    }

    return $limpio;
}

/**
 * Texto de los argumentos de una llamada, dado el offset de su `(`.
 *
 * Recorre equilibrando paréntesis y corchetes en vez de cortar en el primer
 * `]`: `updateOrCreate(['uuid_cliente' => $u], ['estado' => $e])` lleva la
 * clave de estado en el SEGUNDO arreglo, y un `[^\]]*` la perdería.
 *
 * Los literales de texto y los comentarios no cuentan para el equilibrio, y no
 * es un detalle cosmético: el desbalance corre en las DOS direcciones. Uno de
 * apertura de más agranda la ventana —falso positivo, ruidoso, se corrige—,
 * pero uno de cierre sin abrir la corta ahí mismo y todo lo que venga después
 * queda sin mirar. `['motivo' => 'ver inciso b) del contrato'], ['estado' =>
 * $e]` perdía así la clave de estado, en silencio y sobre la forma exacta que
 * va a usar el motor de sync: un falso negativo, que es justo el modo de fallo
 * que esta aduana existe para cerrar, movido un nivel más arriba.
 */
function estadosArgumentosDeLlamada(string $codigo, int $offsetParentesis): string
{
    $profundidad = 0;
    $largo = strlen($codigo);
    $i = $offsetParentesis;

    while ($i < $largo) {
        $salto = estadosFinDeLiteral($codigo, $i) ?? estadosFinDeComentario($codigo, $i, false);

        if ($salto !== null) {
            $i = $salto;

            continue;
        }

        if ($codigo[$i] === '(' || $codigo[$i] === '[') {
            $profundidad++;
        } elseif ($codigo[$i] === ')' || $codigo[$i] === ']') {
            $profundidad--;

            if ($profundidad === 0) {
                return substr($codigo, $offsetParentesis + 1, $i - $offsetParentesis - 1);
            }
        }

        $i++;
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

    $directos = [PATRON_ASIGNACION_DIRECTA, PATRON_ASIGNACION_INDICE, PATRON_ASIGNACION_POR_METODO];

    foreach ($directos as $patron) {
        if (preg_match_all($patron, $limpio, $coincidencias, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($coincidencias[0] as [, $offset]) {
                $lineas[estadosLineaDe($limpio, $offset)] = true;
            }
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

        if (estadosEsServicioDeEstados($relativa) || estadosEsExcepcionDescriptiva($relativa)) {
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

test('la excepción de estado descriptivo es angosta: rige solo para las rutas exactas listadas', function () {
    expect(estadosEsExcepcionDescriptiva('app/Dominios/Mantenimiento/Aplicacion/CrearVehiculo.php'))->toBeTrue()
        ->and(estadosEsExcepcionDescriptiva('app/Dominios/Mantenimiento/Aplicacion/ActualizarVehiculo.php'))->toBeTrue()
        // Ni el resto del módulo Mantenimiento, ni un archivo parecido, quedan
        // habilitados por estar "cerca": la lista es de rutas exactas.
        ->and(estadosEsExcepcionDescriptiva('app/Dominios/Mantenimiento/Aplicacion/EliminarVehiculo.php'))->toBeFalse()
        ->and(estadosEsExcepcionDescriptiva('app/Dominios/Mantenimiento/Infraestructura/Http/Controllers/Web/VehiculosController.php'))->toBeFalse()
        ->and(estadosEsExcepcionDescriptiva('app/Dominios/Operaciones/Aplicacion/CrearVehiculo.php'))->toBeFalse();
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

test('la excepción también rige en el recorrido del árbol, no solo como predicado', function () {
    // El test de arriba prueba el predicado sobre strings; éste prueba el
    // camino por el que la exclusión ocurre de verdad — el filtro dentro de
    // `estadosArchivosVigilados()`. Sin esto, desafinar ese filtro dejaría los
    // otros tests igual de verdes, que es el mismo modo de fallo silencioso
    // que los datasets de más abajo existen para cerrar. Se arma un árbol
    // temporal porque hoy ningún módulo estrenó su máquina de estados.
    $raiz = sys_get_temp_dir().'/aduana-estados-'.bin2hex(random_bytes(8));
    $aplicacion = $raiz.'/app/Dominios/Operaciones/Aplicacion';

    mkdir($aplicacion.'/MaquinaEstados', 0o755, true);
    file_put_contents($aplicacion.'/CerrarSesion.php', "<?php\n");
    file_put_contents($aplicacion.'/MaquinaEstados/MaquinaEstadosSesion.php', "<?php\n");

    $vigilados = estadosArchivosVigilados($raiz);

    // Se limpia ANTES de comparar: si la expectativa falla, el temporal no
    // queda colgado igual.
    unlink($aplicacion.'/MaquinaEstados/MaquinaEstadosSesion.php');
    unlink($aplicacion.'/CerrarSesion.php');

    for ($directorio = $aplicacion.'/MaquinaEstados'; $directorio !== $raiz; $directorio = dirname($directorio)) {
        rmdir($directorio);
    }

    rmdir($raiz);

    expect($vigilados)->toBe(['app/Dominios/Operaciones/Aplicacion/CerrarSesion.php']);
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
    'índice de arreglo, que es el mismo setAttribute' => ["<?php\n\n\$orden['estado'] = EstadoOrdenAplicacion::Consumida;\n", 3],
    'índice de arreglo con sufijo _estado' => ["<?php\n\n\$parte[\"rc_estado\"] = 'capturado';\n", 3],
    'setAttribute explícito' => ["<?php\n\n\$orden->setAttribute('estado', EstadoOrdenAplicacion::Consumida);\n", 3],
    'update en una línea' => ["<?php\n\n\$orden->update(['estado' => EstadoOrdenAplicacion::Consumida]);\n", 3],
    'update multilínea' => ["<?php\n\n\$orden->update([\n    'litros_ha' => 12,\n    'estado' => 'consumida',\n]);\n", 5],
    'create estático' => ["<?php\n\nContrato::create(['cliente_id' => 1, 'estado' => 'vigente']);\n", 3],
    'fill' => ["<?php\n\n\$contrato->fill(['estado' => EstadoContrato::Vigente])->save();\n", 3],
    'forceFill' => ["<?php\n\n\$contrato->forceFill(['estado' => 'cancelado'])->saveQuietly();\n", 3],
    'clave en el segundo arreglo de updateOrCreate' => ["<?php\n\nOrden::updateOrCreate(['uuid_cliente' => \$u], ['estado' => 'vigente']);\n", 3],
    'insert del query builder' => ["<?php\n\nDB::table('ope_ordenes')->insert(['estado' => 'emitida']);\n", 3],
    'insertGetId del query builder' => ["<?php\n\n\$id = DB::table('ope_ordenes')->insertGetId(['estado' => 'emitida']);\n", 3],
    'updateOrInsert del query builder' => ["<?php\n\nDB::table('ope_ordenes')->updateOrInsert(['id' => 1], ['estado' => 'vencida']);\n", 3],
    'firstOrNew, que asigna aunque no persista' => ["<?php\n\nOrden::firstOrNew(['estado' => 'vigente']);\n", 3],
    'línea correcta después de un docblock multilínea' => ["<?php\n\n/**\n * Un docblock\n * de varias líneas.\n */\n\$orden->estado = 'vencida';\n", 7],
    // Los cuatro que siguen son el detector contra su propio modo de fallo
    // silencioso: texto que parece sintaxis y descuadraba la cuenta.
    'paréntesis de cierre dentro de un literal de texto' => ["<?php\n\nOrden::updateOrCreate(\n    ['motivo' => 'ver inciso b) del contrato'],\n    ['estado' => 'vencida'],\n);\n", 5],
    'paréntesis de cierre dentro de un nowdoc' => ["<?php\n\nOrden::updateOrCreate(\n    ['motivo' => <<<'TXT'\n        ver inciso b) del contrato\n        TXT],\n    ['estado' => 'vencida'],\n);\n", 7],
    'paréntesis de cierre en un comentario al final de la línea' => ["<?php\n\n\$orden->update([  // ver a) y b)\n    'estado' => 'consumida',\n]);\n", 4],
    'apertura de comentario dentro de un literal de texto' => ["<?php\n\n\$abre = '/*';\n\$orden->estado = 'vigente';\n\$cierra = '*/';\n", 4],
    // Y éste fija el precio elegido en el encabezado: la cita del antipatrón
    // vale como comentario cuando ABRE la línea; pegada al final de una línea
    // de código se reporta, porque en Blade un `//` a mitad de línea es texto
    // y blanquear desde ahí escondería el código que venga después.
    'cita del antipatrón pegada al final de una línea de código' => ["<?php\n\n\$total = 5; // ojo: nunca \$orden->estado = 'vigente';\n", 3],
]);

test('el detector no confunde con una asignación lo que solo lee o declara un estado', function (string $codigo) {
    expect(estadosAsignacionesSueltas($codigo))->toBe([]);
})->with([
    'lectura en un Resource' => "<?php\n\nreturn ['estado' => \$this->estado->value];\n",
    'lectura por índice' => "<?php\n\nreturn \$fila['estado'];\n",
    'declaración de fillable' => "<?php\n\nprotected \$fillable = ['estado'];\n",
    'declaración de casts' => "<?php\n\nreturn ['estado' => EstadoContrato::class];\n",
    'filtro de consulta' => "<?php\n\n\$consulta->where('estado', \$estado);\n",
    'comparación' => "<?php\n\nif (\$orden->estado === EstadoOrdenAplicacion::Vigente) {\n}\n",
    'comparación por índice' => "<?php\n\nif (\$fila['estado'] === 'vigente') {\n}\n",
    'escritura masiva sin clave de estado' => "<?php\n\n\$token->forceFill(['last_used_at' => now()])->saveQuietly();\n",
    'propiedad que solo empieza igual' => "<?php\n\n\$this->estadoDelArte = 'moderno';\n",
    'declaración de un método llamado create' => "<?php\n\npublic function create(array \$datos): void\n{\n}\n",
    'comentario de una línea que cita el antipatrón' => "<?php\n\n// \$orden->estado = 'vigente';\n",
    'docblock que cita el antipatrón' => "<?php\n\n/** Nunca un \$orden->estado = ... suelto. */\n",
]);
