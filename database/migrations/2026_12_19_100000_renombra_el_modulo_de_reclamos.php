<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El reclamo reemplaza al término anterior en todo el esquema y en los datos que lo nombran
 * (decisión del dueño: el término anterior desaparece del sistema). Es un renombrado, no una
 * transformación: ninguna fila se borra ni cambia de contenido, solo de nombre.
 *
 * Esquema:
 * - Tablas `dsp_disputas`, `dsp_mensajes`, `dsp_resoluciones` y `dsp_historial` pasan a
 *   `rcl_reclamos`, `rcl_mensajes`, `rcl_resoluciones` y `rcl_historial`, con sus índices, restricciones y
 *   secuencias (PostgreSQL no los renombra con la tabla; sin esto quedarían con el prefijo anterior).
 * - Columnas `monto_en_disputa` -> `monto_en_reclamo` y `disputa_id` -> `reclamo_id` (en mensajes,
 *   resoluciones e historial).
 * - Vistas de métricas: `vw_metricas_disputas` -> `vw_metricas_reclamos` (con `abierto_en`) y
 *   `vw_metricas_resoluciones` (con `reclamo_id`). Salen antes de renombrar y vuelven después: así no
 *   dependen del orden en que cada motor reescribe sus referencias.
 *
 * Datos (valores que viajan en la API, en las apps y en el panel):
 * - Estado de la solicitud `disputada` -> `en_reclamo`, en `sol_solicitudes_servicio` y en el historial de
 *   estados (`sol_solicitud_historial`), con el CHECK de la solicitud reescrito.
 * - Avisos: tipos `disputa_abierta|resuelta|apelada` -> `reclamo_abierto|resuelto|apelado`, recurso `disputa`
 *   -> `reclamo` y el prefijo de `clave_evento` (que evita avisos duplicados), con sus dos CHECK.
 * - Permisos `disputas.disputa.*` -> `reclamos.reclamo.*` conservando el id: las asignaciones a roles
 *   (`sec_role_permission`) y el permiso del menú (`sec_menu.permission_id`) quedan intactas.
 * - Menú: ruta `panel.disputas.index` -> `panel.reclamos.index` y la clave de texto de una fila anterior.
 * - Bitácora: la columna `tabla` de los registros de las cuatro tablas sigue al renombrado, para que el
 *   historial de cada registro se siga encontrando. Los valores `antes` y `despues` son fotografías de
 *   otra época y no se reescriben.
 *
 * `down()` es real: devuelve nombres y valores. Las filas nuevas entre `up` y `down` se renombran igual,
 * así que no se pierde nada. Solo PostgreSQL toca restricciones, secuencias y CHECK; SQLite (las pruebas)
 * hace el renombrado de tablas, columnas e índices y la migración de datos.
 */
return new class extends Migration
{
    /** Tablas: nombre anterior -> nombre nuevo. */
    private const TABLAS = [
        'dsp_disputas' => 'rcl_reclamos',
        'dsp_mensajes' => 'rcl_mensajes',
        'dsp_resoluciones' => 'rcl_resoluciones',
        'dsp_historial' => 'rcl_historial',
    ];

    /** Tablas que llevan la clave del reclamo (en sus nombres nuevos). */
    private const CON_CLAVE_DEL_RECLAMO = ['rcl_mensajes', 'rcl_resoluciones', 'rcl_historial'];

    /** Pares de subcadena (anterior -> nueva) para renombrar índices, restricciones y secuencias. */
    private const NOMBRES = [
        'dsp_disputas' => 'rcl_reclamos',
        'dsp_mensajes' => 'rcl_mensajes',
        'dsp_resoluciones' => 'rcl_resoluciones',
        'dsp_historial' => 'rcl_historial',
        'disputa_id' => 'reclamo_id',
        '_disputa_index' => '_reclamo_index',
    ];

    private const ESTADOS = ['disputada' => 'en_reclamo'];

    private const TIPOS_DE_AVISO = [
        'disputa_abierta' => 'reclamo_abierto',
        'disputa_resuelta' => 'reclamo_resuelto',
        'disputa_apelada' => 'reclamo_apelado',
    ];

    private const RECURSOS_DE_AVISO = ['disputa' => 'reclamo'];

    private const PREFIJO_PERMISOS = ['disputas.disputa.' => 'reclamos.reclamo.'];

    private const RUTAS_DE_MENU = ['panel.disputas.index' => 'panel.reclamos.index'];

    private const CLAVES_DE_MENU = ['menu.solicitudes.items.disputas' => 'menu.solicitudes.items.reclamos'];

    public function up(): void
    {
        $this->aplicar(self::TABLAS, self::NOMBRES, 'monto_en_disputa', 'monto_en_reclamo', 'disputa_id', 'reclamo_id', 'abierta_en', 'abierto_en', true);
    }

    public function down(): void
    {
        $this->aplicar(
            array_flip(self::TABLAS),
            array_flip(self::NOMBRES),
            'monto_en_reclamo', 'monto_en_disputa', 'reclamo_id', 'disputa_id', 'abierto_en', 'abierta_en', false,
        );
    }

    /**
     * @param  array<string, string>  $tablas  nombre actual -> nombre al terminar
     * @param  array<string, string>  $nombres  subcadenas de objetos: actual -> al terminar
     */
    private function aplicar(array $tablas, array $nombres, string $montoDe, string $montoA, string $claveDe, string $claveA, string $aperturaDe, string $aperturaA, bool $sube): void
    {
        $this->soltarVistas();

        $esPg = DB::getDriverName() === 'pgsql';
        $checks = $esPg ? $this->soltarChecks($sube) : [];

        foreach ($tablas as $de => $a) {
            if (Schema::hasTable($de)) {
                Schema::rename($de, $a);
            }
        }

        $reclamos = $tablas[$sube ? 'dsp_disputas' : 'rcl_reclamos'];

        if (Schema::hasTable($reclamos) && Schema::hasColumn($reclamos, $montoDe)) {
            Schema::table($reclamos, fn (Blueprint $t) => $t->renameColumn($montoDe, $montoA));
        }

        foreach (self::CON_CLAVE_DEL_RECLAMO as $nuevo) {
            $tabla = $sube ? $nuevo : array_search($nuevo, self::TABLAS, true);

            if (Schema::hasTable($tabla) && Schema::hasColumn($tabla, $claveDe)) {
                Schema::table($tabla, fn (Blueprint $t) => $t->renameColumn($claveDe, $claveA));
            }
        }

        $esPg ? $this->renombrarObjetosPg($tablas, $nombres) : $this->renombrarIndicesSqlite($tablas, $nombres);

        $this->migrarDatos($sube);

        if ($esPg) {
            $this->recrearChecks($checks);
        }

        $this->recrearVistas($sube, $aperturaA);
    }

    private function soltarVistas(): void
    {
        $p = DB::getTablePrefix();

        foreach (['vw_metricas_resoluciones', 'vw_metricas_disputas', 'vw_metricas_reclamos'] as $vista) {
            DB::statement("DROP VIEW IF EXISTS {$p}{$vista}");
        }
    }

    private function recrearVistas(bool $sube, string $apertura): void
    {
        $p = DB::getTablePrefix();
        $tablaReclamos = $sube ? 'rcl_reclamos' : 'dsp_disputas';
        $tablaResoluciones = $sube ? 'rcl_resoluciones' : 'dsp_resoluciones';
        $vista = $sube ? 'vw_metricas_reclamos' : 'vw_metricas_disputas';
        $clave = $sube ? 'reclamo_id' : 'disputa_id';

        if (Schema::hasTable($tablaReclamos)) {
            DB::statement(<<<SQL
                CREATE VIEW {$p}{$vista} AS
                SELECT id AS {$clave}, created_at AS {$apertura}, estado
                FROM {$p}{$tablaReclamos}
                WHERE deleted_at IS NULL
            SQL);
        }

        if (Schema::hasTable($tablaResoluciones)) {
            DB::statement(<<<SQL
                CREATE VIEW {$p}vw_metricas_resoluciones AS
                SELECT {$clave}, ronda, resuelta_en, monto_reembolso, comision_ajuste
                FROM {$p}{$tablaResoluciones}
                WHERE deleted_at IS NULL
            SQL);
        }
    }

    /**
     * PostgreSQL no renombra con la tabla sus restricciones, índices ni secuencias. Las restricciones
     * (clave primaria, única, foránea, CHECK) renombran su índice propio; los índices sueltos y las
     * secuencias van aparte.
     *
     * @param  array<string, string>  $tablas
     * @param  array<string, string>  $nombres
     */
    private function renombrarObjetosPg(array $tablas, array $nombres): void
    {
        $p = DB::getTablePrefix();
        $enLista = implode(', ', array_map(fn (string $t): string => "'{$p}{$t}'", array_values($tablas)));

        $restricciones = DB::select("SELECT t.relname AS tabla, c.conname AS nombre FROM pg_constraint c JOIN pg_class t ON t.oid = c.conrelid WHERE t.relname IN ({$enLista}) ORDER BY c.oid");

        foreach ($restricciones as $r) {
            $nuevo = strtr($r->nombre, $nombres);

            if ($nuevo !== $r->nombre) {
                DB::statement("ALTER TABLE {$r->tabla} RENAME CONSTRAINT {$r->nombre} TO {$nuevo}");
            }
        }

        $indices = DB::select("SELECT indexname AS nombre FROM pg_indexes WHERE schemaname = current_schema() AND tablename IN ({$enLista})");

        foreach ($indices as $i) {
            $nuevo = strtr($i->nombre, $nombres);

            if ($nuevo !== $i->nombre) {
                DB::statement("ALTER INDEX {$i->nombre} RENAME TO {$nuevo}");
            }
        }

        // La secuencia del id conserva el nombre de la tabla anterior: `{tabla}_id_seq`.
        foreach ($tablas as $de => $a) {
            DB::statement("ALTER SEQUENCE IF EXISTS {$p}{$de}_id_seq RENAME TO {$p}{$a}_id_seq");
        }
    }

    /**
     * SQLite no renombra índices: se lee su definición (que el motor ya reescribió con los nombres nuevos de
     * tabla y columna), se suelta y se vuelve a crear con el nombre nuevo.
     *
     * @param  array<string, string>  $tablas
     * @param  array<string, string>  $nombres
     */
    private function renombrarIndicesSqlite(array $tablas, array $nombres): void
    {
        foreach (array_values($tablas) as $tabla) {
            $indices = DB::select("SELECT name, sql FROM sqlite_master WHERE type = 'index' AND tbl_name = ? AND sql IS NOT NULL", [DB::getTablePrefix().$tabla]);

            foreach ($indices as $indice) {
                $nuevo = strtr($indice->name, $nombres);

                if ($nuevo === $indice->name) {
                    continue;
                }

                DB::statement("DROP INDEX {$indice->name}");
                DB::statement(str_replace($indice->name, $nuevo, $indice->sql));
            }
        }
    }

    /**
     * Lee de PostgreSQL las listas vigentes de los tres CHECK que nombran valores renombrados, las suelta y
     * devuelve lo necesario para volver a crearlas con los valores traducidos. Leer la lista vigente (en vez
     * de repetirla aquí) evita perder un valor agregado por una migración posterior.
     *
     * @return list<array{tabla: string, nombre: string, columna: string, valores: list<string>}>
     */
    private function soltarChecks(bool $sube): array
    {
        $p = DB::getTablePrefix();
        $mapas = [
            ['sol_solicitudes_servicio', 'sol_solicitudes_servicio_estado_chk', 'estado', self::ESTADOS],
            ['ntf_notificaciones', 'ntf_notificaciones_tipo_chk', 'tipo', self::TIPOS_DE_AVISO],
            ['ntf_notificaciones', 'ntf_notificaciones_recurso_tipo_chk', 'recurso_tipo', self::RECURSOS_DE_AVISO],
        ];
        $checks = [];

        foreach ($mapas as [$tabla, $nombre, $columna, $mapa]) {
            $mapa = $sube ? $mapa : array_flip($mapa);
            $fila = DB::selectOne('SELECT pg_get_constraintdef(c.oid) AS definicion FROM pg_constraint c JOIN pg_class t ON t.oid = c.conrelid WHERE t.relname = ? AND c.conname = ?', [$p.$tabla, $p.$nombre]);

            if ($fila === null) {
                continue;
            }

            preg_match_all("/'([^']*)'::character varying/", $fila->definicion, $coincidencias);

            if ($coincidencias[1] === []) {
                throw new RuntimeException("No se pudo leer la lista de valores de {$nombre}.");
            }

            $checks[] = [
                'tabla' => $tabla,
                'nombre' => $nombre,
                'columna' => $columna,
                'valores' => array_map(static fn (string $v): string => $mapa[$v] ?? $v, $coincidencias[1]),
            ];

            DB::statement("ALTER TABLE {$p}{$tabla} DROP CONSTRAINT {$p}{$nombre}");
        }

        return $checks;
    }

    /** @param  list<array{tabla: string, nombre: string, columna: string, valores: list<string>}>  $checks */
    private function recrearChecks(array $checks): void
    {
        $p = DB::getTablePrefix();

        foreach ($checks as $check) {
            $lista = implode(', ', array_map(static fn (string $v): string => "'{$v}'", $check['valores']));

            DB::statement("ALTER TABLE {$p}{$check['tabla']} ADD CONSTRAINT {$p}{$check['nombre']} CHECK ({$check['columna']} IN ({$lista}))");
        }
    }

    private function migrarDatos(bool $sube): void
    {
        $estados = $sube ? self::ESTADOS : array_flip(self::ESTADOS);
        $tipos = $sube ? self::TIPOS_DE_AVISO : array_flip(self::TIPOS_DE_AVISO);
        $recursos = $sube ? self::RECURSOS_DE_AVISO : array_flip(self::RECURSOS_DE_AVISO);
        $permisos = $sube ? self::PREFIJO_PERMISOS : array_flip(self::PREFIJO_PERMISOS);
        $rutas = $sube ? self::RUTAS_DE_MENU : array_flip(self::RUTAS_DE_MENU);
        $claves = $sube ? self::CLAVES_DE_MENU : array_flip(self::CLAVES_DE_MENU);
        $tablas = $sube ? self::TABLAS : array_flip(self::TABLAS);

        foreach ($estados as $de => $a) {
            foreach ([['sol_solicitudes_servicio', 'estado'], ['sol_solicitud_historial', 'estado_anterior'], ['sol_solicitud_historial', 'estado_nuevo']] as [$tabla, $columna]) {
                if (Schema::hasTable($tabla)) {
                    DB::table($tabla)->where($columna, $de)->update([$columna => $a]);
                }
            }
        }

        if (Schema::hasTable('ntf_notificaciones')) {
            foreach ($tipos as $de => $a) {
                DB::table('ntf_notificaciones')->where('tipo', $de)->update(['tipo' => $a]);
                DB::table('ntf_notificaciones')->where('clave_evento', 'like', $de.':%')
                    ->update(['clave_evento' => DB::raw("'{$a}' || SUBSTR(clave_evento, ".(strlen($de) + 1).')')]);
            }

            foreach ($recursos as $de => $a) {
                DB::table('ntf_notificaciones')->where('recurso_tipo', $de)->update(['recurso_tipo' => $a]);
            }
        }

        if (Schema::hasTable('sec_permission')) {
            foreach ($permisos as $de => $a) {
                $existentes = DB::table('sec_permission')->where('code', 'like', $a.'%')->count();

                if ($existentes > 0) {
                    throw new RuntimeException("Ya existen permisos «{$a}*» en sec_permission: el catálogo se sembró con los nombres nuevos antes de migrar. Elimina esas filas sin asignaciones y vuelve a migrar.");
                }

                DB::table('sec_permission')->where('code', 'like', $de.'%')
                    ->update(['code' => DB::raw("'{$a}' || SUBSTR(code, ".(strlen($de) + 1).')')]);
            }
        }

        if (Schema::hasTable('sec_menu')) {
            foreach ($rutas as $de => $a) {
                DB::table('sec_menu')->where('ruta', $de)->update(['ruta' => $a]);
            }

            foreach ($claves as $de => $a) {
                DB::table('sec_menu')->where('label', $de)->update(['label' => $a]);
            }
        }

        if (Schema::hasTable('plt_bitacoras')) {
            foreach ($tablas as $de => $a) {
                DB::table('plt_bitacoras')->where('tabla', $de)->update(['tabla' => $a]);
            }
        }
    }
};
