<?php

/*
 * Invariante 9 de CLAUDE.md ("bitácora de auditoría en toda mutación
 * relevante: quién, cuándo, qué entidad, qué acción, valores antes/después
 * donde aplique... es un trait/observer de plataforma — no una llamada
 * manual que cada caso de uso deba recordar") con compuerta automática, no
 * por revisión. Ver ADR 0007.
 *
 * El mecanismo (`RegistraBitacora` + `BitacoraObserver`, en
 * app/Dominios/Compartido/Infraestructura/Eloquent/) es "colgable por
 * modelo": un modelo lo adopta con `use RegistraBitacora;`. Esta aduana
 * decide, a partir del ESQUEMA REAL (las migraciones, no una lista de clases
 * a mano), qué modelos están obligados a llevarlo, y falla si a alguno le
 * falta — el mismo criterio que `ArquitecturaModulosTest` y
 * `TransicionesEstadoTest` ya usan para sus propias fronteras.
 *
 * ── La regla: quién debe auditar ─────────────────────────────────────────
 *
 * El ADR 0007 nombra tres categorías ("empezando por todo lo que toque
 * dinero, roles/permisos y estados operativos"). Esta aduana cubre HOY la
 * categoría "roles/permisos" — la única que, además de ya existir en el
 * esquema, esta tarea tiene permiso de tocar (`docs/gestion/cola_tareas.md`,
 * fila 06: "Puede tocar" no incluye Comercial/Operaciones). "Dinero"
 * (`com_contratos`, `com_lotes`) y "estados operativos"
 * (`com_contratos.estado`, `ope_ordenes_aplicacion.estado`) YA tienen tablas
 * reales hoy, deliberadamente fuera de esta regla: instrumentarlas es
 * trabajo de la tarea que implemente la máquina de estados/devengos —la
 * siguiente en la cola—, que de todos modos va a tocar esos modelos. Ampliar
 * la regla de abajo para incluirlas es el primer paso natural de esa tarea,
 * no una laguna escondida de esta.
 *
 * Una tabla queda dentro de "roles/permisos" si su migración declara:
 *
 *   1. Una columna booleana `state` — la marca que este mismo esquema ya usa
 *      para "catálogo de control de acceso que se activa/desactiva para
 *      todo el sistema" (`sec_role`, `sec_permission`, `sec_user`). No es
 *      cualquier bandera de encendido/apagado: `per_personas` tiene la
 *      misma idea con otro nombre (`activo`), y la propia migración de
 *      `sec_menu` explica por qué ESE catálogo, a propósito, no lleva
 *      `state` — la distinción ya es una convención documentada del
 *      proyecto, no una que esta aduana inventa.
 *   2. Una columna `id_role` o `id_permission` — el par con prefijo (no
 *      sufijo) que ADR 0004 usa exclusivamente para las tablas de
 *      asignación rol↔permiso (`sec_user_role`, `sec_role_permission`); los
 *      docblocks de esos dos modelos ya llaman "inconsistencia heredada" a
 *      esa forma, distinta de `role_id`/`permission_id` (sufijo) que usan
 *      `sec_menu` y `sec_token_dispositivo` para una referencia común, no
 *      una asignación.
 *
 * Ninguna de las dos señales aparece hoy fuera de `Seguridad` (verificado:
 * `grep -rn "id_role\|id_permission\|boolean('state')" database/migrations`
 * solo devuelve `sec_role`, `sec_permission`, `sec_user`, `sec_user_role` y
 * `sec_role_permission`) — la regla es general (cualquier módulo futuro con
 * estas señales queda cubierto sin tocar este archivo), simplemente hoy solo
 * la cumple ese conjunto de cinco tablas.
 *
 * ── Qué NO puede ver esta aduana ──────────────────────────────────────────
 *
 * Lee migraciones como texto (regex), no ejecuta el esquema: un modelo cuya
 * tabla se declarara con un helper propio en vez de `$table->boolean(...)`/
 * `$table->foreignId(...)`/`$table->unsignedBigInteger(...)` literal no
 * sería detectado. Ninguna migración de este repo usa esa forma hoy.
 */

$raizProyecto = dirname(__DIR__, 2);

const PATRON_BITACORA_STATE = '/\$table->boolean\(\s*[\'"]state[\'"]\s*[,)]/';
const PATRON_BITACORA_ROL_PERMISO = '/\$table->(?:foreignId|unsignedBigInteger)\(\s*[\'"](?:id_role|id_permission)[\'"]\s*[,)]/';
const PATRON_BITACORA_TABLA_DECLARADA = '/protected\s+\$table\s*=\s*[\'"]([a-z0-9_]+)[\'"]/';
const PATRON_BITACORA_USA_TRAIT = '/^[ \t]+use\s+RegistraBitacora\s*;/m';
const PATRON_BITACORA_LINEA_CLASE = '/^(?:final\s+|abstract\s+)?class\s+\w+/m';

/** Contenido de las migraciones que crean o alteran la tabla dada, concatenado. */
function bitacoraMigracionesDeTabla(string $tabla, string $raizProyecto): string
{
    $texto = '';

    foreach (glob($raizProyecto.'/database/migrations/*.php') ?: [] as $archivo) {
        $contenido = file_get_contents($archivo);

        if ($contenido === false) {
            continue;
        }

        if (preg_match('/Schema::(?:create|table)\(\s*[\'"]'.preg_quote($tabla, '/').'[\'"]/', $contenido) === 1) {
            $texto .= $contenido."\n";
        }
    }

    return $texto;
}

/** ¿El esquema real de `$tabla` la marca como catálogo o asignación de rol/permiso? */
function bitacoraDebeAuditar(string $tabla, string $raizProyecto): bool
{
    $texto = bitacoraMigracionesDeTabla($tabla, $raizProyecto);

    return preg_match(PATRON_BITACORA_STATE, $texto) === 1
        || preg_match(PATRON_BITACORA_ROL_PERMISO, $texto) === 1;
}

/** ¿El archivo de un modelo ya declara `use RegistraBitacora;` en el CUERPO de la clase? */
function bitacoraUsaTrait(string $contenidoPhp): bool
{
    return preg_match(PATRON_BITACORA_USA_TRAIT, $contenidoPhp) === 1;
}

/** Línea (1-based) donde arranca la declaración de la clase, o 1 si no se encuentra. */
function bitacoraLineaDeClase(string $contenidoPhp): int
{
    if (preg_match(PATRON_BITACORA_LINEA_CLASE, $contenidoPhp, $coincidencia, PREG_OFFSET_CAPTURE) !== 1) {
        return 1;
    }

    return substr_count(substr($contenidoPhp, 0, $coincidencia[0][1]), "\n") + 1;
}

/**
 * Modelos Eloquent de dominio con tabla física propia. `Compartido/` queda
 * afuera (es la plataforma que audita, no algo que se audite a sí misma) y
 * también los subtipos sin `$table` propio (p. ej. `SecUsuarioInterno`, que
 * hereda tabla y trait de `SecUser` por herencia de PHP, sin redeclarar
 * nada) — descubierto igual que `ArquitecturaModulosTest` descubre módulos:
 * un módulo o modelo nuevo queda cubierto sin editar este archivo.
 *
 * @return list<array{archivo: string, tabla: string}>
 */
function bitacoraModelosDeDominio(string $raizProyecto): array
{
    $rutaDominios = $raizProyecto.'/app/Dominios';
    $modelos = [];

    foreach (glob($rutaDominios.'/*', GLOB_ONLYDIR) ?: [] as $rutaModulo) {
        if (basename($rutaModulo) === 'Compartido') {
            continue;
        }

        foreach (glob($rutaModulo.'/Infraestructura/Eloquent/*.php') ?: [] as $archivo) {
            $contenido = file_get_contents($archivo);

            if ($contenido === false || preg_match(PATRON_BITACORA_TABLA_DECLARADA, $contenido, $coincidencia) !== 1) {
                continue;
            }

            $modelos[] = [
                'archivo' => substr($archivo, strlen($raizProyecto) + 1),
                'tabla' => $coincidencia[1],
            ];
        }
    }

    usort($modelos, static fn (array $a, array $b): int => $a['archivo'] <=> $b['archivo']);

    return $modelos;
}

// Red de seguridad del descubrimiento: si esto falla, la regla de abajo no
// protege nada (p. ej. porque se movió app/Dominios o cambió la convención
// de $table).
test('el descubrimiento encuentra los modelos de dominio con tabla propia', function () use ($raizProyecto) {
    $archivos = array_column(bitacoraModelosDeDominio($raizProyecto), 'archivo');

    expect($archivos)
        ->toContain('app/Dominios/Seguridad/Infraestructura/Eloquent/SecRole.php')
        ->toContain('app/Dominios/Comercial/Infraestructura/Eloquent/Contrato.php')
        ->not->toContain('app/Dominios/Seguridad/Infraestructura/Eloquent/SecUsuarioInterno.php');
});

test('todo modelo cuyo esquema lo marca como rol/permiso lleva la bitácora (CLAUDE.md invariante 9, ADR 0007)', function () use ($raizProyecto) {
    $faltantes = [];

    foreach (bitacoraModelosDeDominio($raizProyecto) as $modelo) {
        if (! bitacoraDebeAuditar($modelo['tabla'], $raizProyecto)) {
            continue;
        }

        $contenido = file_get_contents($raizProyecto.'/'.$modelo['archivo']);

        if ($contenido === false || bitacoraUsaTrait($contenido)) {
            continue;
        }

        $linea = bitacoraLineaDeClase($contenido);
        $lineas = explode("\n", $contenido);

        $faltantes[] = sprintf('%s:%d: %s (tabla %s)', $modelo['archivo'], $linea, trim($lineas[$linea - 1] ?? ''), $modelo['tabla']);
    }

    expect($faltantes)->toBe([], "El esquema de esta tabla la marca como catálogo o asignación de rol/permiso (columna `state`, o `id_role`/`id_permission`) — agregá `use RegistraBitacora;` al modelo (ver app/Dominios/Compartido/Infraestructura/Eloquent/RegistraBitacora.php).\n".implode("\n", $faltantes));
});

/*
 * Los tests que siguen son la aduana probándose a sí misma, con texto
 * sintético — no dependen de que el estado actual de app/ siga siendo el
 * mismo mañana.
 */

test('la regla de auditar reconoce cada señal de rol/permiso y no confunde catálogos parecidos', function (string $fragmento, bool $esperado) {
    $coincide = preg_match(PATRON_BITACORA_STATE, $fragmento) === 1
        || preg_match(PATRON_BITACORA_ROL_PERMISO, $fragmento) === 1;

    expect($coincide)->toBe($esperado);
})->with([
    'boolean state, catálogo de rol/permiso' => ["\$table->boolean('state')->default(true);", true],
    'foreignId id_role, asignación' => ["\$table->foreignId('id_role')->constrained('sec_role');", true],
    'unsignedBigInteger id_permission' => ["\$table->unsignedBigInteger('id_permission');", true],
    'boolean activo, no es la señal de rol/permiso' => ["\$table->boolean('activo')->default(true);", false],
    'foreignId role_id, sufijo: referencia común, no asignación' => ["\$table->foreignId('role_id')->constrained('sec_role');", false],
    'foreignId permission_id, sufijo' => ["\$table->foreignId('permission_id')->nullable()->constrained('sec_permission');", false],
    'string estado, máquina de estados: otra categoría' => ["\$table->string('estado', 20)->default('borrador');", false],
    'decimal, dinero: otra categoría' => ["\$table->decimal('monto_total', 12, 2);", false],
]);

test('el detector de la señal mira la tabla correcta, no cualquier migración que la mencione de paso', function () {
    $raiz = sys_get_temp_dir().'/bitacora-aduana-'.bin2hex(random_bytes(8));
    $migraciones = $raiz.'/database/migrations';

    mkdir($migraciones, 0o755, true);

    file_put_contents(
        $migraciones.'/1_create_sec_role_table.php',
        "<?php\nSchema::create('sec_role', function (Blueprint \$table) {\n    \$table->boolean('state')->default(true);\n});\n"
    );
    file_put_contents(
        $migraciones.'/2_create_sec_menu_table.php',
        "<?php\n// comenta sec_role de paso, sin crearla ni alterarla\nSchema::create('sec_menu', function (Blueprint \$table) {\n    \$table->foreignId('permission_id')->nullable();\n});\n"
    );

    $debeRol = bitacoraDebeAuditar('sec_role', $raiz);
    $debeMenu = bitacoraDebeAuditar('sec_menu', $raiz);

    unlink($migraciones.'/1_create_sec_role_table.php');
    unlink($migraciones.'/2_create_sec_menu_table.php');
    rmdir($migraciones);
    rmdir($raiz.'/database');
    rmdir($raiz);

    expect($debeRol)->toBeTrue()
        ->and($debeMenu)->toBeFalse();
});

test('detecta el trait solo cuando el CUERPO de la clase lo usa, no cuando solo se importa el namespace', function (string $codigo, bool $esperado) {
    expect(bitacoraUsaTrait($codigo))->toBe($esperado);
})->with([
    'use de trait, indentado dentro de la clase' => ["<?php\n\nclass SecRole extends ModeloDominio\n{\n    use RegistraBitacora;\n\n    protected \$table = 'sec_role';\n}\n", true],
    'solo el import del namespace, nunca usado en el cuerpo' => ["<?php\n\nuse App\\Dominios\\Compartido\\Infraestructura\\Eloquent\\RegistraBitacora;\n\nclass SecRole extends ModeloDominio\n{\n    protected \$table = 'sec_role';\n}\n", false],
    'ningún use en absoluto' => ["<?php\n\nclass SecRole extends ModeloDominio\n{\n    protected \$table = 'sec_role';\n}\n", false],
]);

test('la línea reportada es la de la declaración de la clase, para ir directo a agregar el use', function () {
    $codigo = "<?php\n\nnamespace App\\Dominios\\Seguridad\\Infraestructura\\Eloquent;\n\nuse App\\Dominios\\Compartido\\Infraestructura\\Eloquent\\ModeloDominio;\n\n/**\n * Un docblock\n * de varias líneas.\n */\nclass SecRole extends ModeloDominio\n{\n    protected \$table = 'sec_role';\n}\n";

    expect(bitacoraLineaDeClase($codigo))->toBe(11);
});
