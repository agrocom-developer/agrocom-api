<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Amplía los catálogos cerrados de `ntf_notificaciones` (ADR 0025, ADR 0038)
 * con los avisos de la asignación: oferta creada, vencida y retirada;
 * solicitud asignada, reasignada y sin proveedor; y proveedor sancionado. El
 * recurso `solicitud` lleva el click al panel. Solo cambian las restricciones
 * CHECK (solo PostgreSQL): no toca filas.
 */
return new class extends Migration
{
    private const TIPOS_ANTERIORES = [
        'contrato_creado', 'orden_trabajo_creada', 'trabajo_cerrado',
        'sujeto_aprobado', 'sujeto_rechazado', 'sujeto_suspendido', 'sujeto_rehabilitado', 'sujeto_dado_de_baja',
        'sujeto_reenviado', 'documento_por_vencer', 'documento_vencido', 'proveedor_registrado',
    ];

    private const TIPOS_NUEVOS = [
        'oferta_creada', 'oferta_vencida', 'oferta_retirada', 'solicitud_asignada', 'solicitud_reasignada',
        'solicitud_sin_proveedor', 'proveedor_sancionado',
    ];

    private const RECURSOS_ANTERIORES = ['contrato', 'orden_trabajo', 'trabajo', 'proveedor', 'piloto', 'auxiliar', 'dron', 'documento'];

    private const RECURSOS_NUEVOS = ['solicitud'];

    public function up(): void
    {
        $this->reemplazar(
            [...self::TIPOS_ANTERIORES, ...self::TIPOS_NUEVOS],
            [...self::RECURSOS_ANTERIORES, ...self::RECURSOS_NUEVOS],
        );
    }

    public function down(): void
    {
        // Los avisos de la asignación no caben en el catálogo anterior y no se
        // borran en silencio: quien revierte decide qué hacer con ellos.
        if (DB::table('ntf_notificaciones')->whereIn('tipo', self::TIPOS_NUEVOS)->exists()) {
            throw new RuntimeException('Hay avisos de la asignación en ntf_notificaciones: retíralos antes de revertir esta migración.');
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
