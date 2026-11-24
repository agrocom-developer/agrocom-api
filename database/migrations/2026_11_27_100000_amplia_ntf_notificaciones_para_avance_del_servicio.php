<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Amplía el catálogo cerrado de tipos de `ntf_notificaciones` (ADR 0025) con los
 * avisos del avance del servicio al cliente: el equipo salió hacia el lote y el
 * servicio quedó ejecutado a la espera de su conformidad. Solo cambia la
 * restricción CHECK (solo PostgreSQL): no toca filas.
 */
return new class extends Migration
{
    private const TIPOS_ANTERIORES = [
        'contrato_creado', 'orden_trabajo_creada', 'trabajo_cerrado',
        'sujeto_aprobado', 'sujeto_rechazado', 'sujeto_suspendido', 'sujeto_rehabilitado', 'sujeto_dado_de_baja',
        'sujeto_reenviado', 'documento_por_vencer', 'documento_vencido', 'proveedor_registrado',
        'oferta_creada', 'oferta_vencida', 'oferta_retirada', 'solicitud_asignada', 'solicitud_reasignada',
        'solicitud_sin_proveedor', 'proveedor_sancionado',
        'solicitud_completada', 'calificacion_registrada', 'calificacion_reportada', 'disputa_abierta',
        'disputa_resuelta', 'disputa_apelada', 'cliente_con_aviso', 'publicacion_limitada',
    ];

    private const TIPOS_NUEVOS = ['equipo_en_camino', 'solicitud_ejecutada'];

    public function up(): void
    {
        $this->reemplazar([...self::TIPOS_ANTERIORES, ...self::TIPOS_NUEVOS]);
    }

    public function down(): void
    {
        // Los avisos nuevos no caben en el catálogo anterior y no se borran en
        // silencio: quien revierte decide qué hacer con ellos.
        if (DB::table('ntf_notificaciones')->whereIn('tipo', self::TIPOS_NUEVOS)->exists()) {
            throw new RuntimeException('Hay avisos de avance del servicio en ntf_notificaciones: retíralos antes de revertir esta migración.');
        }

        $this->reemplazar(self::TIPOS_ANTERIORES);
    }

    /** @param  list<string>  $tipos */
    private function reemplazar(array $tipos): void
    {
        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $prefijo = DB::getTablePrefix();
        $lista = implode(', ', array_map(static fn (string $t): string => "'{$t}'", $tipos));

        DB::statement("ALTER TABLE {$prefijo}ntf_notificaciones DROP CONSTRAINT {$prefijo}ntf_notificaciones_tipo_chk");
        DB::statement("ALTER TABLE {$prefijo}ntf_notificaciones ADD CONSTRAINT {$prefijo}ntf_notificaciones_tipo_chk CHECK (tipo IN ({$lista}))");
    }
};
