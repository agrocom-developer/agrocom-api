<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Avisos de la negociación (`negociacion-contrato.md` §7): nueva solicitud abierta
 * en la zona del piloto, oferta o contraoferta recibida, adjudicación, inicio y
 * fin de la ejecución, avance, mensaje y expiración. Los tipos anteriores se
 * conservan: los avisos ya guardados siguen siendo legibles.
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
    ];

    private const TIPOS_NUEVOS = [
        'nueva_solicitud_abierta', 'oferta_recibida', 'solicitud_adjudicada', 'ejecucion_iniciada',
        'solicitud_finalizada', 'mensaje_nuevo', 'avance_registrado', 'solicitud_expirada', 'solicitud_reabierta', 'precio_cambiado',
    ];

    public function up(): void
    {
        $this->reemplazar([...self::TIPOS_ANTERIORES, ...self::TIPOS_NUEVOS]);
    }

    public function down(): void
    {
        if (DB::table('ntf_notificaciones')->whereIn('tipo', self::TIPOS_NUEVOS)->exists()) {
            throw new RuntimeException('Hay avisos de la negociación en ntf_notificaciones: retíralos antes de revertir esta migración.');
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
