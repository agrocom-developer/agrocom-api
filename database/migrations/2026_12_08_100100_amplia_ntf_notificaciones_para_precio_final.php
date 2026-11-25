<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Amplía el catálogo cerrado de tipos de `ntf_notificaciones` con el aviso al proveedor
 * de que el cliente confirmó el precio final. Solo cambia la restricción CHECK (solo
 * PostgreSQL): no toca filas.
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
        'equipo_en_camino', 'solicitud_ejecutada',
        'nueva_solicitud_abierta', 'oferta_recibida', 'solicitud_adjudicada', 'ejecucion_iniciada',
        'solicitud_finalizada', 'mensaje_nuevo', 'avance_registrado', 'solicitud_expirada', 'solicitud_reabierta', 'precio_cambiado',
    ];

    private const TIPO_NUEVO = 'precio_final_confirmado';

    public function up(): void
    {
        $this->reemplazar([...self::TIPOS_ANTERIORES, self::TIPO_NUEVO]);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql' && DB::table('ntf_notificaciones')->where('tipo', self::TIPO_NUEVO)->exists()) {
            throw new RuntimeException('Hay avisos de precio final confirmado en ntf_notificaciones: retíralos antes de revertir esta migración.');
        }

        $this->reemplazar(self::TIPOS_ANTERIORES);
    }

    /** @param  list<string>  $tipos */
    private function reemplazar(array $tipos): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $prefijo = DB::getTablePrefix();
        $lista = implode(', ', array_map(static fn (string $t): string => "'{$t}'", $tipos));

        DB::statement("ALTER TABLE {$prefijo}ntf_notificaciones DROP CONSTRAINT {$prefijo}ntf_notificaciones_tipo_chk");
        DB::statement("ALTER TABLE {$prefijo}ntf_notificaciones ADD CONSTRAINT {$prefijo}ntf_notificaciones_tipo_chk CHECK (tipo IN ({$lista}))");
    }
};
