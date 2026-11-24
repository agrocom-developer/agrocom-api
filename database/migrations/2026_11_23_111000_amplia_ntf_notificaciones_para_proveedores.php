<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Amplía los catálogos cerrados de `ntf_notificaciones` (ADR 0025, ADR 0036)
 * con los avisos del marketplace: aprobación, rechazo, suspensión, rehabilitación
 * y baja de un proveedor, piloto, auxiliar o dron; su reenvío a revisión;
 * documentos por vencer o vencidos; y el proveedor nuevo en la cola de revisión. Solo cambian las restricciones
 * CHECK (solo PostgreSQL): no toca filas.
 */
return new class extends Migration
{
    private const TIPOS_ANTERIORES = ['contrato_creado', 'orden_trabajo_creada', 'trabajo_cerrado'];

    private const TIPOS_NUEVOS = [
        'sujeto_aprobado', 'sujeto_rechazado', 'sujeto_suspendido', 'sujeto_rehabilitado', 'sujeto_dado_de_baja',
        'sujeto_reenviado', 'documento_por_vencer', 'documento_vencido', 'proveedor_registrado',
    ];

    private const RECURSOS_ANTERIORES = ['contrato', 'orden_trabajo', 'trabajo'];

    private const RECURSOS_NUEVOS = ['proveedor', 'piloto', 'auxiliar', 'dron', 'documento'];

    public function up(): void
    {
        $this->reemplazar(
            [...self::TIPOS_ANTERIORES, ...self::TIPOS_NUEVOS],
            [...self::RECURSOS_ANTERIORES, ...self::RECURSOS_NUEVOS],
        );
    }

    public function down(): void
    {
        // Los avisos del marketplace no caben en el catálogo anterior y no se
        // borran en silencio: quien revierte decide qué hacer con ellos.
        if (DB::table('ntf_notificaciones')->whereIn('tipo', self::TIPOS_NUEVOS)->exists()) {
            throw new RuntimeException('Hay avisos del marketplace en ntf_notificaciones: retíralos antes de revertir esta migración.');
        }

        $this->reemplazar(self::TIPOS_ANTERIORES, self::RECURSOS_ANTERIORES);
    }

    /**
     * @param  list<string>  $tipos
     * @param  list<string>  $recursos
     */
    private function reemplazar(array $tipos, array $recursos): void
    {
        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $prefijo = DB::getTablePrefix();
        $listaTipos = implode(', ', array_map(static fn (string $t): string => "'{$t}'", $tipos));
        $listaRecursos = implode(', ', array_map(static fn (string $r): string => "'{$r}'", $recursos));

        DB::statement("ALTER TABLE {$prefijo}ntf_notificaciones DROP CONSTRAINT {$prefijo}ntf_notificaciones_tipo_chk");
        DB::statement("ALTER TABLE {$prefijo}ntf_notificaciones DROP CONSTRAINT {$prefijo}ntf_notificaciones_recurso_tipo_chk");
        DB::statement("ALTER TABLE {$prefijo}ntf_notificaciones ADD CONSTRAINT {$prefijo}ntf_notificaciones_tipo_chk CHECK (tipo IN ({$listaTipos}))");
        DB::statement("ALTER TABLE {$prefijo}ntf_notificaciones ADD CONSTRAINT {$prefijo}ntf_notificaciones_recurso_tipo_chk CHECK (recurso_tipo IN ({$listaRecursos}))");
    }
};
